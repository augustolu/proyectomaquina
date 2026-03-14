<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/ProfileController.php';
require_once __DIR__ . '/../controllers/AlbumController.php';
require_once __DIR__ . '/../controllers/ImageController.php';
require_once __DIR__ . '/../controllers/InteractionController.php';

$profileController = new ProfileController();
$albumController = new AlbumController();
$imageController = new ImageController();
$interactionController = new InteractionController();

$currentUserId = $_SESSION['user_id'] ?? null;
$targetUserId = $_GET['id'] ?? $currentUserId;

if (!$currentUserId && !$targetUserId) {
    header("Location: login.html");
    exit();
}

$isMyProfile = ($targetUserId == $currentUserId);
$userRes = $profileController->getUserById($targetUserId);
if (!$userRes['success']) {
    die("Usuario no encontrado.");
}
$user = $userRes['data'];

$followStatus = $interactionController->getFollowStatus($targetUserId);
$pendingRequests = $isMyProfile ? $interactionController->getPendingRequests() : [];

$historyResult = $profileController->getProfilePhotoHistory($targetUserId);
$history = $historyResult['success'] ? $historyResult['data'] : [];

$interestsResult = $profileController->getInterestsData($targetUserId);
$all_interests = $interestsResult['all_interests'] ?? [];
$user_interests = $interestsResult['user_interests'] ?? [];

$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'follow':
                $res = $interactionController->followUser($targetUserId);
                break;
            case 'respond_follow':
                $res = $interactionController->respondFollowRequest($_POST['follower_id'], $_POST['accept'] === '1');
                break;
            case 'update_info':
                $res = $profileController->updateProfileInfo($_POST);
                break;
            case 'update_interests':
                $res = $profileController->updateInterests($_POST);
                break;
            case 'change_password':
                $res = $profileController->changePassword($_POST);
                break;
            case 'upload_photo':
                $res = $profileController->uploadProfilePhoto($_FILES['foto']);
                break;
            case 'upload_work':
                $res = $imageController->uploadToAlbum($_POST['album_id'], $_FILES['foto_obra'], $_POST['privacidad'], $_POST['titulo']);
                break;
            case 'create_album':
                $res = $albumController->createAlbum($_POST, $_FILES['foto_album']);
                break;
            case 'delete_image':
                $res = $imageController->deleteImage($_POST['image_id']);
                break;
        }
        $message = $res['message'];
        $messageType = $res['success'] ? "success" : "danger";
        
        if ($res['success']) {
            $user = $profileController->getProfileData()['data'];
            $history = $profileController->getProfilePhotoHistory()['data'];
            $user_interests = $profileController->getInterestsData()['user_interests'];
        }
    }
}

$albumsRes = $albumController->listUserAlbums($targetUserId);
$albums = $albumsRes['success'] ? $albumsRes['data'] : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Artesanos.com</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #8d6e63;
            --bg-color: #fdfcf0;
        }
        body { background-color: var(--bg-color); padding-top: 80px; }
        .navbar { background: white !important; height: 70px; border-bottom: 1px solid #efebe9; }
        .section-title { border-bottom: 2px solid #efebe9; padding-bottom: 0.5rem; color: var(--primary-color); font-weight: 700; margin-bottom: 2rem; }
        .card-profile { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 3rem; margin-bottom: 3rem; }
        .profile-photo-large { width: 160px; height: 160px; border-radius: 50%; object-fit: cover; border: 4px solid var(--bg-color); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .btn-primary { background-color: var(--primary-color) !important; border: none !important; padding: 0.6rem 2rem; border-radius: 8px; }
        .btn-outline-primary { border-color: var(--primary-color) !important; color: var(--primary-color) !important; }
        .btn-outline-primary:hover { background-color: var(--primary-color) !important; color: white !important; }
        .history-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; }
        .history-item { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; border: 2px solid white; transition: transform 0.2s; }
        .history-item:hover { transform: scale(1.05); }
        .nav-link.logout { color: #d32f2f !important; font-weight: 700; }
        .management-section { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 2rem; }
        
        .album-card-dynamic { 
            background: white; 
            border-radius: 12px; 
            overflow: hidden; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            transition: transform 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .album-card-dynamic:hover { transform: translateY(-5px); }
        .album-preview img { width: 100%; height: 180px; object-fit: cover; }
        .album-body { padding: 1rem; }
        .album-title-text { font-weight: 700; color: var(--primary-color); margin-bottom: 0.2rem; display: block; }
        .album-count { font-size: 0.8rem; color: #a1887f; }
    </style>
</head>
<body>

    <nav class="navbar fixed-top px-5">
        <a href="feed.html" class="navbar-brand">Artesanos.com</a>
        <div class="navbar-search d-none d-md-block" style="flex: 0 1 400px;">
            <form action="search.php" method="GET">
                <input type="text" name="q" class="form-control rounded-pill" placeholder="Buscar obras o artesanos...">
            </form>
        </div>
        <ul class="nav">
            <li class="nav-item"><a href="feed.html" class="nav-link text-dark fw-bold">Inicio</a></li>
            <li class="nav-item"><a href="../controllers/AuthController.php?action=logout" class="nav-link logout">Cerrar Sesión</a></li>
        </ul>
    </nav>

    <main class="container py-4">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Cabecera de Perfil -->
        <div class="card-profile d-flex align-items-center gap-5">
            <div class="position-relative">
                <?php $foto = $user['foto_perfil'] ? '../' . $user['foto_perfil'] : 'https://api.dicebear.com/7.x/avataaars/svg?seed=' . $user['username']; ?>
                <img src="<?php echo htmlspecialchars($foto); ?>" alt="Perfil" class="profile-photo-large">
            </div>
            <div class="profile-info flex-grow-1">
                <h1 class="fw-bold mb-2">
                    <?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?>
                    <?php if ($user['es_cuenta_privada']): ?>
                        <span class="badge bg-secondary rounded-pill" style="font-size: 0.6rem; vertical-align: middle;">Privada</span>
                    <?php endif; ?>
                </h1>
                <p class="text-muted mb-4" style="max-width: 600px; line-height: 1.6;">
                    <?php echo htmlspecialchars($user['biografia'] ?: 'Sin biografía disponible.'); ?>
                </p>
                <div class="d-flex gap-2">
                    <?php if ($isMyProfile): ?>
                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editSection">Configurar Perfil</button>
                    <?php else: ?>
                        <form action="profile.php?id=<?php echo $targetUserId; ?>" method="POST">
                            <input type="hidden" name="action" value="follow">
                            <?php if (!$followStatus): ?>
                                <button type="submit" class="btn btn-primary">Seguir</button>
                            <?php elseif ($followStatus === 'pendiente'): ?>
                                <button type="button" class="btn btn-light disabled">Solicitud Pendiente</button>
                            <?php elseif ($followStatus === 'aceptada'): ?>
                                <button type="button" class="btn btn-outline-primary disabled">Siguiendo</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Solicitudes Pendientes (Solo en perfil propio) -->
        <?php if ($isMyProfile && !empty($pendingRequests)): ?>
            <section class="management-section border-start border-4 border-warning mb-5">
                <h5 class="fw-bold mb-3">Solicitudes de Seguimiento</h5>
                <div class="list-group">
                    <?php foreach ($pendingRequests as $req): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">
                            <div>
                                <span class="fw-bold">@<?php echo htmlspecialchars($req['username']); ?></span>
                                <span class="text-muted small ms-2"><?php echo htmlspecialchars($req['nombre'] . ' ' . $req['apellido']); ?></span>
                            </div>
                            <form action="profile.php" method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="respond_follow">
                                <input type="hidden" name="follower_id" value="<?php echo $req['seguidor_id']; ?>">
                                <button type="submit" name="accept" value="1" class="btn btn-sm btn-success rounded-pill px-3">Aceptar</button>
                                <button type="submit" name="accept" value="0" class="btn btn-sm btn-outline-danger rounded-pill px-3">Rechazar</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Sección de Gestión (Colapsable) -->
        <div class="collapse" id="editSection">
            <h2 class="section-title">Ajustes de Cuenta</h2>
            <div class="row">
                <div class="col-lg-8">
                    <div class="management-section mb-4">
                        <h5 class="fw-bold mb-3">Información Personal</h5>
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="action" value="update_info">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Nombre</label><input type="text" class="form-control" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Apellido</label><input type="text" class="form-control" name="apellido" value="<?php echo htmlspecialchars($user['apellido']); ?>"></div>
                                <div class="col-12"><label class="form-label">Biografía</label><textarea class="form-control" name="biografia" rows="3"><?php echo htmlspecialchars($user['biografia']); ?></textarea></div>
                            </div>
                            <button type="submit" class="btn btn-primary mt-3">Guardar Datos</button>
                        </form>
                    </div>

                    <div class="management-section mb-4">
                        <h5 class="fw-bold mb-3">Intereses</h5>
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="action" value="update_interests">
                            <div class="row mb-3">
                                <?php foreach ($all_interests as $interest): ?>
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="intereses[]" value="<?php echo $interest['id']; ?>" <?php echo in_array($interest['id'], $user_interests) ? 'checked' : ''; ?>>
                                            <label class="form-check-label"><?php echo htmlspecialchars($interest['nombre']); ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-primary">Actualizar Intereses</button>
                        </form>
                    </div>

                    <div class="management-section mb-4">
                        <h5 class="fw-bold mb-3 text-danger">Seguridad</h5>
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="action" value="change_password">
                            <label class="form-label">Nueva Contraseña</label>
                            <div class="input-group mb-3">
                                <input type="password" class="form-control" name="new_password" required>
                                <button type="submit" class="btn btn-outline-danger">Cambiar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="management-section mb-4 text-center">
                        <h5 class="fw-bold mb-3">Cambiar Foto</h5>
                        <form action="profile.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload_photo">
                            <input type="file" class="form-control mb-2" name="foto" required>
                            <button type="submit" class="btn btn-primary w-100">Subir</button>
                        </form>
                    </div>

                    <div class="management-section">
                        <h5 class="fw-bold mb-3">Historial de Fotos</h5>
                        <div class="history-grid">
                            <?php if (empty($history)): ?>
                                <p class="text-muted small">Sin historial.</p>
                            <?php else: ?>
                                <?php foreach ($history as $h): ?>
                                    <img src="../<?php echo htmlspecialchars($h['url_almacen']); ?>" class="history-item" title="<?php echo $h['fecha_desde']; ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mis Álbumes Dinámicos -->
        <section class="mt-5">
            <div class="d-flex justify-content-between align-items-center section-title mb-4">
                <h2 class="mb-0 border-0"><?php echo $isMyProfile ? 'Mis Álbumes' : 'Álbumes de ' . htmlspecialchars($user['nombre']); ?></h2>
                <?php if ($isMyProfile): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#newWorkModal">Subir Obra</button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAlbumModal">Nuevo Álbum</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <?php if (empty($albums)): ?>
                    <div class="col-12 text-center py-5">
                        <p class="text-muted">Aún no tienes álbumes. ¡Crea el primero ahora!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($albums as $album): 
                        $cover = $albumController->getAlbumCover($album['id']);
                        $coverPath = $cover ? '../' . $cover : 'https://via.placeholder.com/300x180';
                    ?>
                        <div class="col-md-3">
                            <a href="album_detail.php?id=<?php echo $album['id']; ?>" class="album-card-dynamic">
                                <div class="album-preview">
                                    <img src="<?php echo htmlspecialchars($coverPath); ?>" alt="Miniatura">
                                </div>
                                <div class="album-body">
                                    <span class="album-title-text"><?php echo htmlspecialchars($album['titulo']); ?></span>
                                    <span class="album-count">Explora esta colección</span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Modal Nueva Obra -->
    <div class="modal fade" id="newWorkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Añadir Obra a Colección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="upload_work">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Seleccionar Álbum (Obligatorio)</label>
                            <select class="form-select" name="album_id" required>
                                <option value="" selected disabled>Elige un álbum...</option>
                                <?php foreach ($albums as $album): ?>
                                    <option value="<?php echo $album['id']; ?>"><?php echo htmlspecialchars($album['titulo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Título de la Obra (Opcional)</label>
                            <input type="text" class="form-control" name="titulo" placeholder="Ej: Atardecer en la Sierra">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Privacidad</label>
                            <select class="form-select" name="privacidad">
                                <option value="publico" selected>Público (Todo el mundo)</option>
                                <option value="privado">Privado (Solo seguidores)</option>
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-bold small">Imagen de la Obra</label>
                            <input type="file" class="form-control" name="foto_obra" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Subir Obra</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Álbum -->
    <div class="modal fade" id="newAlbumModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow" style="border-radius: 12px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Nueva Colección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" name="action" value="create_album">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Título (Obligatorio)</label>
                            <input type="text" class="form-control" name="titulo" placeholder="Ej: Esculturas de Barro" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="2" placeholder="Cuéntanos un poco sobre este álbum..."></textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-bold small">Sube la Primera Obra (Obligatorio)</label>
                            <input type="file" class="form-control" name="foto_album" required>
                            <div class="form-text mt-2 small">Regla del Sistema: Todo álbum debe empezar con al menos una imagen.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Álbum</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
