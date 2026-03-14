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

$isOwner = (isset($_SESSION['user_id']) && $album['usuario_id'] == $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_album') {
        $deleteRes = $albumController->deleteAlbum($albumId);
        if ($deleteRes['success']) {
            header("Location: profile.php");
            exit();
        } else {
            $error = $deleteRes['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($album['titulo']); ?> - Artesanos.com</title>
    <link rel="stylesheet" href="../assets/css/main.css?v=<?php echo time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="profile.php" class="text-muted text-decoration-none">Perfil</a></li>
                        <li class="breadcrumb-item active text-accent" aria-current="page"><?php echo htmlspecialchars($album['titulo']); ?></li>
                    </ol>
                </nav>
                <h1 class="display-4 fw-bold mb-0"><?php echo htmlspecialchars($album['titulo']); ?></h1>
                <p class="text-muted lead mt-2"><?php echo htmlspecialchars($album['descripcion'] ?: 'Explora esta colección exclusiva.'); ?></p>
            </div>
            <?php if ($isOwner): ?>
                <form action="album_detail.php?id=<?php echo $albumId; ?>" method="POST" onsubmit="return confirm('¿Borrar este álbum?');">
                    <input type="hidden" name="action" value="delete_album">
                    <button type="submit" class="btn btn-outline-danger px-4 rounded-pill">Borrar Colección</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if (empty($images)): ?>
                <p class="text-center py-5 text-muted">Aún no hay obras en este álbum.</p>
            <?php else: 
                // La primera imagen es la PORTADA
                $cover = array_shift($images); 
            ?>
                <!-- Portada destacados (Hero Section) -->
                <div class="col-12 mb-5">
                    <div class="detail-main p-0 overflow-hidden">
                        <div class="row g-0">
                            <div class="col-md-7">
                                <img src="../<?php echo htmlspecialchars($cover['url_almacen']); ?>" class="img-display w-100" style="height: 500px; object-fit: cover; border-radius: 0;" alt="Portada">
                            </div>
                            <div class="col-md-5 d-flex align-items-center p-5">
                                <div>
                                    <span class="text-uppercase tracking-widest small fw-bold text-accent d-block mb-3">Obra Representativa</span>
                                    <h2 class="display-6 fw-bold mb-4"><?php echo htmlspecialchars($cover['titulo'] ?: $album['titulo']); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <h5 class="fw-bold mb-3">Obras en este álbum</h5>
                </div>

                <?php if (empty($images)): ?>
                    <div class="col-12">
                        <p class="text-muted small">No hay más obras adicionales.</p>
                    </div>
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
            <?php endif; ?>
        </div>
    </main>

</body>
</html>
