<?php
// Centralized Navbar for Artesanos.com
?><nav class="navbar px-4">
    <a href="feed.php" class="navbar-brand" style="color: #c17c5b !important; font-weight: 800;">Artesanos.com</a>
    <div class="navbar-search d-none d-lg-block mx-auto">
        <form action="search.php" method="GET">
            <input type="text" name="q" class="search-input" placeholder="Buscar obras o artesanos..." value="<?php echo isset($query) ? htmlspecialchars($query) : ''; ?>">
        </form>
    </div>
    <ul class="navbar-nav ms-auto" style="flex-direction: row !important; gap: 2rem; list-style: none; margin: 0; padding: 0;">
        <li><a href="feed.php" class="nav-link" style="text-decoration: none; color: #2d3436; font-weight: 600;">Inicio</a></li>
        <li><a href="profile.php" class="nav-link" style="text-decoration: none; color: #2d3436; font-weight: 600;">Mi Perfil</a></li>
        <li><a href="../controllers/AuthController.php?action=logout" class="nav-link" style="text-decoration: none; color: #e74c3c; font-weight: 600;">Cerrar Sesión</a></li>
    </ul>
</nav>
