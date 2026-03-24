<?php
/**
 * VIZENGO - Ver Pedido Completo
 * Muestra todos los datos registrados en las diferentes etapas del pedido
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

// Validar ID del pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$pedidoId = intval($_GET['id']);
$db = getDB();

// Obtener datos principales del pedido
$stmt = $db->prepare("SELECT p.*, c.nombre as cliente_nombre, c.celular as cliente_celular, 
                       u.nombre as vendedor_nombre
                       FROM pedidos p 
                       LEFT JOIN clientes c ON p.cliente_id = c.id
                       LEFT JOIN usuarios u ON p.usuario_id = u.id
                       WHERE p.id = ?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header('Location: dashboard.php');
    exit();
}

// ========== ETAPA 01: CONTRATO ==========
// Diseños iniciales (referencias)
$stmt = $db->prepare("SELECT * FROM disenos_iniciales WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$disenosIniciales = $stmt->fetchAll();

// Kits
$stmt = $db->prepare("SELECT * FROM kits WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$kits = $stmt->fetchAll();

// Adicionales de talla
$stmt = $db->prepare("SELECT * FROM adicionales_talla WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$adicionalesTalla = $stmt->fetchAll();

// Merchandising
$stmt = $db->prepare("SELECT * FROM merchandising WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$merchandising = $stmt->fetchAll();

// ========== ETAPA 02: INTEGRANTES ==========
$stmt = $db->prepare("SELECT * FROM integrantes WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$integrantes = $stmt->fetchAll();

// Imágenes de integrantes
$stmt = $db->prepare("SELECT * FROM imagenes_integrantes WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
$imagenesIntegrantes = $stmt->fetch();

// ========== ETAPA 03: DISEÑO ==========
$stmt = $db->prepare("SELECT df.*, u.nombre as disenador_nombre FROM disenos_finales df 
                       LEFT JOIN usuarios u ON df.disenador_id = u.id
                       WHERE df.pedido_id = ? ORDER BY df.id ASC");
$stmt->execute([$pedidoId]);
$disenosFinales = $stmt->fetchAll();

// ========== ETAPA 04: PLANCHADO ==========
$stmt = $db->prepare("SELECT * FROM planchado WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$planchados = $stmt->fetchAll();

// Merchandising de planchado
$planchadoMerchandising = [];
if (!empty($planchados)) {
    $planchadoIds = array_column($planchados, 'id');
    $placeholders = implode(',', array_fill(0, count($planchadoIds), '?'));
    $stmt = $db->prepare("SELECT * FROM planchado_merchandising WHERE planchado_id IN ($placeholders) ORDER BY id ASC");
    $stmt->execute($planchadoIds);
    $planchadoMerchandising = $stmt->fetchAll();
}

// ========== ETAPA 05: COSTURA ==========
$stmt = $db->prepare("SELECT * FROM costura WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$costuras = $stmt->fetchAll();

// Otros de costura
$costuraOtros = [];
if (!empty($costuras)) {
    $costuraIds = array_column($costuras, 'id');
    $placeholders = implode(',', array_fill(0, count($costuraIds), '?'));
    $stmt = $db->prepare("SELECT * FROM costura_otros WHERE costura_id IN ($placeholders) ORDER BY id ASC");
    $stmt->execute($costuraIds);
    $costuraOtros = $stmt->fetchAll();
}

// ========== ETAPA 06: ENTREGA ==========
$stmt = $db->prepare("SELECT e.*, u.nombre as entregador_nombre FROM entregas e 
                       LEFT JOIN usuarios u ON e.usuario_id = u.id
                       WHERE e.pedido_id = ?");
$stmt->execute([$pedidoId]);
$entrega = $stmt->fetch();

// Historial del pedido
$stmt = $db->prepare("SELECT h.*, u.nombre as usuario_nombre FROM historial_pedidos h 
                       LEFT JOIN usuarios u ON h.usuario_id = u.id
                       WHERE h.pedido_id = ? ORDER BY h.fecha_accion DESC");
$stmt->execute([$pedidoId]);
$historial = $stmt->fetchAll();

// Funciones helper
function getStatusBadge($status, $type = 'contrato') {
    $badgeClass = '';
    $statusText = '';
    
    switch($type) {
        case 'contrato':
        case 'integrantes':
        case 'diseno':
        case 'planchado':
        case 'costura':
            if ($status === 'completo' || $status === 'aprobado') {
                $badgeClass = 'badge-completo';
                $statusText = 'Completo';
            } else {
                $badgeClass = 'badge-pendiente';
                $statusText = 'Pendiente';
            }
            break;
        case 'general':
            switch($status) {
                case 'en_proceso':
                    $badgeClass = 'badge-pendiente';
                    $statusText = 'En Proceso';
                    break;
                case 'listo_entrega':
                    $badgeClass = 'badge-completo';
                    $statusText = 'Listo Entrega';
                    break;
                case 'entregado':
                    $badgeClass = 'badge-completo';
                    $statusText = 'Entregado';
                    break;
                case 'cancelado':
                    $badgeClass = 'badge-urgente';
                    $statusText = 'Cancelado';
                    break;
                default:
                    $badgeClass = 'badge-pendiente';
                    $statusText = 'En Proceso';
            }
            break;
    }
    
    return '<span class="status-badge ' . $badgeClass . '"><span class="dot"></span>' . $statusText . '</span>';
}

// Calcular totales
$totalIntegrantes = count($integrantes);
$totalKits = array_sum(array_column($kits, 'cantidad'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Ver Pedido <?php echo htmlspecialchars($pedido['codigo']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .pedido-header {
            background: linear-gradient(135deg, var(--sidebar-bg) 0%, #1a2744 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .pedido-header-left h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            margin-bottom: 4px;
        }
        
        .pedido-header-left .codigo {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            color: var(--accent);
            letter-spacing: 1px;
        }
        
        .pedido-header-right {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .header-badge {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 10px 16px;
            text-align: center;
        }
        
        .header-badge-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 2px;
        }
        
        .header-badge-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
        }
        
        .etapa-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .etapa-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .etapa-header:hover {
            background: #f8faff;
        }
        
        .etapa-num {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            color: white;
        }
        
        .etapa-1 .etapa-num { background: var(--warning); }
        .etapa-2 .etapa-num { background: var(--primary); }
        .etapa-3 .etapa-num { background: var(--info); }
        .etapa-4 .etapa-num { background: #a855f7; }
        .etapa-5 .etapa-num { background: #ec4899; }
        .etapa-6 .etapa-num { background: var(--success); }
        
        .etapa-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            flex: 1;
        }
        
        .etapa-toggle {
            color: var(--muted);
            transition: transform 0.3s;
        }
        
        .etapa-toggle.collapsed {
            transform: rotate(-90deg);
        }
        
        .etapa-body {
            padding: 0 20px 20px;
            display: none;
        }
        
        .etapa-body.show {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .info-item {
            background: #fafbff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
        }
        
        .info-item-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 4px;
        }
        
        .info-item-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        
        .data-table thead th {
            background: var(--sidebar-bg);
            color: rgba(255,255,255,0.7);
            padding: 12px 14px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            text-align: left;
        }
        
        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
        }
        
        .data-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .data-table tbody td {
            padding: 12px 14px;
            font-size: 0.88rem;
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background: #f8faff;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
        .empty-state p {
            font-size: 0.9rem;
            margin: 0;
        }
        
        .img-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }
        
        .img-gallery-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
            aspect-ratio: 1;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .img-gallery-item:hover {
            transform: scale(1.02);
        }
        
        .img-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .img-gallery-item .img-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
            padding: 8px;
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .totals-summary {
            background: linear-gradient(135deg, #f0f4ff 0%, #fafbff 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 16px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        .total-item {
            text-align: center;
        }
        
        .total-item-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
        }
        
        .total-item-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .total-item-value.accent {
            color: var(--accent);
        }
        
        .total-item-value.success {
            color: var(--success);
        }
        
        .historial-list {
            margin-top: 16px;
        }
        
        .historial-item {
            display: flex;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .historial-item:last-child {
            border-bottom: none;
        }
        
        .historial-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .historial-content {
            flex: 1;
        }
        
        .historial-accion {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
        }
        
        .historial-desc {
            font-size: 0.82rem;
            color: var(--muted);
            margin-top: 2px;
        }
        
        .historial-fecha {
            font-size: 0.75rem;
            color: var(--muted);
        }
        
        .sexo-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        
        .sexo-varon {
            background: rgba(43, 79, 255, 0.1);
            color: var(--primary);
        }
        
        .sexo-dama {
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899;
        }
        
        .regalo-badge {
            background: rgba(6, 214, 160, 0.1);
            color: var(--success);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .pedido-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .totals-summary {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Modal de imagen */
        .img-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .img-modal.show {
            display: flex;
        }
        
        .img-modal img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 10px;
        }
        
        .img-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Header del pedido -->
    <div class="pedido-header">
        <div class="pedido-header-left">
            <h1><i class="fas fa-shopping-bag" style="margin-right:10px;"></i><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></h1>
            <div class="codigo"><?php echo htmlspecialchars($pedido['codigo']); ?> · <?php echo $pedido['tipo_contrato']; ?></div>
        </div>
        <div class="pedido-header-right">
            <div class="header-badge">
                <div class="header-badge-label">Estado</div>
                <div class="header-badge-value"><?php echo getStatusBadge($pedido['estado_general'], 'general'); ?></div>
            </div>
            <div class="header-badge">
                <div class="header-badge-label">Entrega</div>
                <div class="header-badge-value"><?php echo formatDate($pedido['fecha_entrega'], 'd/m/Y'); ?></div>
            </div>
            <div class="header-badge">
                <div class="header-badge-label">Prendas</div>
                <div class="header-badge-value"><?php echo $totalIntegrantes; ?></div>
            </div>
            <div class="header-badge">
                <div class="header-badge-label">Saldo</div>
                <div class="header-badge-value" style="color: var(--accent);"><?php echo formatCurrency($pedido['saldo']); ?></div>
            </div>
        </div>
    </div>

    <!-- Pipeline visual de etapas -->
    <div class="section-header">
        <div class="section-title"><i class="fas fa-route" style="color:var(--primary);margin-right:8px;"></i>Progreso del Pedido</div>
        <a href="lista-pedidos.php" class="section-link"><i class="fas fa-arrow-left"></i> Volver a la lista</a>
    </div>
    
    <div class="stage-pipeline" style="margin-bottom: 24px;">
        <div class="stage-card stage-1 <?php echo $pedido['estado_contrato'] === 'completo' ? 'border-glow' : ''; ?>">
            <div class="stage-icon"><i class="fas fa-file-contract"></i></div>
            <div class="stage-num"><i class="fas fa-<?php echo $pedido['estado_contrato'] === 'completo' ? 'check' : 'clock'; ?>"></i></div>
            <div class="stage-title">Contrato</div>
            <div class="stage-sub"><?php echo $pedido['estado_contrato'] === 'completo' ? 'Completado' : 'Pendiente'; ?></div>
        </div>
        <div class="stage-card stage-2">
            <div class="stage-icon"><i class="fas fa-users"></i></div>
            <div class="stage-num"><?php echo $totalIntegrantes; ?></div>
            <div class="stage-title">Integrantes</div>
            <div class="stage-sub"><?php echo $pedido['estado_integrantes'] === 'completo' ? 'Registrados' : 'Pendientes'; ?></div>
        </div>
        <div class="stage-card stage-3">
            <div class="stage-icon"><i class="fas fa-paint-brush"></i></div>
            <div class="stage-num"><i class="fas fa-<?php echo $pedido['estado_diseno'] === 'completo' || $pedido['estado_diseno'] === 'aprobado' ? 'check' : 'clock'; ?>"></i></div>
            <div class="stage-title">Diseño</div>
            <div class="stage-sub"><?php echo count($disenosFinales); ?> archivos</div>
        </div>
        <div class="stage-card stage-4">
            <div class="stage-icon"><i class="fas fa-tshirt"></i></div>
            <div class="stage-num"><i class="fas fa-<?php echo $pedido['estado_planchado'] === 'completo' ? 'check' : 'clock'; ?>"></i></div>
            <div class="stage-title">Planchado</div>
            <div class="stage-sub"><?php echo $pedido['estado_planchado'] === 'completo' ? 'Completado' : 'Pendiente'; ?></div>
        </div>
        <div class="stage-card stage-5">
            <div class="stage-icon"><i class="fas fa-cut"></i></div>
            <div class="stage-num"><i class="fas fa-<?php echo $pedido['estado_costura'] === 'completo' ? 'check' : 'clock'; ?>"></i></div>
            <div class="stage-title">Costura</div>
            <div class="stage-sub"><?php echo $pedido['estado_costura'] === 'completo' ? 'Completado' : 'Pendiente'; ?></div>
        </div>
        <div class="stage-card stage-6">
            <div class="stage-icon"><i class="fas fa-check-double"></i></div>
            <div class="stage-num"><i class="fas fa-<?php echo $pedido['estado_general'] === 'entregado' ? 'check' : 'clock'; ?>"></i></div>
            <div class="stage-title">Entrega</div>
            <div class="stage-sub"><?php echo $pedido['estado_general'] === 'entregado' ? 'Entregado' : 'Pendiente'; ?></div>
        </div>
    </div>

    <!-- ETAPA 01: CONTRATO -->
    <div class="etapa-section etapa-1">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">1</div>
            <div class="etapa-title">Etapa 01 - Contrato</div>
            <?php echo getStatusBadge($pedido['estado_contrato']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <!-- Datos del contrato -->
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:16px; margin-bottom:12px;">
                <i class="fas fa-file-signature" style="margin-right:6px;"></i>Datos del Contrato
            </h6>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">Tipo de Contrato</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($pedido['tipo_contrato']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Lugar de Entrega</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($pedido['lugar_entrega']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Celular Cliente</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($pedido['celular_cliente'] ?: $pedido['cliente_celular'] ?: '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Vendedor</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($pedido['vendedor_asignado'] ?: $pedido['vendedor_nombre']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Fecha de Pedido</div>
                    <div class="info-item-value"><?php echo formatDate($pedido['fecha_pedido'], 'd/m/Y H:i'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Fecha de Entrega</div>
                    <div class="info-item-value"><?php echo formatDate($pedido['fecha_entrega'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Hora de Entrega</div>
                    <div class="info-item-value"><?php echo $pedido['hora_entrega'] ? date('H:i', strtotime($pedido['hora_entrega'])) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Dirección de Envío</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($pedido['direccion_envio'] ?: '-'); ?></div>
                </div>
                <?php if (!empty($pedido['observaciones_generales'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-item-label">Observaciones Generales</div>
                    <div class="info-item-value"><?php echo nl2br(htmlspecialchars($pedido['observaciones_generales'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($pedido['observaciones_diseno'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-item-label">Observaciones de Diseño</div>
                    <div class="info-item-value"><?php echo nl2br(htmlspecialchars($pedido['observaciones_diseno'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Resumen financiero -->
            <div class="totals-summary">
                <div class="total-item">
                    <div class="total-item-label">Subtotal</div>
                    <div class="total-item-value"><?php echo formatCurrency($pedido['subtotal']); ?></div>
                </div>
                <div class="total-item">
                    <div class="total-item-label">Adelanto</div>
                    <div class="total-item-value success"><?php echo formatCurrency($pedido['adelanto']); ?></div>
                </div>
                <div class="total-item">
                    <div class="total-item-label">Saldo Pendiente</div>
                    <div class="total-item-value accent"><?php echo formatCurrency($pedido['saldo']); ?></div>
                </div>
            </div>

            <!-- Diseños iniciales (referencias) -->
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-images" style="margin-right:6px;"></i>Diseños de Referencia
            </h6>
            <?php if (!empty($disenosIniciales)): ?>
            <div class="img-gallery">
                <?php foreach ($disenosIniciales as $img): ?>
                <div class="img-gallery-item" onclick="openModal('<?php echo htmlspecialchars($img['imagen_path']); ?>')">
                    <img src="<?php echo htmlspecialchars($img['imagen_path']); ?>" alt="Referencia">
                    <div class="img-overlay"><?php echo formatDate($img['fecha_subida'], 'd/m/Y'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <p>No hay diseños de referencia registrados</p>
            </div>
            <?php endif; ?>

            <!-- Kits -->
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-tshirt" style="margin-right:6px;"></i>Kits / Productos
            </h6>
            <?php if (!empty($kits)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Camiseta</th>
                        <th>Tela</th>
                        <th>Short</th>
                        <th>Tela Short</th>
                        <th>Medias</th>
                        <th>Cant.</th>
                        <th>P. Unit.</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kits as $kit): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($kit['camiseta_tipo'] ?: '-'); ?><br><small class="text-muted"><?php echo htmlspecialchars($kit['camiseta_talla'] ?: ''); ?></small></td>
                        <td><?php echo htmlspecialchars($kit['camiseta_tela'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($kit['short_tipo'] ?: '-'); ?><br><small class="text-muted"><?php echo htmlspecialchars($kit['short_talla'] ?: ''); ?></small></td>
                        <td><?php echo htmlspecialchars($kit['short_tela'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($kit['medias_tipo'] ?: '-'); ?><?php echo $kit['medias_detalles'] ? ' - ' . htmlspecialchars($kit['medias_detalles']) : ''; ?></td>
                        <td><strong><?php echo $kit['cantidad']; ?></strong></td>
                        <td><?php echo formatCurrency($kit['precio_unitario']); ?></td>
                        <td><strong style="color:var(--primary);"><?php echo formatCurrency($kit['subtotal']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tshirt"></i>
                <p>No hay kits registrados</p>
            </div>
            <?php endif; ?>

            <!-- Adicionales de talla -->
            <?php if (!empty($adicionalesTalla)): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-tags" style="margin-right:6px;"></i>Adicionales Talla Especial
            </h6>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Talla</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($adicionalesTalla as $adicional): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($adicional['talla']); ?></strong></td>
                        <td><?php echo $adicional['cantidad']; ?></td>
                        <td><?php echo formatCurrency($adicional['precio_unitario']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Merchandising -->
            <?php if (!empty($merchandising)): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-flag" style="margin-right:6px;"></i>Merchandising
            </h6>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th>Cantidad</th>
                        <th>Precio Unit.</th>
                        <th>Tipo</th>
                        <th>Especificaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($merchandising as $merch): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($merch['articulo']); ?></strong></td>
                        <td><?php echo $merch['cantidad']; ?></td>
                        <td><?php echo formatCurrency($merch['precio_unitario']); ?></td>
                        <td><?php echo $merch['es_regalo'] ? '<span class="regalo-badge"><i class="fas fa-gift"></i> Regalo</span>' : 'Venta'; ?></td>
                        <td><?php echo htmlspecialchars($merch['especificaciones'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ETAPA 02: INTEGRANTES -->
    <div class="etapa-section etapa-2">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">2</div>
            <div class="etapa-title">Etapa 02 - Integrantes</div>
            <span class="status-badge badge-<?php echo $pedido['estado_integrantes'] === 'completo' ? 'completo' : 'pendiente'; ?>">
                <span class="dot"></span><?php echo $totalIntegrantes; ?> registrados
            </span>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <?php if (!empty($integrantes)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Talla</th>
                        <th>Número</th>
                        <th>Sexo</th>
                        <th>Short</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($integrantes as $i => $int): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars($int['nombre']); ?></strong></td>
                        <td><?php echo htmlspecialchars($int['talla'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($int['numero'] ?: '-'); ?></td>
                        <td>
                            <span class="sexo-badge sexo-<?php echo strtolower($int['sexo']); ?>">
                                <i class="fas fa-<?php echo $int['sexo'] === 'Varon' ? 'mars' : 'venus'; ?>"></i>
                                <?php echo $int['sexo']; ?>
                            </span>
                        </td>
                        <td><?php echo $int['incluye_short'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>'; ?></td>
                        <td><?php echo htmlspecialchars($int['observacion'] ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Resumen de integrantes -->
            <div class="row mt-3">
                <?php 
                // Contar por sexo
                $varones = 0; $damas = 0;
                foreach ($integrantes as $int) {
                    if ($int['sexo'] === 'Varon') $varones++;
                    else $damas++;
                }
                ?>
                <div class="col-md-4">
                    <div class="info-item">
                        <div class="info-item-label">Total Integrantes</div>
                        <div class="info-item-value"><?php echo $totalIntegrantes; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-item">
                        <div class="info-item-label">Varones</div>
                        <div class="info-item-value" style="color:var(--primary);"><?php echo $varones; ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-item">
                        <div class="info-item-label">Damas</div>
                        <div class="info-item-value" style="color:#ec4899;"><?php echo $damas; ?></div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <p>No hay integrantes registrados</p>
            </div>
            <?php endif; ?>

            <!-- Imágenes de integrantes -->
            <?php if (!empty($imagenesIntegrantes)): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-images" style="margin-right:6px;"></i>Imagen de Referencia de Integrantes
            </h6>
            <div class="img-gallery">
                <div class="img-gallery-item" onclick="openModal('<?php echo htmlspecialchars($imagenesIntegrantes['imagen_path']); ?>')">
                    <img src="<?php echo htmlspecialchars($imagenesIntegrantes['imagen_path']); ?>" alt="Integrantes">
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ETAPA 03: DISEÑO -->
    <div class="etapa-section etapa-3">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">3</div>
            <div class="etapa-title">Etapa 03 - Diseño</div>
            <?php echo getStatusBadge($pedido['estado_diseno']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <?php if (!empty($disenosFinales)): ?>
            <div class="img-gallery">
                <?php foreach ($disenosFinales as $diseno): ?>
                <div class="img-gallery-item" onclick="openModal('<?php echo htmlspecialchars($diseno['imagen_path']); ?>')">
                    <img src="<?php echo htmlspecialchars($diseno['imagen_path']); ?>" alt="<?php echo htmlspecialchars($diseno['tipo']); ?>">
                    <div class="img-overlay">
                        <strong><?php echo ucfirst($diseno['tipo']); ?></strong>
                        <?php if ($diseno['aprobado']): ?>
                        <i class="fas fa-check-circle" style="color:var(--success); margin-left:4px;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <table class="data-table" style="margin-top:16px;">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Diseñador</th>
                        <th>Observaciones</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($disenosFinales as $diseno): ?>
                    <tr>
                        <td><strong><?php echo ucfirst(htmlspecialchars($diseno['tipo'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($diseno['disenador_nombre'] ?: '-'); ?></td>
                        <td><?php echo htmlspecialchars($diseno['observaciones'] ?: '-'); ?></td>
                        <td><?php echo formatDate($diseno['fecha_subida'], 'd/m/Y H:i'); ?></td>
                        <td>
                            <?php if ($diseno['aprobado']): ?>
                            <span class="status-badge badge-completo"><span class="dot"></span>Aprobado</span>
                            <?php else: ?>
                            <span class="status-badge badge-pendiente"><span class="dot"></span>Pendiente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-paint-brush"></i>
                <p>No hay diseños finales registrados</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ETAPA 04: PLANCHADO -->
    <div class="etapa-section etapa-4">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">4</div>
            <div class="etapa-title">Etapa 04 - Planchado</div>
            <?php echo getStatusBadge($pedido['estado_planchado']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <?php if (!empty($planchados)): ?>
            <?php foreach ($planchados as $planchado): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#a855f7; margin-top:16px; margin-bottom:12px;">
                <i class="fas fa-tshirt" style="margin-right:6px;"></i>Registro de Planchado #<?php echo $planchado['id']; ?>
            </h6>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">Planchador</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($planchado['planchador_nombre'] ?: '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Fecha de Planchado</div>
                    <div class="info-item-value"><?php echo formatDate($planchado['fecha_planchado'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Cant. Polos</div>
                    <div class="info-item-value"><?php echo $planchado['cant_polos']; ?> uds</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Cant. Shorts</div>
                    <div class="info-item-value"><?php echo $planchado['cant_shorts']; ?> uds</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Cant. Cuellos</div>
                    <div class="info-item-value"><?php echo $planchado['cant_cuellos']; ?> uds</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Precio Polo</div>
                    <div class="info-item-value"><?php echo formatCurrency($planchado['precio_polo']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Precio Short</div>
                    <div class="info-item-value"><?php echo formatCurrency($planchado['precio_short']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Precio Cuello</div>
                    <div class="info-item-value"><?php echo formatCurrency($planchado['precio_cuello']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Total Pago</div>
                    <div class="info-item-value" style="color:#a855f7; font-size:1.1rem;"><?php echo formatCurrency($planchado['total_pago']); ?></div>
                </div>
                <?php if (!empty($planchado['observaciones'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-item-label">Observaciones</div>
                    <div class="info-item-value"><?php echo nl2br(htmlspecialchars($planchado['observaciones'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- Merchandising de planchado -->
            <?php if (!empty($planchadoMerchandising)): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#a855f7; margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-flag" style="margin-right:6px;"></i>Merchandising Planchado
            </h6>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planchadoMerchandising as $pm): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($pm['articulo']); ?></td>
                        <td><?php echo $pm['cantidad']; ?></td>
                        <td><?php echo formatCurrency($pm['precio_unitario']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-tshirt"></i>
                <p>No hay registros de planchado</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ETAPA 05: COSTURA -->
    <div class="etapa-section etapa-5">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">5</div>
            <div class="etapa-title">Etapa 05 - Costura</div>
            <?php echo getStatusBadge($pedido['estado_costura']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <?php if (!empty($costuras)): ?>
            <?php foreach ($costuras as $costura): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#ec4899; margin-top:16px; margin-bottom:12px;">
                <i class="fas fa-cut" style="margin-right:6px;"></i>Registro de Costura #<?php echo $costura['id']; ?>
            </h6>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">Costurero</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($costura['costurero_nombre'] ?: '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Fecha de Costura</div>
                    <div class="info-item-value"><?php echo formatDate($costura['fecha_costura'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Cant. Polos</div>
                    <div class="info-item-value"><?php echo $costura['cant_polos']; ?> uds</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Cant. Shorts</div>
                    <div class="info-item-value"><?php echo $costura['cant_shorts']; ?> uds</div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Precio Polo</div>
                    <div class="info-item-value"><?php echo formatCurrency($costura['precio_polo']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Precio Short</div>
                    <div class="info-item-value"><?php echo formatCurrency($costura['precio_short']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Total Pago</div>
                    <div class="info-item-value" style="color:#ec4899; font-size:1.1rem;"><?php echo formatCurrency($costura['total_pago']); ?></div>
                </div>
                <?php if (!empty($costura['observaciones'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-item-label">Observaciones</div>
                    <div class="info-item-value"><?php echo nl2br(htmlspecialchars($costura['observaciones'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <!-- Otros de costura -->
            <?php if (!empty($costuraOtros)): ?>
            <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#ec4899; margin-top:24px; margin-bottom:12px;">
                <i class="fas fa-plus-circle" style="margin-right:6px;"></i>Otros Trabajos de Costura
            </h6>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($costuraOtros as $co): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($co['descripcion']); ?></td>
                        <td><?php echo $co['cantidad']; ?></td>
                        <td><?php echo formatCurrency($co['precio_unitario']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-cut"></i>
                <p>No hay registros de costura</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ETAPA 06: ENTREGA -->
    <div class="etapa-section etapa-6">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">6</div>
            <div class="etapa-title">Etapa 06 - Entrega</div>
            <?php echo getStatusBadge($pedido['estado_general'], 'general'); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <?php if (!empty($entrega)): ?>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">Lugar de Entrega</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($entrega['lugar_entrega']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Tipo</div>
                    <div class="info-item-value"><?php echo $entrega['es_envio'] ? 'Envío' : 'Recojo en tienda'; ?></div>
                </div>
                <?php if ($entrega['es_envio']): ?>
                <div class="info-item">
                    <div class="info-item-label">Dirección de Envío</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($entrega['direccion_envio'] ?: '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Costo de Envío</div>
                    <div class="info-item-value"><?php echo formatCurrency($entrega['costo_envio']); ?></div>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <div class="info-item-label">Total Cobrado</div>
                    <div class="info-item-value" style="color:var(--success); font-size:1.1rem;"><?php echo formatCurrency($entrega['total_cobrado']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Entregado por</div>
                    <div class="info-item-value"><?php echo htmlspecialchars($entrega['entregador_nombre']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Fecha de Entrega</div>
                    <div class="info-item-value"><?php echo formatDate($entrega['fecha_entrega'], 'd/m/Y H:i'); ?></div>
                </div>
                <?php if (!empty($entrega['observaciones'])): ?>
                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-item-label">Observaciones</div>
                    <div class="info-item-value"><?php echo nl2br(htmlspecialchars($entrega['observaciones'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box"></i>
                <p>El pedido aún no ha sido entregado</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historial del pedido -->
    <div class="etapa-section" style="border-left:4px solid var(--muted);">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num" style="background:var(--muted);"><i class="fas fa-history"></i></div>
            <div class="etapa-title">Historial del Pedido</div>
            <span style="color:var(--muted); font-size:0.85rem;"><?php echo count($historial); ?> registros</span>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <?php if (!empty($historial)): ?>
            <div class="historial-list">
                <?php foreach ($historial as $h): ?>
                <div class="historial-item">
                    <div class="historial-icon">
                        <i class="fas fa-<?php 
                            echo strpos($h['accion'], 'CREADO') !== false ? 'plus' : 
                                (strpos($h['accion'], 'ENTREGADO') !== false ? 'check' : 
                                (strpos($h['accion'], 'ACTUALIZADO') !== false ? 'edit' : 'info')); 
                        ?>"></i>
                    </div>
                    <div class="historial-content">
                        <div class="historial-accion"><?php echo htmlspecialchars($h['accion']); ?></div>
                        <?php if (!empty($h['descripcion'])): ?>
                        <div class="historial-desc"><?php echo htmlspecialchars($h['descripcion']); ?></div>
                        <?php endif; ?>
                        <div class="historial-fecha">
                            <?php echo formatDate($h['fecha_accion'], 'd/m/Y H:i'); ?> · 
                            <?php echo htmlspecialchars($h['usuario_nombre'] ?: 'Sistema'); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history"></i>
                <p>No hay historial disponible</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- Modal para ver imágenes -->
<div class="img-modal" id="imgModal" onclick="closeModal()">
    <button class="img-modal-close"><i class="fas fa-times"></i></button>
    <img src="" alt="Imagen ampliada" id="modalImg">
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function toggleEtapa(header) {
    const body = header.nextElementSibling;
    const toggle = header.querySelector('.etapa-toggle');
    
    if (body.classList.contains('show')) {
        body.classList.remove('show');
        toggle.classList.add('collapsed');
    } else {
        body.classList.add('show');
        toggle.classList.remove('collapsed');
    }
}

function openModal(imgSrc) {
    const modal = document.getElementById('imgModal');
    const modalImg = document.getElementById('modalImg');
    modalImg.src = imgSrc;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('imgModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Expandir primera etapa por defecto
document.addEventListener('DOMContentLoaded', function() {
    const firstEtapa = document.querySelector('.etapa-section.etapa-1 .etapa-header');
    if (firstEtapa) {
        toggleEtapa(firstEtapa);
    }
});
</script>
</body>
</html>
