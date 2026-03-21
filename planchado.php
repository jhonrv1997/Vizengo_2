<?php
/**
 * VIZENGO - Registro de Planchado
 * Paso 4 del pipeline
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

// Obtener pedidos pendientes de planchado
$stmt = $db->query("SELECT p.id, p.codigo, c.nombre as cliente, p.fecha_entrega,
                    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes
                    FROM pedidos p 
                    LEFT JOIN clientes c ON p.cliente_id = c.id
                    WHERE p.estado_diseno = 'completo' AND p.estado_planchado != 'completo'
                    ORDER BY p.fecha_entrega ASC");
$pedidosPendientes = $stmt->fetchAll();

// Obtener planchadores
$stmt = $db->query("SELECT * FROM planchadores WHERE activo = 1 ORDER BY nombre");
$planchadores = $stmt->fetchAll();

// Obtener pedido específico
$pedidoId = intval($_GET['pedido_id'] ?? 0);
$pedido = null;

if ($pedidoId > 0) {
    $stmt = $db->prepare("SELECT p.*, c.nombre as cliente, c.celular as cliente_celular,
                          (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes
                          FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = intval($_POST['pedido_id'] ?? 0);
    $planchadorNombre = sanitize($_POST['planchador_nombre'] ?? '');
    $cantPolos = intval($_POST['cant_polos'] ?? 0);
    $cantShorts = intval($_POST['cant_shorts'] ?? 0);
    $cantCuellos = intval($_POST['cant_cuellos'] ?? 0);
    $precioPolo = floatval($_POST['precio_polo'] ?? 1.50);
    $precioShort = floatval($_POST['precio_short'] ?? 1.00);
    $precioCuello = floatval($_POST['precio_cuello'] ?? 0.50);
    $observaciones = sanitize($_POST['observaciones'] ?? '');
    $fechaPlanchado = $_POST['fecha_planchado'] ?? date('Y-m-d');
    
    $totalPago = ($cantPolos * $precioPolo) + ($cantShorts * $precioShort) + ($cantCuellos * $precioCuello);
    
    if ($pedidoId > 0 && !empty($planchadorNombre)) {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO planchado (pedido_id, planchador_nombre, cant_polos, cant_shorts, cant_cuellos, precio_polo, precio_short, precio_cuello, total_pago, observaciones, fecha_planchado) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE 
                                  planchador_nombre = VALUES(planchador_nombre), cant_polos = VALUES(cant_polos), cant_shorts = VALUES(cant_shorts), cant_cuellos = VALUES(cant_cuellos),
                                  precio_polo = VALUES(precio_polo), precio_short = VALUES(precio_short), precio_cuello = VALUES(precio_cuello), total_pago = VALUES(total_pago), observaciones = VALUES(observaciones), fecha_planchado = VALUES(fecha_planchado)");
            $stmt->execute([$pedidoId, $planchadorNombre, $cantPolos, $cantShorts, $cantCuellos, $precioPolo, $precioShort, $precioCuello, $totalPago, $observaciones, $fechaPlanchado]);
            
            $stmt = $db->prepare("UPDATE pedidos SET estado_planchado = 'completo' WHERE id = ?");
            $stmt->execute([$pedidoId]);
            
            $db->commit();
            header('Location: costura.php?pedido_id=' . $pedidoId . '&saved=1');
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
    <title>VIZENGO - Registro de Planchado</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .pipeline-tracker{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 16px;margin-bottom:24px;display:flex;align-items:center;justify-content:center;}
        .pt-step{display:flex;flex-direction:column;align-items:center;text-align:center;flex:1;max-width:130px;}
        .pt-circle{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;border:2px solid var(--border);background:var(--surface);color:var(--muted);transition:all .3s;margin-bottom:6px;}
        .pt-step.done .pt-circle{background:var(--success);border-color:var(--success);color:white;}
        .pt-step.active .pt-circle{background:var(--info);border-color:var(--info);color:white;box-shadow:0 0 0 5px rgba(56,189,248,.15);}
        .pt-label{font-family:'Barlow Condensed',sans-serif;font-size:.75rem;font-weight:700;text-transform:uppercase;}
        .pt-step.done .pt-label{color:var(--success);}
        .pt-step.active .pt-label{color:var(--info);}
        .pt-step.pending .pt-label{color:var(--muted);}
        .pt-line{flex:1;height:2px;background:var(--border);max-width:60px;align-self:flex-start;margin-top:20px;}
        .pt-line.done{background:var(--success);}
        
        /* Contador */
        .qty-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:20px;text-align:center;}
        .qty-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:8px;}
        .qty-controls{display:flex;align-items:center;justify-content:center;gap:12px;}
        .qty-btn{width:40px;height:40px;border-radius:50%;border:none;cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;transition:all .2s;}
        .qty-btn.plus{background:var(--success);color:white;}
        .qty-btn.minus{background:var(--danger);color:white;}
        .qty-input{width:70px;text-align:center;font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:800;border:2px solid var(--border);border-radius:10px;padding:6px;outline:none;color:var(--text);}
        .price-input-group{display:flex;align-items:center;gap:6px;margin-top:10px;padding-top:10px;border-top:1px dashed var(--border);}
        
        /* Total box */
        .total-box{background:var(--sidebar-bg);border-radius:14px;padding:24px;color:white;text-align:center;}
        .total-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.4);margin-bottom:8px;}
        .total-amount{font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:800;color:var(--success);line-height:1;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-tshirt" style="color:var(--info);margin-right:10px;"></i>Registro de Planchado</h1>
            <p>Paso 4: Registra las prendas planchadas y el pago</p>
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
        <div class="pt-step active"><div class="pt-circle"><i class="fas fa-tshirt"></i></div><div class="pt-label">Planchado</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle"><i class="fas fa-cut"></i></div><div class="pt-label">Costura</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle"><i class="fas fa-box"></i></div><div class="pt-label">Entrega</div></div>
    </div>

    <?php if ($pedido): ?>
    <form method="POST" id="formPlanchado">
        <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
        
        <!-- Info del pedido -->
        <div class="card-v">
            <div class="card-v-header">
                <h5 class="card-v-title" style="color:var(--info);"><i class="fas fa-file-contract" style="margin-right:8px;"></i>Pedido a Planchar</h5>
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

        <!-- Datos del planchador -->
        <div class="card-v">
            <div class="card-v-header">
                <h5 class="card-v-title" style="color:var(--info);"><i class="fas fa-user-cog" style="margin-right:8px;"></i>Datos del Planchador</h5>
            </div>
            <div class="card-v-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Nombre del Planchador <span style="color:var(--danger);">*</span></label>
                            <select class="field-ctrl" name="planchador_nombre" required>
                                <option value="" disabled selected>Seleccione...</option>
                                <?php foreach ($planchadores as $p): ?>
                                <option value="<?php echo htmlspecialchars($p['nombre']); ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                                <?php endforeach; ?>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Fecha de Trabajo</label>
                            <input type="date" class="field-ctrl" name="fecha_planchado" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="field-group" style="margin-bottom:0;">
                            <label class="field-lbl">Observaciones</label>
                            <textarea class="field-ctrl" name="observaciones" rows="2" placeholder="Notas sobre el planchado..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cantidades -->
        <div class="card-v">
            <div class="card-v-header">
                <h5 class="card-v-title" style="color:var(--info);"><i class="fas fa-layer-group" style="margin-right:8px;"></i>Cantidades de Prendas</h5>
            </div>
            <div class="card-v-body">
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="qty-card">
                            <div class="qty-label">Polos / Camisetas</div>
                            <div class="qty-controls">
                                <button type="button" class="qty-btn minus" onclick="cambiarQty('cant_polos',-1)"><i class="fas fa-minus"></i></button>
                                <input type="number" class="qty-input" id="cant_polos" name="cant_polos" value="<?php echo $pedido['total_integrantes']; ?>" min="0" oninput="calcularTotal()">
                                <button type="button" class="qty-btn plus" onclick="cambiarQty('cant_polos',1)"><i class="fas fa-plus"></i></button>
                            </div>
                            <div class="price-input-group">
                                <label>Precio Unit.</label>
                                <span>S/</span>
                                <input type="number" step="0.10" name="precio_polo" value="1.50" style="width:60px;padding:4px;border:1px solid var(--border);border-radius:4px;" oninput="calcularTotal()">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="qty-card">
                            <div class="qty-label">Shorts</div>
                            <div class="qty-controls">
                                <button type="button" class="qty-btn minus" onclick="cambiarQty('cant_shorts',-1)"><i class="fas fa-minus"></i></button>
                                <input type="number" class="qty-input" id="cant_shorts" name="cant_shorts" value="<?php echo $pedido['total_integrantes']; ?>" min="0" oninput="calcularTotal()">
                                <button type="button" class="qty-btn plus" onclick="cambiarQty('cant_shorts',1)"><i class="fas fa-plus"></i></button>
                            </div>
                            <div class="price-input-group">
                                <label>Precio Unit.</label>
                                <span>S/</span>
                                <input type="number" step="0.10" name="precio_short" value="1.00" style="width:60px;padding:4px;border:1px solid var(--border);border-radius:4px;" oninput="calcularTotal()">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="qty-card">
                            <div class="qty-label">Cuellos RIB</div>
                            <div class="qty-controls">
                                <button type="button" class="qty-btn minus" onclick="cambiarQty('cant_cuellos',-1)"><i class="fas fa-minus"></i></button>
                                <input type="number" class="qty-input" id="cant_cuellos" name="cant_cuellos" value="0" min="0" oninput="calcularTotal()">
                                <button type="button" class="qty-btn plus" onclick="cambiarQty('cant_cuellos',1)"><i class="fas fa-plus"></i></button>
                            </div>
                            <div class="price-input-group">
                                <label>Precio Unit.</label>
                                <span>S/</span>
                                <input type="number" step="0.10" name="precio_cuello" value="0.50" style="width:60px;padding:4px;border:1px solid var(--border);border-radius:4px;" oninput="calcularTotal()">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="total-box">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="total-label">Polos</div>
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent);" id="subPolos">S/ 0.00</div>
                        </div>
                        <div class="col-md-4">
                            <div class="total-label">Shorts</div>
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent);" id="subShorts">S/ 0.00</div>
                        </div>
                        <div class="col-md-4">
                            <div class="total-label">Cuellos</div>
                            <div style="font-size:1.2rem;font-weight:700;color:var(--accent);" id="subCuellos">S/ 0.00</div>
                        </div>
                    </div>
                    <div style="height:1px;background:rgba(255,255,255,.1);margin:16px 0;"></div>
                    <div class="total-label">Pago Total al Planchador</div>
                    <div class="total-amount" id="totalPago">S/ 0.00</div>
                    <div style="font-size:.82rem;color:rgba(255,255,255,.4);margin-top:6px;">Total prendas: <strong id="totalPrendas">0</strong></div>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:28px;">
            <a href="lista-pedidos.php" class="btn-v btn-outline-v">Cancelar</a>
            <button type="submit" class="btn-v btn-success-v" style="background:var(--info);">
                <i class="fas fa-save"></i> Guardar Planchado
            </button>
        </div>
    </form>
    
    <?php else: ?>
    <div class="card-v">
        <div class="card-v-header">
            <h5 class="card-v-title" style="color:var(--info);"><i class="fas fa-file-contract" style="margin-right:8px;"></i>Seleccionar Pedido</h5>
        </div>
        <div class="card-v-body">
            <select class="field-ctrl" onchange="window.location.href='?pedido_id='+this.value">
                <option value="">— Seleccionar pedido pendiente —</option>
                <?php foreach ($pedidosPendientes as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo $p['codigo']; ?> · <?php echo htmlspecialchars($p['cliente']); ?> (<?php echo $p['total_integrantes']; ?> prendas) - Entrega: <?php echo formatDate($p['fecha_entrega'], 'd/m'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function cambiarQty(id, delta) {
    const input = document.getElementById(id);
    const val = parseInt(input.value) || 0;
    input.value = Math.max(0, val + delta);
    calcularTotal();
}

function calcularTotal() {
    const polos = parseInt(document.getElementById('cant_polos').value) || 0;
    const shorts = parseInt(document.getElementById('cant_shorts').value) || 0;
    const cuellos = parseInt(document.getElementById('cant_cuellos').value) || 0;
    
    const precioPolo = parseFloat(document.querySelector('[name="precio_polo"]').value) || 1.50;
    const precioShort = parseFloat(document.querySelector('[name="precio_short"]').value) || 1.00;
    const precioCuello = parseFloat(document.querySelector('[name="precio_cuello"]').value) || 0.50;
    
    const subPolos = polos * precioPolo;
    const subShorts = shorts * precioShort;
    const subCuellos = cuellos * precioCuello;
    const total = subPolos + subShorts + subCuellos;
    
    document.getElementById('subPolos').textContent = 'S/ ' + subPolos.toFixed(2);
    document.getElementById('subShorts').textContent = 'S/ ' + subShorts.toFixed(2);
    document.getElementById('subCuellos').textContent = 'S/ ' + subCuellos.toFixed(2);
    document.getElementById('totalPago').textContent = 'S/ ' + total.toFixed(2);
    document.getElementById('totalPrendas').textContent = polos + shorts + cuellos;
}

document.addEventListener('DOMContentLoaded', calcularTotal);
</script>
</body>
</html>
