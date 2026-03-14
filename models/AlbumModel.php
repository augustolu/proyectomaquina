<?php
class AlbumModel {
    private $conn;
    private $table_name = "albumes";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Crea un nuevo álbum para el usuario.
     */
    public function create($userId, $titulo, $descripcion = null) {
        $query = "INSERT INTO " . $this->table_name . " (usuario_id, titulo, descripcion) VALUES (:usuario_id, :titulo, :descripcion)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":usuario_id", $userId);
        $stmt->bindParam(":titulo", $titulo);
        $stmt->bindParam(":descripcion", $descripcion);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Obtiene los álbumes de un usuario, excluyendo el de perfil.
     */
    public function getUserAlbums($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE usuario_id = :usuario_id AND tipo = 'normal' ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario_id", $userId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todas las imágenes de un álbum.
     */
    public function getAlbumImages($albumId) {
        $query = "SELECT * FROM imagenes WHERE album_id = :album_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":album_id", $albumId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una imagen para usar como portada del álbum.
     */
    public function getAlbumCover($albumId) {
        $query = "SELECT url_almacen FROM imagenes WHERE album_id = :album_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":album_id", $albumId);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ? $res['url_almacen'] : null;
    }
}
?>
