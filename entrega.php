<?php
/**
 * VIZENGO - Registro de Entrega
 * Paso F: Finalizar pedido y registrar entrega al cliente
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

$db = getDB();

// Obtener pedidos listos para entrega
$stmt = $db->query("SELECT p.id, p.codigo, p.tipo_contrato, p.lugar_entrega, p.direccion_envio,
                    p.subtotal, p.adelanto, p.saldo, p.fecha_entrega,
                    c.nombre as cliente, c.celular as cliente_celular,
                    u.nombre as vendedor,
                    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes,
                    pl.planchador_nombre, co.costurero_nombre
                    FROM pedidos p 
                    LEFT JOIN clientes c ON p.cliente_id = c.id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    LEFT JOIN planchado pl ON pl.pedido_id = p.id
                    LEFT JOIN costura co ON co.pedido_id = p.id
                    WHERE p.estado_general = 'listo_entrega'
                    ORDER BY p.fecha_entrega ASC");
$pedidosListos = $stmt->fetchAll();

// Procesar entrega
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'entregar') {
    try {
        $pedidoId = intval($_POST['pedido_id'] ?? 0);
        
        if ($pedidoId <= 0) {
            throw new Exception('ID de pedido no válido');
        }
        
        $db->beginTransaction();
        
        // Verificar que el pedido está listo para entrega
        $stmt = $db->prepare("SELECT p.*, c.nombre as cliente FROM pedidos p 
                              LEFT JOIN clientes c ON p.cliente_id = c.id
                              WHERE p.id = ? AND p.estado_general = 'listo_entrega'");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch();
        
        if (!$pedido) {
            throw new Exception('El pedido no está listo para entrega o no existe');
        }
        
        // Datos de entrega
        $esEnvio = intval($_POST['es_envio'] ?? 0);
        $costoEnvio = floatval($_POST['costo_envio'] ?? 0);
        $totalCobrado = floatval($pedido['saldo']) + $costoEnvio;
        
        // Insertar registro de entrega
        $stmt = $db->prepare("INSERT INTO entregas (
            pedido_id, usuario_id, lugar_entrega, es_envio, direccion_envio,
            costo_envio, total_cobrado, observaciones, fecha_entrega
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $pedidoId,
            $user['id'],
            sanitize($_POST['lugar_entrega'] ?? $pedido['lugar_entrega']),
            $esEnvio,
            sanitize($_POST['direccion_envio'] ?? $pedido['direccion_envio']),
            $costoEnvio,
            $totalCobrado,
            sanitize($_POST['observaciones'] ?? '')
        ]);
        
        // Actualizar estado del pedido
        $stmt = $db->prepare("UPDATE pedidos SET estado_general = 'entregado', fecha_completado = NOW() WHERE id = ?");
        $stmt->execute([$pedidoId]);
        
        // Log de actividad
        logActivity($pedidoId, $user['id'], 'PEDIDO_ENTREGADO', "Pedido entregado al cliente {$pedido['cliente']}");
        
        $db->commit();
        
        $mensaje = "Pedido {$pedido['codigo']} entregado exitosamente a {$pedido['cliente']}";
        $tipoMensaje = 'success';
        
        // Recargar lista de pedidos
        $stmt = $db->query("SELECT p.id, p.codigo, p.tipo_contrato, p.lugar_entrega, p.direccion_envio,
                            p.subtotal, p.adelanto, p.saldo, p.fecha_entrega,
                            c.nombre as cliente, c.celular as cliente_celular,
                            u.nombre as vendedor,
                            (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes,
                            pl.planchador_nombre, co.costurero_nombre
                            FROM pedidos p 
                            LEFT JOIN clientes c ON p.cliente_id = c.id
                            LEFT JOIN usuarios u ON p.usuario_id = u.id
                            LEFT JOIN planchado pl ON pl.pedido_id = p.id
                            LEFT JOIN costura co ON co.pedido_id = p.id
                            WHERE p.estado_general = 'listo_entrega'
                            ORDER BY p.fecha_entrega ASC");
        $pedidosListos = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $db->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipoMensaje = 'danger';
    }
}

// Obtener datos de un pedido específico via AJAX
if (isset($_GET['get_pedido']) && $_GET['get_pedido'] > 0) {
    $pedidoId = intval($_GET['get_pedido']);
    
    $stmt = $db->prepare("SELECT p.*, c.nombre as cliente, c.celular as cliente_celular,
                          u.nombre as vendedor,
                          (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes,
                          pl.planchador_nombre, pl.cant_polos as pl_polos, pl.cant_shorts as pl_shorts,
                          co.costurero_nombre, co.cant_polos as co_polos, co.cant_shorts as co_shorts
                          FROM pedidos p 
                          LEFT JOIN clientes c ON p.cliente_id = c.id
                          LEFT JOIN usuarios u ON p.usuario_id = u.id
                          LEFT JOIN planchado pl ON pl.pedido_id = p.id
                          LEFT JOIN costura co ON co.pedido_id = p.id
                          WHERE p.id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    
    if ($pedido) {
        // Obtener integrantes
        $stmt = $db->prepare("SELECT talla, COUNT(*) as cantidad FROM integrantes WHERE pedido_id = ? GROUP BY talla");
        $stmt->execute([$pedidoId]);
        $tallas = $stmt->fetchAll();
        $pedido['tallas'] = $tallas;
        
        // Obtener kits
        $stmt = $db->prepare("SELECT * FROM kits WHERE pedido_id = ?");
        $stmt->execute([$pedidoId]);
        $pedido['kits'] = $stmt->fetchAll();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'pedido' => $pedido]);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
    exit();
}

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
    <title>VIZENGO - Registro de Entrega</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .badge-status{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase;}
        .badge-listo{background:rgba(6,214,160,.12);color:#059669;}
        .badge-envio{background:rgba(245,158,11,.12);color:#d97706;}
        .badge-tienda{background:rgba(43,79,255,.12);color:var(--primary);}
        
        .info-row{display:flex;gap:16px;padding:10px 0;border-bottom:1px solid var(--border);}
        .info-row:last-child{border-bottom:none;}
        .info-key{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);min-width:120px;}
        .info-val{font-size:.88rem;font-weight:600;color:var(--text);}
        
        .resumen-box{background:linear-gradient(135deg,rgba(6,214,160,.05),rgba(6,214,160,.1));border:1px solid rgba(6,214,160,.2);border-radius:12px;padding:20px;}
        .resumen-title{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:800;text-transform:uppercase;color:var(--success);margin-bottom:16px;}
        .resumen-title i{margin-right:8px;}
        
        .tabla-resumen{width:100%;border-collapse:collapse;}
        .tabla-resumen th{background:var(--bg);padding:10px 12px;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);border-bottom:2px solid var(--border);}
        .tabla-resumen td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:.85rem;}
        .tabla-resumen tr:last-child td{border-bottom:none;}
        .tabla-resumen tr:hover{background:rgba(6,214,160,.03);}
        
        .envio-card{background:linear-gradient(135deg,rgba(245,158,11,.05),rgba(245,158,11,.1));border:1px solid rgba(245,158,11,.25);border-radius:12px;padding:16px;margin-top:16px;}
        .envio-title{font-family:'Barlow Condensed',sans-serif;font-size:.85rem;font-weight:800;text-transform:uppercase;color:var(--warning);margin-bottom:12px;}
        .envio-title i{margin-right:6px;}
        
        .total-final-box{background:var(--sidebar-bg);border-radius:14px;padding:24px;color:white;}
        .total-final-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.4);margin-bottom:8px;}
        .total-final-amount{font-family:'Barlow Condensed',sans-serif;font-size:2.5rem;font-weight:800;color:var(--accent);line-height:1;}
        .total-final-detalle{font-size:.82rem;color:rgba(255,255,255,.4);margin-top:8px;}
        
        .timeline-etapas{display:flex;flex-direction:column;gap:8px;}
        .etapa-item{display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg);border-radius:8px;}
        .etapa-icon{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;}
        .etapa-icon.done{background:var(--success);color:white;}
        .etapa-icon.pending{background:var(--border);color:var(--muted);}
        .etapa-info{flex:1;}
        .etapa-nombre{font-size:.8rem;font-weight:700;color:var(--text);}
        .etapa-fecha{font-size:.7rem;color:var(--muted);}
        .etapa-encargado{font-size:.72rem;color:var(--success);font-weight:600;}
        
        .pedido-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;cursor:pointer;transition:all .2s;}
        .pedido-card:hover{border-color:var(--primary);box-shadow:0 4px 12px rgba(43,79,255,.1);}
        .pedido-card.selected{border-color:var(--success);background:rgba(6,214,160,.03);}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-box" style="color:var(--success);margin-right:10px;"></i>Registro de Entrega</h1>
            <p>Paso F: Finaliza el pedido y registra la entrega al cliente</p>
        </div>
        <div class="topbar-right">
            <a href="lista-pedidos.php" class="btn-outline-action"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?php echo $tipoMensaje; ?> alert-dismissible fade show" role="alert">
        <?php echo $mensaje; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Pipeline - 6 Etapas -->
    <div class="pipeline-tracker">
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Contrato</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Integrantes</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Diseño</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Planchado</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Costura</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step active"><div class="pt-circle"><i class="fas fa-box"></i></div><div class="pt-label">Entrega</div></div>
    </div>

    <div class="row g-4">
        <!-- FORMULARIO PRINCIPAL -->
        <div class="col-lg-8">
            <!-- Selector de pedido -->
            <div class="card-v">
                <div class="card-v-header">
                    <h5 class="card-v-title"><i class="fas fa-file-contract" style="margin-right:8px;"></i>Pedidos Listos para Entrega (<?php echo count($pedidosListos); ?>)</h5>
                </div>
                <div class="card-v-body">
                    <?php if (empty($pedidosListos)): ?>
                    <div style="text-align:center;padding:30px;color:var(--muted);">
                        <i class="fas fa-check-circle" style="font-size:3rem;display:block;margin-bottom:16px;color:var(--success);"></i>
                        <p style="font-size:1.1rem;font-weight:600;">¡No hay pedidos pendientes de entrega!</p>
                        <p style="font-size:.85rem;">Todos los pedidos han sido entregados o están en proceso.</p>
                    </div>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($pedidosListos as $p): ?>
                        <div class="col-md-6">
                            <div class="pedido-card" onclick="seleccionarPedido(<?php echo $p['id']; ?>)" data-pedido-id="<?php echo $p['id']; ?>">
                                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px;">
                                    <div>
                                        <span style="font-family:'Barlow Condensed',sans-serif;font-size:.8rem;font-weight:700;color:var(--primary);"><?php echo $p['codigo']; ?></span>
                                        <div style="font-size:1rem;font-weight:700;color:var(--text);"><?php echo htmlspecialchars($p['cliente']); ?></div>
                                    </div>
                                    <?php if ($p['lugar_entrega'] === 'ENVÍO'): ?>
                                    <span class="badge-status badge-envio"><i class="fas fa-truck"></i> ENVÍO</span>
                                    <?php else: ?>
                                    <span class="badge-status badge-tienda"><i class="fas fa-store"></i> TIENDA</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;gap:16px;font-size:.78rem;color:var(--muted);">
                                    <span><i class="fas fa-tshirt"></i> <?php echo $p['total_integrantes']; ?> prendas</span>
                                    <span><i class="fas fa-calendar"></i> <?php echo formatDate($p['fecha_entrega'], 'd/m'); ?></span>
                                </div>
                                <div style="display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:1px solid var(--border);">
                                    <span style="font-size:.75rem;color:var(--muted);">Saldo:</span>
                                    <span style="font-weight:700;color:var(--success);">S/ <?php echo number_format($p['saldo'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detalles del pedido seleccionado -->
            <div id="detallePedidoContainer" style="display:none;">
                <!-- Resumen del Pedido -->
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title"><i class="fas fa-clipboard-list" style="margin-right:8px;"></i>Resumen del Pedido</h5>
                    </div>
                    <div class="card-v-body">
                        <div id="resumenPedidoBox">
                            <div style="text-align:center;padding:30px;color:var(--muted);font-size:.85rem;font-style:italic;">
                                <i class="fas fa-hand-pointer" style="font-size:1.8rem;display:block;margin-bottom:10px;opacity:.3;"></i>
                                Selecciona un pedido para ver el resumen
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle de Prendas -->
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title"><i class="fas fa-tshirt" style="margin-right:8px;"></i>Detalle de Prendas</h5>
                    </div>
                    <div class="card-v-body">
                        <div id="detallePrendasBox">
                            <div style="text-align:center;padding:20px;color:var(--muted);font-size:.85rem;font-style:italic;">
                                Selecciona un pedido para ver el detalle
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Costos y Envío -->
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title"><i class="fas fa-calculator" style="margin-right:8px;"></i>Costos Finales</h5>
                    </div>
                    <div class="card-v-body">
                        <div id="costosBox">
                            <div style="text-align:center;padding:20px;color:var(--muted);font-size:.85rem;font-style:italic;">
                                Selecciona un pedido para ver los costos
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de entrega -->
                <form method="POST" id="formEntrega">
                    <input type="hidden" name="accion" value="entregar">
                    <input type="hidden" name="pedido_id" id="formPedidoId">
                    <input type="hidden" name="lugar_entrega" id="formLugarEntrega">
                    <input type="hidden" name="es_envio" id="formEsEnvio" value="0">
                    <input type="hidden" name="direccion_envio" id="formDireccionEnvio">
                    <input type="hidden" name="costo_envio" id="formCostoEnvio" value="0">
                    <input type="hidden" name="total_cobrado" id="formTotalCobrado">
                    
                    <div id="envioFields" style="display:none;">
                        <div class="card-v">
                            <div class="card-v-header">
                                <h5 class="card-v-title" style="color:var(--warning);"><i class="fas fa-truck" style="margin-right:8px;"></i>Datos de Envío</h5>
                            </div>
                            <div class="card-v-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="field-group">
                                            <label class="field-lbl">Dirección de Envío</label>
                                            <input type="text" class="field-ctrl" id="inputDireccionEnvio" placeholder="Dirección completa...">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="field-group">
                                            <label class="field-lbl">Costo de Envío (S/)</label>
                                            <input type="number" class="field-ctrl" id="inputCostoEnvio" value="0" step="0.10" min="0" oninput="actualizarTotal()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-v">
                        <div class="card-v-body">
                            <div class="field-group" style="margin-bottom:0;">
                                <label class="field-lbl">Observaciones de Entrega</label>
                                <textarea name="observaciones" class="field-ctrl" rows="2" placeholder="Notas sobre la entrega..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:28px;">
                        <button type="button" class="btn-outline-action" onclick="cancelarSeleccion()">Cancelar</button>
                        <button type="submit" class="btn-success-action">
                            <i class="fas fa-check-circle"></i> Confirmar Entrega
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PANEL LATERAL -->
        <div class="col-lg-4">
            <!-- Info del pedido -->
            <div class="card-v" style="position:sticky;top:20px;">
                <div class="card-v-header" style="background:linear-gradient(to right,#fafbff,#fff);">
                    <h5 class="card-v-title" style="color:var(--muted);"><i class="fas fa-info-circle" style="margin-right:8px;"></i>Info del Pedido</h5>
                </div>
                <div class="card-v-body">
                    <div id="infoPedidoBox">
                        <div style="text-align:center;padding:20px;color:var(--muted);font-size:.85rem;font-style:italic;">
                            <i class="fas fa-hand-pointer" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:.3;"></i>
                            Selecciona un pedido
                        </div>
                    </div>
                </div>

                <!-- Timeline de etapas -->
                <div class="card-v-header" style="background:linear-gradient(to right,#fafbff,#fff);">
                    <h5 class="card-v-title" style="color:var(--muted);"><i class="fas fa-history" style="margin-right:8px;"></i>Historial del Pedido</h5>
                </div>
                <div class="card-v-body">
                    <div class="timeline-etapas" id="timelineEtapas">
                        <div class="etapa-item">
                            <div class="etapa-icon done"><i class="fas fa-check"></i></div>
                            <div class="etapa-info">
                                <div class="etapa-nombre">Contrato</div>
                                <div class="etapa-fecha">Registrado</div>
                            </div>
                        </div>
                        <div class="etapa-item">
                            <div class="etapa-icon done"><i class="fas fa-check"></i></div>
                            <div class="etapa-info">
                                <div class="etapa-nombre">Integrantes</div>
                                <div class="etapa-fecha">Cargados</div>
                            </div>
                        </div>
                        <div class="etapa-item">
                            <div class="etapa-icon done"><i class="fas fa-check"></i></div>
                            <div class="etapa-info">
                                <div class="etapa-nombre">Diseño</div>
                                <div class="etapa-encargado">Aprobado</div>
                            </div>
                        </div>
                        <div class="etapa-item">
                            <div class="etapa-icon done"><i class="fas fa-check"></i></div>
                            <div class="etapa-info">
                                <div class="etapa-nombre">Planchado</div>
                                <div class="etapa-encargado" id="etapaPlanchador">—</div>
                            </div>
                        </div>
                        <div class="etapa-item">
                            <div class="etapa-icon done"><i class="fas fa-check"></i></div>
                            <div class="etapa-info">
                                <div class="etapa-nombre">Costura</div>
                                <div class="etapa-encargado" id="etapaCosturero">—</div>
                            </div>
                        </div>
                        <div class="etapa-item" style="background:rgba(6,214,160,.1);border:1px solid rgba(6,214,160,.2);">
                            <div class="etapa-icon done"><i class="fas fa-box"></i></div>
                            <div class="etapa-info">
                                <div class="etapa-nombre" style="color:var(--success);">Entrega</div>
                                <div class="etapa-fecha" style="color:var(--success);">Pendiente de entrega</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total final -->
                <div class="card-v-body" style="padding:0;">
                    <div class="total-final-box" style="border-radius:0 0 14px 14px;">
                        <div class="total-final-label">Total a Cobrar</div>
                        <div class="total-final-amount" id="totalFinal">S/ 0.00</div>
                        <div class="total-final-detalle" id="totalFinalDetalle">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let pedidoSeleccionado = null;
let costoEnvio = 0;

function seleccionarPedido(id) {
    // Remover selección anterior
    document.querySelectorAll('.pedido-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Marcar como seleccionado
    document.querySelector(`.pedido-card[data-pedido-id="${id}"]`).classList.add('selected');
    
    // Obtener datos del pedido
    fetch(`entrega.php?get_pedido=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                pedidoSeleccionado = data.pedido;
                mostrarDetallePedido();
                
                // Mostrar formulario
                document.getElementById('detallePedidoContainer').style.display = 'block';
                document.getElementById('formPedidoId').value = id;
            } else {
                alert('Error al obtener datos del pedido');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error de conexión');
        });
}

function mostrarDetallePedido() {
    const p = pedidoSeleccionado;
    
    // Actualizar campos del formulario
    document.getElementById('formLugarEntrega').value = p.lugar_entrega;
    document.getElementById('formEsEnvio').value = p.lugar_entrega === 'ENVÍO' ? 1 : 0;
    
    // Mostrar campos de envío si corresponde
    if (p.lugar_entrega === 'ENVÍO') {
        document.getElementById('envioFields').style.display = 'block';
        document.getElementById('inputDireccionEnvio').value = p.direccion_envio || '';
    } else {
        document.getElementById('envioFields').style.display = 'none';
    }
    
    // Resumen del pedido
    document.getElementById('resumenPedidoBox').innerHTML = `
        <div class="resumen-box">
            <div class="resumen-title"><i class="fas fa-file-alt"></i>Información del Contrato</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-key">Código</span>
                        <span class="info-val">${p.codigo}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key">Cliente</span>
                        <span class="info-val">${p.cliente}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key">Celular</span>
                        <span class="info-val">${p.cliente_celular || '—'}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-row">
                        <span class="info-key">Tipo Contrato</span>
                        <span class="info-val" style="color:var(--info);">${p.tipo_contrato}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-key">Lugar Entrega</span>
                        <span class="info-val">${p.lugar_entrega}</span>
                    </div>
                    ${p.lugar_entrega === 'ENVÍO' ? `
                    <div class="info-row">
                        <span class="info-key">Dirección</span>
                        <span class="info-val">${p.direccion_envio || 'Por confirmar'}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;
    
    // Detalle de prendas
    let tallasHTML = '';
    if (p.tallas && p.tallas.length > 0) {
        tallasHTML = p.tallas.map(t => `${t.talla}:${t.cantidad}`).join(' ');
    }
    
    const totalPrendas = (parseInt(p.pl_polos) || 0) + (parseInt(p.pl_shorts) || 0);
    
    document.getElementById('detallePrendasBox').innerHTML = `
        <table class="tabla-resumen">
            <thead>
                <tr>
                    <th>Tipo de Prenda</th>
                    <th style="text-align:center;">Cantidad</th>
                    <th style="text-align:center;">Tallas</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <i class="fas fa-tshirt" style="color:var(--info);"></i>
                            Polos / Camisetas
                        </span>
                    </td>
                    <td style="text-align:center;font-weight:700;">${p.pl_polos || p.total_integrantes}</td>
                    <td style="text-align:center;">
                        <span style="font-size:.75rem;color:var(--muted);">${tallasHTML || '—'}</span>
                    </td>
                </tr>
                ${p.pl_shorts > 0 ? `
                <tr>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:8px;">
                            <i class="fas fa-running" style="color:var(--success);"></i>
                            Shorts
                        </span>
                    </td>
                    <td style="text-align:center;font-weight:700;">${p.pl_shorts}</td>
                    <td style="text-align:center;">—</td>
                </tr>
                ` : ''}
            </tbody>
            <tfoot style="background:var(--bg);">
                <tr>
                    <td style="font-weight:700;">TOTAL PRENDAS</td>
                    <td style="text-align:center;font-weight:800;font-size:1.1rem;color:var(--success);">${totalPrendas || p.total_integrantes}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    `;
    
    // Costos
    let envioHTML = '';
    if (p.lugar_entrega === 'ENVÍO') {
        envioHTML = `
            <div class="envio-card">
                <div class="envio-title"><i class="fas fa-truck"></i>Costo de Envío</div>
                <div style="font-size:.85rem;color:var(--muted);">
                    El costo de envío se suma al saldo pendiente.
                </div>
            </div>
        `;
    }
    
    document.getElementById('costosBox').innerHTML = `
        <div class="row g-3">
            <div class="col-md-4">
                <div style="background:var(--bg);border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Subtotal</div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--text);">S/ ${parseFloat(p.subtotal).toFixed(2)}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:rgba(6,214,160,.08);border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Adelanto</div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--success);">S/ ${parseFloat(p.adelanto).toFixed(2)}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:rgba(239,71,111,.08);border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Saldo Pendiente</div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--danger);">S/ ${parseFloat(p.saldo).toFixed(2)}</div>
                </div>
            </div>
        </div>
        ${envioHTML}
    `;
    
    // Info lateral
    document.getElementById('infoPedidoBox').innerHTML = `
        <div class="info-row"><span class="info-key">Pedido</span><span class="info-val">${p.codigo}</span></div>
        <div class="info-row"><span class="info-key">Cliente</span><span class="info-val">${p.cliente}</span></div>
        <div class="info-row"><span class="info-key">Prendas</span><span class="info-val">${totalPrendas || p.total_integrantes}</span></div>
        <div class="info-row"><span class="info-key">Vendedor</span><span class="info-val">${p.vendedor}</span></div>
        <div class="info-row"><span class="info-key">Entrega</span><span class="info-val" style="color:var(--warning);">${p.lugar_entrega}</span></div>
    `;
    
    // Timeline
    document.getElementById('etapaPlanchador').textContent = p.planchador_nombre ? `Por: ${p.planchador_nombre}` : 'Completado';
    document.getElementById('etapaCosturero').textContent = p.costurero_nombre ? `Por: ${p.costurero_nombre}` : 'Completado';
    
    // Total
    actualizarTotal();
}

function actualizarTotal() {
    if (!pedidoSeleccionado) return;
    
    let total = parseFloat(pedidoSeleccionado.saldo);
    let detalle = `Saldo: S/ ${total.toFixed(2)}`;
    
    if (pedidoSeleccionado.lugar_entrega === 'ENVÍO') {
        const envioInput = document.getElementById('inputCostoEnvio');
        if (envioInput) {
            costoEnvio = parseFloat(envioInput.value) || 0;
            total += costoEnvio;
            detalle += ` + Envío: S/ ${costoEnvio.toFixed(2)}`;
            
            // Actualizar formulario
            document.getElementById('formCostoEnvio').value = costoEnvio;
            document.getElementById('formDireccionEnvio').value = document.getElementById('inputDireccionEnvio').value;
        }
    }
    
    document.getElementById('totalFinal').textContent = `S/ ${total.toFixed(2)}`;
    document.getElementById('totalFinalDetalle').textContent = detalle;
    document.getElementById('formTotalCobrado').value = total;
}

function cancelarSeleccion() {
    document.querySelectorAll('.pedido-card').forEach(card => {
        card.classList.remove('selected');
    });
    document.getElementById('detallePedidoContainer').style.display = 'none';
    pedidoSeleccionado = null;
    
    // Resetear info lateral
    document.getElementById('infoPedidoBox').innerHTML = `
        <div style="text-align:center;padding:20px;color:var(--muted);font-size:.85rem;font-style:italic;">
            <i class="fas fa-hand-pointer" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:.3;"></i>
            Selecciona un pedido
        </div>
    `;
    document.getElementById('totalFinal').textContent = 'S/ 0.00';
    document.getElementById('totalFinalDetalle').textContent = '—';
}

// Validar formulario antes de enviar
document.getElementById('formEntrega').addEventListener('submit', function(e) {
    if (!pedidoSeleccionado) {
        e.preventDefault();
        alert('Por favor, selecciona un pedido para entregar.');
        return false;
    }
    
    const confirmacion = confirm(
        `¿Confirmar entrega del pedido?\n\n` +
        `Pedido: ${pedidoSeleccionado.codigo}\n` +
        `Cliente: ${pedidoSeleccionado.cliente}\n` +
        `Total a cobrar: ${document.getElementById('totalFinal').textContent}\n\n` +
        `Esta acción marcará el pedido como ENTREGADO.`
    );
    
    if (!confirmacion) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>
</body>
</html>
