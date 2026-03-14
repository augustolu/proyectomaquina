<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/ImageController.php';
require_once __DIR__ . '/../controllers/InteractionController.php';

$imageController = new ImageController();
$interactionController = new InteractionController();

$imageId = $_GET['id'] ?? null;
if (!$imageId) {
    header("Location: search.php");
    exit();
}

$imageRes = $imageController->getDetails($imageId);
if (!$imageRes['success']) {
    die("Obra no encontrada.");
}
$img = $imageRes['data'];

// Procesar interacciones (Likes y Comentarios)
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_like':
                $res = $interactionController->toggleLike($imageId);
                break;
            case 'post_comment':
                $res = $interactionController->postComment([
                    'imagen_id' => $imageId,
                    'contenido' => $_POST['contenido']
                ]);
                break;
        }
        $message = $res['message'];
        $messageType = $res['success'] ? "success" : "danger";
    }
}

$interactions = $interactionController->getInteractionsForImage($imageId);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($img['titulo'] ?: 'Obra'); ?> - Artesanos.com</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8d6e63;
            --bg-color: #fdfcf0;
        }
        body { background-color: var(--bg-color); padding-top: 80px; }
        .navbar { background: white !important; height: 70px; border-bottom: 1px solid #efebe9; }
        .detail-main { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .img-display { width: 100%; max-height: 600px; object-fit: contain; background: #fafafa; border-radius: 8px; }
        .btn-like-active { background-color: #d84315 !important; color: white !important; border: none !important; }
        .comment-item { border-bottom: 1px solid #eee; padding: 1rem 0; }
        .comment-user { font-weight: 700; color: var(--primary-color); font-size: 0.9rem; }
        .comment-text { margin-top: 0.3rem; }
    </style>
</head>
<body>

    <nav class="navbar fixed-top px-5">
        <a href="feed.html" class="navbar-brand text-decoration-none" style="color: var(--primary-color); font-weight:700;">Artesanos.com</a>
        <ul class="nav">
            <li class="nav-item"><a href="feed.html" class="nav-link text-dark fw-bold">Inicio</a></li>
            <li class="nav-item"><a href="search.php" class="nav-link text-dark">Buscador</a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link text-dark">Mi Perfil</a></li>
        </ul>
    </nav>

    <main class="container py-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Imagen Central -->
            <div class="col-lg-8">
                <div class="detail-main text-center">
                    <img src="../<?php echo htmlspecialchars($img['url_almacen']); ?>" class="img-display mb-3" alt="Obra">
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-start">
                            <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($img['titulo'] ?: 'Sin título'); ?></h2>
                            <p class="text-muted">Por <?php echo htmlspecialchars($img['nombre'] . ' ' . $img['apellido']); ?> en álbum <strong><?php echo htmlspecialchars($img['album_titulo']); ?></strong></p>
                        </div>
                        
                        <form action="image_detail.php?id=<?php echo $imageId; ?>" method="POST">
                            <input type="hidden" name="action" value="toggle_like">
                            <button type="submit" class="btn btn-outline-danger px-4 rounded-pill <?php echo $interactions['user_liked'] ? 'btn-like-active' : ''; ?>">
                                ❤️ <?php echo $interactions['likes_count']; ?> Likes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Interacciones (Comentarios) -->
            <div class="col-lg-4">
                <div class="detail-main">
                    <h5 class="fw-bold mb-4">Comentarios</h5>
                    
                    <div class="comment-list mb-4" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($interactions['comments'])): ?>
                            <p class="text-muted small">No hay comentarios aún. ¡Sé el primero!</p>
                        <?php else: ?>
                            <?php foreach ($interactions['comments'] as $c): ?>
                                <div class="comment-item">
                                    <span class="comment-user">@<?php echo htmlspecialchars($c['username']); ?></span>
                                    <p class="comment-text mb-0"><?php echo htmlspecialchars($c['contenido']); ?></p>
                                    <small class="text-muted"><?php echo $c['created_at']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form action="image_detail.php?id=<?php echo $imageId; ?>" method="POST">
                        <input type="hidden" name="action" value="post_comment">
                        <div class="mb-2">
                            <textarea class="form-control" name="contenido" rows="3" placeholder="Escribe un comentario..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Publicar</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
