<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ImageModel.php';

class ImageController {
    private $imageModel;

    public function __construct() {
        $database = new Database();
        $this->imageModel = new ImageModel($database->getConnection());
    }

    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Sube una imagen a un álbum con validación de límites
     */
    public function uploadToAlbum($albumId, $fileData, $privacidad = 'publico') {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Autenticación requerida."];
        }

        if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            return ["success" => false, "message" => "Archivo no válido."];
        }

        $userId = $_SESSION['user_id'];
        $uploadDir = __DIR__ . '/../uploads/gallery/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);
        
        $validMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $validMimes)) {
            return ["success" => false, "message" => "Formato de imagen no permitido."];
        }

        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $fileName = sprintf('album_%s_%s.%s', $albumId, bin2hex(random_bytes(8)), $extension);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            $sizeBytes = filesize($targetPath);
            $urlAlmacen = 'uploads/gallery/' . $fileName;

            try {
                if ($this->imageModel->create($albumId, $urlAlmacen, $mimeType, $sizeBytes, $privacidad)) {
                    return ["success" => true, "message" => "Imagen añadida al álbum."];
                }
                unlink($targetPath);
            } catch (Exception $e) {
                unlink($targetPath);
                return ["success" => false, "message" => $e->getMessage()];
            }
        }
        return ["success" => false, "message" => "Error al guardar el archivo."];
    }

    /**
     * Elimina una imagen con validación de mínimo 1
     */
    public function deleteImage($imageId) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Acceso no autorizado."];
        }

        try {
            if ($this->imageModel->delete($imageId)) {
                return ["success" => true, "message" => "Imagen eliminada."];
            }
        } catch (Exception $e) {
            return ["success" => false, "message" => $e->getMessage()];
        }
        return ["success" => false, "message" => "No se pudo eliminar la imagen."];
    }

    /**
     * Motor de búsqueda global con privacidad
     */
    public function search($keyword) {
        $currentUserId = $_SESSION['user_id'] ?? 0;
        $keyword = trim($keyword);
        
        if (empty($keyword)) {
            return ["success" => false, "message" => "Criterio de búsqueda vacío."];
        }

        $results = $this->imageModel->searchImages($keyword, $currentUserId);
        return ["success" => true, "data" => $results];
    }

    /**
     * Obtiene detalles comerciales de la imagen
     */
    public function getDetails($imageId) {
        $details = $this->imageModel->getImageDetails($imageId);
        if ($details) {
            return ["success" => true, "data" => $details];
        }
        return ["success" => false, "message" => "Imagen no encontrada."];
    }
}
?>
