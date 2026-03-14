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

$currentUserId = $_SESSION['user_id'] ?? null;
$isOwner = ($currentUserId && $img['usuario_id'] == $currentUserId);

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_title':
                $res = $imageController->updateTitle($imageId, $_POST['titulo']);
                if ($res['success']) $img['titulo'] = $_POST['titulo'];
                break;
            case 'delete_image':
                $res = $imageController->deleteImage($imageId);
                if ($res['success']) {
                    header("Location: profile.php");
                    exit();
                }
                break;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/main.css?v=<?php echo time(); ?>">
    <style>
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

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
                <div class="detail-main">
                    <div class="text-center mb-4">
                        <img src="../<?php echo htmlspecialchars($img['url_almacen']); ?>" class="img-display img-fluid" alt="Obra">
                    </div>
                    
                    <div class="artist-badge">
                        <span>🎨</span>
                        <span>
                            Por <strong><?php echo htmlspecialchars($img['nombre'] . ' ' . $img['apellido']); ?></strong> 
                            en <strong><?php echo htmlspecialchars($img['album_titulo']); ?></strong>
                        </span>
                    </div>

                    <div class="mb-4">
                        <?php if ($isOwner): ?>
                            <form action="image_detail.php?id=<?php echo $imageId; ?>" method="POST" class="d-flex align-items-center gap-2">
                                <input type="hidden" name="action" value="update_title">
                                <input type="text" name="titulo" class="form-control form-control-lg fw-bold border-0 bg-light" value="<?php echo htmlspecialchars($img['titulo'] ?: ''); ?>" placeholder="Añadir título...">
                                <button type="submit" class="btn btn-primary">Guardar</button>
                            </form>
                        <?php else: ?>
                            <h1 class="display-5 fw-bold mb-0"><?php echo htmlspecialchars($img['titulo'] ?: 'Sin título'); ?></h1>
                        <?php endif; ?>
                    </div>

                    <div class="action-row">
                        <form action="image_detail.php?id=<?php echo $imageId; ?>" method="POST">
                            <input type="hidden" name="action" value="toggle_like">
                            <button type="submit" class="btn btn-outline-danger px-4 rounded-pill <?php echo $interactions['user_liked'] ? 'btn-like-active' : ''; ?>">
                                ❤️ <?php echo $interactions['likes_count']; ?> Likes
                            </button>
                        </form>

                        <?php if ($isOwner): ?>
                            <form action="image_detail.php?id=<?php echo $imageId; ?>" method="POST" onsubmit="return confirm('¿Estás seguro de borrar esta obra?');">
                                <input type="hidden" name="action" value="delete_image">
                                <button type="submit" class="btn-delete"><span>🗑️</span> Eliminar Obra</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Interacciones (Comentarios) -->
            <div class="col-lg-4">
                <div class="detail-main h-100 d-flex flex-column">
                    <h5 class="fw-bold mb-4">Comentarios</h5>
                    
                    <div class="comment-list flex-grow-1 mb-4" style="max-height: 500px; overflow-y: auto;">
                        <?php if (empty($interactions['comments'])): ?>
                            <div class="text-center py-5">
                                <span class="display-1 text-muted opacity-25">💬</span>
                                <p class="text-muted mt-3">No hay comentarios aún.<br>¡Sé el primero en comentar!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($interactions['comments'] as $c): ?>
                                <div class="comment-bubble">
                                    <span class="comment-user">@<?php echo htmlspecialchars($c['username']); ?></span>
                                    <p class="comment-text mb-1"><?php echo htmlspecialchars($c['contenido']); ?></p>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('d M, Y', strtotime($c['created_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-auto">
                        <form action="image_detail.php?id=<?php echo $imageId; ?>" method="POST">
                            <input type="hidden" name="action" value="post_comment">
                            <div class="mb-3">
                                <textarea class="form-control border-0 bg-light" name="contenido" rows="3" placeholder="Escribe un comentario..." required style="border-radius: 15px; resize: none;"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Publicar Comentario</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
