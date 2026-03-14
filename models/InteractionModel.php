<?php
class InteractionModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Alterna el estado de "me gusta" para una imagen (toggle).
     */
    public function toggleLike($imageId, $userId) {
        // Verificar existencia previa por clave compuesta
        $queryCheck = "SELECT 1 FROM interacciones_likes WHERE imagen_id = :img AND usuario_id = :user";
        $stmtCheck = $this->conn->prepare($queryCheck);
        $stmtCheck->execute([':img' => $imageId, ':user' => $userId]);
        
        if ($stmtCheck->fetch()) {
            $query = "DELETE FROM interacciones_likes WHERE imagen_id = :img AND usuario_id = :user";
        } else {
            $query = "INSERT INTO interacciones_likes (imagen_id, usuario_id) VALUES (:img, :user)";
        }
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':img' => $imageId, ':user' => $userId]);
    }

    /**
     * Agrega un comentario, soportando hilos si se provee un ID padre.
     */
    public function addComment($imageId, $userId, $content, $parentId = null) {
        $query = "INSERT INTO interacciones_comentarios (imagen_id, usuario_id, comentario_id_padre, contenido) 
                  VALUES (:img, :user, :parent, :content)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':img' => $imageId,
            ':user' => $userId,
            ':parent' => $parentId,
            ':content' => $content
        ]);
    }

    /**
     * Obtiene el total de likes de una imagen.
     */
    public function getLikesCount($imageId) {
        $query = "SELECT COUNT(*) FROM interacciones_likes WHERE imagen_id = :img";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':img' => $imageId]);
        return $stmt->fetchColumn();
    }

    /**
     * Verifica si un usuario específico le dio like a una imagen.
     */
    public function userLiked($imageId, $userId) {
        $query = "SELECT 1 FROM interacciones_likes WHERE imagen_id = :img AND usuario_id = :user";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':img' => $imageId, ':user' => $userId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Obtiene la lista de comentarios de una imagen con los nombres de usuario.
     */
    public function getComments($imageId) {
        $query = "SELECT c.*, u.username 
                  FROM interacciones_comentarios c
                  JOIN usuarios u ON c.usuario_id = u.id
                  WHERE c.imagen_id = :img
                  ORDER BY c.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':img' => $imageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Envía una solicitud de seguimiento.
     */
    public function sendFollowRequest($followerId, $followedId) {
        $query = "INSERT INTO seguidores (seguidor_id, seguido_id, estado) VALUES (:follower, :followed, 'pendiente')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);
    }

    /**
     * Rechaza una solicitud de seguimiento.
     */
    public function rejectFollowRequest($followerId, $followedId) {
        $query = "UPDATE seguidores SET estado = 'rechazada' WHERE seguidor_id = :follower AND seguido_id = :followed";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);
    }

    /**
     * Lógica Crítica: Acepta solicitud y dispara clonación de imágenes "likeadas".
     */
    public function acceptFollowRequest($followerId, $followedId) {
        $this->conn->beginTransaction();
        try {
            // 1. Actualizar estado a 'aceptada'
            $query = "UPDATE seguidores SET estado = 'aceptada' WHERE seguidor_id = :follower AND seguido_id = :followed";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);

            // 2. Obtener datos del seguido para el título del nuevo álbum del seguidor
            $queryUser = "SELECT nombre, apellido FROM usuarios WHERE id = :id";
            $stmtUser = $this->conn->prepare($queryUser);
            $stmtUser->execute([':id' => $followedId]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception("Usuario seguido no encontrado.");
            }

            $albumTitle = $user['nombre'] . ' ' . $user['apellido'];

            // 3. Crear nuevo álbum automático para el seguidor (invitante)
            $queryAlbum = "INSERT INTO albumes (usuario_id, titulo, descripcion) 
                           VALUES (:usuario_id, :titulo, 'Álbum automático: Artesano seguido')";
            $stmtAlbum = $this->conn->prepare($queryAlbum);
            $stmtAlbum->execute([
                ':usuario_id' => $followerId, 
                ':titulo' => $albumTitle
            ]);
            $newAlbumId = $this->conn->lastInsertId();

            // 4. Buscar imágenes del seguido a las que el seguidor les haya dado "like"
            // Se asocia vía interacciones_likes -> imagenes -> albumes (del seguido)
            $queryLikes = "SELECT i.titulo, i.url_almacen, i.mime_type, i.tamano_bytes, i.privacidad 
                           FROM interacciones_likes l
                           JOIN imagenes i ON l.imagen_id = i.id
                           JOIN albumes a ON i.album_id = a.id
                           WHERE l.usuario_id = :follower AND a.usuario_id = :followed";
            $stmtLikes = $this->conn->prepare($queryLikes);
            $stmtLikes->execute([':follower' => $followerId, ':followed' => $followedId]);
            $likedImages = $stmtLikes->fetchAll(PDO::FETCH_ASSOC);

            // 5. Clonación de registros: Insertar en nuevo álbum manteniendo el mismo path físico (URL)
            // Se crean nuevas filas en 'imagenes' con el ID del nuevo álbum
            $queryClone = "INSERT INTO imagenes (album_id, titulo, url_almacen, mime_type, tamano_bytes, privacidad) 
                           VALUES (:album_id, :titulo, :url, :mime, :tamano, :privacidad)";
            $stmtClone = $this->conn->prepare($queryClone);

            foreach ($likedImages as $img) {
                $stmtClone->execute([
                    ':album_id' => $newAlbumId,
                    ':titulo' => $img['titulo'],
                    ':url' => $img['url_almacen'],
                    ':mime' => $img['mime_type'],
                    ':tamano' => $img['tamano_bytes'],
                    ':privacidad' => $img['privacidad']
                ]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error transaccional en acceptFollowRequest: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el estado de seguimiento entre dos usuarios.
     */
    public function getFollowStatus($followerId, $followedId) {
        $query = "SELECT estado FROM seguidores WHERE seguidor_id = :follower AND seguido_id = :followed LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':follower' => $followerId, ':followed' => $followedId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['estado'] : null;
    }

    /**
     * Lista las solicitudes de seguimiento pendientes para un usuario.
     */
    public function getPendingRequests($followedId) {
        $query = "SELECT s.*, u.username, u.nombre, u.apellido 
                  FROM seguidores s
                  JOIN usuarios u ON s.seguidor_id = u.id
                  WHERE s.seguido_id = :followed AND s.estado = 'pendiente'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':followed' => $followedId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
