<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/controllers/AuthController.php';

$auth = new AuthController();
$error = $_SESSION['error_message'] ?? null;
$success = ($_GET['registered'] ?? '') === 'success';

// Limpiar mensajes después de leerlos
unset($_SESSION['error_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth->register($_POST);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Artesanos.com</title>
    <!-- Estilos compartidos de autenticación -->
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        /* Ajustes específicos para el registro que tiene más campos */
        .auth-card { max-width: 500px; }
        .row { display: flex; gap: 1rem; }
        .row .form-group { flex: 1; }
        .alert { 
            padding: 0.8rem; 
            margin-bottom: 1rem; 
            border-radius: 8px; 
            font-size: 0.9rem;
            text-align: center;
        }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-link { font-weight: bold; color: inherit; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <h1>Únete a Artesanos</h1>
        <p>Crea tu cuenta para empezar a compartir</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ¡Registro exitoso! Ya puedes <a href="views/login.html" class="alert-link">Inicia sesión</a>.
        </div>
    <?php endif; ?>

    <form action="registro.php" method="POST">
        <div class="form-group">
            <label for="username">Nombre de Usuario</label>
            <input type="text" id="username" name="username" placeholder="Tu apodo artístico" required>
        </div>
        
        <div class="row">
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" placeholder="Ej: Juan" required>
            </div>
            <div class="form-group">
                <label for="apellido">Apellido</label>
                <input type="text" id="apellido" name="apellido" placeholder="Ej: Pérez" required>
            </div>
        </div>

        <div class="form-group">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" placeholder="ejemplo@artesanos.com" required>
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn-primary">Registrarse</button>
    </form>

    <div class="auth-footer">
        ¿Ya tienes cuenta? <a href="views/login.html">Inicia sesión</a>
    </div>
</div>

</body>
</html>
