<?php
/**
 * VIZENGO - Lista de Pedidos
 * Vista de todos los pedidos con filtros, búsqueda y acciones
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

$db = getDB();

// Filtros
$filtroEstado = sanitize($_GET['estado'] ?? '');
$filtroVendedor = sanitize($_GET['vendedor'] ?? '');
$busqueda = sanitize($_GET['search'] ?? '');

// Query para obtener pedidos
$sql = "SELECT 
    p.id, p.codigo, p.tipo_contrato, p.lugar_entrega,
    p.estado_contrato, p.estado_integrantes, p.estado_diseno,
    p.estado_planchado, p.estado_costura, p.estado_general,
    p.fecha_pedido, p.fecha_entrega,
    p.subtotal, p.adelanto, p.saldo,
    c.nombre as cliente, c.celular as cliente_celular,
    u.nombre as vendedor,
    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes,
    CASE WHEN p.fecha_entrega <= CURDATE() AND p.estado_general != 'entregado' THEN 1 ELSE 0 END as es_urgente
FROM pedidos p
LEFT JOIN clientes c ON p.cliente_id = c.id
LEFT JOIN usuarios u ON p.usuario_id = u.id
WHERE 1=1";

$params = [];

// Filtro por rol
if ($user['rol'] === 'vendedor') {
    $sql .= " AND p.usuario_id = ?";
    $params[] = $user['id'];
} elseif ($user['rol'] === 'disenador') {
    $sql .= " AND p.estado_contrato = 'completo' AND p.estado_integrantes = 'completo'";
}

// Filtro por estado
if (!empty($filtroEstado)) {
    if ($filtroEstado === 'urgente') {
        $sql .= " AND p.fecha_entrega <= CURDATE() AND p.estado_general != 'entregado'";
    } elseif ($filtroEstado === 'entrega') {
        $sql .= " AND p.estado_general = 'listo_entrega'";
    } elseif ($filtroEstado === 'completado') {
        $sql .= " AND p.estado_general = 'entregado'";
    } else {
        $sql .= " AND (p.estado_contrato = ? OR p.estado_integrantes = ? OR p.estado_diseno = ? OR p.estado_planchado = ? OR p.estado_costura = ?)";
        $params = array_merge($params, [$filtroEstado, $filtroEstado, $filtroEstado, $filtroEstado, $filtroEstado]);
    }
}

// Filtro por vendedor
if (!empty($filtroVendedor)) {
    $sql .= " AND u.nombre LIKE ?";
    $params[] = "%$filtroVendedor%";
}

// Búsqueda
if (!empty($busqueda)) {
    $sql .= " AND (p.codigo LIKE ? OR c.nombre LIKE ? OR u.nombre LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " ORDER BY p.fecha_entrega ASC, p.fecha_pedido DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estadísticas para los chips
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado_general = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
    SUM(CASE WHEN estado_contrato != 'completo' OR estado_contrato IS NULL THEN 1 ELSE 0 END) as contrato_incompleto,
    SUM(CASE WHEN estado_integrantes = 'completo' AND estado_diseno != 'completo' THEN 1 ELSE 0 END) as sin_diseno,
    SUM(CASE WHEN estado_diseno = 'completo' AND estado_planchado != 'completo' THEN 1 ELSE 0 END) as sin_planchado,
    SUM(CASE WHEN estado_general = 'listo_entrega' THEN 1 ELSE 0 END) as listo_entrega,
    SUM(CASE WHEN fecha_entrega <= CURDATE() AND estado_general != 'entregado' THEN 1 ELSE 0 END) as urgentes
FROM pedidos");
$stats = $stmt->fetch();

// Obtener vendedores para el filtro
$stmt = $db->query("SELECT DISTINCT nombre FROM usuarios WHERE rol = 'vendedor' AND activo = 1 ORDER BY nombre");
$vendedores = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener lista de usuarios para mostrar (si es admin)
$usuarios = [];
if ($user['rol'] === 'administrador') {
    $stmt = $db->query("SELECT * FROM usuarios WHERE activo = 1 ORDER BY nombre");
    $usuarios = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Lista de Pedidos</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2B4FFF; --accent: #FFD23F;
            --success: #06d6a0; --warning: #f59e0b; --danger: #ef476f; --info: #38bdf8;
            --bg: #f0f4ff; --surface: #ffffff; --border: #e2e8f0;
            --text: #1e293b; --muted: #64748b;
            --sidebar-bg: #0f1729; --sidebar-w: 240px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Barlow', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: var(--sidebar-w); background: var(--sidebar-bg); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
        .sidebar-brand { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .brand-logo { font-family: 'Barlow Condensed', sans-serif; font-size: 1.8rem; font-weight: 800; letter-spacing: 2px; color: white; text-decoration: none; }
        .brand-logo span { color: var(--accent); }
        .brand-sub { font-size: 0.68rem; color: rgba(255,255,255,0.3); letter-spacing: 2px; text-transform: uppercase; margin-top: 2px; }
        .sidebar-section { padding: 12px 20px 4px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.25); }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 11px 20px; margin: 1px 10px; border-radius: 8px; color: rgba(255,255,255,0.55); text-decoration: none; font-size: 0.88rem; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.9); }
        .nav-item.active { background: var(--primary); color: white; }
        .nav-item i { width: 18px; text-align: center; font-size: 0.9rem; }
        .nav-item .badge-nav { background: var(--accent); color: #1a1a1a; border-radius: 20px; padding: 1px 8px; font-size: 0.7rem; font-weight: 700; margin-left: auto; }
        .sidebar-user { margin-top: auto; padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.06); display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-family: 'Barlow Condensed', sans-serif; font-weight: 700; color: white; font-size: 1rem; }
        .user-info .user-name { font-size: 0.9rem; font-weight: 600; color: white; }
        .user-info .user-role { font-size: 0.72rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 1px; }
        .btn-logout { margin-left: auto; color: rgba(255,255,255,0.3); background: none; border: none; cursor: pointer; font-size: 1rem; transition: color 0.2s; }
        .btn-logout:hover { color: var(--danger); }

        .main-content { margin-left: var(--sidebar-w); flex: 1; padding: 28px; }

        .topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
        .topbar h1 { font-family: 'Barlow Condensed', sans-serif; font-size: 1.8rem; font-weight: 800; letter-spacing: 0.5px; }
        .topbar p { font-size: 0.85rem; color: var(--muted); margin-top: 2px; }
        .topbar-actions { display: flex; gap: 10px; }

        .btn-action {
            padding: 9px 16px; border-radius: 10px; font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.88rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
            cursor: pointer; border: none; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-primary-a { background: var(--primary); color: white; }
        .btn-primary-a:hover { background: #1a35cc; color: white; }
        .btn-outline-a { background: white; color: var(--primary); border: 1.5px solid var(--primary); }
        .btn-outline-a:hover { background: var(--primary); color: white; }

        /* Barra de stats */
        .stats-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 20px; }
        .stat-chip {
            background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
            padding: 12px 16px; display: flex; align-items: center; gap: 10px;
            cursor: pointer; transition: all 0.2s;
        }
        .stat-chip:hover { border-color: var(--primary); }
        .stat-chip.active { border-color: var(--primary); background: rgba(43,79,255,0.05); }
        .stat-chip-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .stat-chip-val { font-family: 'Barlow Condensed', sans-serif; font-size: 1.4rem; font-weight: 800; line-height: 1; }
        .stat-chip-label { font-size: 0.72rem; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1px; }

        /* Toolbar */
        .toolbar { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px 18px; display: flex; gap: 12px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
        .search-box { display: flex; align-items: center; gap: 8px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 8px 14px; flex: 1; min-width: 200px; }
        .search-box input { background: none; border: none; outline: none; font-family: 'Barlow', sans-serif; font-size: 0.88rem; color: var(--text); width: 100%; }
        .search-box i { color: var(--muted); }
        .toolbar-select { border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; font-family: 'Barlow', sans-serif; font-size: 0.85rem; color: var(--text); background: white; outline: none; cursor: pointer; }

        /* Tabla */
        .table-wrapper { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
        .table-vizengo { width: 100%; border-collapse: collapse; }
        .table-vizengo thead th {
            background: var(--sidebar-bg); color: rgba(255,255,255,0.6);
            padding: 12px 16px; font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px;
            white-space: nowrap; position: sticky; top: 0;
        }
        .table-vizengo tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        .table-vizengo tbody tr:last-child { border-bottom: none; }
        .table-vizengo tbody tr:hover { background: #f8faff; }
        .table-vizengo tbody td { padding: 13px 16px; vertical-align: middle; font-size: 0.87rem; }

        .order-num { font-family: 'Barlow Condensed', sans-serif; font-weight: 800; color: var(--primary); font-size: 0.9rem; }
        .client-name { font-weight: 700; color: var(--text); }
        .client-meta { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }

        /* Badges */
        .bdg {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 3px 9px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;
        }
        .bdg .dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .bdg-ok  { background: rgba(6,214,160,0.12); color: #059669; }
        .bdg-ok .dot { background: #059669; }
        .bdg-inc { background: rgba(245,158,11,0.12); color: #d97706; }
        .bdg-inc .dot { background: #d97706; }
        .bdg-pend { background: rgba(100,116,139,0.1); color: #64748b; }
        .bdg-pend .dot { background: #94a3b8; }
        .bdg-urg { background: rgba(239,71,111,0.12); color: #dc2626; }
        .bdg-urg .dot { background: #dc2626; animation: blink 1s infinite; }
        .bdg-done { background: rgba(139,92,246,0.12); color: #7c3aed; }
        .bdg-done .dot { background: #7c3aed; }
        @keyframes blink { 0%,100%{opacity:1}50%{opacity:0.3} }

        .delivery-soon { color: var(--danger); font-weight: 700; }
        .delivery-ok { color: var(--muted); }

        .icon-btn { background: none; border: 1.5px solid var(--border); border-radius: 6px; padding: 5px 9px; cursor: pointer; color: var(--primary); font-size: 0.8rem; transition: all 0.2s; }
        .icon-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .icon-btn + .icon-btn { margin-left: 4px; }

        /* Paginación */
        .pagination-bar { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-top: 1px solid var(--border); font-size: 0.82rem; color: var(--muted); }
        .page-btn { background: none; border: 1px solid var(--border); border-radius: 6px; padding: 5px 10px; cursor: pointer; font-size: 0.82rem; color: var(--text); transition: all 0.2s; margin: 0 2px; }
        .page-btn.active, .page-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        /* Responsive */
        @media (max-width: 1100px) {
            .stats-row { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .table-wrapper { overflow-x: auto; }
            .table-vizengo { min-width: 900px; }
        }
        .mobile-toggle { display: none; position: fixed; top: 16px; left: 16px; z-index: 200; background: var(--primary); color: white; border: none; border-radius: 8px; padding: 8px 12px; cursor: pointer; }
        @media (max-width: 768px) { .mobile-toggle { display: flex; } }
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 99; }
        .overlay.show { display: block; }
    </style>
</head>
<body>

<button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div>
            <h1><i class="fas fa-list-ul" style="color:var(--primary);margin-right:10px;"></i>Lista de Pedidos</h1>
            <p>Todos los pedidos registrados con estado de seguimiento</p>
        </div>
        <div class="topbar-actions">
            <a href="seguimiento.php" class="btn-action btn-outline-a"><i class="fas fa-route"></i> Seguimiento</a>
            <?php if ($user['rol'] === 'vendedor' || $user['rol'] === 'administrador'): ?>
            <a href="ingreso-pedido.php" class="btn-action btn-primary-a"><i class="fas fa-plus"></i> Nuevo Pedido</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats chips -->
    <div class="stats-row">
        <div class="stat-chip <?php echo empty($filtroEstado) ? 'active' : ''; ?>" onclick="filtrarEstado('todos',this)">
            <div class="stat-chip-icon" style="background:rgba(43,79,255,0.1);color:var(--primary);"><i class="fas fa-inbox"></i></div>
            <div><div class="stat-chip-val"><?php echo $stats['total']; ?></div><div class="stat-chip-label">Total</div></div>
        </div>
        <div class="stat-chip <?php echo $filtroEstado === 'contrato_incompleto' ? 'active' : ''; ?>" onclick="filtrarEstado('contrato_incompleto',this)">
            <div class="stat-chip-icon" style="background:rgba(245,158,11,0.1);color:var(--warning);"><i class="fas fa-file-contract"></i></div>
            <div><div class="stat-chip-val"><?php echo $stats['contrato_incompleto']; ?></div><div class="stat-chip-label">Contrato Inc.</div></div>
        </div>
        <div class="stat-chip <?php echo $filtroEstado === 'diseno' ? 'active' : ''; ?>" onclick="filtrarEstado('diseno',this)">
            <div class="stat-chip-icon" style="background:rgba(43,79,255,0.1);color:var(--primary);"><i class="fas fa-paint-brush"></i></div>
            <div><div class="stat-chip-val"><?php echo $stats['sin_diseno']; ?></div><div class="stat-chip-label">Sin Diseño</div></div>
        </div>
        <div class="stat-chip <?php echo $filtroEstado === 'planchado' ? 'active' : ''; ?>" onclick="filtrarEstado('planchado',this)">
            <div class="stat-chip-icon" style="background:rgba(56,189,248,0.1);color:var(--info);"><i class="fas fa-tshirt"></i></div>
            <div><div class="stat-chip-val"><?php echo $stats['sin_planchado']; ?></div><div class="stat-chip-label">Sin Planchar</div></div>
        </div>
        <div class="stat-chip <?php echo $filtroEstado === 'entrega' ? 'active' : ''; ?>" onclick="filtrarEstado('entrega',this)">
            <div class="stat-chip-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-box"></i></div>
            <div><div class="stat-chip-val"><?php echo $stats['listo_entrega']; ?></div><div class="stat-chip-label">Listo Entrega</div></div>
        </div>
        <div class="stat-chip <?php echo $filtroEstado === 'urgente' ? 'active' : ''; ?>" onclick="filtrarEstado('urgente',this)">
            <div class="stat-chip-icon" style="background:rgba(239,71,111,0.1);color:var(--danger);"><i class="fas fa-exclamation-triangle"></i></div>
            <div><div class="stat-chip-val"><?php echo $stats['urgentes']; ?></div><div class="stat-chip-label">Urgentes</div></div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar por cliente, número o vendedor..." value="<?php echo htmlspecialchars($busqueda); ?>" oninput="buscarTabla(this.value)">
        </div>
        <select class="toolbar-select" onchange="filtrarVendedor(this.value)">
            <option value="">Todos los vendedores</option>
            <?php foreach ($vendedores as $v): ?>
            <option value="<?php echo htmlspecialchars($v); ?>" <?php echo $filtroVendedor === $v ? 'selected' : ''; ?>><?php echo htmlspecialchars($v); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="toolbar-select" onchange="filtrarFecha(this.value)">
            <option value="">Todas las fechas</option>
            <option value="hoy">Hoy</option>
            <option value="semana">Esta semana</option>
            <option value="mes">Este mes</option>
        </select>
        <button class="btn-action btn-outline-a" style="padding:8px 14px;" onclick="exportar()">
            <i class="fas fa-file-excel"></i> Exportar
        </button>
    </div>

    <!-- Tabla -->
    <div class="table-wrapper">
        <table class="table-vizengo" id="tablaPedidos">
            <thead>
                <tr>
                    <th>#Pedido</th>
                    <th>Cliente</th>
                    <th>Cantidad</th>
                    <th>Entrega</th>
                    <th>Vendedor</th>
                    <th>Contrato</th>
                    <th>Diseño</th>
                    <th>Planchado</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tablaBody">
                <?php if (count($pedidos) === 0): ?>
                <tr>
                    <td colspan="10" style="text-align:center;padding:40px;color:var(--muted);">
                        <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:10px;display:block;opacity:0.3;"></i>
                        No se encontraron pedidos
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($pedidos as $p): ?>
                <?php
                // Determinar etapa actual y siguiente acción
                $etapa = '';
                $accionUrl = '';
                $accionIcon = '';
                
                if ($p['estado_general'] === 'entregado') {
                    $etapa = 'completado';
                    $accionUrl = 'seguimiento.php';
                    $accionIcon = 'fas fa-eye';
                } elseif ($p['estado_general'] === 'listo_entrega') {
                    $etapa = 'entrega';
                    $accionUrl = 'entrega.php';
                    $accionIcon = 'fas fa-box';
                } elseif ($p['estado_costura'] !== 'completo' && $p['estado_planchado'] === 'completo') {
                    $etapa = 'costura';
                    $accionUrl = 'costura.php';
                    $accionIcon = 'fas fa-cut';
                } elseif ($p['estado_planchado'] !== 'completo' && $p['estado_diseno'] === 'completo') {
                    $etapa = 'planchado';
                    $accionUrl = 'planchado.php';
                    $accionIcon = 'fas fa-tshirt';
                } elseif ($p['estado_diseno'] !== 'completo' && $p['estado_integrantes'] === 'completo') {
                    $etapa = 'diseno';
                    $accionUrl = 'diseno.php';
                    $accionIcon = 'fas fa-paint-brush';
                } elseif ($p['estado_integrantes'] !== 'completo') {
                    $etapa = 'integrantes';
                    $accionUrl = 'registro-integrantes.php';
                    $accionIcon = 'fas fa-users';
                } else {
                    $etapa = 'contrato';
                    $accionUrl = 'ingreso-pedido.php';
                    $accionIcon = 'fas fa-file-contract';
                }
                ?>
                <tr data-estado="<?php echo $etapa; ?>" data-urgente="<?php echo $p['es_urgente'] ? 'true' : 'false'; ?>" data-vendedor="<?php echo strtolower($p['vendedor'] ?? ''); ?>">
                    <td><span class="order-num"><?php echo htmlspecialchars($p['codigo']); ?></span></td>
                    <td>
                        <div class="client-name"><?php echo htmlspecialchars($p['cliente']); ?></div>
                        <div class="client-meta"><?php echo $p['total_integrantes']; ?> prendas · <?php echo htmlspecialchars($p['lugar_entrega']); ?></div>
                    </td>
                    <td><?php echo $p['total_integrantes']; ?></td>
                    <td class="<?php echo $p['es_urgente'] ? 'delivery-soon' : 'delivery-ok'; ?>">
                        <i class="fas fa-circle fa-xs" style="color:<?php echo $p['es_urgente'] ? 'var(--danger)' : 'var(--muted)'; ?>;margin-right:4px;"></i>
                        <?php 
                        if ($p['es_urgente'] && $p['estado_general'] !== 'entregado') {
                            echo 'HOY';
                        } else {
                            echo formatDate($p['fecha_entrega'], 'd/m');
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($p['vendedor'] ?? '-'); ?></td>
                    <td><span class="bdg bdg-<?php echo $p['estado_contrato'] === 'completo' ? 'ok' : 'inc'; ?>"><span class="dot"></span><?php echo $p['estado_contrato'] === 'completo' ? 'Completo' : 'Pendiente'; ?></span></td>
                    <td><span class="bdg bdg-<?php echo $p['estado_diseno'] === 'completo' ? 'ok' : ($p['estado_diseno'] === 'aprobado' ? 'ok' : 'pend'); ?>"><span class="dot"></span><?php echo $p['estado_diseno'] === 'completo' || $p['estado_diseno'] === 'aprobado' ? 'Completo' : 'Pendiente'; ?></span></td>
                    <td><span class="bdg bdg-<?php echo $p['estado_planchado'] === 'completo' ? 'ok' : 'pend'; ?>"><span class="dot"></span><?php echo $p['estado_planchado'] === 'completo' ? 'Completo' : 'Pendiente'; ?></span></td>
                    <td>
                        <?php if ($p['estado_general'] === 'entregado'): ?>
                        <span class="bdg bdg-done"><span class="dot"></span>Entregado</span>
                        <?php elseif ($p['es_urgente']): ?>
                        <span class="bdg bdg-urg"><span class="dot"></span>Urgente</span>
                        <?php elseif ($p['estado_general'] === 'listo_entrega'): ?>
                        <span class="bdg bdg-done"><span class="dot"></span>Listo Entrega</span>
                        <?php else: ?>
                        <span class="bdg bdg-inc"><span class="dot"></span>En proceso</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="icon-btn" onclick="window.location.href='seguimiento.php?id=<?php echo $p['id']; ?>'" title="Seguimiento"><i class="fas fa-route"></i></button>
                        <button class="icon-btn" onclick="window.location.href='<?php echo $accionUrl; ?>?pedido_id=<?php echo $p['id']; ?>'" title="Acción: <?php echo ucfirst($etapa); ?>"><i class="<?php echo $accionIcon; ?>"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginación -->
        <div class="pagination-bar">
            <div>Mostrando <?php echo count($pedidos); ?> de <?php echo $stats['total']; ?> pedidos</div>
            <div>
                <button class="page-btn">‹</button>
                <button class="page-btn active">1</button>
                <button class="page-btn">›</button>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}

function filtrarEstado(estado, el) {
    document.querySelectorAll('.stat-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    const url = new URL(window.location);
    if (estado === 'todos') {
        url.searchParams.delete('estado');
    } else {
        url.searchParams.set('estado', estado);
    }
    window.location.href = url.toString();
}

function filtrarVendedor(val) {
    const url = new URL(window.location);
    if (val) {
        url.searchParams.set('vendedor', val);
    } else {
        url.searchParams.delete('vendedor');
    }
    window.location.href = url.toString();
}

function filtrarFecha(val) {
    const url = new URL(window.location);
    if (val) {
        url.searchParams.set('fecha', val);
    } else {
        url.searchParams.delete('fecha');
    }
    window.location.href = url.toString();
}

function buscarTabla(val) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        const url = new URL(window.location);
        if (val) {
            url.searchParams.set('search', val);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 500);
}

function exportar() {
    alert('Función de exportación disponible próximamente.');
}
</script>
</body>
</html>
