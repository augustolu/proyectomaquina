<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/AlbumController.php';
$albumController = new AlbumController();
$res = $albumController->listPublicAlbums();
$albums = $res['success'] ? $res['data'] : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Principal - Artesanos.com</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css?v=<?php echo time(); ?>">
    <style>
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }
        .art-card-img-container {
            height: 250px;
            overflow: hidden;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .art-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .art-card:hover .art-card-img {
            transform: scale(1.1);
        }
        .placeholder-cover {
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--accent-soft), var(--bg-cream));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-primary);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Contenido Principal -->
    <main class="container">
        <header class="py-5 text-center">
            <h1 class="display-4 fw-bold">Descubre el Arte</h1>
            <p class="text-muted">Explora los álbumes publicados por nuestra comunidad de artesanos.</p>
        </header>

        <div class="gallery-grid">
            <?php if (empty($albums)): ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No hay álbumes públicos publicados aún.</p>
                </div>
            <?php else: ?>
                <?php foreach ($albums as $alb): ?>
                    <a href="album_detail.php?id=<?php echo $alb['id']; ?>" class="text-decoration-none">
                        <article class="art-card h-100">
                            <div class="art-card-img-container">
                                <?php if ($alb['portada']): ?>
                                    <img src="../<?php echo htmlspecialchars($alb['portada']); ?>" alt="<?php echo htmlspecialchars($alb['titulo']); ?>" class="art-card-img">
                                <?php else: ?>
                                    <div class="placeholder-cover">Sin fotos aún</div>
                                <?php endif; ?>
                            </div>
                            <div class="art-card-body">
                                <h2 class="art-title h5 mb-1"><?php echo htmlspecialchars($alb['titulo']); ?></h2>
                                <p class="art-author mb-0 text-muted">
                                    por <strong><?php echo htmlspecialchars($alb['nombre'] . ' ' . $alb['apellido']); ?></strong>
                                    <br>
                                    <small>@<?php echo htmlspecialchars($alb['username']); ?></small>
                                </p>
                            </div>
                        </article>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
