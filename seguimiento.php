<?php
/**
 * VIZENGO - Seguimiento de Pedidos
 * Pipeline visual del estado de cada pedido
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
$filtroEtapa = sanitize($_GET['etapa'] ?? '');
$filtroUrgente = sanitize($_GET['urgente'] ?? '');
$busqueda = sanitize($_GET['search'] ?? '');

// Query para obtener pedidos con toda la información
$sql = "SELECT 
    p.id, p.codigo, p.tipo_contrato, p.lugar_entrega,
    p.estado_contrato, p.estado_integrantes, p.estado_diseno,
    p.estado_planchado, p.estado_costura, p.estado_general,
    p.fecha_pedido, p.fecha_entrega,
    p.subtotal, p.adelanto, p.saldo,
    c.nombre as cliente, c.celular as cliente_celular,
    u.nombre as vendedor,
    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes,
    CASE WHEN p.fecha_entrega <= CURDATE() AND p.estado_general != 'entregado' THEN 1 ELSE 0 END as es_urgente,
    pl.planchador_nombre, pl.fecha_planchado,
    co.costurero_nombre, co.fecha_costura
FROM pedidos p
LEFT JOIN clientes c ON p.cliente_id = c.id
LEFT JOIN usuarios u ON p.usuario_id = u.id
LEFT JOIN planchado pl ON pl.pedido_id = p.id
LEFT JOIN costura co ON co.pedido_id = p.id
WHERE 1=1";

$params = [];

// Filtro por rol
if ($user['rol'] === 'vendedor') {
    $sql .= " AND p.usuario_id = ?";
    $params[] = $user['id'];
} elseif ($user['rol'] === 'disenador') {
    $sql .= " AND p.estado_contrato = 'completo' AND p.estado_integrantes = 'completo'";
}

// Filtro por etapa
if (!empty($filtroEtapa)) {
    if ($filtroEtapa === 'urgente') {
        $sql .= " AND p.fecha_entrega <= CURDATE() AND p.estado_general != 'entregado'";
    } elseif ($filtroEtapa === 'completado') {
        $sql .= " AND p.estado_general = 'entregado'";
    } elseif ($filtroEtapa === 'entrega') {
        $sql .= " AND p.estado_general = 'listo_entrega'";
    } else {
        // Mapeo de etapas
        $etapaMap = [
            'contrato' => 'estado_contrato',
            'integrantes' => 'estado_integrantes',
            'diseno' => 'estado_diseno',
            'planchado' => 'estado_planchado',
            'costura' => 'estado_costura'
        ];
        if (isset($etapaMap[$filtroEtapa])) {
            $sql .= " AND p.{$etapaMap[$filtroEtapa]} != 'completo'";
        }
    }
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

// Función para determinar la etapa actual
function getEtapaActual($p) {
    if ($p['estado_general'] === 'entregado') return 'completado';
    if ($p['estado_general'] === 'listo_entrega') return 'entrega';
    if ($p['estado_costura'] !== 'completo' && $p['estado_planchado'] === 'completo') return 'costura';
    if ($p['estado_planchado'] !== 'completo' && $p['estado_diseno'] === 'completo') return 'planchado';
    if ($p['estado_diseno'] !== 'completo' && $p['estado_integrantes'] === 'completo') return 'diseno';
    if ($p['estado_integrantes'] !== 'completo') return 'integrantes';
    return 'contrato';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Seguimiento de Pedidos</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Filtros */
        .filters-bar {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; padding: 16px 20px;
            display: flex; gap: 12px; align-items: center; margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: none; border: 1.5px solid var(--border);
            border-radius: 8px; padding: 7px 14px;
            font-family: 'Barlow', sans-serif; font-size: 0.82rem; font-weight: 600;
            color: var(--muted); cursor: pointer; transition: all 0.2s;
        }
        .filter-btn:hover, .filter-btn.active { background: var(--primary); border-color: var(--primary); color: white; }
        .filter-search {
            margin-left: auto; display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1px solid var(--border); border-radius: 8px;
            padding: 7px 14px; min-width: 220px;
        }
        .filter-search input { background: none; border: none; outline: none; font-family: 'Barlow', sans-serif; font-size: 0.85rem; color: var(--text); width: 100%; }
        .filter-search i { color: var(--muted); }

        /* Tarjeta de seguimiento */
        .tracking-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; margin-bottom: 16px; overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .tracking-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.07); }

        .tc-header {
            display: grid; grid-template-columns: 1fr auto;
            padding: 16px 20px; gap: 12px; align-items: center;
            cursor: pointer; border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }
        .tc-header.open { border-bottom-color: var(--border); }
        .tc-order-name { font-family: 'Barlow Condensed', sans-serif; font-size: 1.15rem; font-weight: 800; text-transform: uppercase; }
        .tc-meta { font-size: 0.78rem; color: var(--muted); margin-top: 3px; }
        .tc-badges { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        /* Pipeline steps - 6 etapas */
        .tc-pipeline { padding: 20px; display: none; }
        .tc-pipeline.open { display: block; animation: slideDown 0.25s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .pipeline-steps {
            display: grid; grid-template-columns: repeat(6, 1fr);
            gap: 0; position: relative;
        }
        .pipeline-steps::before {
            content: ''; position: absolute;
            top: 28px; left: 5%; right: 5%; height: 3px;
            background: var(--border); z-index: 0;
        }
        .step {
            display: flex; flex-direction: column; align-items: center;
            text-align: center; position: relative; z-index: 1;
        }
        .step-circle {
            width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; border: 3px solid var(--border);
            background: var(--surface); transition: all 0.3s; margin-bottom: 10px;
        }
        .step.done .step-circle { background: var(--success); border-color: var(--success); color: white; }
        .step.active .step-circle { background: var(--primary); border-color: var(--primary); color: white; box-shadow: 0 0 0 6px rgba(43,79,255,0.12); }
        .step.warn .step-circle { background: var(--warning); border-color: var(--warning); color: white; }
        .step.pending .step-circle { background: var(--bg); color: var(--muted); }
        .step-label { font-family: 'Barlow Condensed', sans-serif; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; }
        .step.done .step-label { color: var(--success); }
        .step.active .step-label { color: var(--primary); }
        .step.warn .step-label { color: var(--warning); }
        .step.pending .step-label { color: var(--muted); }
        .step-detail { font-size: 0.7rem; color: var(--muted); margin-top: 3px; }
        .step-date { font-size: 0.68rem; color: var(--muted); margin-top: 4px; }

        /* Acciones dentro de la card */
        .tc-actions {
            display: flex; gap: 8px; padding: 14px 20px;
            background: #fafbff; border-top: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 7px 14px; border-radius: 8px;
            font-size: 0.8rem; font-weight: 600; cursor: pointer;
            border: 1.5px solid; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .action-btn.primary { border-color: var(--primary); color: var(--primary); background: rgba(43,79,255,0.05); }
        .action-btn.primary:hover { background: var(--primary); color: white; }
        .action-btn.success { border-color: var(--success); color: #059669; background: rgba(6,214,160,0.05); }
        .action-btn.success:hover { background: var(--success); color: white; }
        .action-btn.warn { border-color: var(--warning); color: #d97706; background: rgba(245,158,11,0.05); }
        .action-btn.warn:hover { background: var(--warning); color: white; }
        .action-btn.danger { border-color: var(--danger); color: var(--danger); background: rgba(239,71,111,0.05); }
        .action-btn.danger:hover { background: var(--danger); color: white; }

        /* Badges */
        .badge-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px;
            font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .badge-pill .dot { width: 6px; height: 6px; border-radius: 50%; }
        .bp-success { background: rgba(6,214,160,0.12); color: #059669; }
        .bp-success .dot { background: #059669; }
        .bp-warn { background: rgba(245,158,11,0.12); color: #d97706; }
        .bp-warn .dot { background: #d97706; }
        .bp-pending { background: rgba(100,116,139,0.1); color: #64748b; }
        .bp-pending .dot { background: #94a3b8; }
        .bp-danger { background: rgba(239,71,111,0.12); color: #dc2626; }
        .bp-danger .dot { background: #dc2626; animation: blink 1s infinite; }
        @keyframes blink { 0%,100%{opacity:1}50%{opacity:0.3} }
        .bp-purple { background: rgba(168,85,247,0.12); color: #7c3aed; }
        .bp-purple .dot { background: #7c3aed; }

        /* Urgente banner */
        .urgente-banner {
            background: rgba(239,71,111,0.08); border: 1px solid rgba(239,71,111,0.2);
            border-left: 4px solid var(--danger); border-radius: 8px;
            padding: 10px 14px; margin-bottom: 16px;
            display: flex; align-items: center; gap: 10px;
            font-size: 0.82rem; color: #991b1b;
        }

        @media (max-width: 900px) {
            .pipeline-steps { grid-template-columns: repeat(3, 1fr); }
            .pipeline-steps::before { display: none; }
        }
        @media (max-width: 600px) {
            .pipeline-steps { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-route" style="color:var(--primary);margin-right:10px;"></i>Seguimiento de Pedidos</h1>
            <p>Estado en tiempo real de cada etapa del proceso</p>
        </div>
        <div class="topbar-right">
            <?php if ($user['rol'] === 'vendedor' || $user['rol'] === 'administrador'): ?>
            <a href="ingreso-pedido.php" class="btn-primary-action" id="btn-nuevo">
                <i class="fas fa-plus"></i> Nuevo Pedido
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <button class="filter-btn <?php echo empty($filtroEtapa) ? 'active' : ''; ?>" onclick="filtrar('todos')">Todos</button>
        <button class="filter-btn <?php echo $filtroEtapa === 'contrato' ? 'active' : ''; ?>" onclick="filtrar('contrato')"><i class="fas fa-file-contract" style="margin-right:4px;"></i>Contrato</button>
        <button class="filter-btn <?php echo $filtroEtapa === 'diseno' ? 'active' : ''; ?>" onclick="filtrar('diseno')"><i class="fas fa-paint-brush" style="margin-right:4px;"></i>Diseño</button>
        <button class="filter-btn <?php echo $filtroEtapa === 'planchado' ? 'active' : ''; ?>" onclick="filtrar('planchado')"><i class="fas fa-tshirt" style="margin-right:4px;"></i>Planchado</button>
        <button class="filter-btn <?php echo $filtroEtapa === 'costura' ? 'active' : ''; ?>" onclick="filtrar('costura')"><i class="fas fa-cut" style="margin-right:4px;"></i>Costura</button>
        <button class="filter-btn <?php echo $filtroEtapa === 'entrega' ? 'active' : ''; ?>" onclick="filtrar('entrega')"><i class="fas fa-box" style="margin-right:4px;"></i>Entrega</button>
        <button class="filter-btn <?php echo $filtroEtapa === 'completado' ? 'active' : ''; ?>" onclick="filtrar('completado')"><i class="fas fa-check-circle" style="margin-right:4px;"></i>Completados</button>
        <button class="filter-btn" style="border-color:var(--danger);color:var(--danger);<?php echo $filtroEtapa === 'urgente' ? 'background:var(--danger);color:white;' : ''; ?>" onclick="filtrar('urgente')"><i class="fas fa-exclamation-triangle" style="margin-right:4px;"></i>Urgentes</button>
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar cliente o pedido..." value="<?php echo htmlspecialchars($busqueda); ?>" oninput="buscar(this.value)">
        </div>
    </div>

    <!-- Tarjetas de seguimiento -->
    <div id="trackingList">
        <?php if (count($pedidos) === 0): ?>
        <div style="text-align:center;padding:60px 20px;color:var(--muted);">
            <i class="fas fa-inbox" style="font-size:3rem;margin-bottom:16px;opacity:0.3;display:block;"></i>
            <p>No se encontraron pedidos</p>
        </div>
        <?php endif; ?>
        
        <?php foreach ($pedidos as $p): ?>
        <?php $etapaActual = getEtapaActual($p); ?>
        <div class="tracking-card" data-etapa="<?php echo $etapaActual; ?>" data-urgente="<?php echo $p['es_urgente'] ? 'true' : 'false'; ?>" data-nombre="<?php echo strtolower($p['cliente']); ?>">
            <div class="tc-header" onclick="toggleCard(this)">
                <div>
                    <div class="tc-order-name"><?php echo htmlspecialchars($p['cliente']); ?> <span style="font-size:0.75rem;color:var(--muted);font-weight:400;">#<?php echo $p['codigo']; ?></span></div>
                    <div class="tc-meta">
                        <?php echo $p['total_integrantes']; ?> prendas · 
                        Vendedor: <?php echo htmlspecialchars($p['vendedor'] ?? '-'); ?> · 
                        <?php echo htmlspecialchars($p['lugar_entrega']); ?> · 
                        <?php if ($p['es_urgente'] && $p['estado_general'] !== 'entregado'): ?>
                        <strong style="color:var(--danger);">HOY <?php echo date('H:i', strtotime($p['fecha_entrega'])); ?></strong>
                        <?php else: ?>
                        Entrega: <?php echo formatDate($p['fecha_entrega'], 'd/m/Y'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="tc-badges">
                    <?php if ($p['es_urgente'] && $p['estado_general'] !== 'entregado'): ?>
                    <span class="badge-pill bp-danger"><span class="dot"></span>URGENTE</span>
                    <?php endif; ?>
                    
                    <?php if ($p['estado_general'] === 'entregado'): ?>
                    <span class="badge-pill bp-purple"><span class="dot"></span>COMPLETADO</span>
                    <?php else: ?>
                    <span class="badge-pill bp-<?php echo $p['estado_contrato'] === 'completo' ? 'success' : 'warn'; ?>"><span class="dot"></span>Contrato</span>
                    <span class="badge-pill bp-<?php echo $p['estado_integrantes'] === 'completo' ? 'success' : 'pending'; ?>"><span class="dot"></span>Integrantes</span>
                    <span class="badge-pill bp-<?php echo $p['estado_diseno'] === 'completo' || $p['estado_diseno'] === 'aprobado' ? 'success' : 'pending'; ?>"><span class="dot"></span>Diseño</span>
                    <span class="badge-pill bp-<?php echo $p['estado_planchado'] === 'completo' ? 'success' : 'pending'; ?>"><span class="dot"></span>Planchado</span>
                    <span class="badge-pill bp-<?php echo $p['estado_costura'] === 'completo' ? 'success' : 'pending'; ?>"><span class="dot"></span>Costura</span>
                    <?php if ($p['estado_general'] === 'listo_entrega'): ?>
                    <span class="badge-pill bp-warn"><span class="dot"></span>Entrega</span>
                    <?php endif; ?>
                    <?php endif; ?>
                    <i class="fas fa-chevron-down" style="color:var(--muted);margin-left:8px;transition:transform 0.2s;"></i>
                </div>
            </div>
            <div class="tc-pipeline">
                <?php if ($p['es_urgente'] && $p['estado_general'] !== 'entregado'): ?>
                <div style="background:rgba(239,71,111,0.06);border:1px solid rgba(239,71,111,0.15);border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:0.82rem;color:#991b1b;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Entrega hoy.</strong> Pendiente de completar las etapas restantes.
                </div>
                <?php endif; ?>
                
                <div class="pipeline-steps">
                    <!-- Etapa 1: Contrato -->
                    <div class="step <?php echo $p['estado_contrato'] === 'completo' ? 'done' : ($etapaActual === 'contrato' ? 'active' : 'pending'); ?>">
                        <div class="step-circle"><i class="fas fa-file-contract"></i></div>
                        <div class="step-label">Contrato</div>
                        <div class="step-detail"><?php echo $p['estado_contrato'] === 'completo' ? 'Completo' : 'Pendiente'; ?></div>
                        <div class="step-date"><?php echo formatDate($p['fecha_pedido'], 'd/m/Y'); ?></div>
                    </div>
                    <!-- Etapa 2: Integrantes -->
                    <div class="step <?php echo $p['estado_integrantes'] === 'completo' ? 'done' : ($etapaActual === 'integrantes' ? 'active' : 'pending'); ?>">
                        <div class="step-circle"><i class="fas fa-users"></i></div>
                        <div class="step-label">Integrantes</div>
                        <div class="step-detail"><?php echo $p['estado_integrantes'] === 'completo' ? $p['total_integrantes'].' registrados' : 'Pendiente'; ?></div>
                    </div>
                    <!-- Etapa 3: Diseño -->
                    <div class="step <?php echo ($p['estado_diseno'] === 'completo' || $p['estado_diseno'] === 'aprobado') ? 'done' : ($etapaActual === 'diseno' ? 'active' : 'pending'); ?>">
                        <div class="step-circle"><i class="fas fa-paint-brush"></i></div>
                        <div class="step-label">Diseño</div>
                        <div class="step-detail"><?php echo ($p['estado_diseno'] === 'completo' || $p['estado_diseno'] === 'aprobado') ? 'Completo' : 'Pendiente'; ?></div>
                    </div>
                    <!-- Etapa 4: Planchado -->
                    <div class="step <?php echo $p['estado_planchado'] === 'completo' ? 'done' : ($etapaActual === 'planchado' ? 'active' : 'pending'); ?>">
                        <div class="step-circle"><i class="fas fa-tshirt"></i></div>
                        <div class="step-label">Planchado</div>
                        <div class="step-detail"><?php echo $p['estado_planchado'] === 'completo' ? htmlspecialchars($p['planchador_nombre'] ?? 'Completo') : 'Pendiente'; ?></div>
                    </div>
                    <!-- Etapa 5: Costura -->
                    <div class="step <?php echo $p['estado_costura'] === 'completo' ? 'done' : ($etapaActual === 'costura' ? 'active' : 'pending'); ?>">
                        <div class="step-circle"><i class="fas fa-cut"></i></div>
                        <div class="step-label">Costura</div>
                        <div class="step-detail"><?php echo $p['estado_costura'] === 'completo' ? htmlspecialchars($p['costurero_nombre'] ?? 'Completo') : 'Pendiente'; ?></div>
                    </div>
                    <!-- Etapa 6: Entrega -->
                    <div class="step <?php echo $p['estado_general'] === 'entregado' ? 'done' : ($p['estado_general'] === 'listo_entrega' ? 'active' : 'pending'); ?>">
                        <div class="step-circle"><i class="fas fa-box"></i></div>
                        <div class="step-label">Entrega</div>
                        <div class="step-detail"><?php echo $p['estado_general'] === 'entregado' ? 'Entregado' : ($p['estado_general'] === 'listo_entrega' ? 'Listo' : 'Pendiente'); ?></div>
                    </div>
                </div>
            </div>
            <div class="tc-actions">
                <?php 
                // Determinar acción principal según la etapa actual
                $accionUrl = '#';
                $accionLabel = '';
                $accionClass = 'primary';
                
                if ($p['estado_general'] === 'listo_entrega') {
                    $accionUrl = "entrega.php?pedido_id={$p['id']}";
                    $accionLabel = 'Registrar Entrega';
                    $accionClass = 'success';
                } elseif ($p['estado_costura'] !== 'completo' && $p['estado_planchado'] === 'completo') {
                    $accionUrl = "costura.php?pedido_id={$p['id']}";
                    $accionLabel = 'Registrar Costura';
                    $accionClass = 'warn';
                } elseif ($p['estado_planchado'] !== 'completo' && $p['estado_diseno'] === 'completo') {
                    $accionUrl = "planchado.php?pedido_id={$p['id']}";
                    $accionLabel = 'Registrar Planchado';
                    $accionClass = 'warn';
                } elseif ($p['estado_diseno'] !== 'completo' && $p['estado_integrantes'] === 'completo') {
                    $accionUrl = "diseno.php?pedido_id={$p['id']}";
                    $accionLabel = 'Subir Diseño';
                } elseif ($p['estado_integrantes'] !== 'completo') {
                    $accionUrl = "registro-integrantes.php?pedido_id={$p['id']}";
                    $accionLabel = 'Registrar Integrantes';
                }
                
                if ($accionUrl !== '#'):
                ?>
                <a href="<?php echo $accionUrl; ?>" class="action-btn <?php echo $accionClass; ?>">
                    <i class="fas fa-arrow-right"></i> <?php echo $accionLabel; ?>
                </a>
                <?php endif; ?>
                <a href="lista-pedidos.php" class="action-btn primary"><i class="fas fa-eye"></i> Ver Detalles</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function toggleCard(header) {
    header.classList.toggle('open');
    const pipeline = header.nextElementSibling;
    pipeline.classList.toggle('open');
    const chevron = header.querySelector('.fa-chevron-down');
    if (chevron) {
        chevron.style.transform = pipeline.classList.contains('open') ? 'rotate(180deg)' : '';
    }
}

function filtrar(etapa) {
    const url = new URL(window.location);
    if (etapa === 'todos') {
        url.searchParams.delete('etapa');
    } else {
        url.searchParams.set('etapa', etapa);
    }
    window.location.href = url.toString();
}

function buscar(val) {
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

// Auto-expand first urgent card
document.addEventListener('DOMContentLoaded', function() {
    const urgentCards = document.querySelectorAll('[data-urgente="true"]');
    if (urgentCards.length > 0) {
        const header = urgentCards[0].querySelector('.tc-header');
        if (header) toggleCard(header);
    }
});
</script>
</body>
</html>
