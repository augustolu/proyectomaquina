<?php
class Database {
    private $host = "localhost";
    private $db_name = "artesanos_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Empleamos utf8mb4 según fue definido en la BDD para evitar problemas con emojis u otros caracteres
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            
            // Forzar que PDO lance excepciones en caso de error (buena práctica para manejo de errores)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Prevenir emulación de prepares (garantiza inyección segura en base de datos moderna)
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
        } catch(PDOException $exception) {
            // En entorno de producción, sería mejor registrar este error en un log y no mostrarlo directamente
            error_log("Connection error: " . $exception->getMessage());
            die("Error de conexión a la base de datos.");
        }
        return $this->conn;
    }
}
?>
