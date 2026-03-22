<?php
/**
 * VIZENGO - Registro de Costura
 * Paso 5 del pipeline
 */
require_once 'config.php';
startSecureSession();

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

// Solo diseñadores y admins
if (!in_array($user['rol'], ['disenador', 'administrador'])) {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();

// Obtener pedidos pendientes de costura
$stmt = $db->query("SELECT p.id, p.codigo, c.nombre as cliente, p.fecha_entrega,
                    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes
                    FROM pedidos p 
                    LEFT JOIN clientes c ON p.cliente_id = c.id
                    WHERE p.estado_planchado = 'completo' AND p.estado_costura != 'completo'
                    ORDER BY p.fecha_entrega ASC");
$pedidosPendientes = $stmt->fetchAll();

// Obtener costureros
$stmt = $db->query("SELECT * FROM costureros WHERE activo = 1 ORDER BY nombre");
$costureros = $stmt->fetchAll();

// Obtener pedido específico
$pedidoId = intval($_GET['pedido_id'] ?? 0);
$pedido = null;

if ($pedidoId > 0) {
    $stmt = $db->prepare("SELECT p.*, c.nombre as cliente,
                          (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes
                          FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    
    // Obtener información del kit (tipos de prenda)
    $stmt = $db->prepare("SELECT camiseta_tipo, camiseta_tela, short_tipo, short_tela FROM kits WHERE pedido_id = ? LIMIT 1");
    $stmt->execute([$pedidoId]);
    $kit = $stmt->fetch();
    
    // Contar integrantes que incluyen short
    $stmt = $db->prepare("SELECT COUNT(*) as con_short FROM integrantes WHERE pedido_id = ? AND incluye_short = 1");
    $stmt->execute([$pedidoId]);
    $countShorts = $stmt->fetch();
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = intval($_POST['pedido_id'] ?? 0);
    $costureroNombre = sanitize($_POST['costurero_nombre'] ?? '');
    $cantPolos = intval($_POST['cant_polos'] ?? 0);
    $cantShorts = intval($_POST['cant_shorts'] ?? 0);
    $precioPolo = floatval($_POST['precio_polo'] ?? 2.00);
    $precioShort = floatval($_POST['precio_short'] ?? 1.50);
    $observaciones = sanitize($_POST['observaciones'] ?? '');
    $fechaCostura = $_POST['fecha_costura'] ?? date('Y-m-d');
    
        // Procesar otros costos (JSON)
    $otrosCostosJson = $_POST['otros_costos'] ?? '[]';
    $otrosCostos = json_decode($otrosCostosJson, true);
    $totalOtros = 0;
    
    $totalPago = ($cantPolos * $precioPolo) + ($cantShorts * $precioShort);
    
    if ($pedidoId > 0 && !empty($costureroNombre)) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO costura (pedido_id, costurero_nombre, cant_polos, cant_shorts, precio_polo, precio_short, total_pago, observaciones, fecha_costura) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE 
                                  costurero_nombre = VALUES(costurero_nombre), cant_polos = VALUES(cant_polos), cant_shorts = VALUES(cant_shorts),
                                  precio_polo = VALUES(precio_polo), precio_short = VALUES(precio_short), total_pago = VALUES(total_pago), observaciones = VALUES(observaciones), fecha_costura = VALUES(fecha_costura)");
            $stmt->execute([$pedidoId, $costureroNombre, $cantPolos, $cantShorts, $precioPolo, $precioShort, $totalPago, $observaciones, $fechaCostura]);
            $costuraId = $db->lastInsertId();
            
            // Si es una actualización, obtener el ID existente
            if (!$costuraId) {
                $stmt = $db->prepare("SELECT id FROM costura WHERE pedido_id = ?");
                $stmt->execute([$pedidoId]);
                $existingCostura = $stmt->fetch();
                $costuraId = $existingCostura['id'];
            }
            
            // Guardar otros costos
            if ($costuraId && !empty($otrosCostos)) {
                // Eliminar anteriores
                $stmt = $db->prepare("DELETE FROM costura_otros WHERE costura_id = ?");
                $stmt->execute([$costuraId]);
                
                // Insertar nuevos
                $stmt = $db->prepare("INSERT INTO costura_otros (costura_id, descripcion, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                foreach ($otrosCostos as $otro) {
                    if (!empty($otro['descripcion'])) {
                        $stmt->execute([$costuraId, $otro['descripcion'], intval($otro['cantidad']), floatval($otro['precio'])]);
                        $totalOtros += intval($otro['cantidad']) * floatval($otro['precio']);
                    }
                }
                
                // Actualizar total con otros costos
                $totalPago += $totalOtros;
                $stmt = $db->prepare("UPDATE costura SET total_pago = ? WHERE id = ?");
                $stmt->execute([$totalPago, $costuraId]);
            }
            
            $stmt = $db->prepare("UPDATE pedidos SET estado_costura = 'completo', estado_general = 'listo_entrega' WHERE id = ?");
            $stmt->execute([$pedidoId]);
            
            $db->commit();
            header('Location: entrega.php?pedido_id=' . $pedidoId . '&saved=1');
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Registro de Costura</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root{--purple:#8b5cf6;}
        .pipeline-tracker{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 16px;margin-bottom:24px;display:flex;align-items:center;justify-content:center;}
        .pt-step{display:flex;flex-direction:column;align-items:center;text-align:center;flex:1;max-width:100px;}
        .pt-circle{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;border:2px solid var(--border);background:var(--surface);color:var(--muted);transition:all .3s;margin-bottom:6px;}
        .pt-step.done .pt-circle{background:var(--success);border-color:var(--success);color:white;}
        .pt-step.active .pt-circle{background:var(--purple);border-color:var(--purple);color:white;box-shadow:0 0 0 5px rgba(139,92,246,.15);}
        .pt-label{font-family:'Barlow Condensed',sans-serif;font-size:.72rem;font-weight:700;text-transform:uppercase;}
        .pt-step.done .pt-label{color:var(--success);}
        .pt-step.active .pt-label{color:var(--purple);}
        .pt-step.pending .pt-label{color:var(--muted);}
        .pt-line{flex:1;height:2px;background:var(--border);max-width:40px;align-self:flex-start;margin-top:20px;}
        .pt-line.done{background:var(--success);}
        
        .qty-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:20px;text-align:center;}
        .qty-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:8px;}
        .qty-type{font-size:.7rem;font-weight:600;color:var(--purple);background:rgba(139,92,246,.1);padding:2px 8px;border-radius:4px;margin-bottom:8px;display:inline-block;}
        .qty-controls{display:flex;align-items:center;justify-content:center;gap:12px;}
        .qty-btn{width:40px;height:40px;border-radius:50%;border:none;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;transition:all .2s;}
        .qty-btn.plus{background:var(--success);color:white;}
        .qty-btn.minus{background:var(--danger);color:white;}
        .qty-input{width:70px;text-align:center;font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:800;border:2px solid var(--border);border-radius:10px;padding:6px;outline:none;color:var(--text);}
        
        .total-box{background:var(--sidebar-bg);border-radius:14px;padding:24px;color:white;text-align:center;}
        .total-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.4);margin-bottom:8px;}
        .total-amount{font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:800;color:var(--success);line-height:1;}
        
        /* Estilos para Otros Costos */
        .otros-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:20px;}
        .otros-title{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;color:var(--purple);margin-bottom:16px;display:flex;align-items:center;gap:8px;}
        .otros-item{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;}
        .otros-item-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
        .otros-item-num{font-size:.8rem;font-weight:700;color:var(--purple);text-transform:uppercase;}
        .btn-remove-otro{background:var(--danger);color:white;border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.8rem;transition:all .2s;}
        .btn-remove-otro:hover{opacity:.8;transform:scale(1.1);}
        .btn-agregar-otro{background:var(--purple);color:white;border:none;border-radius:8px;padding:10px 20px;cursor:pointer;font-weight:600;font-size:.85rem;display:inline-flex;align-items:center;gap:8px;transition:all .2s;width:100%;justify-content:center;}
        .btn-agregar-otro:hover{opacity:.9;transform:translateY(-2px);}
        .qty-tela{font-size:.65rem;font-weight:600;color:var(--muted);background:rgba(0,0,0,.05);padding:2px 6px;border-radius:4px;margin-top:4px;display:inline-block;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-cut" style="color:#8b5cf6;margin-right:10px;"></i>Registro de Costura</h1>
            <p>Paso 5: Registra los trabajos de costura y el pago</p>
        </div>
        <a href="lista-pedidos.php" class="btn-v btn-outline-v"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <!-- Pipeline -->
    <div class="pipeline-tracker">
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Contrato</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Integrantes</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Diseño</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-tshirt"></i></div><div class="pt-label">Planchado</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step active"><div class="pt-circle"><i class="fas fa-cut"></i></div><div class="pt-label">Costura</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle"><i class="fas fa-box"></i></div><div class="pt-label">Entrega</div></div>
    </div>

    <?php if ($pedido): ?>
    <form method="POST" id="formCostura">
        <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
        <input type="hidden" name="otros_costos" id="otrosCostosInput" value="[]">
        
        <!-- Info del pedido -->
        <div class="card-v">
            <div class="card-v-header">
                <h5 class="card-v-title" style="color:#8b5cf6;"><i class="fas fa-file-contract" style="margin-right:8px;"></i>Pedido a Costurar</h5>
            </div>
            <div class="card-v-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="field-lbl">Pedido</label>
                        <div style="font-weight:700;font-size:.9rem;padding-top:8px;"><?php echo $pedido['codigo']; ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="field-lbl">Cliente</label>
                        <div style="font-weight:700;font-size:.9rem;padding-top:8px;"><?php echo htmlspecialchars($pedido['cliente']); ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="field-lbl">Entrega</label>
                        <div style="font-weight:700;font-size:.9rem;padding-top:8px;color:var(--danger);"><?php echo formatDate($pedido['fecha_entrega'], 'd/m/Y'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos del costurero -->
        <div class="card-v">
            <div class="card-v-header">
                <h5 class="card-v-title" style="color:#8b5cf6;"><i class="fas fa-user-cog" style="margin-right:8px;"></i>Datos del Costurero</h5>
            </div>
            <div class="card-v-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Nombre del Costurero <span style="color:var(--danger);">*</span></label>
                            <select class="field-ctrl" name="costurero_nombre" required>
                                <option value="" disabled selected>Seleccione...</option>
                                <?php foreach ($costureros as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['nombre']); ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                <?php endforeach; ?>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Fecha de Trabajo</label>
                            <input type="date" class="field-ctrl" name="fecha_costura" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="field-group" style="margin-bottom:0;">
                            <label class="field-lbl">Observaciones</label>
                            <textarea class="field-ctrl" name="observaciones" rows="2" placeholder="Notas sobre la costura..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cantidades -->
        <div class="card-v">
            <div class="card-v-header">
                <h5 class="card-v-title" style="color:#8b5cf6;"><i class="fas fa-layer-group" style="margin-right:8px;"></i>Cantidades de Prendas</h5>
            </div>
            <div class="card-v-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="qty-card">
                            <div class="qty-label">Polos / Camisetas</div>
                            <div class="qty-type"><?php echo $kit ? htmlspecialchars($kit['camiseta_tipo']) : 'POLOS'; ?></div>
                            <?php if ($kit && $kit['camiseta_tela']): ?>
                            <div class="qty-tela"><?php echo htmlspecialchars($kit['camiseta_tela']); ?></div>
                            <?php endif; ?>
                            <div class="qty-controls">
                                <button type="button" hidden class="qty-btn minus" onclick="cambiarQty('cant_polos',-1)"><i class="fas fa-minus"></i></button>
                                <input type="number" readonly class="qty-input" id="cant_polos" name="cant_polos" value="<?php echo $pedido['total_integrantes']; ?>" min="0" oninput="calcularTotal()">
                                <button type="button" hidden class="qty-btn plus" onclick="cambiarQty('cant_polos',1)"><i class="fas fa-plus"></i></button>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);">
                                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--muted);">Precio Unit.</label>
                                <span style="font-weight:700;color:#8b5cf6;">S/</span>
                                <input type="number" step="0.10" name="precio_polo" value="2.00" style="width:60px;padding:4px;border:1px solid var(--border);border-radius:4px;" oninput="calcularTotal()">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="qty-card">
                            <div class="qty-label">Shorts</div>
                            <div class="qty-type" style="background:rgba(6,214,160,.1);color:var(--success);"><?php echo $kit ? htmlspecialchars($kit['short_tipo']) : 'SHORTS'; ?></div>
                            <?php if ($kit && $kit['short_tela']): ?>
                            <div class="qty-tela"><?php echo htmlspecialchars($kit['short_tela']); ?></div>
                            <?php endif; ?>
                            <div class="qty-controls">
                                <button type="button" hidden class="qty-btn minus" onclick="cambiarQty('cant_shorts',-1)"><i class="fas fa-minus"></i></button>
                                <input type="number" readonly class="qty-input" id="cant_shorts" name="cant_shorts" value="<?php echo isset($countShorts) ? $countShorts['con_short'] : $pedido['total_integrantes']; ?>" min="0" oninput="calcularTotal()">
                                <button type="button" hidden class="qty-btn plus" onclick="cambiarQty('cant_shorts',1)"><i class="fas fa-plus"></i></button>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);">
                                <label style="font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--muted);">Precio Unit.</label>
                                <span style="font-weight:700;color:#8b5cf6;">S/</span>
                                <input type="number" step="0.10" name="precio_short" value="1.50" style="width:60px;padding:4px;border:1px solid var(--border);border-radius:4px;" oninput="calcularTotal()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="total-box">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="total-label">Polos/Camisetas</div>
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent);" id="subPolos">S/ 0.00</div>
                        </div>
                        <div class="col-md-4">
                            <div class="total-label">Shorts</div>
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent);" id="subShorts">S/ 0.00</div>
                        </div>
                        <div class="col-md-4">
                            <div class="total-label">Otros Costos</div>
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent);" id="subOtros">S/ 0.00</div>
                        </div>
                    </div>
                    <div style="height:1px;background:rgba(255,255,255,.1);margin:16px 0;"></div>
                    <div class="total-label">Pago Total al Costurero</div>
                    <div class="total-amount" id="totalPago">S/ 0.00</div>
                    <div style="font-size:.82rem;color:rgba(255,255,255,.4);margin-top:6px;">Total prendas: <strong id="totalPrendas">0</strong></div>
                </div>
            </div>
        </div>

        <!-- Sección de Otros Costos -->
        <div class="otros-card">
            <div class="otros-title"><i class="fas fa-plus-circle"></i> Otros Costos</div>
            
            <!-- Contenedor de items de otros costos -->
            <div id="otrosCostosContainer">
                <!-- Los items se agregarán dinámicamente aquí -->
            </div>

            <!-- Botón Agregar -->
            <button type="button" class="btn-agregar-otro" onclick="agregarOtroCosto()">
                <i class="fas fa-plus-circle"></i> Agregar Artículo
            </button>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:28px;">
            <a href="lista-pedidos.php" class="btn-v btn-outline-v">Cancelar</a>
            <button type="submit" class="btn-v btn-success-v" style="background:#8b5cf6;">
                <i class="fas fa-save"></i> Guardar Costura
            </button>
        </div>
    </form>
    
    <?php else: ?>
    <div class="card-v">
        <div class="card-v-header">
            <h5 class="card-v-title" style="color:#8b5cf6;"><i class="fas fa-file-contract" style="margin-right:8px;"></i>Seleccionar Pedido</h5>
        </div>
        <div class="card-v-body">
            <select class="field-ctrl" onchange="window.location.href='?pedido_id='+this.value">
                <option value="">— Seleccionar pedido pendiente —</option>
                <?php foreach ($pedidosPendientes as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo $p['codigo']; ?> · <?php echo htmlspecialchars($p['cliente']); ?> (<?php echo $p['total_integrantes']; ?> prendas)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
// Array para almacenar los otros costos
let otrosCostos = [];
let contadorOtrosCostos = 0;

function cambiarQty(id, delta) {
    const input = document.getElementById(id);
    const val = parseInt(input.value) || 0;
    input.value = Math.max(0, val + delta);
    calcularTotal();
}

// Función para agregar un nuevo artículo de "Otros Costos"
function agregarOtroCosto() {
    contadorOtrosCostos++;
    const id = contadorOtrosCostos;
    
    const itemHTML = `
        <div class="otros-item" id="otroCosto_${id}">
            <div class="otros-item-header">
                <span class="otros-item-num">Artículo #${id}</span>
                <button type="button" class="btn-remove-otro" onclick="eliminarOtroCosto(${id})" title="Eliminar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="field-lbl">Descripción</label>
                    <input type="text" class="field-ctrl" id="descOtros_${id}" placeholder="Ej: Refuerzo de costuras..." oninput="actualizarOtroCosto(${id})">
                </div>
                <div class="col-md-2">
                    <label class="field-lbl">Cantidad</label>
                    <input type="number" class="field-ctrl text-center" id="cantOtros_${id}" value="0" min="0" oninput="actualizarOtroCosto(${id})">
                </div>
                <div class="col-md-2">
                    <label class="field-lbl">Precio Unit. (S/)</label>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <span style="font-weight:700;color:var(--purple);font-size:.8rem;">S/</span>
                        <input type="number" class="field-ctrl" id="precioOtros_${id}" value="0.00" step="0.10" min="0" oninput="actualizarOtroCosto(${id})">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="field-lbl">Subtotal</label>
                    <div style="font-weight:700;color:var(--purple);font-size:1.1rem;padding-top:8px;" id="subtotalOtros_${id}">S/ 0.00</div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('otrosCostosContainer').insertAdjacentHTML('beforeend', itemHTML);
    
    // Agregar al array
    otrosCostos.push({
        id: id,
        descripcion: '',
        cantidad: 0,
        precio: 0,
        subtotal: 0
    });
    
    // Enfocar en el campo de descripción
    document.getElementById(`descOtros_${id}`).focus();
}

// Función para eliminar un artículo de "Otros Costos"
function eliminarOtroCosto(id) {
    const item = document.getElementById(`otroCosto_${id}`);
    if (item) {
        item.remove();
        otrosCostos = otrosCostos.filter(o => o.id !== id);
        calcularTotal();
    }
}

// Función para actualizar un artículo de "Otros Costos"
function actualizarOtroCosto(id) {
    const descInput = document.getElementById(`descOtros_${id}`);
    const cantInput = document.getElementById(`cantOtros_${id}`);
    const precioInput = document.getElementById(`precioOtros_${id}`);
    const subtotalEl = document.getElementById(`subtotalOtros_${id}`);
    
    if (!descInput || !cantInput || !precioInput) return;
    
    const cantidad = parseInt(cantInput.value) || 0;
    const precio = parseFloat(precioInput.value) || 0;
    const subtotal = cantidad * precio;
    
    subtotalEl.textContent = 'S/ ' + subtotal.toFixed(2);
    
    // Actualizar en el array
    const item = otrosCostos.find(o => o.id === id);
    if (item) {
        item.descripcion = descInput.value;
        item.cantidad = cantidad;
        item.precio = precio;
        item.subtotal = subtotal;
    }
    
    calcularTotal();
}

function calcularTotal() {
    const polos = parseInt(document.getElementById('cant_polos').value) || 0;
    const shorts = parseInt(document.getElementById('cant_shorts').value) || 0;
    const precioPolo = parseFloat(document.querySelector('[name="precio_polo"]').value) || 2.00;
    const precioShort = parseFloat(document.querySelector('[name="precio_short"]').value) || 1.50;
    
    const subPolos = polos * precioPolo;
    const subShorts = shorts * precioShort;
    
    // Calcular total de otros costos
    const subOtros = otrosCostos.reduce((sum, item) => sum + item.subtotal, 0);
    
    const total = subPolos + subShorts + subOtros;
    
    document.getElementById('subPolos').textContent = 'S/ ' + subPolos.toFixed(2);
    document.getElementById('subShorts').textContent = 'S/ ' + subShorts.toFixed(2);
    document.getElementById('subOtros').textContent = 'S/ ' + subOtros.toFixed(2);
    document.getElementById('totalPago').textContent = 'S/ ' + total.toFixed(2);
    document.getElementById('totalPrendas').textContent = polos + shorts;
    
    // Actualizar el input hidden con los otros costos
    const otrosCostosData = otrosCostos.map(o => ({
        descripcion: o.descripcion,
        cantidad: o.cantidad,
        precio: o.precio
    }));
    document.getElementById('otrosCostosInput').value = JSON.stringify(otrosCostosData);
}

// Validar formulario antes de enviar
document.getElementById('formCostura').addEventListener('submit', function(e) {
    // Actualizar el input hidden con los datos finales
    calcularTotal();
});

document.addEventListener('DOMContentLoaded', calcularTotal);
</script>
</body>
</html>
