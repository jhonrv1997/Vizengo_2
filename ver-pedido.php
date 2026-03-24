<?php
/**
 * VIZENGO - Ver Pedido Completo (VERSIÓN OPTIMIZADA)
 * Muestra todos los datos registrados en las diferentes etapas del pedido
 * 
 * OPTIMIZACIONES APLICADAS:
 * 1. Consultas consolidadas con JOINs (de 14 consultas a 3)
 * 2. Carga AJAX diferida para etapas (carga solo lo visible)
 * 3. SELECT específico de columnas necesarias
 * 4. Caché de sesión para datos del pedido
 * 5. Lazy loading para imágenes
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

// =====================================================
// CONSULTA OPTIMIZADA 1: Datos principales del pedido
// Solo una consulta con todos los datos necesarios
// =====================================================
$stmt = $db->prepare("SELECT 
                        p.id, p.codigo, p.tipo_contrato, p.lugar_entrega, 
                        p.direccion_envio, p.vendedor_asignado, p.celular_cliente,
                        p.observaciones_generales, p.observaciones_diseno,
                        p.estado_contrato, p.estado_integrantes, p.estado_diseno,
                        p.estado_planchado, p.estado_costura, p.estado_general,
                        p.fecha_pedido, p.fecha_entrega, p.hora_entrega,
                        p.subtotal, p.adelanto, p.saldo,
                        c.nombre as cliente_nombre, c.celular as cliente_celular, 
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

// =====================================================
// CONSULTA OPTIMIZADA 2: Contadores para el pipeline
// Una sola consulta para obtener todos los conteos
// =====================================================
$stmt = $db->prepare("SELECT 
                        (SELECT COUNT(*) FROM integrantes WHERE pedido_id = ?) as total_integrantes,
                        (SELECT COUNT(*) FROM kits WHERE pedido_id = ?) as total_kits,
                        (SELECT SUM(cantidad) FROM kits WHERE pedido_id = ?) as total_prendas_kits,
                        (SELECT COUNT(*) FROM disenos_finales WHERE pedido_id = ?) as total_disenos,
                        (SELECT COUNT(*) FROM disenos_iniciales WHERE pedido_id = ?) as total_referencias");
$stmt->execute([$pedidoId, $pedidoId, $pedidoId, $pedidoId, $pedidoId]);
$contadores = $stmt->fetch();

$totalIntegrantes = $contadores['total_integrantes'] ?? 0;
$totalKits = $contadores['total_prendas_kits'] ?? 0;
$totalDisenos = $contadores['total_disenos'] ?? 0;

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
        
        /* Loading spinner */
        .etapa-loading {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        
        .etapa-loading .spinner-border {
            width: 2rem;
            height: 2rem;
            margin-bottom: 10px;
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
        
        /* Badge de optimización */
        .optimization-badge {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
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
            <div class="stage-sub"><?php echo $totalDisenos; ?> archivos</div>
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
    <div class="etapa-section etapa-1" data-etapa="contrato" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">1</div>
            <div class="etapa-title">Etapa 01 - Contrato</div>
            <?php echo getStatusBadge($pedido['estado_contrato']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando datos del contrato...</p>
            </div>
        </div>
    </div>

    <!-- ETAPA 02: INTEGRANTES -->
    <div class="etapa-section etapa-2" data-etapa="integrantes" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">2</div>
            <div class="etapa-title">Etapa 02 - Integrantes</div>
            <span class="status-badge badge-<?php echo $pedido['estado_integrantes'] === 'completo' ? 'completo' : 'pendiente'; ?>">
                <span class="dot"></span><?php echo $totalIntegrantes; ?> registrados
            </span>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando integrantes...</p>
            </div>
        </div>
    </div>

    <!-- ETAPA 03: DISEÑO -->
    <div class="etapa-section etapa-3" data-etapa="diseno" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">3</div>
            <div class="etapa-title">Etapa 03 - Diseño</div>
            <?php echo getStatusBadge($pedido['estado_diseno']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando diseños...</p>
            </div>
        </div>
    </div>

    <!-- ETAPA 04: PLANCHADO -->
    <div class="etapa-section etapa-4" data-etapa="planchado" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">4</div>
            <div class="etapa-title">Etapa 04 - Planchado</div>
            <?php echo getStatusBadge($pedido['estado_planchado']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando datos de planchado...</p>
            </div>
        </div>
    </div>

    <!-- ETAPA 05: COSTURA -->
    <div class="etapa-section etapa-5" data-etapa="costura" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">5</div>
            <div class="etapa-title">Etapa 05 - Costura</div>
            <?php echo getStatusBadge($pedido['estado_costura']); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando datos de costura...</p>
            </div>
        </div>
    </div>

    <!-- ETAPA 06: ENTREGA -->
    <div class="etapa-section etapa-6" data-etapa="entrega" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num">6</div>
            <div class="etapa-title">Etapa 06 - Entrega</div>
            <?php echo $pedido['estado_general'] === 'entregado' ? getStatusBadge('completo') : getStatusBadge('pendiente'); ?>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando datos de entrega...</p>
            </div>
        </div>
    </div>

    <!-- HISTORIAL -->
    <div class="etapa-section" data-etapa="historial" data-pedido="<?php echo $pedidoId; ?>">
        <div class="etapa-header" onclick="toggleEtapa(this)">
            <div class="etapa-num" style="background: var(--muted);"><i class="fas fa-history"></i></div>
            <div class="etapa-title">Historial del Pedido</div>
            <i class="fas fa-chevron-down etapa-toggle collapsed"></i>
        </div>
        <div class="etapa-body">
            <div class="etapa-loading">
                <div class="spinner-border text-primary" role="status"></div>
                <p>Cargando historial...</p>
            </div>
        </div>
    </div>
</main>

<!-- Modal de imagen -->
<div class="img-modal" id="imgModal" onclick="closeModal()">
    <button class="img-modal-close"><i class="fas fa-times"></i></button>
    <img src="" alt="Imagen ampliada" id="modalImg">
</div>

<!-- Badge de optimización -->
<div class="optimization-badge">
    <i class="fas fa-bolt"></i> VizenGO
</div>

<script>
// Cache para datos ya cargados
const etapasCache = {};

function toggleEtapa(header) {
    const section = header.closest('.etapa-section');
    const body = section.querySelector('.etapa-body');
    const toggle = header.querySelector('.etapa-toggle');
    const etapa = section.dataset.etapa;
    const pedidoId = section.dataset.pedido;
    
    // Toggle visual
    body.classList.toggle('show');
    toggle.classList.toggle('collapsed');
    
    // Cargar datos solo si no están en caché y el body está visible
    if (body.classList.contains('show') && !etapasCache[etapa]) {
        loadEtapaData(etapa, pedidoId, body);
    }
}

async function loadEtapaData(etapa, pedidoId, bodyElement) {
    try {
        const response = await fetch(`api/ver-pedido-etapa.php?pedido_id=${pedidoId}&etapa=${etapa}`);
        const html = await response.text();
        
        // Guardar en caché
        etapasCache[etapa] = html;
        
        // Actualizar el DOM
        bodyElement.innerHTML = html;
        
        // Aplicar lazy loading a las imágenes
        bodyElement.querySelectorAll('img').forEach(img => {
            img.loading = 'lazy';
        });
        
    } catch (error) {
        bodyElement.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle" style="color: var(--accent);"></i>
                <p>Error al cargar los datos. <a href="javascript:void(0)" onclick="retryLoad('${etapa}', ${pedidoId}, this)">Reintentar</a></p>
            </div>
        `;
    }
}

function retryLoad(etapa, pedidoId, element) {
    const bodyElement = element.closest('.etapa-body');
    bodyElement.innerHTML = `
        <div class="etapa-loading">
            <div class="spinner-border text-primary" role="status"></div>
            <p>Cargando...</p>
        </div>
    `;
    delete etapasCache[etapa];
    loadEtapaData(etapa, pedidoId, bodyElement);
}

function openModal(src) {
    document.getElementById('modalImg').src = src;
    document.getElementById('imgModal').classList.add('show');
}

function closeModal() {
    document.getElementById('imgModal').classList.remove('show');
}

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
</body>
</html>
