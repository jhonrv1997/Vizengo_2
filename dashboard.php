<?php
/**
 * VIZENGO - Dashboard Principal
 * Panel de control con estadísticas y acceso rápido
 */
require_once 'config.php';
startSecureSession();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit();
}

$user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'nombre' => $_SESSION['nombre'],
    'rol' => $_SESSION['rol']
];

// Obtener estadísticas
$db = getDB();

// Stats del dashboard
$stmt = $db->query("SELECT 
    COUNT(*) as total_pedidos,
    SUM(CASE WHEN estado_general = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado_general = 'listo_entrega' THEN 1 ELSE 0 END) as listos_entrega,
    SUM(CASE WHEN estado_general = 'entregado' THEN 1 ELSE 0 END) as entregados,
    SUM(CASE WHEN fecha_entrega <= CURDATE() AND estado_general != 'entregado' THEN 1 ELSE 0 END) as urgentes
    FROM pedidos");
$stats = $stmt->fetch();

// Pedidos por etapa
$stmt = $db->query("SELECT 
    SUM(CASE WHEN estado_contrato != 'completo' OR estado_contrato IS NULL THEN 1 ELSE 0 END) as contrato,
    SUM(CASE WHEN estado_contrato = 'completo' AND (estado_integrantes != 'completo' OR estado_integrantes IS NULL) THEN 1 ELSE 0 END) as integrantes,
    SUM(CASE WHEN estado_integrantes = 'completo' AND (estado_diseno != 'completo' OR estado_diseno IS NULL) AND estado_diseno != 'aprobado' THEN 1 ELSE 0 END) as diseno,
    SUM(CASE WHEN estado_diseno = 'completo' AND (estado_planchado != 'completo' OR estado_planchado IS NULL) THEN 1 ELSE 0 END) as planchado,
    SUM(CASE WHEN estado_planchado = 'completo' AND (estado_costura != 'completo' OR estado_costura IS NULL) THEN 1 ELSE 0 END) as costura,
    SUM(CASE WHEN estado_general = 'listo_entrega' THEN 1 ELSE 0 END) as listo_entrega,
    SUM(CASE WHEN estado_general = 'entregado' THEN 1 ELSE 0 END) as completados
    FROM pedidos");
$etapas = $stmt->fetch();

// Pedidos urgentes
$stmt = $db->query("SELECT p.id, p.codigo, p.fecha_entrega, c.nombre as cliente,
                    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_prendas
                    FROM pedidos p 
                    LEFT JOIN clientes c ON p.cliente_id = c.id
                    WHERE p.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
                    AND p.estado_general != 'entregado'
                    ORDER BY p.fecha_entrega ASC LIMIT 5");
$urgentes = $stmt->fetchAll();

// Pedidos recientes
$stmt = $db->query("SELECT p.id, p.codigo, p.fecha_pedido, p.estado_general, p.estado_contrato, p.estado_integrantes, p.estado_diseno, p.estado_planchado, p.estado_costura,
                    c.nombre as cliente, u.nombre as vendedor,
                    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_prendas
                    FROM pedidos p 
                    LEFT JOIN clientes c ON p.cliente_id = c.id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    ORDER BY p.fecha_pedido DESC LIMIT 5");
$recientes = $stmt->fetchAll();

// Fecha actual
$fechaHoy = date('l, d \d\e F \d\e Y');
$dias = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
$meses = ['January'=>'Enero','February'=>'Febrero','March'=>'Marzo','April'=>'Abril','May'=>'Mayo','June'=>'Junio','July'=>'Julio','August'=>'Agosto','September'=>'Septiembre','October'=>'Octubre','November'=>'Noviembre','December'=>'Diciembre'];
$fechaHoy = str_replace(array_keys($dias), array_values($dias), $fechaHoy);
$fechaHoy = str_replace(array_keys($meses), array_values($meses), $fechaHoy);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p><?php echo $fechaHoy; ?></p>
        </div>
        <div class="topbar-right">
            <?php if ($user['rol'] === 'vendedor' || $user['rol'] === 'administrador'): ?>
            <a href="ingreso-pedido.php" class="btn-primary-action">
                <i class="fas fa-plus"></i> Nuevo Pedido
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerta urgente -->
    <?php if (count($urgentes) > 0): ?>
    <div class="alert-banner">
        <div class="alert-banner-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="alert-banner-text">
            <strong>⚠️ <?php echo count($urgentes); ?> pedido(s) con entrega urgente</strong>
            <p><?php echo implode(' · ', array_slice(array_column($urgentes, 'cliente'), 0, 3)); ?> — Verifica el estado de producción</p>
        </div>
        <a href="lista-pedidos.php" class="action-btn-sm">Ver pedidos</a>
    </div>
    <?php endif; ?>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card blue">
            <div class="kpi-icon"><i class="fas fa-inbox"></i></div>
            <div class="kpi-label">Total Pedidos</div>
            <div class="kpi-value"><?php echo $stats['total_pedidos']; ?></div>
            <div class="kpi-sub">En el sistema</div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="kpi-label">En Producción</div>
            <div class="kpi-value"><?php echo $stats['en_proceso']; ?></div>
            <div class="kpi-sub">En proceso activo</div>
        </div>
        <div class="kpi-card red">
            <div class="kpi-icon"><i class="fas fa-clock"></i></div>
            <div class="kpi-label">Urgentes</div>
            <div class="kpi-value"><?php echo $stats['urgentes']; ?></div>
            <div class="kpi-sub">Entrega hoy o mañana</div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-label">Completados</div>
            <div class="kpi-value"><?php echo $stats['entregados']; ?></div>
            <div class="kpi-sub">Entregados</div>
        </div>
    </div>

    <!-- Pipeline de etapas - 6 ETAPAS -->
    <div class="section-header">
        <div class="section-title"><i class="fas fa-route" style="color:var(--primary);margin-right:8px;"></i>Estado del Pipeline</div>
        <a href="seguimiento.php" class="section-link">Ver todo el seguimiento →</a>
    </div>
    <div class="stage-pipeline">
        <div class="stage-card stage-1">
            <div class="stage-icon"><i class="fas fa-file-contract"></i></div>
            <div class="stage-num"><?php echo $etapas['contrato'] ?? 0; ?></div>
            <div class="stage-title">Contrato</div>
            <div class="stage-sub">Pendientes de registro</div>
        </div>
        <div class="stage-card stage-2">
            <div class="stage-icon"><i class="fas fa-users"></i></div>
            <div class="stage-num"><?php echo $etapas['integrantes'] ?? 0; ?></div>
            <div class="stage-title">Integrantes</div>
            <div class="stage-sub">Pendientes de registrar</div>
        </div>
        <div class="stage-card stage-3">
            <div class="stage-icon"><i class="fas fa-paint-brush"></i></div>
            <div class="stage-num"><?php echo $etapas['diseno'] ?? 0; ?></div>
            <div class="stage-title">Diseño</div>
            <div class="stage-sub">Pendientes de diseño</div>
        </div>
        <div class="stage-card stage-4">
            <div class="stage-icon"><i class="fas fa-tshirt"></i></div>
            <div class="stage-num"><?php echo $etapas['planchado'] ?? 0; ?></div>
            <div class="stage-title">Planchado</div>
            <div class="stage-sub">Pendientes de planchar</div>
        </div>
        <div class="stage-card stage-5">
            <div class="stage-icon"><i class="fas fa-cut"></i></div>
            <div class="stage-num"><?php echo $etapas['costura'] ?? 0; ?></div>
            <div class="stage-title">Costura</div>
            <div class="stage-sub">En proceso de costura</div>
        </div>
        <div class="stage-card stage-6">
            <div class="stage-icon"><i class="fas fa-check-double"></i></div>
            <div class="stage-num"><?php echo $etapas['completados'] ?? 0; ?></div>
            <div class="stage-title">Completados</div>
            <div class="stage-sub">Entregados o enviados</div>
        </div>
    </div>

    <!-- Lista de pedidos recientes -->
    <div class="section-header">
        <div class="section-title"><i class="fas fa-list" style="color:var(--primary);margin-right:8px;"></i>Pedidos Recientes</div>
        <a href="lista-pedidos.php" class="section-link">Ver todos →</a>
    </div>
    <div class="pipeline-wrapper">
        <div class="pipeline-header">
            <div class="pipeline-col-head">Pedido</div>
            <div class="pipeline-col-head">Entrega</div>
            <div class="pipeline-col-head">Contrato</div>
            <div class="pipeline-col-head">Diseño</div>
            <div class="pipeline-col-head">Planchado</div>
            <div class="pipeline-col-head">Acciones</div>
        </div>

        <?php foreach ($recientes as $pedido): ?>
        <div class="pipeline-row">
            <div class="order-info">
                <div class="order-name"><?php echo htmlspecialchars($pedido['cliente']); ?></div>
                <div class="order-meta"><?php echo $pedido['total_prendas']; ?> prendas · Vendedor: <?php echo htmlspecialchars($pedido['vendedor']); ?> · <?php echo $pedido['codigo']; ?></div>
            </div>
            <div class="order-date">
                <i class="fas fa-calendar" style="color:var(--muted);margin-right:4px;"></i>
                <?php echo formatDate($pedido['fecha_entrega'] ?? '', 'd/m'); ?>
            </div>
            <div>
                <span class="status-badge badge-<?php echo $pedido['estado_contrato'] === 'completo' ? 'completo' : 'pendiente'; ?>">
                    <span class="dot"></span><?php echo $pedido['estado_contrato'] === 'completo' ? 'Completo' : 'Pendiente'; ?>
                </span>
            </div>
            <div>
                <span class="status-badge badge-<?php echo $pedido['estado_diseno'] === 'completo' ? 'completo' : 'pendiente'; ?>">
                    <span class="dot"></span><?php echo $pedido['estado_diseno'] === 'completo' ? 'Completo' : 'Pendiente'; ?>
                </span>
            </div>
            <div>
                <span class="status-badge badge-<?php echo $pedido['estado_planchado'] === 'completo' ? 'completo' : 'pendiente'; ?>">
                    <span class="dot"></span><?php echo $pedido['estado_planchado'] === 'completo' ? 'Completo' : 'Pendiente'; ?>
                </span>
            </div>
            <div>
                <a href="ver-pedido.php?id=<?php echo $pedido['id']; ?>" class="action-btn-sm">Ver</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Acciones Rápidas -->
    <div class="section-header">
        <div class="section-title"><i class="fas fa-bolt" style="color:var(--accent);margin-right:8px;"></i>Acciones Rápidas</div>
    </div>
    <div class="quick-actions-grid">
        <?php if ($user['rol'] === 'vendedor' || $user['rol'] === 'administrador'): ?>
        <a href="ingreso-pedido.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(43,79,255,0.1);color:var(--primary);"><i class="fas fa-plus-circle"></i></div>
            <div><div class="qa-title">Nuevo Pedido</div><div class="qa-desc">Registrar contrato</div></div>
        </a>
        <a href="registro-integrantes.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(6,214,160,0.1);color:var(--success);"><i class="fas fa-users"></i></div>
            <div><div class="qa-title">Integrantes</div><div class="qa-desc">Registrar tallas</div></div>
        </a>
        <a href="entrega.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(236,72,153,0.1);color:#ec4899;"><i class="fas fa-box"></i></div>
            <div><div class="qa-title">Entrega</div><div class="qa-desc">Gestionar entregas</div></div>
        </a>
        <?php endif; ?>
        
        <?php if ($user['rol'] === 'disenador' || $user['rol'] === 'administrador'): ?>
        <a href="diseno.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(43,79,255,0.1);color:var(--primary);"><i class="fas fa-paint-brush"></i></div>
            <div><div class="qa-title">Diseño</div><div class="qa-desc">Subir diseño final</div></div>
        </a>
        <a href="planchado.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(56,189,248,0.1);color:#38bdf8;"><i class="fas fa-tshirt"></i></div>
            <div><div class="qa-title">Planchado</div><div class="qa-desc">Registro de planchado</div></div>
        </a>
        <a href="costura.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(168,85,247,0.1);color:#a855f7;"><i class="fas fa-cut"></i></div>
            <div><div class="qa-title">Costura</div><div class="qa-desc">Proceso de costura</div></div>
        </a>
        <?php endif; ?>
        
        <?php if ($user['rol'] === 'administrador'): ?>
        <a href="usuarios.php" class="qa-card">
            <div class="qa-icon" style="background:rgba(239,71,111,0.1);color:var(--danger);"><i class="fas fa-users-cog"></i></div>
            <div><div class="qa-title">Usuarios</div><div class="qa-desc">Gestionar usuarios</div></div>
        </a>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
