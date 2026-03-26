<?php
/**
 * VIZENGO - Modificar Pedido
 * Permite a vendedores y administradores modificar pedidos
 * Solo si el pedido no ha llegado a la etapa de planchado
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

// Solo vendedores y administradores pueden acceder
if (!in_array($user['rol'], ['vendedor', 'administrador'])) {
    header('Location: dashboard.php');
    exit();
}

// Validar ID del pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: lista-pedidos.php');
    exit();
}

$pedidoId = intval($_GET['id']);
$db = getDB();

// Obtener datos del pedido
$stmt = $db->prepare("SELECT p.*, c.nombre as cliente_nombre, c.celular as cliente_celular,
                      u.nombre as vendedor_nombre
                      FROM pedidos p
                      LEFT JOIN clientes c ON p.cliente_id = c.id
                      LEFT JOIN usuarios u ON p.usuario_id = u.id
                      WHERE p.id = ?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    header('Location: lista-pedidos.php');
    exit();
}

// Verificar si el pedido es modificable
$modificable = $pedido['estado_planchado'] !== 'completo' && $pedido['estado_general'] !== 'entregado';

// Obtener kits del pedido
$stmt = $db->prepare("SELECT * FROM kits WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
$kits = $stmt->fetchAll();

// Obtener integrantes del pedido
$stmt = $db->prepare("SELECT * FROM integrantes WHERE pedido_id = ? ORDER BY id");
$stmt->execute([$pedidoId]);
$integrantes = $stmt->fetchAll();

// Obtener merchandising del pedido
$stmt = $db->prepare("SELECT * FROM merchandising WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
$merchandising = $stmt->fetchAll();

// Obtener adicionales de talla
$stmt = $db->prepare("SELECT * FROM adicionales_talla WHERE pedido_id = ?");
$stmt->execute([$pedidoId]);
$adicionales = $stmt->fetchAll();

// Obtener historial de modificaciones
$stmt = $db->prepare("SELECT m.*, u.nombre as usuario_nombre, u.rol as usuario_rol
                      FROM modificaciones_pedido m
                      LEFT JOIN usuarios u ON m.usuario_id = u.id
                      WHERE m.pedido_id = ?
                      ORDER BY m.fecha_modificacion DESC");
$stmt->execute([$pedidoId]);
$historialModificaciones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Modificar Pedido <?php echo htmlspecialchars($pedido['codigo']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .alert-modificable {
            background: linear-gradient(135deg, rgba(6, 214, 160, 0.1) 0%, rgba(5, 153, 105, 0.1) 100%);
            border: 1px solid var(--success);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        
        .alert-no-modificable {
            background: linear-gradient(135deg, rgba(239, 71, 111, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            border: 1px solid var(--danger);
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        
        .section-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .section-header {
            background: var(--sidebar-bg);
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--accent);
        }
        
        .section-body {
            padding: 20px;
        }
        
        .item-row {
            background: #fafbff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .item-row:hover {
            border-color: var(--primary);
        }
        
        .item-info {
            flex: 1;
            min-width: 200px;
        }
        
        .item-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .item-details {
            font-size: 0.85rem;
            color: var(--muted);
        }
        
        .item-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-modify {
            background: rgba(43, 79, 255, 0.1);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-modify:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-delete {
            background: rgba(239, 71, 111, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }
        
        .btn-add {
            background: rgba(6, 214, 160, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-add:hover {
            background: var(--success);
            color: white;
        }
        
        .totals-box {
            background: linear-gradient(135deg, var(--sidebar-bg) 0%, #1a2744 100%);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .total-row:last-child {
            border-bottom: none;
        }
        
        .total-label {
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }
        
        .total-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: white;
        }
        
        .total-value.accent {
            color: var(--accent);
        }
        
        .history-item {
            background: #fafbff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 10px;
            display: flex;
            gap: 14px;
        }
        
        .history-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .history-icon.adicion {
            background: rgba(6, 214, 160, 0.15);
            color: var(--success);
        }
        
        .history-icon.disminucion {
            background: rgba(239, 71, 111, 0.15);
            color: var(--danger);
        }
        
        .history-icon.cambio {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }
        
        .history-content {
            flex: 1;
        }
        
        .history-title {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .history-details {
            font-size: 0.85rem;
            color: var(--muted);
        }
        
        .history-date {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--muted);
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.3;
        }
        
        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-overlay.show {
            display: flex;
        }
        
        .modal-content {
            background: var(--surface);
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            background: var(--sidebar-bg);
            padding: 16px 20px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .field-group {
            margin-bottom: 16px;
        }
        
        .field-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        
        .field-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        
        .field-control:focus {
            outline: none;
            border-color: var(--primary);
        }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <!-- Header -->
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-edit" style="color:var(--primary);margin-right:10px;"></i>Modificar Pedido</h1>
            <p><?php echo htmlspecialchars($pedido['codigo']); ?> - <?php echo htmlspecialchars($pedido['cliente_nombre']); ?></p>
        </div>
        <div class="topbar-right">
            <a href="ver-pedido.php?id=<?php echo $pedidoId; ?>" class="btn-outline-action"><i class="fas fa-eye"></i> Ver Pedido</a>
            <a href="lista-pedidos.php" class="btn-outline-action"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php if ($modificable): ?>
    <div class="alert-modificable">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="fas fa-check-circle" style="color:var(--success);font-size:1.5rem;"></i>
            <div>
                <strong style="color:var(--success);">Pedido Modificable</strong>
                <p style="margin:0;font-size:0.9rem;color:var(--muted);">Puede agregar, modificar o eliminar items del pedido. El pedido aún no ha llegado a la etapa de planchado.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert-no-modificable">
        <div style="display:flex;align-items:center;gap:12px;">
            <i class="fas fa-lock" style="color:var(--danger);font-size:1.5rem;"></i>
            <div>
                <strong style="color:var(--danger);">Pedido No Modificable</strong>
                <p style="margin:0;font-size:0.9rem;color:var(--muted);">
                    <?php if ($pedido['estado_planchado'] === 'completo'): ?>
                        El pedido ya completó la etapa de planchado.
                    <?php elseif ($pedido['estado_general'] === 'entregado'): ?>
                        El pedido ya fue entregado.
                    <?php else: ?>
                        El pedido no puede ser modificado.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Columna principal -->
        <div class="col-lg-8">
            <!-- Kits / Proforma -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title"><i class="fas fa-tshirt"></i> Kits / Proforma</div>
                    <?php if ($modificable): ?>
                    <button class="btn-add" onclick="openModal('kit', 'agregar')"><i class="fas fa-plus"></i> Agregar Kit</button>
                    <?php endif; ?>
                </div>
                <div class="section-body">
                    <?php if (empty($kits)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <p>No hay kits registrados</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($kits as $kit): ?>
                    <div class="item-row">
                        <div class="item-info">
                            <div class="item-name"><?php echo htmlspecialchars($kit['camiseta_tipo'] ?: 'Kit'); ?></div>
                            <div class="item-details">
                                <?php echo intval($kit['cantidad']); ?> unidad(es) × S/ <?php echo number_format($kit['precio_unitario'], 2); ?> = 
                                <strong>S/ <?php echo number_format($kit['subtotal'], 2); ?></strong>
                            </div>
                        </div>
                        <?php if ($modificable): ?>
                        <div class="item-actions">
                            <button class="btn-modify" onclick="openModal('kit', 'modificar', <?php echo $kit['id']; ?>)">
                                <i class="fas fa-edit"></i> Modificar
                            </button>
                            <button class="btn-delete" onclick="confirmDelete('kit', <?php echo $kit['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Integrantes -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title"><i class="fas fa-users"></i> Integrantes (<?php echo count($integrantes); ?>)</div>
                    <?php if ($modificable): ?>
                    <button class="btn-add" onclick="openModal('integrante', 'agregar')"><i class="fas fa-plus"></i> Agregar</button>
                    <?php endif; ?>
                </div>
                <div class="section-body">
                    <?php if (empty($integrantes)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No hay integrantes registrados</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($integrantes as $int): ?>
                    <div class="item-row">
                        <div class="item-info">
                            <div class="item-name"><?php echo htmlspecialchars($int['nombre']); ?></div>
                            <div class="item-details">
                                Talla: <?php echo htmlspecialchars($int['talla']); ?> | 
                                N°: <?php echo htmlspecialchars($int['numero']); ?> |
                                <?php echo $int['sexo']; ?>
                            </div>
                        </div>
                        <?php if ($modificable): ?>
                        <div class="item-actions">
                            <button class="btn-modify" onclick="openModal('integrante', 'modificar', <?php echo $int['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-delete" onclick="confirmDelete('integrante', <?php echo $int['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Merchandising -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title"><i class="fas fa-flag"></i> Merchandising</div>
                    <?php if ($modificable): ?>
                    <button class="btn-add" onclick="openModal('merchandising', 'agregar')"><i class="fas fa-plus"></i> Agregar</button>
                    <?php endif; ?>
                </div>
                <div class="section-body">
                    <?php if (empty($merchandising)): ?>
                    <div class="empty-state">
                        <i class="fas fa-flag"></i>
                        <p>No hay merchandising registrado</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($merchandising as $merch): ?>
                    <div class="item-row">
                        <div class="item-info">
                            <div class="item-name">
                                <?php echo htmlspecialchars($merch['articulo']); ?>
                                <?php if ($merch['es_regalo']): ?>
                                <span style="background:rgba(6,214,160,0.15);color:var(--success);padding:2px 8px;border-radius:10px;font-size:0.7rem;margin-left:8px;">REGALO</span>
                                <?php endif; ?>
                            </div>
                            <div class="item-details">
                                <?php echo intval($merch['cantidad']); ?> unidad(es) × S/ <?php echo number_format($merch['precio_unitario'], 2); ?>
                            </div>
                        </div>
                        <?php if ($modificable): ?>
                        <div class="item-actions">
                            <button class="btn-modify" onclick="openModal('merchandising', 'modificar', <?php echo $merch['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-delete" onclick="confirmDelete('merchandising', <?php echo $merch['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Adicionales de Talla -->
            <div class="section-card">
                <div class="section-header">
                    <div class="section-title"><i class="fas fa-expand-arrows-alt"></i> Adicionales Talla Especial</div>
                    <?php if ($modificable): ?>
                    <button class="btn-add" onclick="openModal('adicional', 'agregar')"><i class="fas fa-plus"></i> Agregar</button>
                    <?php endif; ?>
                </div>
                <div class="section-body">
                    <?php if (empty($adicionales)): ?>
                    <div class="empty-state">
                        <i class="fas fa-expand"></i>
                        <p>No hay adicionales de talla especial</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($adicionales as $adic): ?>
                    <div class="item-row">
                        <div class="item-info">
                            <div class="item-name">Talla <?php echo htmlspecialchars($adic['talla']); ?></div>
                            <div class="item-details">
                                <?php echo intval($adic['cantidad']); ?> unidad(es) × S/ <?php echo number_format($adic['precio_unitario'], 2); ?>
                            </div>
                        </div>
                        <?php if ($modificable): ?>
                        <div class="item-actions">
                            <button class="btn-modify" onclick="openModal('adicional', 'modificar', <?php echo $adic['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-delete" onclick="confirmDelete('adicional', <?php echo $adic['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna lateral -->
        <div class="col-lg-4">
            <!-- Totales -->
            <div class="totals-box">
                <h4 style="color:white;margin-bottom:16px;font-family:'Barlow Condensed',sans-serif;">
                    <i class="fas fa-calculator" style="margin-right:8px;color:var(--accent);"></i>Resumen
                </h4>
                <div class="total-row">
                    <span class="total-label">Subtotal</span>
                    <span class="total-value">S/ <?php echo number_format($pedido['subtotal'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span class="total-label">Adelanto</span>
                    <span class="total-value">S/ <?php echo number_format($pedido['adelanto'], 2); ?></span>
                </div>
                <div class="total-row">
                    <span class="total-label">Saldo</span>
                    <span class="total-value accent">S/ <?php echo number_format($pedido['saldo'], 2); ?></span>
                </div>
            </div>

            <!-- Datos Generales -->
            <?php if ($modificable): ?>
            <div class="section-card" style="margin-top:20px;">
                <div class="section-header">
                    <div class="section-title"><i class="fas fa-cog"></i> Datos Generales</div>
                </div>
                <div class="section-body">
                    <button class="btn-modify" style="width:100%;" onclick="openModal('datos_generales', 'modificar')">
                        <i class="fas fa-edit"></i> Modificar Adelanto / Fecha
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Historial de Modificaciones -->
            <div class="section-card" style="margin-top:20px;">
                <div class="section-header">
                    <div class="section-title"><i class="fas fa-history"></i> Historial de Cambios</div>
                </div>
                <div class="section-body">
                    <?php if (empty($historialModificaciones)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>Sin modificaciones registradas</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($historialModificaciones as $h): ?>
                    <div class="history-item">
                        <div class="history-icon <?php echo strtolower($h['tipo_modificacion']); ?>">
                            <i class="fas <?php 
                                echo $h['tipo_modificacion'] === 'ADICION' ? 'fa-plus' : 
                                    ($h['tipo_modificacion'] === 'DISMINUCION' ? 'fa-minus' : 'fa-edit'); 
                            ?>"></i>
                        </div>
                        <div class="history-content">
                            <div class="history-title">
                                <?php echo htmlspecialchars($h['tabla_afectada']); ?> - 
                                <?php echo $h['tipo_modificacion']; ?>
                            </div>
                            <div class="history-details">
                                <?php if ($h['campo_modificado']): ?>
                                <strong><?php echo htmlspecialchars($h['campo_modificado']); ?>:</strong><br>
                                <?php endif; ?>
                                <?php if ($h['valor_anterior'] && $h['valor_nuevo']): ?>
                                <span style="color:var(--danger);text-decoration:line-through;"><?php echo htmlspecialchars($h['valor_anterior']); ?></span>
                                →
                                <span style="color:var(--success);"><?php echo htmlspecialchars($h['valor_nuevo']); ?></span>
                                <?php endif; ?>
                                <?php if ($h['motivo']): ?>
                                <br><em>"<?php echo htmlspecialchars($h['motivo']); ?>"</em>
                                <?php endif; ?>
                            </div>
                            <div class="history-date">
                                <?php echo formatDate($h['fecha_modificacion'], 'd/m/Y H:i'); ?> por 
                                <?php echo htmlspecialchars($h['usuario_nombre']); ?> (<?php echo $h['usuario_rol']; ?>)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal para modificar items -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">Modificar</div>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Contenido dinámico -->
        </div>
        <div class="modal-footer" id="modalFooter">
            <button class="btn-outline-action" onclick="closeModal()">Cancelar</button>
            <button class="btn-success-action" id="btnGuardar" onclick="guardarCambios()">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<script>
let currentType = '';
let currentAction = '';
let currentId = 0;

// Datos del pedido
const pedidoId = <?php echo $pedidoId; ?>;
const modificable = <?php echo $modificable ? 'true' : 'false'; ?>;

// Datos actuales para edición
const kitsData = <?php echo json_encode($kits); ?>;
const integrantesData = <?php echo json_encode($integrantes); ?>;
const merchandisingData = <?php echo json_encode($merchandising); ?>;
const adicionalesData = <?php echo json_encode($adicionales); ?>;

function openModal(type, action, id = 0) {
    if (!modificable) {
        alert('Este pedido no puede ser modificado.');
        return;
    }
    
    currentType = type;
    currentAction = action;
    currentId = id;
    
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    let title = '';
    let body = '';
    
    switch(type) {
        case 'kit':
            title = action === 'agregar' ? 'Agregar Kit' : 'Modificar Kit';
            body = getKitForm(action, id);
            break;
        case 'integrante':
            title = action === 'agregar' ? 'Agregar Integrante' : 'Modificar Integrante';
            body = getIntegranteForm(action, id);
            break;
        case 'merchandising':
            title = action === 'agregar' ? 'Agregar Merchandising' : 'Modificar Merchandising';
            body = getMerchandisingForm(action, id);
            break;
        case 'adicional':
            title = action === 'agregar' ? 'Agregar Adicional' : 'Modificar Adicional';
            body = getAdicionalForm(action, id);
            break;
        case 'datos_generales':
            title = 'Modificar Datos Generales';
            body = getDatosGeneralesForm();
            break;
    }
    
    modalTitle.textContent = title;
    modalBody.innerHTML = body;
    document.getElementById('modalOverlay').classList.add('show');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('show');
}

function getKitForm(action, id) {
    let kit = action === 'modificar' ? kitsData.find(k => k.id == id) : {};
    
    return `
        <div class="field-group">
            <label class="field-label">Camiseta / Tipo</label>
            <select class="field-control" id="kitCamisetaTipo">
                <option value="CAMISETA MANGA CORTA" ${kit.camiseta_tipo === 'CAMISETA MANGA CORTA' ? 'selected' : ''}>CAMISETA MANGA CORTA</option>
                <option value="CAMISETA MANGA LARGA" ${kit.camiseta_tipo === 'CAMISETA MANGA LARGA' ? 'selected' : ''}>CAMISETA MANGA LARGA</option>
                <option value="POLO PUBLICITARIO" ${kit.camiseta_tipo === 'POLO PUBLICITARIO' ? 'selected' : ''}>POLO PUBLICITARIO</option>
                <option value="SUDADERA" ${kit.camiseta_tipo === 'SUDADERA' ? 'selected' : ''}>SUDADERA</option>
                <option value="OTROS" ${kit.camiseta_tipo === 'OTROS' ? 'selected' : ''}>OTROS</option>
            </select>
        </div>
        <div class="field-group">
            <label class="field-label">Cantidad</label>
            <input type="number" class="field-control" id="kitCantidad" value="${kit.cantidad || 1}" min="1">
        </div>
        <div class="field-group">
            <label class="field-label">Precio Unitario (S/)</label>
            <input type="number" class="field-control" id="kitPrecio" value="${kit.precio_unitario || '35.00'}" step="0.01">
        </div>
        <div class="field-group">
            <label class="field-label">Motivo del cambio</label>
            <textarea class="field-control" id="kitMotivo" rows="2" placeholder="Describa el motivo del cambio..."></textarea>
        </div>
    `;
}

function getIntegranteForm(action, id) {
    let int = action === 'modificar' ? integrantesData.find(i => i.id == id) : {};
    
    return `
        <div class="field-group">
            <label class="field-label">Nombre</label>
            <input type="text" class="field-control" id="intNombre" value="${int.nombre || ''}" placeholder="Nombre del integrante">
        </div>
        <div class="row">
            <div class="col-6">
                <div class="field-group">
                    <label class="field-label">Talla</label>
                    <input type="text" class="field-control" id="intTalla" value="${int.talla || ''}" placeholder="M, L, XL...">
                </div>
            </div>
            <div class="col-6">
                <div class="field-group">
                    <label class="field-label">Número</label>
                    <input type="text" class="field-control" id="intNumero" value="${int.numero || ''}" placeholder="10, 7...">
                </div>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Sexo</label>
            <select class="field-control" id="intSexo">
                <option value="Varon" ${int.sexo === 'Varon' ? 'selected' : ''}>Varón</option>
                <option value="Dama" ${int.sexo === 'Dama' ? 'selected' : ''}>Dama</option>
            </select>
        </div>
        <div class="field-group">
            <label class="field-label">Motivo del cambio</label>
            <textarea class="field-control" id="intMotivo" rows="2" placeholder="Describa el motivo del cambio..."></textarea>
        </div>
    `;
}

function getMerchandisingForm(action, id) {
    let merch = action === 'modificar' ? merchandisingData.find(m => m.id == id) : {};
    
    return `
        <div class="field-group">
            <label class="field-label">Artículo</label>
            <select class="field-control" id="merchArticulo">
                <option value="BANDEROLA" ${merch.articulo === 'BANDEROLA' ? 'selected' : ''}>BANDEROLA</option>
                <option value="BANDERA" ${merch.articulo === 'BANDERA' ? 'selected' : ''}>BANDERA</option>
                <option value="GORRO" ${merch.articulo === 'GORRO' ? 'selected' : ''}>GORRO</option>
                <option value="SOMBRERO" ${merch.articulo === 'SOMBRERO' ? 'selected' : ''}>SOMBRERO</option>
                <option value="OTROS" ${merch.articulo === 'OTROS' ? 'selected' : ''}>OTROS</option>
            </select>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="field-group">
                    <label class="field-label">Cantidad</label>
                    <input type="number" class="field-control" id="merchCantidad" value="${merch.cantidad || 1}" min="1">
                </div>
            </div>
            <div class="col-6">
                <div class="field-group">
                    <label class="field-label">Precio (S/)</label>
                    <input type="number" class="field-control" id="merchPrecio" value="${merch.precio_unitario || '0.00'}" step="0.01">
                </div>
            </div>
        </div>
        <div class="field-group">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" id="merchRegalo" ${merch.es_regalo ? 'checked' : ''}>
                <span>Es un regalo (no suma al total)</span>
            </label>
        </div>
        <div class="field-group">
            <label class="field-label">Motivo del cambio</label>
            <textarea class="field-control" id="merchMotivo" rows="2" placeholder="Describa el motivo del cambio..."></textarea>
        </div>
    `;
}

function getAdicionalForm(action, id) {
    let adic = action === 'modificar' ? adicionalesData.find(a => a.id == id) : {};
    
    return `
        <div class="field-group">
            <label class="field-label">Talla Especial</label>
            <select class="field-control" id="adicTalla">
                <option value="XL" ${adic.talla === 'XL' ? 'selected' : ''}>XL</option>
                <option value="XXL" ${adic.talla === 'XXL' ? 'selected' : ''}>XXL</option>
                <option value="XXXL" ${adic.talla === 'XXXL' ? 'selected' : ''}>XXXL</option>
            </select>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="field-group">
                    <label class="field-label">Cantidad</label>
                    <input type="number" class="field-control" id="adicCantidad" value="${adic.cantidad || 1}" min="1">
                </div>
            </div>
            <div class="col-6">
                <div class="field-group">
                    <label class="field-label">Precio Adicional (S/)</label>
                    <input type="number" class="field-control" id="adicPrecio" value="${adic.precio_unitario || '0.00'}" step="0.01">
                </div>
            </div>
        </div>
        <div class="field-group">
            <label class="field-label">Motivo del cambio</label>
            <textarea class="field-control" id="adicMotivo" rows="2" placeholder="Describa el motivo del cambio..."></textarea>
        </div>
    `;
}

function getDatosGeneralesForm() {
    return `
        <div class="field-group">
            <label class="field-label">Adelanto Adicional (S/)</label>
            <input type="number" class="field-control" id="datosAdelanto" value="<?php echo $pedido['adelanto']; ?>" step="0.01">
        </div>
        <div class="field-group">
            <label class="field-label">Motivo del cambio</label>
            <textarea class="field-control" id="datosMotivo" rows="2" placeholder="Describa el motivo del cambio..."></textarea>
        </div>
    `;
}

async function guardarCambios() {
    // Validar que las variables globales estén definidas
    if (typeof pedidoId === 'undefined' || !pedidoId) {
        alert('Error: No se pudo obtener el ID del pedido. Recargue la página.');
        return;
    }
    
    if (!currentType) {
        alert('Error: No se pudo determinar el tipo de modificación.');
        return;
    }
    
    if (!currentAction) {
        alert('Error: No se pudo determinar la acción a realizar.');
        return;
    }
    
    let data = {
        pedido_id: pedidoId,
        accion: currentAction
    };
    
    try {
        switch(currentType) {
            case 'kit':
                data.camiseta_tipo = document.getElementById('kitCamisetaTipo').value;
                data.cantidad = parseInt(document.getElementById('kitCantidad').value) || 1;
                data.precio_unitario = parseFloat(document.getElementById('kitPrecio').value) || 0;
                data.motivo = document.getElementById('kitMotivo').value || '';
                if (currentAction === 'modificar') data.kit_id = currentId;
                break;
            case 'integrante':
                data.nombre = document.getElementById('intNombre').value || '';
                data.talla = document.getElementById('intTalla').value || '';
                data.numero = document.getElementById('intNumero').value || '';
                data.sexo = document.getElementById('intSexo').value || 'Varon';
                data.motivo = document.getElementById('intMotivo').value || '';
                if (currentAction === 'modificar') data.integrante_id = currentId;
                break;
            case 'merchandising':
                data.articulo = document.getElementById('merchArticulo').value || '';
                data.cantidad = parseInt(document.getElementById('merchCantidad').value) || 1;
                data.precio_unitario = parseFloat(document.getElementById('merchPrecio').value) || 0;
                data.es_regalo = document.getElementById('merchRegalo').checked ? 1 : 0;
                data.motivo = document.getElementById('merchMotivo').value || '';
                if (currentAction === 'modificar') data.merchandising_id = currentId;
                break;
            case 'adicional':
                data.talla = document.getElementById('adicTalla').value || '';
                data.cantidad = parseInt(document.getElementById('adicCantidad').value) || 1;
                data.precio_unitario = parseFloat(document.getElementById('adicPrecio').value) || 0;
                data.motivo = document.getElementById('adicMotivo').value || '';
                if (currentAction === 'modificar') data.adicional_id = currentId;
                break;
            case 'datos_generales':
                data.adelanto = parseFloat(document.getElementById('datosAdelanto').value) || 0;
                data.motivo = document.getElementById('datosMotivo').value || '';
                break;
            default:
                alert('Tipo de modificación no reconocido: ' + currentType);
                return;
        }
        
        // Debug: Mostrar datos que se enviarán (descomentar para debug)
        console.log('Enviando datos:', data);
        
        const endpoint = currentType === 'datos_generales' ? 'datos_generales' : currentType;
        const url = `api/pedidos.php?action=modificar&sub=${endpoint}`;
        
        console.log('URL de petición:', url);
        
        const response = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        // Verificar si la respuesta HTTP es válida
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Error HTTP:', response.status, errorText);
            try {
                const errorJson = JSON.parse(errorText);
                alert(errorJson.error || `Error del servidor (${response.status})`);
            } catch (e) {
                alert(`Error del servidor (${response.status}): ${errorText}`);
            }
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message || 'Cambios guardados correctamente');
            location.reload();
        } else {
            alert(result.error || 'Error al guardar los cambios');
        }
    } catch (error) {
        console.error('Error en guardarCambios:', error);
        alert('Error de conexión: ' + error.message);
    }
}

async function confirmDelete(type, id) {
    if (!modificable) {
        alert('Este pedido no puede ser modificado.');
        return;
    }
    
    const motivo = prompt('Ingrese el motivo de la eliminación:');
    if (motivo === null) return;
    
    if (!confirm('¿Está seguro de eliminar este item?')) return;
    
    let data = {
        pedido_id: pedidoId,
        accion: 'eliminar',
        motivo: motivo
    };
    
    switch(type) {
        case 'kit':
            data.kit_id = id;
            break;
        case 'integrante':
            data.integrante_id = id;
            break;
        case 'merchandising':
            data.merchandising_id = id;
            break;
        case 'adicional':
            data.adicional_id = id;
            break;
    }
    
    try {
        const response = await fetch(`api/pedidos.php?action=modificar&sub=${type}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message || 'Item eliminado correctamente');
            location.reload();
        } else {
            alert(result.error || 'Error al eliminar');
        }
    } catch (error) {
        alert('Error de conexión: ' + error.message);
    }
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});
</script>
</body>
</html>
