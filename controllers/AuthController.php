<?php
// Configuración básica de protección de sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// ini_set('session.cookie_secure', 1); // Descomentar en producción bajo HTTPS
session_start();

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->userModel = new UserModel($database->getConnection());
    }

    /**
     * Procesa la petición de registro, sanitizando las entradas
     */
    public function register($data) {
        // Prevención básica de inyección e XSS saneando la entrada
        $username = trim(htmlspecialchars(strip_tags($data['username'] ?? '')));
        $email    = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';
        $nombre   = trim(htmlspecialchars(strip_tags($data['nombre'] ?? '')));
        $apellido = trim(htmlspecialchars(strip_tags($data['apellido'] ?? '')));
        $fecha_nac = !empty($data['fecha_nacimiento']) ? $data['fecha_nacimiento'] : null;

        if (empty($username) || empty($email) || empty($password) || empty($nombre) || empty($apellido)) {
            return ["success" => false, "message" => "Ocurrió un error. Faltan datos requeridos para el registro."];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ["success" => false, "message" => "Formato de email incorrecto."];
        }

        if (strlen($password) < 8) {
            return ["success" => false, "message" => "La contraseña debe tener al menos 8 caracteres."];
        }

        $userId = $this->userModel->create($username, $email, $password, $nombre, $apellido, $fecha_nac);
        
        if ($userId) {
            return ["success" => true, "message" => "Te has registrado correctamente. Ahora puedes iniciar sesión."];
        }
        
        return ["success" => false, "message" => "Hubo un problema registrando el perfil o el usuario/correo ya está en uso."];
    }

    /**
     * Validar credenciales y construir sesión segura
     */
    public function login($data) {
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $data['password'] ?? '';

        if(empty($email) || empty($password)) {
            return ["success" => false, "message" => "Proporcione credenciales válidas."];
        }

        $user = $this->userModel->login($email, $password);
        
        if ($user) {
            // Mitigación recomendada de fijación de sesión (Session Fixation)
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            
            return ["success" => true, "message" => "Ingreso concedido."];
        }
        
        return ["success" => false, "message" => "Correo o contraseña equivocados."];
    }

    /**
     * Terminar la sesión
     */
    public function logout() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return ["success" => true, "message" => "Se ha cerrado la sesión exitosamente."];
    }
}
?>
