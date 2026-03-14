<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Artesanos.com</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="auth-card">
        <div class="auth-header">
            <h1>Artesanos.com</h1>
            <p>Bienvenido de vuelta a la comunidad</p>
        </div>

        <form action="../controllers/AuthController.php?action=login" method="POST">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" placeholder="ejemplo@artesanos.com" required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-primary">Ingresar</button>
        </form>

        <div class="auth-footer">
            ¿Aún no eres miembro? <a href="../registro.php">Únete aquí</a>
        </div>
    </div>
</body>
</html>
