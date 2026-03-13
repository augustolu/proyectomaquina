<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/AlbumModel.php';

class AlbumController {
    private $albumModel;

    public function __construct() {
        $database = new Database();
        $this->albumModel = new AlbumModel($database->getConnection());
    }

    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Crea un nuevo álbum
     */
    public function createAlbum($data) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Sesión no iniciada."];
        }

        $userId = $_SESSION['user_id'];
        $titulo = trim(htmlspecialchars(strip_tags($data['titulo'] ?? '')));
        $descripcion = trim(htmlspecialchars(strip_tags($data['descripcion'] ?? '')));

        if (empty($titulo)) {
            return ["success" => false, "message" => "El título del álbum es obligatorio."];
        }

        if ($this->albumModel->create($userId, $titulo, $descripcion)) {
            return ["success" => true, "message" => "Álbum creado exitosamente."];
        }
        return ["success" => false, "message" => "Error al crear el álbum."];
    }

    /**
     * Lista los álbumes del usuario (excluyendo perfil)
     */
    public function listMyAlbums() {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Acceso denegado."];
        }

        $userId = $_SESSION['user_id'];
        $albums = $this->albumModel->getUserAlbums($userId);

        return ["success" => true, "data" => $albums];
    }
}
?>
