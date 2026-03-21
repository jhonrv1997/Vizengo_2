<?php
/**
 * VIZENGO - Sidebar Component
 * Menú lateral de navegación
 */

// Verificar si la sesión está iniciada
if (!isset($_SESSION['logged_in'])) {
    require_once __DIR__ . '/../config.php';
    startSecureSession();
}

// Obtener datos del usuario
$rol = $_SESSION['rol'] ?? 'vendedor';
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$rolLabels = ['vendedor' => 'Vendedor', 'disenador' => 'Diseñador', 'administrador' => 'Administrador'];

// Obtener contador de pedidos activos
$totalPedidos = 0;
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as total FROM pedidos WHERE estado_general != 'entregado'");
    $result = $stmt->fetch();
    $totalPedidos = $result['total'] ?? 0;
} catch (Exception $e) {
    // Silenciar error
}

// Página actual
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">VIZEN<span>GO</span></div>
        <div class="brand-sub">Gestión de Pedidos</div>
    </div>

    <div style="margin-top: 12px;">
        <div class="sidebar-section">Principal</div>
        <a class="nav-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-chart-line"></i> Dashboard
        </a>
        <a class="nav-item <?php echo $currentPage === 'lista-pedidos.php' ? 'active' : ''; ?>" href="lista-pedidos.php">
            <i class="fas fa-list-ul"></i> Lista de Pedidos
            <?php if ($totalPedidos > 0): ?>
            <span class="badge-nav"><?php echo $totalPedidos; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-item <?php echo $currentPage === 'seguimiento.php' ? 'active' : ''; ?>" href="seguimiento.php">
            <i class="fas fa-route"></i> Seguimiento
        </a>

        <?php if ($rol === 'administrador'): ?>
        <div class="sidebar-section">Administración</div>
        <a class="nav-item <?php echo $currentPage === 'usuarios.php' ? 'active' : ''; ?>" href="usuarios.php">
            <i class="fas fa-users-cog"></i> Usuarios
        </a>
        <?php endif; ?>

        <?php if ($rol === 'vendedor' || $rol === 'administrador'): ?>
        <div class="sidebar-section">Vendedor</div>
        <a class="nav-item <?php echo $currentPage === 'ingreso-pedido.php' ? 'active' : ''; ?>" href="ingreso-pedido.php">
            <i class="fas fa-plus-circle"></i> Nuevo Pedido
        </a>
        <a class="nav-item <?php echo $currentPage === 'registro-integrantes.php' ? 'active' : ''; ?>" href="registro-integrantes.php">
            <i class="fas fa-users"></i> Integrantes
        </a>
        <a class="nav-item <?php echo $currentPage === 'entrega.php' ? 'active' : ''; ?>" href="entrega.php">
            <i class="fas fa-box"></i> Entrega
        </a>
        <?php endif; ?>

        <?php if ($rol === 'disenador' || $rol === 'administrador'): ?>
        <div class="sidebar-section">Diseñador</div>
        <a class="nav-item <?php echo $currentPage === 'diseno.php' ? 'active' : ''; ?>" href="diseno.php">
            <i class="fas fa-paint-brush"></i> Subir Diseño
        </a>
        <a class="nav-item <?php echo $currentPage === 'planchado.php' ? 'active' : ''; ?>" href="planchado.php">
            <i class="fas fa-tshirt"></i> Planchado
        </a>
        <a class="nav-item <?php echo $currentPage === 'costura.php' ? 'active' : ''; ?>" href="costura.php">
            <i class="fas fa-cut"></i> Costura
        </a>
        <?php endif; ?>

        <div class="sidebar-section">Sistema</div>
        <a class="nav-item" href="api/export.php" target="_blank">
            <i class="fas fa-file-export"></i> Exportar Datos
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?php echo strtoupper(substr($nombre, 0, 1)); ?></div>
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($nombre); ?></div>
            <div class="user-role"><?php echo $rolLabels[$rol] ?? $rol; ?></div>
        </div>
        <button class="btn-logout" onclick="cerrarSesion()" title="Cerrar sesión">
            <i class="fas fa-sign-out-alt"></i>
        </button>
    </div>
</aside>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

function cerrarSesion() {
    if (confirm('¿Está seguro que desea cerrar sesión?')) {
        fetch('api/auth.php?action=logout')
            .then(() => {
                sessionStorage.clear();
                window.location.href = 'index.php';
            })
            .catch(() => {
                sessionStorage.clear();
                window.location.href = 'index.php';
            });
    }
}
</script>
