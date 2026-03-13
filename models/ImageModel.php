<?php
class ImageModel {
    private $conn;
    private $table_name = "imagenes";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Inserta una imagen validando el límite de 20 por álbum.
     */
    public function create($albumId, $urlAlmacen, $mimeType, $tamanoBytes, $privacidad = 'publico', $titulo = null) {
        // Validación de límite máximo
        $queryCount = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE album_id = :album_id";
        $stmtCount = $this->conn->prepare($queryCount);
        $stmtCount->bindParam(":album_id", $albumId);
        $stmtCount->execute();
        $row = $stmtCount->fetch(PDO::FETCH_ASSOC);

        if ($row['total'] >= 20) {
            throw new Exception("Límite de imágenes alcanzado (máximo 20 por álbum).");
        }

        $query = "INSERT INTO " . $this->table_name . " (album_id, titulo, url_almacen, mime_type, tamano_bytes, privacidad) 
                  VALUES (:album_id, :titulo, :url, :mime, :tamano, :privacidad)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":album_id", $albumId);
        $stmt->bindParam(":titulo", $titulo);
        $stmt->bindParam(":url", $urlAlmacen);
        $stmt->bindParam(":mime", $mimeType);
        $stmt->bindParam(":tamano", $tamanoBytes);
        $stmt->bindParam(":privacidad", $privacidad);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Elimina una imagen validando que el álbum no quede vacío.
     */
    public function delete($imageId) {
        // Obtener album_id
        $queryAlbum = "SELECT album_id FROM " . $this->table_name . " WHERE id = :id";
        $stmtAlbum = $this->conn->prepare($queryAlbum);
        $stmtAlbum->bindParam(":id", $imageId);
        $stmtAlbum->execute();
        $imgData = $stmtAlbum->fetch(PDO::FETCH_ASSOC);

        if (!$imgData) return false;

        // Validación de límite mínimo
        $queryCount = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE album_id = :album_id";
        $stmtCount = $this->conn->prepare($queryCount);
        $stmtCount->bindParam(":album_id", $imgData['album_id']);
        $stmtCount->execute();
        $row = $stmtCount->fetch(PDO::FETCH_ASSOC);

        if ($row['total'] <= 1) {
            throw new Exception("El álbum debe contener al menos una imagen.");
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $imageId);
        return $stmt->execute();
    }

    /**
     * Buscador global con reglas de privacidad estrictas.
     */
    public function searchImages($keyword, $currentUserId) {
        $query = "SELECT i.*, u.username 
                  FROM imagenes i
                  JOIN albumes a ON i.album_id = a.id
                  JOIN usuarios u ON a.usuario_id = u.id
                  WHERE (i.titulo LIKE :keyword)
                  AND i.estado_moderacion = 'aprobada'
                  AND (
                      i.privacidad = 'publico' 
                      OR (
                          i.privacidad = 'privado' 
                          AND EXISTS (
                              SELECT 1 FROM seguidores s 
                              WHERE s.seguidor_id = :current_user 
                              AND s.seguido_id = u.id 
                              AND s.estado = 'aceptada'
                          )
                      )
                      OR u.id = :current_user_owner -- El dueño siempre puede ver sus fotos privadas
                  )
                  ORDER BY i.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $keywordParam = "%$keyword%";
        $stmt->bindParam(":keyword", $keywordParam);
        $stmt->bindParam(":current_user", $currentUserId);
        $stmt->bindParam(":current_user_owner", $currentUserId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna detalles de imagen con datos de contacto del artesano.
     */
    public function getImageDetails($imageId) {
        $query = "SELECT i.*, u.nombre, u.apellido, u.email, a.titulo as album_titulo
                  FROM imagenes i
                  JOIN albumes a ON i.album_id = a.id
                  JOIN usuarios u ON a.usuario_id = u.id
                  WHERE i.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $imageId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
