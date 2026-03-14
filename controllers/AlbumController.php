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
    public function createAlbum($data, $fileData, $base64Data = null) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Sesión no iniciada."];
        }

        $userId = $_SESSION['user_id'];
        $titulo = trim(htmlspecialchars(strip_tags($data['titulo'] ?? '')));
        $descripcion = trim(htmlspecialchars(strip_tags($data['descripcion'] ?? '')));

        if (empty($titulo)) {
            return ["success" => false, "message" => "Cuidado: El álbum NECESITA un título."];
        }

        // REGLA: Sin foto no hay álbum (Debe venir archivo O base64)
        $hasFile = isset($fileData['tmp_name']) && !empty($fileData['tmp_name']);
        if (!$hasFile && !$base64Data) {
            return ["success" => false, "message" => "Por regla del sistema, debes subir al menos una foto para crear el álbum."];
        }

        $albumId = $this->albumModel->create($userId, $titulo, $descripcion);
        if ($albumId) {
            // Intentar subir la primera imagen
            $imgRes = $this->imageController->uploadToAlbum($albumId, $fileData, 'publico', null, $base64Data);
            
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

    /**
     * Elimina un álbum y todas sus imágenes asociadas.
     */
    public function deleteAlbum($albumId) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Sesión no iniciada."];
        }

        $userId = $_SESSION['user_id'];
        $album = $this->albumModel->getById($albumId);

        if (!$album || $album['usuario_id'] != $userId) {
            return ["success" => false, "message" => "No tienes permiso para borrar este álbum."];
        }

        // 1. Obtener todas las imágenes para borrar los archivos físicos
        $images = $this->albumModel->getAlbumImages($albumId);
        foreach ($images as $img) {
            $filePath = __DIR__ . '/../' . $img['url_almacen'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        // 2. Borrar de la base de datos (Imágenes primero si no hay CASCADE)
        if ($this->albumModel->delete($albumId)) {
            return ["success" => true, "message" => "Álbum eliminado correctamente."];
        }
        
        return ["success" => false, "message" => "Error al eliminar el álbum."];
    }
    /**
     * Lista todos los álbumes de usuarios públicos para el feed global.
     */
    public function listPublicAlbums() {
        $albums = $this->albumModel->getAllPublicAlbums();
        
        // Enriquecer con la portada
        foreach ($albums as &$alb) {
            $alb['portada'] = $this->albumModel->getAlbumCover($alb['id']);
        }
        
        return ["success" => true, "data" => $albums];
    }
}
?>
