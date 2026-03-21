<?php
/**
 * VIZENGO - Subir Diseño Final
 * Paso 3 del pipeline
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

// Obtener pedidos pendientes de diseño
$stmt = $db->query("SELECT p.id, p.codigo, c.nombre as cliente, 
                    (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes,
                    p.tipo_contrato, p.observaciones_diseno
                    FROM pedidos p 
                    LEFT JOIN clientes c ON p.cliente_id = c.id
                    WHERE p.estado_integrantes = 'completo' AND p.estado_diseno != 'completo'
                    ORDER BY p.fecha_entrega ASC");
$pedidosPendientes = $stmt->fetchAll();

// Procesar subida de diseño
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = intval($_POST['pedido_id'] ?? 0);
    $tipo = sanitize($_POST['tipo'] ?? '');
    $imagenBase64 = $_POST['imagen'] ?? '';
    $observaciones = sanitize($_POST['observaciones'] ?? '');
    
    if ($pedidoId > 0 && !empty($tipo) && !empty($imagenBase64)) {
        // Guardar imagen
        if (preg_match('/^data:image\/(\w+);base64,/', $imagenBase64, $type)) {
            $data = substr($imagenBase64, strpos($imagenBase64, ',') + 1);
            $extension = strtolower($type[1]);
            $data = base64_decode($data);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filepath = UPLOAD_PATH . 'disenos/' . $filename;
            if (!is_dir(dirname($filepath))) mkdir(dirname($filepath), 0755, true);
            file_put_contents($filepath, $data);
            
            // Guardar en BD
            $stmt = $db->prepare("INSERT INTO disenos_finales (pedido_id, disenador_id, tipo, imagen_path, observaciones, aprobado) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$pedidoId, $user['id'], $tipo, 'uploads/disenos/' . $filename, $observaciones]);
            
            // Verificar si ya están todos los diseños
            $stmt = $db->prepare("SELECT COUNT(DISTINCT tipo) as tipos FROM disenos_finales WHERE pedido_id = ? AND aprobado = 1");
            $stmt->execute([$pedidoId]);
            $result = $stmt->fetch();
            
            if ($result['tipos'] >= 2) { // Al menos camiseta y short
                $stmt = $db->prepare("UPDATE pedidos SET estado_diseno = 'completo' WHERE id = ?");
                $stmt->execute([$pedidoId]);
            }
            
            $success = 'Diseño subido exitosamente';
        }
    }
}

// Obtener pedido específico
$pedidoId = intval($_GET['pedido_id'] ?? 0);
$pedido = null;
$integrantes = [];
$disenosActuales = [];

if ($pedidoId > 0) {
    $stmt = $db->prepare("SELECT p.*, c.nombre as cliente FROM pedidos p LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    
    if ($pedido) {
        $stmt = $db->prepare("SELECT * FROM integrantes WHERE pedido_id = ? ORDER BY id");
        $stmt->execute([$pedidoId]);
        $integrantes = $stmt->fetchAll();
        
        $stmt = $db->prepare("SELECT * FROM disenos_finales WHERE pedido_id = ?");
        $stmt->execute([$pedidoId]);
        $disenosActuales = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Subir Diseño</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .pipeline-tracker{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:center;}
        .pt-step{display:flex;flex-direction:column;align-items:center;text-align:center;position:relative;z-index:1;flex:1;max-width:160px;}
        .pt-circle{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;border:2px solid var(--border);background:var(--surface);color:var(--muted);transition:all .3s;margin-bottom:8px;}
        .pt-step.done .pt-circle{background:var(--success);border-color:var(--success);color:white;}
        .pt-step.active .pt-circle{background:var(--primary);border-color:var(--primary);color:white;box-shadow:0 0 0 5px rgba(43,79,255,.15);}
        .pt-label{font-family:'Barlow Condensed',sans-serif;font-size:.82rem;font-weight:700;text-transform:uppercase;}
        .pt-step.done .pt-label{color:var(--success);}
        .pt-step.active .pt-label{color:var(--primary);}
        .pt-step.pending .pt-label{color:var(--muted);}
        .pt-line{flex:1;height:2px;background:var(--border);max-width:80px;align-self:flex-start;margin-top:22px;}
        .pt-line.done{background:var(--success);}
        
        /* Upload zones */
        .upload-grid{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:auto auto;gap:16px;margin-bottom:20px;}
        .upload-sector{border-radius:14px;border:2px dashed var(--border);background:#fafbff;padding:20px;text-align:center;cursor:pointer;transition:all .3s;position:relative;min-height:180px;display:flex;flex-direction:column;align-items:center;justify-content:center;}
        .upload-sector.camiseta-grande{grid-row:span 2;min-height:380px;}
        .upload-sector:hover{border-color:var(--primary);background:rgba(43,79,255,.03);}
        .upload-sector.has-image{border-color:var(--success);border-style:solid;background:rgba(6,214,160,.04);}
        .sector-label{font-family:'Barlow Condensed',sans-serif;font-size:.85rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-top:10px;}
        .sector-sub{font-size:.75rem;color:var(--muted);margin-top:4px;}
        .sector-icon{font-size:2rem;color:rgba(43,79,255,.25);}
        .sector-preview{width:100%;height:160px;object-fit:cover;border-radius:8px;display:none;}
        .upload-sector.camiseta-grande .sector-preview{height:320px;}
        .sector-badge{position:absolute;top:8px;left:8px;background:var(--success);color:white;border-radius:20px;padding:2px 10px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;display:none;}
        .sector-badge.show{display:block;}
        
        /* Integrantes tabla */
        .integrantes-tabla{width:100%;border-collapse:collapse;}
        .integrantes-tabla thead th{background:var(--sidebar-bg);color:rgba(255,255,255,.5);padding:8px 14px;font-family:'Barlow Condensed',sans-serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;}
        .integrantes-tabla tbody tr{border-bottom:1px solid var(--border);}
        .integrantes-tabla tbody tr:hover{background:#f8faff;}
        .integrantes-tabla tbody td{padding:9px 14px;font-size:.85rem;vertical-align:middle;}
        .talla-badge{display:inline-block;background:rgba(43,79,255,.1);color:var(--primary);border-radius:6px;padding:2px 8px;font-family:'Barlow Condensed',sans-serif;font-weight:700;font-size:.88rem;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-paint-brush" style="color:var(--primary);margin-right:10px;"></i>Subir Diseño Final</h1>
            <p>Paso 3: Revisa el pedido y sube los diseños aprobados</p>
        </div>
        <a href="lista-pedidos.php" class="btn-v btn-outline-v"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <!-- Pipeline -->
    <div class="pipeline-tracker">
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Contrato</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Integrantes</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step active"><div class="pt-circle"><i class="fas fa-paint-brush"></i></div><div class="pt-label">Diseño</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle"><i class="fas fa-tshirt"></i></div><div class="pt-label">Planchado</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle"><i class="fas fa-cut"></i></div><div class="pt-label">Costura</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle"><i class="fas fa-box"></i></div><div class="pt-label">Entrega</div></div>
    </div>

    <!-- Selector de pedido -->
    <div class="card-v">
        <div class="card-v-header">
            <h5 class="card-v-title"><i class="fas fa-info-circle" style="margin-right:8px;"></i>Pedido de Trabajo</h5>
        </div>
        <div class="card-v-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="field-lbl">Seleccionar Pedido</label>
                    <select class="field-ctrl" id="selectPedido" onchange="seleccionarPedido(this.value)">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($pedidosPendientes as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $pedidoId === $p['id'] ? 'selected' : ''; ?>><?php echo $p['codigo']; ?> · <?php echo htmlspecialchars($p['cliente']); ?> (<?php echo $p['total_integrantes']; ?> prendas)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7">
                    <?php if ($pedido): ?>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="field-lbl">Cliente</label>
                            <div style="font-weight:700;font-size:.9rem;padding-top:8px;"><?php echo htmlspecialchars($pedido['cliente']); ?></div>
                        </div>
                        <div class="col-4">
                            <label class="field-lbl">Prendas</label>
                            <div style="font-weight:700;font-size:.9rem;padding-top:8px;"><?php echo count($integrantes); ?></div>
                        </div>
                        <div class="col-4">
                            <label class="field-lbl">Entrega</label>
                            <div style="font-weight:700;font-size:.9rem;padding-top:8px;color:var(--danger);"><?php echo formatDate($pedido['fecha_entrega'], 'd/m/Y'); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pedido): ?>
    <!-- Upload de diseños -->
    <div class="card-v">
        <div class="card-v-header">
            <h5 class="card-v-title"><i class="fas fa-images" style="margin-right:8px;"></i>Diseños Finales (máx. 3 fotos)</h5>
            <span style="font-size:.78rem;color:var(--muted);">Haz clic en cada sector para subir</span>
        </div>
        <div class="card-v-body">
            <form method="POST" id="formDiseno">
                <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
                <input type="hidden" name="imagen" id="imagenBase64">
                
                <div class="upload-grid">
                    <!-- Camiseta -->
                    <div class="upload-sector camiseta-grande" id="sector-camiseta" onclick="document.getElementById('file-camiseta').click()">
                        <input type="file" id="file-camiseta" hidden accept="image/*" onchange="handleUpload(this, 'camiseta')">
                        <div class="sector-badge" id="badge-camiseta">✓ Subido</div>
                        <img class="sector-preview" id="preview-camiseta">
                        <i class="fas fa-tshirt sector-icon" id="icon-camiseta"></i>
                        <div class="sector-label" id="label-camiseta">Camiseta</div>
                        <div class="sector-sub">Diseño frontal y posterior</div>
                    </div>
                    <!-- Short -->
                    <div class="upload-sector" id="sector-short" onclick="document.getElementById('file-short').click()">
                        <input type="file" id="file-short" hidden accept="image/*" onchange="handleUpload(this, 'short')">
                        <div class="sector-badge" id="badge-short">✓ Subido</div>
                        <img class="sector-preview" id="preview-short">
                        <i class="fas fa-running sector-icon" id="icon-short"></i>
                        <div class="sector-label" id="label-short">Short</div>
                        <div class="sector-sub">Diseño del short</div>
                    </div>
                    <!-- Banderola -->
                    <div class="upload-sector" id="sector-banderola" onclick="document.getElementById('file-banderola').click()">
                        <input type="file" id="file-banderola" hidden accept="image/*" onchange="handleUpload(this, 'banderola')">
                        <div class="sector-badge" id="badge-banderola">✓ Subido</div>
                        <img class="sector-preview" id="preview-banderola">
                        <i class="fas fa-flag sector-icon" id="icon-banderola"></i>
                        <div class="sector-label" id="label-banderola">Banderola</div>
                        <div class="sector-sub">Si corresponde al pedido</div>
                    </div>
                </div>

                <input type="hidden" name="tipo" id="tipoDiseno">
                <div class="field-group">
                    <label class="field-lbl">Observaciones del Diseño</label>
                    <textarea class="field-ctrl" name="observaciones" rows="2" placeholder="Notas sobre el diseño..."><?php echo htmlspecialchars($pedido['observaciones_diseno'] ?? ''); ?></textarea>
                </div>
            </form>
            
            <!-- Integrantes -->
            <h5 style="font-family:'Barlow Condensed',sans-serif;font-weight:800;text-transform:uppercase;letter-spacing:1px;margin:20px 0 12px;">Integrantes Registrados</h5>
            <div style="overflow-x:auto;">
                <table class="integrantes-tabla">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nombre</th>
                            <th>Talla</th>
                            <th>Número</th>
                            <th>Sexo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($integrantes as $i => $int): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo htmlspecialchars($int['nombre']); ?></td>
                            <td><span class="talla-badge"><?php echo htmlspecialchars($int['talla']); ?></span></td>
                            <td><?php echo htmlspecialchars($int['numero']); ?></td>
                            <td><?php echo htmlspecialchars($int['sexo']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card-v">
        <div class="card-v-body" style="text-align:center;padding:60px;">
            <i class="fas fa-paint-brush" style="font-size:4rem;color:var(--primary);margin-bottom:16px;opacity:0.3;"></i>
            <h3 style="color:var(--text);margin-bottom:8px;">Selecciona un pedido</h3>
            <p style="color:var(--muted);">Elige un pedido pendiente de diseño para comenzar.</p>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function seleccionarPedido(id) {
    if (id) window.location.href = '?pedido_id=' + id;
}

function handleUpload(input, tipo) {
    const file = input.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        // Mostrar preview
        document.getElementById('preview-' + tipo).src = e.target.result;
        document.getElementById('preview-' + tipo).style.display = 'block';
        document.getElementById('icon-' + tipo).style.display = 'none';
        document.getElementById('badge-' + tipo).classList.add('show');
        document.getElementById('sector-' + tipo).classList.add('has-image');
        
        // Guardar para envío
        document.getElementById('imagenBase64').value = e.target.result;
        document.getElementById('tipoDiseno').value = tipo;
        
        // Enviar formulario
        document.getElementById('formDiseno').submit();
    };
    reader.readAsDataURL(file);
}
</script>
</body>
</html>
