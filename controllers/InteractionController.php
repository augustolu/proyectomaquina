<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/InteractionModel.php';

class InteractionController {
    private $interactionModel;

    public function __construct() {
        $database = new Database();
        $this->interactionModel = new InteractionModel($database->getConnection());
    }

    private function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Alterna un Like en una imagen.
     */
    public function toggleLike($imageId) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Debe iniciar sesión para interactuar."];
        }

        $userId = $_SESSION['user_id'];
        if ($this->interactionModel->toggleLike($imageId, $userId)) {
            return ["success" => true, "message" => "Interacción de Like procesada."];
        }
        return ["success" => false, "message" => "Error al procesar el Like."];
    }

    /**
     * Publica un comentario (acepta respuestas anidadas).
     */
    public function postComment($data) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Sesión requerida para comentar."];
        }

        $userId = $_SESSION['user_id'];
        $imageId = $data['imagen_id'] ?? null;
        $content = trim(htmlspecialchars(strip_tags($data['contenido'] ?? '')));
        $parentId = !empty($data['comentario_id_padre']) ? $data['comentario_id_padre'] : null;

        if (!$imageId || empty($content)) {
            return ["success" => false, "message" => "Imagen y contenido son campos obligatorios."];
        }

        if ($this->interactionModel->addComment($imageId, $userId, $content, $parentId)) {
            return ["success" => true, "message" => "Comentario publicado."];
        }
        return ["success" => false, "message" => "Error al guardar el comentario."];
    }

    /**
     * Inicia una solicitud de seguimiento.
     */
    public function followUser($followedId) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Autenticación necesaria."];
        }

        $followerId = $_SESSION['user_id'];
        if ($followerId == $followedId) {
            return ["success" => false, "message" => "No puede seguirse a sí mismo."];
        }

        if ($this->interactionModel->sendFollowRequest($followerId, $followedId)) {
            return ["success" => true, "message" => "Solicitud enviada correctamente."];
        }
        return ["success" => false, "message" => "Error al enviar solicitud o ya existe."];
    }

    /**
     * Responde a una solicitud de seguimiento (Aceptar dispara lógica crítica).
     */
    public function respondFollowRequest($followerId, $accept = true) {
        if (!$this->isAuthenticated()) {
            return ["success" => false, "message" => "Acceso denegado."];
        }

        $followedId = $_SESSION['user_id']; // El usuario logueado es el que fue seguido

        if ($accept) {
            // Lógica crítica con transacción y clonación de imágenes
            if ($this->interactionModel->acceptFollowRequest($followerId, $followedId)) {
                return ["success" => true, "message" => "Solicitud aceptada y álbum sincronizado."];
            }
        } else {
            if ($this->interactionModel->rejectFollowRequest($followerId, $followedId)) {
                return ["success" => true, "message" => "Solicitud rechazada."];
            }
        }

        return ["success" => false, "message" => "Error al procesar la respuesta de seguimiento."];
    }

    /**
     * Obtiene todos los datos de interacción para una imagen.
     */
    public function getInteractionsForImage($imageId) {
        $likes = $this->interactionModel->getLikesCount($imageId);
        $comments = $this->interactionModel->getComments($imageId);
        $userLiked = false;
        
        if ($this->isAuthenticated()) {
            $userLiked = $this->interactionModel->userLiked($imageId, $_SESSION['user_id']);
        }

        return [
            "likes_count" => $likes,
            "user_liked" => $userLiked,
            "comments" => $comments
        ];
    }

    /**
     * Obtiene el estado de seguimiento.
     */
    public function getFollowStatus($followedId) {
        if (!$this->isAuthenticated()) return null;
        return $this->interactionModel->getFollowStatus($_SESSION['user_id'], $followedId);
    }

    /**
     * Obtiene las solicitudes pendientes para el usuario logueado.
     */
    public function getPendingRequests() {
        if (!$this->isAuthenticated()) return [];
        return $this->interactionModel->getPendingRequests($_SESSION['user_id']);
    }
}
?>
