<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/AlbumModel.php';
require_once __DIR__ . '/ImageController.php';

class AlbumController {
    private $albumModel;
    private $imageController;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->albumModel = new AlbumModel($db);
        $this->imageController = new ImageController();
    }

    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Crea un nuevo álbum obligando a subir la primera imagen
     */
    public function createAlbum($data, $fileData) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Sesión no iniciada."];
        }

        $userId = $_SESSION['user_id'];
        $titulo = trim(htmlspecialchars(strip_tags($data['titulo'] ?? '')));
        $descripcion = trim(htmlspecialchars(strip_tags($data['descripcion'] ?? '')));

        if (empty($titulo)) {
            return ["success" => false, "message" => "Cuidado: El álbum NECESITA un título."];
        }

        // REGLA: Sin foto no hay álbum
        if (!isset($fileData['tmp_name']) || empty($fileData['tmp_name'])) {
            return ["success" => false, "message" => "Por regla del sistema, debes subir al menos una foto para crear el álbum."];
        }

        $albumId = $this->albumModel->create($userId, $titulo, $descripcion);
        if ($albumId) {
            // Intentar subir la primera imagen
            $imgRes = $this->imageController->uploadToAlbum($albumId, $fileData);
            
            if ($imgRes['success']) {
                return ["success" => true, "message" => "¡Álbum '$titulo' creado con su primera obra!"];
            } else {
                // Si la imagen falla, no queremos un álbum vacío (Regla del Mínimo)
                // Nota: Podríamos borrar el álbum aquí si fuera necesario, o simplemente informar.
                return ["success" => false, "message" => "Error al subir la imagen inicial: " . $imgRes['message']];
            }
        }
        return ["success" => false, "message" => "Error interno al crear el álbum."];
    }

    /**
     * Lista los álbumes del usuario (excluyendo perfil)
     */
    public function listMyAlbums() {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Acceso denegado."];
        }

        return $this->listUserAlbums($_SESSION['user_id']);
    }

    /**
     * Lista los álbumes de cualquier usuario.
     */
    public function listUserAlbums($userId) {
        $albums = $this->albumModel->getUserAlbums($userId);
        return ["success" => true, "data" => $albums];
    }

    /**
     * Obtiene la portada de un álbum
     */
    public function getAlbumCover($albumId) {
        return $this->albumModel->getAlbumCover($albumId);
    }
}
?>
