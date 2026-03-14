<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/ImageController.php';
require_once __DIR__ . '/../controllers/InteractionController.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../config/Database.php';

$imageController = new ImageController();
$interactionController = new InteractionController();
$db = (new Database())->getConnection();
$userModel = new UserModel($db);

$query = isset($_GET['q']) ? $_GET['q'] : '';
$imageResults = [];
$userResults = [];
$message = "";
$messageType = "info";

// Procesar solicitud de seguimiento
if (isset($_POST['action']) && $_POST['action'] === 'follow') {
    $res = $interactionController->followUser($_POST['user_id']);
    $message = $res['message'];
    $messageType = $res['success'] ? "success" : "danger";
}

if (!empty($query)) {
    // Buscar Imágenes
    $resImg = $imageController->search($query);
    if ($resImg['success']) {
        $imageResults = $resImg['data'];
    }

    // Buscar Usuarios
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $userResults = $userModel->searchUsers($query, $currentUserId);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador - Artesanos.com</title>
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
        .art-card-img { width: 100%; height: 250px; object-fit: cover; }
        .art-card-body { padding: 1rem; }
        .badge-private { background-color: #eee; color: #666; font-size: 0.7rem; border-radius: 4px; padding: 2px 6px; }
        .user-card { background: white; border-radius: 12px; padding: 1rem; display: flex; align-items: center; justify-content: space-between; border: 1px solid #eee; margin-bottom: 1rem; }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .user-avatar { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; background: #eee; }
    </style>
</head>
<body>

    <nav class="navbar fixed-top px-5">
        <a href="feed.html" class="navbar-brand text-decoration-none" style="color: var(--primary-color); font-weight:700;">Artesanos.com</a>
        <div class="navbar-search d-none d-md-block" style="flex: 0 1 400px;">
            <form action="search.php" method="GET">
                <input type="text" name="q" class="form-control rounded-pill" placeholder="Buscar obras o artesanos..." value="<?php echo htmlspecialchars($query); ?>">
            </form>
        </div>
        <ul class="nav">
            <li class="nav-item"><a href="feed.html" class="nav-link text-dark fw-bold">Inicio</a></li>
            <li class="nav-item"><a href="profile.php" class="nav-link text-dark">Mi Perfil</a></li>
        </ul>
    </nav>

    <main class="container py-4">
        <h2 class="mb-4">Resultados para: <span class="text-muted">"<?php echo htmlspecialchars($query); ?>"</span></h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Sección de Usuarios -->
        <?php if (!empty($userResults)): ?>
            <section class="mb-5">
                <h4 class="fw-bold mb-3">Artesanos</h4>
                <div class="row">
                    <?php foreach ($userResults as $u): 
                        $followStatus = $interactionController->getFollowStatus($u['id']);
                        $profilePic = $u['foto_perfil'] ? '../' . $u['foto_perfil'] : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $u['username'];
                    ?>
                        <div class="col-md-6">
                            <div class="user-card shadow-sm">
                                <a href="profile.php?id=<?php echo $u['id']; ?>" class="user-info text-decoration-none text-dark">
                                    <img src="<?php echo $profilePic; ?>" class="user-avatar" alt="Avatar">
                                    <div>
                                        <div class="fw-bold">@<?php echo htmlspecialchars($u['username']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?></div>
                                    </div>
                                </a>
                                
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <form action="search.php?q=<?php echo urlencode($query); ?>" method="POST">
                                        <input type="hidden" name="action" value="follow">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <?php if (!$followStatus): ?>
                                            <button type="submit" class="btn btn-sm btn-primary px-3 rounded-pill">Seguir</button>
                                        <?php elseif ($followStatus === 'pendiente'): ?>
                                            <button type="button" class="btn btn-sm btn-light px-3 rounded-pill disabled">Pendiente</button>
                                        <?php elseif ($followStatus === 'aceptada'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary px-3 rounded-pill disabled">Siguiendo</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <h4 class="fw-bold mb-3">Obras</h4>

        <div class="row g-4">
            <?php if (empty($imageResults)): ?>
                <div class="col-12 text-center py-5">
                    <p class="text-muted">No se encontraron obras que coincidan con tu búsqueda.</p>
                </div>
            <?php else: ?>
                <?php foreach ($imageResults as $img): ?>
                    <div class="col-md-4">
                        <a href="image_detail.php?id=<?php echo $img['id']; ?>" class="text-decoration-none">
                            <article class="art-card">
                                <img src="../<?php echo htmlspecialchars($img['url_almacen']); ?>" alt="Obra" class="art-card-img">
                                <div class="art-card-body">
                                    <h5 class="fw-bold mb-1">
                                        <?php echo htmlspecialchars($img['titulo'] ?: 'Obra sin título'); ?>
                                        <?php if ($img['privacidad'] === 'privada'): ?>
                                            <span class="badge-private">Privado</span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="text-muted small mb-0">por @<?php echo htmlspecialchars($img['username']); ?></p>
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
