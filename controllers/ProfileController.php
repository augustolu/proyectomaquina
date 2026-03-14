<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/UserModel.php';

class ProfileController {
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->userModel = new UserModel($database->getConnection());
    }

    /**
     * Obtiene los datos de un usuario por ID.
     */
    public function getUserById($userId) {
        $user = $this->userModel->getUserById($userId);
        if ($user) {
            return ["success" => true, "data" => $user];
        }
        return ["success" => false, "message" => "Usuario no encontrado."];
    }

    /**
     * Verificación de acceso
     */
    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Actualiza información básica de perfil
     */
    public function updateProfileInfo($data) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "No está autorizado para esta acción."];
        }

        $userId = $_SESSION['user_id'];
        
        // Sanitizar inputs
        $nombre    = trim(htmlspecialchars(strip_tags($data['nombre'] ?? '')));
        $apellido  = trim(htmlspecialchars(strip_tags($data['apellido'] ?? '')));
        $biografia = htmlspecialchars(strip_tags($data['biografia'] ?? ''), ENT_QUOTES);
        $es_privada = isset($data['es_cuenta_privada']) ? (int)$data['es_cuenta_privada'] : 0;

        if (empty($nombre) || empty($apellido)) {
            return ["success" => false, "message" => "Los campos de nombre y apellido no pueden estar vacíos."];
        }

        if ($this->userModel->updateProfile($userId, $nombre, $apellido, $biografia, $es_privada)) {
            return ["success" => true, "message" => "Perfil actualizado exitosamente."];
        }
        return ["success" => false, "message" => "Error interno actualizando la información."];
    }

    /**
     * Gestiona el resguardo de intereses seleccionados
     */
    public function updateInterests($data) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Acceso denegado."];
        }

        // Se asume un arreglo de enteros por IDs
        $userId = $_SESSION['user_id'];
        $interestIds = $data['intereses'] ?? []; 
        
        $cleanInterestIds = array_map('intval', $interestIds);

        if ($this->userModel->updateInterests($userId, $cleanInterestIds)) {
            return ["success" => true, "message" => "Lista de intereses actualizada."];
        }
        return ["success" => false, "message" => "Error al guardar intereses."];
    }
    
    /**
     * Actualizar contraseña
     */
    public function changePassword($data) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "No está autorizado."];
        }
        
        $newPassword = $data['new_password'] ?? '';
        if (strlen($newPassword) < 8) {
            return ["success" => false, "message" => "La contraseña tiene que tener al menos 8 caracteres."];
        }
        
        $userId = $_SESSION['user_id'];
        if ($this->userModel->changePassword($userId, $newPassword)) {
            return ["success" => true, "message" => "Contraseña modificada correctamente."];
        }
        return ["success" => false, "message" => "Error de sistema al cambiar la contraseña."];
    }

    /**
     * Procesamiento de foto de perfil con manejo de archivos y delegación al modelo
     */
    public function uploadProfilePhoto($fileData) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Acceso no autorizado."];
        }
        
        if (!isset($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            return ["success" => false, "message" => "No se ha subido ningún archivo válido."];
        }
        
        $userId = $_SESSION['user_id'];
        $uploadDir = __DIR__ . '/../uploads/profiles/';
        
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true); // Crear de forma segura
        }

        // Validación de tipo (Prevenir subida de archivos php ocultos como imagen, un vector común)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileData['tmp_name']);
        finfo_close($finfo);
        
        $validMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $validMimes)) {
            return ["success" => false, "message" => "Solo se permiten imágenes (JPEG, PNG, GIF, WEBP)."];
        }

        // Generar nombre de destino seguro
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $fileName = sprintf('%s_%s.%s', $userId, bin2hex(random_bytes(8)), $extension);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($fileData['tmp_name'], $targetPath)) {
            $sizeBytes = filesize($targetPath);
            $urlAlmacen = 'uploads/profiles/' . $fileName;
            
            if ($this->userModel->updateProfilePhoto($userId, $urlAlmacen, $mimeType, $sizeBytes)) {
                return ["success" => true, "message" => "Nueva foto subida exitosamente.", "url" => $urlAlmacen];
            }
            // Limpieza en caso de no poder insertarlo en base de datos
            unlink($targetPath);
        }
        
        return ["success" => false, "message" => "Fallo de persistencia en disco o base de datos."];
    }

    /**
     * Retorna el registro de modificaciones a la foto de perfil (opcionalmente por userId)
     */
    public function getProfilePhotoHistory($userId = null) {
        $id = $userId ?? ($_SESSION['user_id'] ?? null);
        if (!$id) return ["success" => false, "message" => "ID no proporcionado."];
        
        $history = $this->userModel->getProfilePhotoHistory($id);
        
        return ["success" => true, "data" => $history];
    }

    /**
     * Obtiene todos los datos del perfil actual
     */
    public function getProfileData() {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "No está autorizado."];
        }
        
        $userId = $_SESSION['user_id'];
        $data = $this->userModel->getUserById($userId);
        
        if ($data) {
            return ["success" => true, "data" => $data];
        }
        return ["success" => false, "message" => "Usuario no encontrado."];
    }

    /**
     * Obtiene la lista de todos los intereses y los marcados por un usuario
     */
    public function getInterestsData($userId = null) {
        $id = $userId ?? ($_SESSION['user_id'] ?? null);
        $all = $this->userModel->getAllInterests();
        $userInterests = $id ? $this->userModel->getUserInterests($id) : [];

        return [
            "success" => true,
            "all_interests" => $all,
            "user_interests" => $userInterests
        ];
    }
}
?>
