<?php
class ModerationModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Registra una denuncia y verifica si la obra debe pasar a revisión.
     */
    public function reportImage($imageId, $userId, $reason) {
        $this->conn->beginTransaction();
        try {
            // 1. Insertar la denuncia
            $query = "INSERT INTO denuncias (imagen_id, usuario_id, motivo) VALUES (:img, :user, :motivo)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':img' => $imageId,
                ':user' => $userId,
                ':motivo' => $reason
            ]);

            // 2. Contar denuncias activas (no resueltas) para esta imagen
            $queryCount = "SELECT COUNT(*) as total FROM denuncias WHERE imagen_id = :img AND resuelta = 0";
            $stmtCount = $this->conn->prepare($queryCount);
            $stmtCount->execute([':img' => $imageId]);
            $count = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // 3. Si llega a 5, poner bajo revisión
            if ($count >= 5) {
                $queryUpdate = "UPDATE imagenes SET estado_moderacion = 'bajo_revision' WHERE id = :img";
                $stmtUpdate = $this->conn->prepare($queryUpdate);
                $stmtUpdate->execute([':img' => $imageId]);
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error al reportar imagen: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validación estricta: Bloquea si el usuario tiene 3 o más obras bajo revisión.
     */
    public function canUserUpload($userId) {
        $query = "SELECT COUNT(*) as total 
                  FROM imagenes i 
                  JOIN albumes a ON i.album_id = a.id 
                  WHERE a.usuario_id = :user AND i.estado_moderacion = 'bajo_revision'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user' => $userId]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $count < 3;
    }
}
?>
