<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/ModerationModel.php';

class ModerationController {
    private $moderationModel;

    public function __construct() {
        $database = new Database();
        $this->moderationModel = new ModerationModel($database->getConnection());
    }

    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Procesa una denuncia de imagen
     */
    public function fileReport($data) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Autenticación requerida para denunciar."];
        }

        $userId = $_SESSION['user_id'];
        $imageId = $data['imagen_id'] ?? null;
        $reason = trim(htmlspecialchars(strip_tags($data['motivo'] ?? '')));

        if (!$imageId || empty($reason)) {
            return ["success" => false, "message" => "ID de imagen y motivo son obligatorios."];
        }

        if ($this->moderationModel->reportImage($imageId, $userId, $reason)) {
            return ["success" => true, "message" => "Denuncia registrada correctamente. Gracias por ayudar a mantener la comunidad segura."];
        }
        return ["success" => false, "message" => "No se pudo registrar la denuncia. Verifique si ya ha denunciado esta obra."];
    }

    /**
     * Método de utilidad para verificar bloqueo de usuario
     */
    public function checkUploadPermission($userId) {
        return $this->moderationModel->canUserUpload($userId);
    }
}
?>
