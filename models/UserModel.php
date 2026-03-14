<?php
class UserModel {
    private $conn;
    private $table_name = "usuarios";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Registra un nuevo usuario en la base de datos.
     */
    public function create($username, $email, $password, $nombre, $apellido, $fecha_nacimiento = null) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password_hash, nombre, apellido, fecha_nacimiento) 
                  VALUES (:username, :email, :password_hash, :nombre, :apellido, :fecha)";
        
        $stmt = $this->conn->prepare($query);
        
        // Hashing seguro de la contraseña usando el algoritmo por defecto (bcrypt actualmente)
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":username", $username);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":apellido", $apellido);
        $stmt->bindParam(":fecha", $fecha_nacimiento);
        
        try {
            if($stmt->execute()) {
                return $this->conn->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Error registro: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Verifica credenciales para el inicio de sesión.
     */
    public function login($email, $password) {
        $query = "SELECT id, username, password_hash FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Validamos la contraseña usando password_verify para prevenir timing attacks
            if(password_verify($password, $row['password_hash'])) {
                // Prevenimos exponer el hash en la sesión o retorno limpio 
                unset($row['password_hash']);
                return $row;
            }
        }
        return false;
    }

    /**
     * Actualiza la contraseña de un usuario existente.
     */
    public function changePassword($userId, $newPassword) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":id", $userId);
        return $stmt->execute();
    }

    /**
     * Actualiza datos básicos del perfil.
     */
    public function updateProfile($userId, $nombre, $apellido, $biografia, $es_cuenta_privada) {
        $query = "UPDATE " . $this->table_name . " 
                  SET nombre = :nombre, apellido = :apellido, biografia = :biografia, es_cuenta_privada = :privada 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":nombre", $nombre);
        $stmt->bindParam(":apellido", $apellido);
        $stmt->bindParam(":biografia", $biografia);
        $stmt->bindParam(":privada", $es_cuenta_privada, PDO::PARAM_INT);
        $stmt->bindParam(":id", $userId);
        
        return $stmt->execute();
    }

    /**
     * Reemplaza el historial de intereses de un usuario usando transacciones.
     */
    public function updateInterests($userId, $interestIds) {
        $this->conn->beginTransaction();
        try {
            // Eliminamos los anteriores
            $queryDel = "DELETE FROM usuario_intereses WHERE usuario_id = :usuario_id";
            $stmtDel = $this->conn->prepare($queryDel);
            $stmtDel->bindParam(":usuario_id", $userId);
            $stmtDel->execute();

            // Insertamos los seleccionados actualmente
            if (!empty($interestIds)) {
                $queryIns = "INSERT INTO usuario_intereses (usuario_id, interes_id) VALUES (:usuario_id, :interes_id)";
                $stmtIns = $this->conn->prepare($queryIns);
                foreach ($interestIds as $interes_id) {
                    $stmtIns->bindParam(":usuario_id", $userId);
                    $stmtIns->bindParam(":interes_id", $interes_id);
                    $stmtIns->execute();
                }
            }
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al actualizar intereses: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el ID del álbum designado para fotos de perfil, si no existe lo crea.
     */
    private function getProfileAlbumId($userId) {
        $query = "SELECT id FROM albumes WHERE usuario_id = :usuario_id AND tipo = 'perfil' LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario_id", $userId);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['id'];
        }
        
        $queryIns = "INSERT INTO albumes (usuario_id, titulo, tipo) VALUES (:usuario_id, 'Fotos de Perfil restrictiva del sistema', 'perfil')";
        $stmtIns = $this->conn->prepare($queryIns);
        $stmtIns->bindParam(":usuario_id", $userId);
        if ($stmtIns->execute()) {
            return $this->conn->lastInsertId();
        }
        throw new Exception("No se pudo obtener o crear el álbum de perfil.");
    }

    /**
     * Sube y registra una nueva foto de perfil actualizando el historial adecuadamente.
     */
    public function updateProfilePhoto($userId, $imageUrl, $mimeType, $sizeBytes) {
        $this->conn->beginTransaction();
        try {
            $albumId = $this->getProfileAlbumId($userId);

            // 1. Insertar nueva imagen en el repositorio de base de datos
            $queryImg = "INSERT INTO imagenes (album_id, url_almacen, mime_type, tamano_bytes) 
                         VALUES (:album_id, :url, :mime, :tamano)";
            $stmtImg = $this->conn->prepare($queryImg);
            $stmtImg->bindParam(":album_id", $albumId);
            $stmtImg->bindParam(":url", $imageUrl);
            $stmtImg->bindParam(":mime", $mimeType);
            $stmtImg->bindParam(":tamano", $sizeBytes);
            $stmtImg->execute();
            $imagenId = $this->conn->lastInsertId();

            // 2. Terminar vigencia de la foto anterior estableciendo fecha_hasta
            $queryHistOld = "UPDATE historial_fotos_perfil 
                             SET fecha_hasta = CURRENT_TIMESTAMP 
                             WHERE usuario_id = :usuario_id AND fecha_hasta IS NULL";
            $stmtHistOld = $this->conn->prepare($queryHistOld);
            $stmtHistOld->bindParam(":usuario_id", $userId);
            $stmtHistOld->execute();

            // 3. Crear nuevo registro en historial
            $queryHistNew = "INSERT INTO historial_fotos_perfil (usuario_id, imagen_id) VALUES (:usuario_id, :imagen_id)";
            $stmtHistNew = $this->conn->prepare($queryHistNew);
            $stmtHistNew->bindParam(":usuario_id", $userId);
            $stmtHistNew->bindParam(":imagen_id", $imagenId);
            $stmtHistNew->execute();
            $historialId = $this->conn->lastInsertId();

            // 4. Actualizar referencia cruzada a la foto actual (Dependencia circular completada)
            $queryUser = "UPDATE usuarios SET foto_perfil_actual_id = :historial_id WHERE id = :usuario_id";
            $stmtUser = $this->conn->prepare($queryUser);
            $stmtUser->bindParam(":historial_id", $historialId);
            $stmtUser->bindParam(":usuario_id", $userId);
            $stmtUser->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error actualizando foto de perfil: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna todo el historial de fotos de perfil del usuario junto a la metadata de imagen.
     */
    public function getProfilePhotoHistory($userId) {
        $query = "SELECT h.id as historial_id, h.fecha_desde, h.fecha_hasta, 
                         i.id as imagen_id, i.url_almacen, i.created_at 
                  FROM historial_fotos_perfil h
                  JOIN imagenes i ON h.imagen_id = i.id
                  WHERE h.usuario_id = :usuario_id
                  ORDER BY h.fecha_desde DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario_id", $userId);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los datos de un usuario por su ID (excluyendo password).
     */
    public function getUserById($userId) {
        $query = "SELECT u.id, u.username, u.email, u.nombre, u.apellido, u.biografia, u.es_cuenta_privada,
                         i.url_almacen as foto_perfil
                  FROM " . $this->table_name . " u
                  LEFT JOIN historial_fotos_perfil h ON u.foto_perfil_actual_id = h.id
                  LEFT JOIN imagenes i ON h.imagen_id = i.id
                  WHERE u.id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $userId);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los intereses disponibles en el sistema.
     */
    public function getAllInterests() {
        $query = "SELECT id, nombre FROM intereses ORDER BY nombre ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los IDs de los intereses de un usuario específico.
     */
    public function getUserInterests($userId) {
        $query = "SELECT interes_id FROM usuario_intereses WHERE usuario_id = :usuario_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario_id", $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
