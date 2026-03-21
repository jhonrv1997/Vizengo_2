<?php
/**
 * VIZENGO - Registro de Nuevo Pedido
 * Paso A: Ingreso de datos del contrato
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

// Obtener clientes frecuentes
$stmt = $db->query("SELECT DISTINCT nombre FROM clientes ORDER BY nombre ASC LIMIT 50");
$clientesFrecuentes = $stmt->fetchAll();

// Obtener pedidos de los próximos 7 días para disponibilidad
$pedidosSemana = [];
try {
    // Calcular desde hoy hasta los próximos 7 días
    $hoy = new DateTime();
    $hoyStr = $hoy->format('Y-m-d');
    
    $finSemana = clone $hoy;
    $finSemana->modify('+6 days'); // Hoy + 6 días = 7 días totales
    $finSemanaStr = $finSemana->format('Y-m-d');
    
    // Consultar pedidos agrupados por fecha de entrega en los próximos 7 días
    $stmt = $db->prepare("
        SELECT fecha_entrega, COUNT(*) as cantidad 
        FROM pedidos 
        WHERE fecha_entrega BETWEEN ? AND ?
        AND estado_general NOT IN ('cancelado', 'entregado')
        GROUP BY fecha_entrega
    ");
    $stmt->execute([$hoyStr, $finSemanaStr]);
    $resultados = $stmt->fetchAll();
    
    // Crear array con fechas como keys
    foreach ($resultados as $r) {
        $pedidosSemana[$r['fecha_entrega']] = $r['cantidad'];
    }
} catch (Exception $e) {
    // Si hay error, continuar con array vacío
    $pedidosSemana = [];
}

// Procesar formulario
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Datos del cliente
        $clienteNombre = sanitize($_POST['cliente_nombre'] ?? '');
        $clienteCelular = sanitize($_POST['cliente_celular'] ?? '');
        
        if (empty($clienteNombre)) {
            throw new Exception('El nombre del cliente es requerido');
        }
        
        // Buscar o crear cliente
        $stmt = $db->prepare("SELECT id FROM clientes WHERE nombre = ?");
        $stmt->execute([$clienteNombre]);
        $cliente = $stmt->fetch();
        
        if ($cliente) {
            $clienteId = $cliente['id'];
            // Actualizar celular si se proporcionó
            if (!empty($clienteCelular)) {
                $stmt = $db->prepare("UPDATE clientes SET celular = ? WHERE id = ?");
                $stmt->execute([$clienteCelular, $clienteId]);
            }
        } else {
            $stmt = $db->prepare("INSERT INTO clientes (nombre, celular) VALUES (?, ?)");
            $stmt->execute([$clienteNombre, $clienteCelular]);
            $clienteId = $db->lastInsertId();
        }
        
        // Generar código de pedido
        $codigo = generatePedidoCode();
        
        // Calcular totales
        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $adelanto = floatval($_POST['adelanto'] ?? 0);
        $saldo = $subtotal - $adelanto;
        
        // Fecha de entrega
        $fechaEntrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
        
        // Hora de entrega
        $horaEntrega = !empty($_POST['hora_entrega']) ? $_POST['hora_entrega'] : null;
        
        // Insertar pedido
        $stmt = $db->prepare("INSERT INTO pedidos (
            codigo, cliente_id, usuario_id, tipo_contrato, lugar_entrega,
            direccion_envio, vendedor_asignado, celular_cliente,
            observaciones_generales, observaciones_diseno,
            fecha_entrega, hora_entrega, subtotal, adelanto, saldo, estado_contrato, estado_general
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completo', 'en_proceso')");
        
        $stmt->execute([
            $codigo,
            $clienteId,
            $user['id'],
            sanitize($_POST['tipo_contrato'] ?? 'PEDIDO'),
            sanitize($_POST['lugar_entrega'] ?? 'TIENDA VIZENGO'),
            sanitize($_POST['direccion_envio'] ?? ''),
            sanitize($_POST['vendedor_asignado'] ?? $user['nombre']),
            $clienteCelular,
            sanitize($_POST['observaciones_generales'] ?? ''),
            sanitize($_POST['observaciones_diseno'] ?? ''),
            $fechaEntrega,
            $horaEntrega,
            $subtotal,
            $adelanto,
            $saldo
        ]);
        
        $pedidoId = $db->lastInsertId();
        
        // Insertar kits
        if (!empty($_POST['kits']) && is_array($_POST['kits'])) {
            $stmtKit = $db->prepare("INSERT INTO kits (
                pedido_id, camiseta_tipo, camiseta_tela, camiseta_talla,
                short_tipo, short_tela, short_talla, medias_tipo, medias_detalles,
                cantidad, precio_unitario, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['kits'] as $kit) {
                $cantidad = intval($kit['cantidad'] ?? 1);
                $precioUnitario = floatval($kit['precio_unitario'] ?? 0);
                $subtotalKit = $cantidad * $precioUnitario;
                
                $stmtKit->execute([
                    $pedidoId,
                    sanitize($kit['camiseta_tipo'] ?? ''),
                    sanitize($kit['camiseta_tela'] ?? ''),
                    sanitize($kit['camiseta_talla'] ?? ''),
                    sanitize($kit['short_tipo'] ?? ''),
                    sanitize($kit['short_tela'] ?? ''),
                    sanitize($kit['short_talla'] ?? ''),
                    sanitize($kit['medias_tipo'] ?? ''),
                    sanitize($kit['medias_detalles'] ?? ''),
                    $cantidad,
                    $precioUnitario,
                    $subtotalKit
                ]);
            }
        }
        
        // Insertar adicionales de talla
        if (!empty($_POST['adicionales']) && is_array($_POST['adicionales'])) {
            $stmtAdicional = $db->prepare("INSERT INTO adicionales_talla (
                pedido_id, talla, cantidad, precio_unitario
            ) VALUES (?, ?, ?, ?)");
            
            foreach ($_POST['adicionales'] as $adicional) {
                $stmtAdicional->execute([
                    $pedidoId,
                    sanitize($adicional['talla'] ?? ''),
                    intval($adicional['cantidad'] ?? 1),
                    floatval($adicional['precio_unitario'] ?? 0)
                ]);
            }
        }
        
        // Insertar merchandising
        if (!empty($_POST['merchandising']) && is_array($_POST['merchandising'])) {
            $stmtMerch = $db->prepare("INSERT INTO merchandising (
                pedido_id, articulo, cantidad, precio_unitario, es_regalo, especificaciones
            ) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($_POST['merchandising'] as $merch) {
                $stmtMerch->execute([
                    $pedidoId,
                    sanitize($merch['articulo'] ?? ''),
                    intval($merch['cantidad'] ?? 1),
                    floatval($merch['precio_unitario'] ?? 0),
                    intval($merch['es_regalo'] ?? 0),
                    sanitize($merch['especificaciones'] ?? '')
                ]);
            }
        }
        
        // Procesar imágenes si se subieron
        if (!empty($_FILES['logos'])) {
            $uploadDir = UPLOAD_PATH . 'referencias/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            foreach ($_FILES['logos']['tmp_name'] as $key => $tmpName) {
                if ($_FILES['logos']['error'][$key] === UPLOAD_ERR_OK) {
                    $extension = pathinfo($_FILES['logos']['name'][$key], PATHINFO_EXTENSION);
                    $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
                    $rutaDestino = $uploadDir . $nombreArchivo;
                    
                    if (move_uploaded_file($tmpName, $rutaDestino)) {
                        $stmt = $db->prepare("INSERT INTO referencias_pedido (pedido_id, imagen_path) VALUES (?, ?)");
                        $stmt->execute([$pedidoId, 'uploads/referencias/' . $nombreArchivo]);
                    }
                }
            }
        }
        
        // Log de actividad
        logActivity($pedidoId, $user['id'], 'PEDIDO_CREADO', "Pedido {$codigo} creado");
        
        $db->commit();
        
        $mensaje = "Pedido {$codigo} creado exitosamente";
        $tipoMensaje = 'success';
        
        // Redirigir a registro de integrantes
        header("Location: registro-integrantes.php?pedido_id={$pedidoId}&codigo={$codigo}");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $mensaje = "Error: " . $e->getMessage();
        $tipoMensaje = 'danger';
    }
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
    <title>VIZENGO - Nuevo Pedido</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .kit-box{background:#fafbff;border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px;}
        .kit-item{background:white;border:1px solid var(--border);border-radius:8px;padding:12px;position:relative;height:100%;}
        .kit-item-header{display:flex;align-items:center;gap:8px;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border);}
        .kit-item-icon{width:28px;height:28px;border-radius:6px;background:var(--primary);display:flex;align-items:center;justify-content:center;color:white;font-size:.75rem;}
        .kit-item-title{font-family:'Barlow Condensed',sans-serif;font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--primary);}
        .kit-pricing{display:flex;gap:12px;padding-top:12px;margin-top:12px;border-top:1px solid var(--border);}
        .kit-pricing-item{flex:1;}
        .kit-pricing-item label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:4px;display:block;}
        
        .pf-table{width:100%;border-collapse:collapse;}
        .pf-table thead th{background:var(--sidebar-bg);color:rgba(255,255,255,.6);padding:10px 14px;font-family:'Barlow Condensed',sans-serif;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;}
        .pf-table tbody tr{border-bottom:1px solid var(--border);}
        .pf-table tbody tr:last-child{border-bottom:none;}
        .pf-table tbody td{padding:11px 14px;font-size:.87rem;vertical-align:middle;}
        .pf-table tbody tr:hover{background:#f8faff;}
        .pf-empty{text-align:center;padding:24px;color:var(--muted);font-size:.88rem;font-style:italic;}
        
        .totals-bar{background:var(--sidebar-bg);border-radius:0 0 14px 14px;padding:16px 20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:center;}
        .tb-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.4);}
        .tb-val{font-family:'Barlow Condensed',sans-serif;font-size:1.8rem;font-weight:800;color:var(--accent);line-height:1.1;}
        .tb-input{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:6px 12px;color:white;font-family:'Barlow Condensed',sans-serif;font-size:1.1rem;font-weight:700;width:100%;outline:none;}
        .tb-input:focus{border-color:var(--accent);}
        
        .upload-zone{border:2px dashed var(--border);border-radius:10px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbff;}
        .upload-zone:hover{border-color:var(--primary);background:rgba(43,79,255,.03);}
        .upload-zone.has-files{border-color:var(--success);background:rgba(6,214,160,.04);}
        .preview-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:12px;}
        .preview-item{position:relative;border-radius:8px;overflow:hidden;border:1px solid var(--border);aspect-ratio:1;}
        .preview-item img{width:100%;height:100%;object-fit:cover;display:block;}
        .preview-remove{position:absolute;top:4px;right:4px;background:rgba(220,38,38,.85);color:white;border:none;border-radius:50%;width:22px;height:22px;font-size:.6rem;cursor:pointer;display:flex;align-items:center;justify-content:center;}
        
        .design-obs{margin-top:12px;padding-top:12px;border-top:1px dashed var(--border);}
        .design-obs-label{display:flex;align-items:center;gap:6px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px;}
        .design-obs-label i{color:var(--warning);}
        .design-obs-textarea{min-height:60px;resize:vertical;}
        
        .cal-grid{display:flex;gap:8px;overflow-x:auto;padding:4px 0;}
        .cal-day{min-width:60px;background:white;border:1.5px solid var(--border);border-radius:10px;padding:8px 6px;text-align:center;cursor:pointer;transition:all .2s;}
        .cal-day:hover{border-color:var(--primary);}
        .cal-day.selected{border-color:var(--primary);background:rgba(43,79,255,.06);}
        .cal-dn{font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);}
        .cal-num{font-family:'Barlow Condensed',sans-serif;font-size:1.3rem;font-weight:800;color:var(--text);}
        .cal-badge{border-radius:12px;padding:1px 8px;font-size:.68rem;font-weight:700;margin-top:3px;display:inline-block;}
        .cb-rojo{background:rgba(239,71,111,.15);color:#dc2626;}
        .cb-amarillo{background:rgba(245,158,11,.15);color:#d97706;}
        .cb-verde{background:rgba(6,214,160,.15);color:#059669;}
        .cb-gris{background:rgba(100,116,139,.1);color:#64748b;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-plus-circle" style="color:var(--primary);margin-right:10px;"></i>Registro de Nuevo Pedido</h1>
            <p>Paso A: Ingresa los datos del contrato</p>
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

    <!-- Pipeline tracker - 6 etapas -->
    <div class="pipeline-tracker">
        <div class="pt-step active">
            <div class="pt-circle"><i class="fas fa-file-contract"></i></div>
            <div class="pt-label">Contrato</div>
        </div>
        <div class="pt-line"></div>
        <div class="pt-step pending">
            <div class="pt-circle">2</div>
            <div class="pt-label">Integrantes</div>
        </div>
        <div class="pt-line"></div>
        <div class="pt-step pending">
            <div class="pt-circle">3</div>
            <div class="pt-label">Diseño</div>
        </div>
        <div class="pt-line"></div>
        <div class="pt-step pending">
            <div class="pt-circle">4</div>
            <div class="pt-label">Planchado</div>
        </div>
        <div class="pt-line"></div>
        <div class="pt-step pending">
            <div class="pt-circle">5</div>
            <div class="pt-label">Costura</div>
        </div>
        <div class="pt-line"></div>
        <div class="pt-step pending">
            <div class="pt-circle">6</div>
            <div class="pt-label">Entrega</div>
        </div>
    </div>

    <form method="POST" id="formPedido" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- COLUMNA IZQUIERDA -->
            <div class="col-lg-8">
                <!-- Datos del Contrato -->
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title"><i class="fas fa-file-signature" style="margin-right:8px;"></i>Datos del Contrato</h5>
                    </div>
                    <div class="card-v-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="field-group">
                                    <label class="field-lbl">Tipo de Contrato</label>
                                    <select class="field-ctrl" name="tipo_contrato" id="tipoContratoSelect" onchange="toggleClienteInput(this)">
                                        <option value="PEDIDO">PEDIDO</option>
                                        <option value="SERVICIO">SERVICIO</option>
                                        <option value="COSTURA">COSTURA</option>
                                        <option value="ESTAMPADO">ESTAMPADO</option>
                                        <option value="PLANCHADO">PLANCHADO</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-group">
                                    <label class="field-lbl">Lugar de Entrega</label>
                                    <select class="field-ctrl" name="lugar_entrega" id="lugarEntregaSelect" onchange="toggleEnvioInput(this)">
                                        <option value="TIENDA VIZENGO">TIENDA VIZENGO</option>
                                        <option value="TIENDA 2">TIENDA 2</option>
                                        <option value="TIENDA 3">TIENDA 3</option>
                                        <option value="ENVÍO">ENVÍO / AGENCIA</option>
                                    </select>
                                    <input type="text" name="direccion_envio" id="direccionEnvio" class="field-ctrl mt-2" placeholder="Especifique agencia o dirección..." style="display:none;">
                                </div>
                            </div>
                                                        <div class="col-md-4">
                                                                <div class="field-group">
                                                                        <label class="field-lbl">Vendedor</label>
                                                                        <input type="hidden" name="vendedor_asignado" value="<?php echo htmlspecialchars($user['nombre']); ?>">
                                                                        <input type="text" class="field-ctrl" value="<?php echo htmlspecialchars($user['nombre']); ?>" readonly style="background:#f8f9fa;cursor:not-allowed;">
                                                                </div>
                                                        </div>
                            <div class="col-md-8">
                                <div class="field-group">
                                    <label class="field-lbl">Cliente</label>
                                    <input type="text" name="cliente_nombre" id="clienteInput" class="field-ctrl" placeholder="Nombre completo del cliente" list="clientesList" required>
                                    <datalist id="clientesList">
                                        <?php foreach ($clientesFrecuentes as $c): ?>
                                        <option value="<?php echo htmlspecialchars($c['nombre']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-group">
                                    <label class="field-lbl">Celular</label>
                                    <input type="tel" name="cliente_celular" class="field-ctrl" placeholder="999-999-999">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-group">
                                    <label class="field-lbl">Fecha de Entrega</label>
                                    <input type="date" name="fecha_entrega" class="field-ctrl" min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="field-group">
                                    <label class="field-lbl">Hora de Entrega</label>
                                    <input type="time" name="hora_entrega" class="field-ctrl">
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="field-group" style="margin-bottom:0;">
                                    <label class="field-lbl">Observación General</label>
                                    <textarea name="observaciones_generales" class="field-ctrl" rows="2" placeholder="Notas importantes del cliente o pedido..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kit / Productos -->
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title"><i class="fas fa-tshirt" style="margin-right:8px;"></i>Detalle del Pedido (Kits)</h5>
                        <button type="button" class="btn-primary-action btn-sm" onclick="agregarKitPrincipal()"><i class="fas fa-plus"></i> Agregar a Proforma</button>
                    </div>
                    <div class="card-v-body">
                        <!-- Kit principal -->
                        <div class="kit-box" id="kitPrincipal">
                            <div class="row g-3">
                                <!-- Camiseta / Superior -->
                                <div class="col-md-4">
                                    <div class="kit-item">
                                        <div class="kit-item-header">
                                            <div class="kit-item-icon"><i class="fas fa-tshirt"></i></div>
                                            <span class="kit-item-title">Camiseta / Superior</span>
                                        </div>
                                        <div class="field-group" style="margin-bottom:8px;">
                                            <label class="field-lbl">Tipo</label>
                                            <select class="field-ctrl kit-camiseta-tipo" onchange="toggleOtros(this)">
                                                <option>CAMISETA MANGA CORTA</option>
                                                <option>CAMISETA MANGA LARGA</option>
                                                <option>CAMISETA REPLICA</option>
                                                <option>CAMISETA BASQUET</option>
                                                <option>POLO LISTO</option>
                                                <option>SUDADERA</option>
                                                <option>POLO 20/1</option>
                                                <option>POLO 30/1</option>
                                                <option>POLO PUBLICITARIO</option>
                                                <option>MANGALARGA 20/1</option>
                                                <option>MANGALARGA 30/1</option>
                                                <option>POLO CAMISERO</option>
                                                <option>MANGALARGA CAMISERO</option>
                                                <option>CASACA</option>
                                                <option>CHALECO</option>
                                                <option>POLERA</option>
                                                <option value="OTROS">OTROS</option>
                                            </select>
                                            <input type="text" class="field-ctrl mt-1 kit-camiseta-otros" placeholder="Especifique..." style="display:none;">
                                        </div>
                                        <div class="field-group" style="margin-bottom:8px;">
                                            <label class="field-lbl">Tela</label>
                                            <select class="field-ctrl kit-camiseta-tela" style="font-size:.8rem;color:var(--muted);">
                                                <option>Tela: ESPIGA</option>
                                                <option>Tela: MARATHON</option>
                                                <option>Tela: DRY</option>
                                                <option>Tela: ALGODON</option>
                                                <option>Tela: PIQUE</option>
                                                <option>Tela: SPUM</option>
                                                <option>Tela: MALLA WAFER</option>
                                                <option>Tela: DRILL</option>
                                                <option>Tela: POLISTRECH</option>
                                                <option>Tela: POLINAN</option>
                                                <option>Tela: POLAR</option>
                                                <option value="Tela: OTROS">Tela: OTROS</option>
                                            </select>
                                        </div>
                                        <div class="field-group" style="margin-bottom:0;">
                                            <label class="field-lbl">Talla Principal</label>
                                            <input type="text" class="field-ctrl kit-camiseta-talla" placeholder="Ej: M, L, XL...">
                                        </div>
                                    </div>
                                </div>

                                <!-- Short / Inferior -->
                                <div class="col-md-4">
                                    <div class="kit-item">
                                        <div class="kit-item-header">
                                            <div class="kit-item-icon" style="background:var(--success);"><i class="fas fa-socks"></i></div>
                                            <span class="kit-item-title" style="color:var(--success);">Short / Inferior</span>
                                        </div>
                                        <div class="field-group" style="margin-bottom:8px;">
                                            <label class="field-lbl">Tipo</label>
                                            <select class="field-ctrl kit-short-tipo" onchange="toggleOtros(this)">
                                                <option value="NINGUNO">NINGUNO</option>
                                                <option selected>SHORT</option>
                                                <option>SNICKER</option>
                                                <option>BASQUET</option>
                                                <option>SHORT FALDA</option>
                                                <option>REPLICA</option>
                                                <option value="OTROS">OTROS</option>
                                            </select>
                                            <input type="text" class="field-ctrl mt-1 kit-short-otros" placeholder="Especifique..." style="display:none;">
                                        </div>
                                        <div class="field-group" style="margin-bottom:8px;">
                                            <label class="field-lbl">Tela</label>
                                            <select class="field-ctrl kit-short-tela" style="font-size:.8rem;color:var(--muted);">
                                                <option>Tela: ESPIGA</option>
                                                <option>Tela: MARATHON</option>
                                                <option>Tela: DRY</option>
                                                <option>Tela: NOVA</option>
                                                <option>Tela: FULL LICRA</option>
                                                <option>Tela: FRENCH TERRY</option>
                                                <option value="Tela: OTROS">Tela: OTROS</option>
                                            </select>
                                        </div>
                                        <div class="field-group" style="margin-bottom:0;">
                                            <label class="field-lbl">Talla</label>
                                            <input type="text" class="field-ctrl kit-short-talla" placeholder="Ej: M, L, XL...">
                                        </div>
                                    </div>
                                </div>

                                <!-- Medias / Otros -->
                                <div class="col-md-4">
                                    <div class="kit-item">
                                        <div class="kit-item-header">
                                            <div class="kit-item-icon" style="background:var(--warning);"><i class="fas fa-shoe-prints"></i></div>
                                            <span class="kit-item-title" style="color:var(--warning);">Medias / Otros</span>
                                        </div>
                                        <div class="field-group" style="margin-bottom:8px;">
                                            <label class="field-lbl">Tipo</label>
                                            <select class="field-ctrl kit-medias-tipo" onchange="toggleOtros(this)">
                                                <option selected value="NINGUNO">NINGUNO</option>
                                                <option>RODILLERA</option>
                                                <option>FUTSALERA</option>
                                                <option>TOBILLERA</option>
                                                <option>TALONERA</option>
                                                <option value="OTROS">OTROS</option>
                                            </select>
                                            <input type="text" class="field-ctrl mt-1 kit-medias-otros" placeholder="Especifique..." style="display:none;">
                                        </div>
                                        <div class="field-group" style="margin-bottom:0;">
                                            <label class="field-lbl">Detalles</label>
                                            <input type="text" class="field-ctrl kit-medias-detalles" placeholder="Color, tamaño...">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cantidad y Precio -->
                            <div class="kit-pricing">
                                <div class="kit-pricing-item">
                                    <label>Cantidad</label>
                                    <input type="number" id="kitCantidad" class="field-ctrl text-center fw-bold" value="1" min="1" oninput="calcularSubtotal()">
                                </div>
                                <div class="kit-pricing-item">
                                    <label>Precio Unitario</label>
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        <span style="font-size:1rem;color:var(--primary);font-weight:700;">S/</span>
                                        <input type="number" id="kitPrecio" class="field-ctrl text-end fw-bold" value="35.00" oninput="calcularSubtotal()" style="font-size:1rem;color:var(--primary);">
                                    </div>
                                </div>
                                <div class="kit-pricing-item" style="display:flex;align-items:flex-end;">
                                    <button type="button" class="btn-success-action" onclick="agregarKitPrincipal()" style="width:100%;justify-content:center;">
                                        <i class="fas fa-plus"></i> Agregar Kit
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Adicional talla especial -->
                        <div class="kit-box" style="border-color:rgba(43,79,255,.2);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                <span style="font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:.9rem;color:var(--primary);text-transform:uppercase;"><i class="fas fa-tags" style="margin-right:6px;"></i>Adicional Talla Especial</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="field-lbl">Talla</label>
                                    <select class="field-ctrl" id="xlTalla">
                                        <option disabled selected>Seleccionar...</option>
                                        <option>XL</option>
                                        <option>XXL</option>
                                        <option>XXXL</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="field-lbl">Cantidad</label>
                                    <input type="number" id="xlCantidad" class="field-ctrl text-center" value="1" min="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="field-lbl">Precio Unit.</label>
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        <span style="font-size:.85rem;color:var(--muted);font-weight:600;">S/</span>
                                        <input type="number" id="xlPrecio" class="field-ctrl text-end" value="0.00" step="0.01">
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top:12px;">
                                <button type="button" class="btn-primary-action btn-sm" onclick="agregarAdicional()"><i class="fas fa-plus"></i> Agregar a Proforma</button>
                            </div>
                        </div>

                        <!-- Banderolas y Merchandising -->
                        <div class="kit-box" style="border-color:rgba(100,116,139,.2);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                <span style="font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:.9rem;color:var(--muted);text-transform:uppercase;"><i class="fas fa-flag" style="margin-right:6px;"></i>Banderolas / Merchandising</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-8">
                                    <label class="field-lbl">Artículo</label>
                                    <select class="field-ctrl" id="merchArticulo">
                                        <option value="NINGUNO">NINGUNO</option>
                                        <option>BANDEROLA</option>
                                        <option>SOMBRERO</option>
                                        <option>GORRO</option>
                                        <option>BANDERA</option>
                                        <option>PAÑUELO</option>
                                        <option>IMPRESIÓN PAPEL</option>
                                        <option>ESTAMPADO</option>
                                        <option>BORDADO</option>
                                        <option>SUBLIMADO</option>
                                        <option>OTROS</option>
                                    </select>
                                </div>
                                <div class="col-4" style="display:flex;align-items:flex-end;padding-bottom:2px;">
                                    <label style="display:flex;align-items:center;gap:6px;font-size:.82rem;font-weight:600;color:var(--success);cursor:pointer;">
                                        <input type="checkbox" id="regaloCheck"> ¿Regalo?
                                    </label>
                                </div>
                                <div class="col-4">
                                    <label class="field-lbl">Cant.</label>
                                    <input type="number" id="merchCantidad" class="field-ctrl text-center" value="1" min="1">
                                </div>
                                <div class="col-8">
                                    <label class="field-lbl">Precio Unit.</label>
                                    <div style="display:flex;align-items:center;gap:4px;">
                                        <span style="font-size:.85rem;color:var(--muted);font-weight:600;">S/</span>
                                        <input type="number" id="merchPrecio" class="field-ctrl text-end" value="0.00" step="0.01">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="field-lbl">Especificaciones</label>
                                    <input type="text" id="merchEspecificaciones" class="field-ctrl" placeholder="Ej: 1.5m x 3m, fondo azul...">
                                </div>
                            </div>
                            <div style="margin-top:12px;">
                                <button type="button" class="btn-primary-action btn-sm" onclick="agregarMerch()"><i class="fas fa-plus"></i> Agregar a Proforma</button>
                            </div>
                        </div>

                        <!-- Logo/Referencia -->
                        <div class="kit-box">
                            <label class="field-lbl" style="margin-bottom:10px;"><i class="fas fa-image" style="color:var(--primary);margin-right:6px;"></i>Logo / Referencia (máx. 4 fotos)</label>
                            <div id="uploadArea" class="upload-zone" onclick="document.getElementById('logoInput').click()">
                                <input type="file" id="logoInput" name="logos[]" hidden accept="image/*" multiple onchange="handleFileSelect(event)">
                                <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem;color:var(--muted);display:block;margin-bottom:6px;"></i>
                                <div style="font-size:.82rem;font-weight:600;color:var(--muted);">Arrastra o haz clic · Máx. 4 fotos</div>
                            </div>
                            <div id="previewGrid" class="preview-grid" style="display:none;"></div>
                            
                            <!-- Observaciones de Diseño -->
                            <div class="design-obs">
                                <div class="design-obs-label"><i class="fas fa-pencil-alt"></i> Observaciones de Diseño</div>
                                <textarea name="observaciones_diseno" class="field-ctrl design-obs-textarea" rows="3" placeholder="Indica detalles del diseño: colores, posición del logo, texto a incluir, estilo preferido..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Proforma -->
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title" style="color:var(--success);"><i class="fas fa-shopping-cart" style="margin-right:8px;"></i>Proforma del Pedido</h5>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="pf-table">
                            <thead>
                                <tr>
                                    <th style="text-align:center;">Cant.</th>
                                    <th>Descripción</th>
                                    <th style="text-align:center;">Talla</th>
                                    <th style="text-align:right;">P. Unit.</th>
                                    <th style="text-align:right;">Sub Total</th>
                                    <th style="text-align:center;">—</th>
                                </tr>
                            </thead>
                            <tbody id="listaProforma">
                                <tr><td colspan="6" class="pf-empty"><i class="fas fa-info-circle" style="margin-right:6px;"></i>Agrega kits o artículos para ver la proforma</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <input type="hidden" name="subtotal" id="subtotalInput" value="0">
                    <input type="hidden" name="adelanto" id="adelantoInput" value="0">
                </div>
            </div>

            <!-- COLUMNA DERECHA -->
            <div class="col-lg-4">
                <!-- Disponibilidad Semanal -->
                <div class="card-v" style="margin-bottom:16px;">
                    <div class="card-v-header">
                        <h5 class="card-v-title"><i class="fas fa-calendar-week" style="margin-right:8px;"></i>Disponibilidad (Próximos 7 días)</h5>
                    </div>
                    <div class="card-v-body">
                        <p style="font-size:.8rem;color:var(--muted);margin-bottom:14px;"><i class="fas fa-info-circle" style="margin-right:4px;"></i>Pedidos ya registrados:</p>
                        <div class="cal-grid">
                            <?php
                            // Array de días en español
                            $diasCortos = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
                            
                            // Mostrar los próximos 7 días desde hoy
                            $hoyCal = new DateTime();
                            
                            for ($i = 0; $i < 7; $i++) {
                                $fecha = clone $hoyCal;
                                $fecha->modify("+{$i} days");
                                $fechaStr = $fecha->format('Y-m-d');
                                $diaNum = $fecha->format('d');
                                $diaSemanaNum = (int)$fecha->format('w'); // 0=Dom, 6=Sáb
                                $cantidad = isset($pedidosSemana[$fechaStr]) ? $pedidosSemana[$fechaStr] : 0;
                                
                                // Determinar color del badge según cantidad
                                if ($cantidad == 0) {
                                    $badgeClass = 'cb-gris';
                                } elseif ($cantidad <= 12) {
                                    $badgeClass = 'cb-verde';
                                } elseif ($cantidad <= 15) {
                                    $badgeClass = 'cb-amarillo';
                                } else {
                                    $badgeClass = 'cb-rojo';
                                }
                                
                                // El primer día (hoy) aparece seleccionado por defecto
                                $esHoy = ($i === 0) ? ' selected' : '';
                                
                                echo '<div class="cal-day' . $esHoy . '" onclick="selDia(this)" data-fecha="' . $fechaStr . '">';
                                echo '<div class="cal-dn">' . $diasCortos[$diaSemanaNum] . '</div>';
                                echo '<div class="cal-num">' . $diaNum . '</div>';
                                echo '<span class="cal-badge ' . $badgeClass . '">' . $cantidad . '</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <div style="height:1px;background:var(--border);margin:16px 0;"></div>
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <span style="font-size:.7rem;color:var(--muted);display:flex;align-items:center;gap:4px;">
                                    <span class="cal-badge cb-verde" style="font-size:.6rem;">●</span> Disponible
                                </span>
                                <span style="font-size:.7rem;color:var(--muted);display:flex;align-items:center;gap:4px;">
                                    <span class="cal-badge cb-amarillo" style="font-size:.6rem;">●</span> Moderado
                                </span>
                                <span style="font-size:.7rem;color:var(--muted);display:flex;align-items:center;gap:4px;">
                                    <span class="cal-badge cb-rojo" style="font-size:.6rem;">●</span> Ocupado
                                </span>
                            </div>
                        </div>
                        <p style="font-size:.75rem;color:var(--muted);margin-top:12px;font-style:italic;">
                            <i class="fas fa-lightbulb" style="color:var(--warning);margin-right:4px;"></i>
                            Haz clic en un día para sugerirlo como fecha de entrega
                        </p>
                    </div>
                </div>
                
                <!-- Totales -->
                <div class="card-v" style="position:sticky;top:20px;">
                    <div class="card-v-header" style="background:var(--sidebar-bg);">
                        <h5 class="card-v-title" style="color:white;"><i class="fas fa-calculator" style="margin-right:8px;"></i>Resumen de Pago</h5>
                    </div>
                    <div class="totals-bar" style="border-radius:0;">
                        <div>
                            <div class="tb-label">Subtotal</div>
                            <div class="tb-val" id="subtotalVal">S/ 0.00</div>
                        </div>
                        <div>
                            <div class="tb-label">Adelanto</div>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                                <span style="color:rgba(255,255,255,.4);font-size:.9rem;">S/</span>
                                <input type="number" class="tb-input" placeholder="0.00" id="adelantoInputDisplay" oninput="calcSaldo()">
                            </div>
                        </div>
                        <div>
                            <div class="tb-label">Saldo</div>
                            <div class="tb-val" id="saldoVal" style="color:var(--danger);">S/ 0.00</div>
                        </div>
                    </div>
                    <div class="card-v-body">
                        <button type="submit" class="btn-success-action" style="width:100%;justify-content:center;padding:14px;font-size:1rem;">
                            <i class="fas fa-save"></i> Registrar Pedido
                        </button>
                        <p style="text-align:center;font-size:.75rem;color:var(--muted);margin-top:12px;">
                            <i class="fas fa-info-circle"></i> Serás redirigido al registro de integrantes
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Variables globales
let proformaItems = [];
let kitCounter = 0;
let adicionalCounter = 0;
let merchCounter = 0;
let selectedFiles = []; // Array para almacenar las imágenes seleccionadas (máx. 4)

// Función para seleccionar día en el calendario de disponibilidad
function selDia(el) {
    // Quitar selección previa
    document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('selected'));
    // Agregar selección al elemento clickeado
    el.classList.add('selected');
    
    // Obtener la fecha del día seleccionado y actualizar el campo de fecha de entrega
    const fechaSeleccionada = el.getAttribute('data-fecha');
    if (fechaSeleccionada) {
        const campoFecha = document.querySelector('input[name="fecha_entrega"]');
        if (campoFecha) {
            campoFecha.value = fechaSeleccionada;
        }
    }
}

// Toggle para campo de dirección de envío
function toggleEnvioInput(select) {
    const envioInput = document.getElementById('direccionEnvio');
    if (select.value === 'ENVÍO') {
        envioInput.style.display = 'block';
        envioInput.required = true;
    } else {
        envioInput.style.display = 'none';
        envioInput.required = false;
    }
}

// Toggle para campo "Otros"
function toggleOtros(select) {
    const otrosInput = select.parentElement.querySelector('input');
    if (otrosInput) {
        if (select.value === 'OTROS') {
            otrosInput.style.display = 'block';
            otrosInput.required = true;
        } else {
            otrosInput.style.display = 'none';
            otrosInput.required = false;
        }
    }
}

// Toggle cliente input
function toggleClienteInput(select) {
    // Lógica adicional si se necesita según tipo de contrato
}

// Calcular subtotal del kit actual
function calcularSubtotal() {
    const cantidad = parseFloat(document.getElementById('kitCantidad').value) || 0;
    const precio = parseFloat(document.getElementById('kitPrecio').value) || 0;
    return cantidad * precio;
}

// Agregar kit a proforma
function agregarKitPrincipal() {
    const cantidad = parseInt(document.getElementById('kitCantidad').value) || 1;
    const precio = parseFloat(document.getElementById('kitPrecio').value) || 0;
    
    // Obtener valores del kit
    const camisetaTipo = document.querySelector('.kit-camiseta-tipo').value;
    const camisetaTela = document.querySelector('.kit-camiseta-tela').value;
    const camisetaTalla = document.querySelector('.kit-camiseta-talla').value || '-';
    
    const shortTipo = document.querySelector('.kit-short-tipo').value;
    const shortTela = document.querySelector('.kit-short-tela').value;
    const shortTalla = document.querySelector('.kit-short-talla').value || '-';
    
    const mediasTipo = document.querySelector('.kit-medias-tipo').value;
    const mediasDetalles = document.querySelector('.kit-medias-detalles').value || '';
    
    // Construir descripción
    let descripcion = camisetaTipo;
    if (shortTipo && shortTipo !== 'NINGUNO') {
        descripcion += ' + ' + shortTipo;
    }
    
    // Crear item de proforma
    const item = {
        id: ++kitCounter,
        cantidad: cantidad,
        descripcion: descripcion,
        talla: camisetaTalla,
        precioUnitario: precio,
        subtotal: cantidad * precio,
        kit: {
            camiseta_tipo: camisetaTipo,
            camiseta_tela: camisetaTela,
            camiseta_talla: camisetaTalla,
            short_tipo: shortTipo,
            short_tela: shortTela,
            short_talla: shortTalla,
            medias_tipo: mediasTipo,
            medias_detalles: mediasDetalles,
            cantidad: cantidad,
            precio_unitario: precio
        }
    };
    
    proformaItems.push(item);
    renderProforma();
    actualizarTotales();
}

// Agregar adicional de talla especial a proforma
function agregarAdicional() {
    const tallaSelect = document.getElementById('xlTalla');
    const talla = tallaSelect.value;
    
    if (!talla || talla === 'Seleccionar...') {
        alert('Por favor, selecciona una talla.');
        return;
    }
    
    const cantidad = parseInt(document.getElementById('xlCantidad').value) || 1;
    const precio = parseFloat(document.getElementById('xlPrecio').value) || 0;
    
    const item = {
        id: ++kitCounter,
        tipo: 'adicional_talla',
        adicionalId: ++adicionalCounter,
        cantidad: cantidad,
        descripcion: `Adicional Talla ${talla}`,
        talla: talla,
        precioUnitario: precio,
        subtotal: cantidad * precio,
        adicional: {
            talla: talla,
            cantidad: cantidad,
            precio_unitario: precio
        }
    };
    
    proformaItems.push(item);
    renderProforma();
    actualizarTotales();
    
    // Resetear campos
    tallaSelect.selectedIndex = 0;
    document.getElementById('xlCantidad').value = 1;
    document.getElementById('xlPrecio').value = 0;
}

// Agregar merchandising a proforma
function agregarMerch() {
    const articulo = document.getElementById('merchArticulo').value;
    
    if (!articulo || articulo === 'NINGUNO') {
        alert('Por favor, selecciona un artículo.');
        return;
    }
    
    const cantidad = parseInt(document.getElementById('merchCantidad').value) || 1;
    const precio = parseFloat(document.getElementById('merchPrecio').value) || 0;
    const esRegalo = document.getElementById('regaloCheck').checked;
    const especificaciones = document.getElementById('merchEspecificaciones').value || '';
    
    const item = {
        id: ++kitCounter,
        tipo: 'merchandising',
        merchId: ++merchCounter,
        cantidad: cantidad,
        descripcion: `${articulo}${esRegalo ? ' (REGALO)' : ''}`,
        talla: '-',
        precioUnitario: esRegalo ? 0 : precio,
        subtotal: esRegalo ? 0 : (cantidad * precio),
        merch: {
            articulo: articulo,
            cantidad: cantidad,
            precio_unitario: precio,
            es_regalo: esRegalo ? 1 : 0,
            especificaciones: especificaciones
        }
    };
    
    proformaItems.push(item);
    renderProforma();
    actualizarTotales();
    
    // Resetear campos
    document.getElementById('merchArticulo').value = 'NINGUNO';
    document.getElementById('merchCantidad').value = 1;
    document.getElementById('merchPrecio').value = 0;
    document.getElementById('regaloCheck').checked = false;
    document.getElementById('merchEspecificaciones').value = '';
}

// Renderizar proforma
function renderProforma() {
    const tbody = document.getElementById('listaProforma');
    
    if (proformaItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="pf-empty"><i class="fas fa-info-circle" style="margin-right:6px;"></i>Agrega kits o artículos para ver la proforma</td></tr>';
        return;
    }
    
    let html = '';
    proformaItems.forEach(item => {
        html += `
            <tr data-id="${item.id}">
                <td style="text-align:center;font-weight:700;">${item.cantidad}</td>
                <td>${item.descripcion}</td>
                <td style="text-align:center;">${item.talla}</td>
                <td style="text-align:right;">S/ ${item.precioUnitario.toFixed(2)}</td>
                <td style="text-align:right;font-weight:700;">S/ ${item.subtotal.toFixed(2)}</td>
                <td style="text-align:center;">
                    <button type="button" class="btn-remove-item" onclick="eliminarItem(${item.id})" style="background:none;border:none;color:var(--danger);cursor:pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Eliminar item de proforma
function eliminarItem(id) {
    proformaItems = proformaItems.filter(item => item.id !== id);
    renderProforma();
    actualizarTotales();
}

// Actualizar totales
function actualizarTotales() {
    let subtotal = 0;
    proformaItems.forEach(item => {
        subtotal += item.subtotal;
    });
    
    document.getElementById('subtotalVal').textContent = 'S/ ' + subtotal.toFixed(2);
    document.getElementById('subtotalInput').value = subtotal;
    
    calcSaldo();
    
    // Actualizar campos hidden para kits
    updateKitsHidden();
}

// Calcular saldo
function calcSaldo() {
    const subtotal = parseFloat(document.getElementById('subtotalInput').value) || 0;
    const adelanto = parseFloat(document.getElementById('adelantoInputDisplay').value) || 0;
    const saldo = subtotal - adelanto;
    
    document.getElementById('saldoVal').textContent = 'S/ ' + saldo.toFixed(2);
    document.getElementById('adelantoInput').value = adelanto;
}

// Actualizar campos hidden de kits, adicionales y merchandising
function updateKitsHidden() {
    // Remover campos anteriores
    document.querySelectorAll('input[name^="kits["]').forEach(el => el.remove());
    document.querySelectorAll('input[name^="adicionales["]').forEach(el => el.remove());
    document.querySelectorAll('input[name^="merchandising["]').forEach(el => el.remove());
    
    let kitIndex = 0;
    let adicionalIndex = 0;
    let merchIndex = 0;
    
    proformaItems.forEach((item) => {
        if (item.tipo === 'kit' || !item.tipo) {
            // Es un kit normal
            Object.keys(item.kit).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `kits[${kitIndex}][${key}]`;
                input.value = item.kit[key];
                document.getElementById('formPedido').appendChild(input);
            });
            kitIndex++;
        } else if (item.tipo === 'adicional_talla') {
            // Es un adicional de talla
            Object.keys(item.adicional).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `adicionales[${adicionalIndex}][${key}]`;
                input.value = item.adicional[key];
                document.getElementById('formPedido').appendChild(input);
            });
            adicionalIndex++;
        } else if (item.tipo === 'merchandising') {
            // Es merchandising
            Object.keys(item.merch).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `merchandising[${merchIndex}][${key}]`;
                input.value = item.merch[key];
                document.getElementById('formPedido').appendChild(input);
            });
            merchIndex++;
        }
    });
}

// Manejar selección de archivos - Acumula imágenes hasta un máximo de 4
function handleFileSelect(event) {
    const newFiles = Array.from(event.target.files);
    const previewGrid = document.getElementById('previewGrid');
    const uploadArea = document.getElementById('uploadArea');
    
    // Calcular cuántos archivos más se pueden agregar
    const remainingSlots = 4 - selectedFiles.length;
    
    if (remainingSlots <= 0) {
        alert('Ya tienes el máximo de 4 imágenes seleccionadas. Elimina alguna para agregar nuevas.');
        event.target.value = ''; // Limpiar el input
        return;
    }
    
    // Agregar solo los archivos que caben
    const filesToAdd = newFiles.slice(0, remainingSlots);
    
    if (filesToAdd.length < newFiles.length) {
        alert(`Solo se agregaron ${filesToAdd.length} imagen(es) para no exceder el límite de 4.`);
    }
    
    // Agregar los nuevos archivos al array acumulado
    selectedFiles = [...selectedFiles, ...filesToAdd];
    
    // Actualizar el preview
    updatePreview();
    
    // Limpiar el input para permitir seleccionar más archivos después
    event.target.value = '';
}

// Actualizar la vista previa de imágenes
function updatePreview() {
    const previewGrid = document.getElementById('previewGrid');
    const uploadArea = document.getElementById('uploadArea');
    
    previewGrid.innerHTML = '';
    
    if (selectedFiles.length > 0) {
        uploadArea.classList.add('has-files');
        previewGrid.style.display = 'grid';
        
        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <button type="button" class="preview-remove" onclick="removeFile(${index})" title="Eliminar imagen">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                previewGrid.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
        
        // Actualizar texto del área de carga
        const uploadText = uploadArea.querySelector('div[style*="font-size"]');
        if (uploadText) {
            uploadText.textContent = `${selectedFiles.length}/4 imágenes seleccionadas · Haz clic para agregar más`;
        }
    } else {
        uploadArea.classList.remove('has-files');
        previewGrid.style.display = 'none';
        
        // Restaurar texto original
        const uploadText = uploadArea.querySelector('div[style*="font-size"]');
        if (uploadText) {
            uploadText.textContent = 'Arrastra o haz clic · Máx. 4 fotos';
        }
    }
}

// Eliminar una imagen individual del array
function removeFile(index) {
    selectedFiles.splice(index, 1);
    updatePreview();
}

// Actualizar el input file con los archivos acumulados antes de enviar
function updateFileInput() {
    const logoInput = document.getElementById('logoInput');
    const dataTransfer = new DataTransfer();
    
    selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
    });
    
    logoInput.files = dataTransfer.files;
}

// Validar formulario antes de enviar
document.getElementById('formPedido').addEventListener('submit', function(e) {
    if (proformaItems.length === 0) {
        e.preventDefault();
        alert('Por favor, agrega al menos un kit o artículo a la proforma.');
        return false;
    }
    
    const cliente = document.getElementById('clienteInput').value.trim();
    if (!cliente) {
        e.preventDefault();
        alert('Por favor, ingresa el nombre del cliente.');
        return false;
    }
    
	// Validar que el adelanto sea mayor a 0
const adelantoInput = document.getElementById('adelantoInputDisplay');
const adelantoValue = parseFloat(adelantoInput.value) || 0;
if (adelantoValue <= 0 || adelantoInput.value.trim() === '') {
    e.preventDefault();
    alert('Por favor, ingresa un monto de adelanto mayor a 0.');
    adelantoInput.focus();
    return false;
}
    // Actualizar el input file con los archivos acumulados antes de enviar
    updateFileInput();
    
    return true;
});
</script>
</body>
</html>
