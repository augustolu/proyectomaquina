<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/UserModel.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->userModel = new UserModel($database->getConnection());
    }

    /**
     * Maneja el registro de nuevos usuarios
     */
    public function register($data) {
        $username = trim(htmlspecialchars(strip_tags($data['username'] ?? '')));
        $nombre = trim(htmlspecialchars(strip_tags($data['nombre'] ?? '')));
        $apellido = trim(htmlspecialchars(strip_tags($data['apellido'] ?? '')));
        $email = trim(htmlspecialchars(strip_tags($data['email'] ?? '')));
        $password = $data['password'] ?? '';

        if (empty($username) || empty($email) || empty($password) || empty($nombre) || empty($apellido)) {
            $target = (basename($_SERVER['PHP_SELF']) == 'registro.php') ? 'registro.php' : '../registro.php';
            $this->redirectWithError("Todos los campos son obligatorios.", $target);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $target = (basename($_SERVER['PHP_SELF']) == 'registro.php') ? 'registro.php' : '../registro.php';
            $this->redirectWithError("Formato de email inválido.", $target);
        }

        $userId = $this->userModel->create($username, $email, $password, $nombre, $apellido);

        if ($userId) {
            $target = (basename($_SERVER['PHP_SELF']) == 'registro.php') ? 'registro.php?registered=success' : '../registro.php?registered=success';
            header("Location: $target");
            exit();
        } else {
            $target = (basename($_SERVER['PHP_SELF']) == 'registro.php') ? 'registro.php' : '../registro.php';
            $this->redirectWithError("Error al crear el usuario. El email o username ya pueden estar en uso.", $target);
        }
    }

    /**
     * Maneja el inicio de sesión
     */
    public function login($data) {
        $email = trim(htmlspecialchars(strip_tags($data['email'] ?? '')));
        $password = $data['password'] ?? '';

        if (empty($email) || empty($password)) {
            $target = (basename($_SERVER['PHP_SELF']) == 'AuthController.php') ? '../views/login.html' : 'views/login.html';
            $this->redirectWithError("Email y contraseña requeridos.", $target);
        }

        $user = $this->userModel->login($email, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $target = (basename($_SERVER['PHP_SELF']) == 'AuthController.php') ? '../views/feed.html' : 'views/feed.html';
            header("Location: $target");
            exit();
        } else {
            $target = (basename($_SERVER['PHP_SELF']) == 'AuthController.php') ? '../views/login.html' : 'views/login.html';
            $this->redirectWithError("Credenciales incorrectas.", $target);
        }
    }

    /**
     * Cierra la sesión
     */
    public function logout() {
        session_unset();
        session_destroy();
        $target = (basename($_SERVER['PHP_SELF']) == 'AuthController.php') ? '../views/login.html' : 'views/login.html';
        header("Location: $target");
        exit();
    }

    private function redirectWithError($message, $url) {
        $_SESSION['error_message'] = $message;
        header("Location: $url");
        exit();
    }
}

// Router básico para el controlador - Solo se ejecuta si se llama directamente
if (basename($_SERVER['PHP_SELF']) == 'AuthController.php') {
    $auth = new AuthController();
    $action = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'register') {
            $auth->register($_POST);
        } elseif ($action === 'login') {
            $auth->login($_POST);
        }
    } elseif ($action === 'logout') {
        $auth->logout();
    }
}
?>
