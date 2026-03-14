<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ImageModel.php';
require_once __DIR__ . '/../models/ModerationModel.php';

class ImageController {
    private $imageModel;
    private $moderationModel;

    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
        $this->imageModel = new ImageModel($db);
        $this->moderationModel = new ModerationModel($db);
    }

    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Sube una imagen a un álbum con validación de límites (Soporta archivos y Base64)
     */
    public function uploadToAlbum($albumId, $fileData, $privacidad = 'publico', $titulo = null, $base64Data = null) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Autenticación requerida."];
        }

        $userId = $_SESSION['user_id'];
        $uploadDir = __DIR__ . '/../uploads/gallery/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if ($base64Data) {
            // Procesamiento de imagen Base64 (Recortada)
            list($type, $data) = explode(';', $base64Data);
            list(, $data) = explode(',', $data);
            $decodedData = base64_decode($data);
            
            $mimeType = str_replace('data:', '', $type);
            $extension = 'png'; // Croppie suele devolver PNG por defecto si no se especifica
            if (strpos($mimeType, 'jpeg') !== false) $extension = 'jpg';
            
            $fileName = sprintf('album_%s_%s.%s', $albumId, bin2hex(random_bytes(8)), $extension);
            $targetPath = $uploadDir . $fileName;
            
            if (file_put_contents($targetPath, $decodedData)) {
                $sizeBytes = strlen($decodedData);
                $urlAlmacen = 'uploads/gallery/' . $fileName;
                
                try {
                    if ($this->imageModel->create($albumId, $urlAlmacen, $mimeType, $sizeBytes, $privacidad)) {
                        return ["success" => true, "message" => "Imagen (recortada) añadida al álbum."];
                    }
                    unlink($targetPath);
                } catch (Exception $e) {
                    unlink($targetPath);
                    return ["success" => false, "message" => $e->getMessage()];
                }
            }
            return ["success" => false, "message" => "Error al guardar la imagen recortada."];
        }

        // Procesamiento estándar de archivo
        if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            return ["success" => false, "message" => "Archivo no válido."];
        }

        $userId = $_SESSION['user_id'];

        // REGLA DE INGENIERÍA: Bloqueo preventivo por moderación
        if (!$this->moderationModel->canUserUpload($userId)) {
            return ["success" => false, "message" => "Subida bloqueada: Tienes 3 o más obras bajo revisión de moderación."];
        }

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

        $userId = $_SESSION['user_id'];
        $details = $this->imageModel->getImageDetails($imageId);

        if (!$details || $details['usuario_id'] != $userId) {
            return ["success" => false, "message" => "No tienes permiso para borrar esta obra."];
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
     * Actualiza el título de una obra
     */
    public function updateTitle($imageId, $title) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Sesión requerida."];
        }

        $userId = $_SESSION['user_id'];
        $details = $this->imageModel->getImageDetails($imageId);

        if (!$details || $details['usuario_id'] != $userId) {
            return ["success" => false, "message" => "No tienes permiso para editar esta obra."];
        }

        $title = trim(htmlspecialchars(strip_tags($title)));
        
        if ($this->imageModel->updateTitle($imageId, $title)) {
            return ["success" => true, "message" => "Título actualizado."];
        }
        return ["success" => false, "message" => "Error al actualizar título."];
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
