<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/AlbumController.php';

$albumController = new AlbumController();
$albumId = $_GET['id'] ?? null;

if (!$albumId) {
    header("Location: profile.php");
    exit();
}

// Obtener datos del álbum y sus imágenes
// Necesitamos un método en AlbumController para esto, o usar el modelo directamente
$albumsResult = $albumController->listMyAlbums(); // Esto solo lista los del usuario actual
// Como no hay un "getAlbumDetail" público en el controller todavía, voy a improvisar o añadirlo.
// Para propósitos de este ejercicio, asumiré que podemos obtener imágenes del modelo.

require_once __DIR__ . '/../models/AlbumModel.php';
require_once __DIR__ . '/../config/Database.php';
$db = (new Database())->getConnection();
$albumModel = new AlbumModel($db);

$album = $albumModel->getById($albumId);
if (!$album) {
    die("Álbum no encontrado.");
}
$images = $albumModel->getAlbumImages($albumId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album['titulo']); ?> - Artesanos.com</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8d6e63;
            --bg-color: #fdfcf0;
        }
        body { background-color: var(--bg-color); padding-top: 80px; }
        .navbar { background: white !important; height: 70px; border-bottom: 1px solid #efebe9; }
        .art-card { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .art-card:hover { transform: translateY(-5px); }
        .art-card-img { width: 100%; height: 200px; object-fit: cover; }
        .art-card-body { padding: 1rem; }
    </style>
</head>
<body>

    <nav class="navbar fixed-top px-5">
        <a href="feed.html" class="navbar-brand text-decoration-none" style="color: var(--primary-color); font-weight:700;">Artesanos.com</a>
        <ul class="nav">
            <li class="nav-item"><a href="feed.html" class="nav-link text-dark fw-bold">Inicio</a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link text-dark">Mi Perfil</a></li>
        </ul>
    </nav>

    <main class="container py-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="profile.php">Mi Perfil</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($album['titulo']); ?></li>
            </ol>
        </nav>

        <h1 class="fw-bold mb-2"><?php echo htmlspecialchars($album['titulo']); ?></h1>
        <p class="text-muted mb-4"><?php echo htmlspecialchars($album['descripcion'] ?: 'Sin descripción.'); ?></p>

        <div class="row g-4">
            <?php if (empty($images)): ?>
                <p class="text-center py-5 text-muted">Aún no hay obras en este álbum.</p>
            <?php else: ?>
                <?php foreach ($images as $img): ?>
                    <div class="col-6 col-md-3">
                        <a href="image_detail.php?id=<?php echo $img['id']; ?>" class="text-decoration-none">
                            <article class="art-card">
                                <img src="../<?php echo htmlspecialchars($img['url_almacen']); ?>" class="art-card-img" alt="Obra">
                                <div class="art-card-body text-center">
                                    <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($img['titulo'] ?: 'Sin título'); ?></h6>
                                </div>
                            </article>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
