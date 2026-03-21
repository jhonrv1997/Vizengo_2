<?php
/**
 * VIZENGO - Registro de Integrantes
 * Paso 2 del pipeline: Registrar tallas y números
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

// Solo vendedores y admins
if (!in_array($user['rol'], ['vendedor', 'administrador'])) {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();

// Obtener pedido seleccionado
$pedidoId = intval($_GET['pedido_id'] ?? 0);

// Si no hay pedido, obtener pedidos pendientes de integrantes
if ($pedidoId === 0) {
    $stmt = $db->prepare("SELECT p.id, p.codigo, c.nombre as cliente, 
                          (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes
                          FROM pedidos p 
                          LEFT JOIN clientes c ON p.cliente_id = c.id
                          WHERE p.estado_integrantes != 'completo'
                          ORDER BY p.fecha_entrega ASC");
    $stmt->execute();
    $pedidosPendientes = $stmt->fetchAll();
} else {
    // Cargar datos del pedido
    $stmt = $db->prepare("SELECT p.*, c.nombre as cliente FROM pedidos p 
                          LEFT JOIN clientes c ON p.cliente_id = c.id WHERE p.id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        header('Location: lista-pedidos.php');
        exit();
    }
    
    // Cargar integrantes existentes
    $stmt = $db->prepare("SELECT * FROM integrantes WHERE pedido_id = ? ORDER BY id");
    $stmt->execute([$pedidoId]);
    $integrantes = $stmt->fetchAll();
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pedidoId = intval($_POST['pedido_id'] ?? 0);
    $integrantesJson = $_POST['integrantes'] ?? '[]';
    $imagenLista = $_POST['imagen_lista'] ?? '';
    
    if ($pedidoId > 0) {
        $db->beginTransaction();
        try {
            // Si hay imagen de lista
            if (!empty($imagenLista)) {
                // Guardar imagen y marcar como completo
                $stmt = $db->prepare("INSERT INTO imagenes_integrantes (pedido_id, imagen_path) VALUES (?, ?) 
                                      ON DUPLICATE KEY UPDATE imagen_path = ?");
                // Extraer y guardar imagen base64
                if (preg_match('/^data:image\/(\w+);base64,/', $imagenLista, $type)) {
                    $data = substr($imagenLista, strpos($imagenLista, ',') + 1);
                    $type = strtolower($type[1]);
                    $data = base64_decode($data);
                    $filename = uniqid() . '.' . $type;
                    $filepath = UPLOAD_PATH . 'integrantes/' . $filename;
                    if (!is_dir(dirname($filepath))) mkdir(dirname($filepath), 0755, true);
                    file_put_contents($filepath, $data);
                    $stmt->execute([$pedidoId, 'uploads/integrantes/' . $filename, 'uploads/integrantes/' . $filename]);
                }
            }
            
            // Guardar integrantes de la tabla
            $integrantesData = json_decode($integrantesJson, true);
            if (!empty($integrantesData)) {
                // Eliminar anteriores
                $stmt = $db->prepare("DELETE FROM integrantes WHERE pedido_id = ?");
                $stmt->execute([$pedidoId]);
                
                // Insertar nuevos
                $stmt = $db->prepare("INSERT INTO integrantes (pedido_id, nombre, talla, numero, observacion, incluye_short, sexo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($integrantesData as $int) {
                    if (!empty($int['nombre'])) {
                        $stmt->execute([
                            $pedidoId,
                            sanitize($int['nombre']),
                            sanitize($int['talla'] ?? ''),
                            sanitize($int['numero'] ?? ''),
                            sanitize($int['observacion'] ?? ''),
                            intval($int['incluye_short'] ?? 1),
                            sanitize($int['sexo'] ?? 'Varon')
                        ]);
                    }
                }
            }
            
            // Actualizar estado
            $stmt = $db->prepare("UPDATE pedidos SET estado_integrantes = 'completo' WHERE id = ?");
            $stmt->execute([$pedidoId]);
            
            $db->commit();
            header('Location: diseno.php?pedido_id=' . $pedidoId . '&saved=1');
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$tallas = ['2','4','6','8','10','12','14','16','XS','S','M','L','XL','XXL'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Registro de Integrantes</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Pipeline tracker */
        .pipeline-tracker{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 28px;margin-bottom:24px;display:flex;align-items:center;justify-content:center;}
        .pt-step{display:flex;flex-direction:column;align-items:center;text-align:center;position:relative;z-index:1;flex:1;max-width:120px;}
        .pt-circle{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;border:2px solid var(--border);background:var(--surface);color:var(--muted);transition:all .3s;margin-bottom:8px;}
        .pt-step.done .pt-circle{background:var(--success);border-color:var(--success);color:white;}
        .pt-step.active .pt-circle{background:var(--primary);border-color:var(--primary);color:white;box-shadow:0 0 0 5px rgba(43,79,255,.15);}
        .pt-label{font-family:'Barlow Condensed',sans-serif;font-size:.78rem;font-weight:700;text-transform:uppercase;}
        .pt-step.done .pt-label{color:var(--success);}
        .pt-step.active .pt-label{color:var(--primary);}
        .pt-step.pending .pt-label{color:var(--muted);}
        .pt-line{flex:1;height:2px;background:var(--border);max-width:60px;align-self:flex-start;margin-top:22px;}
        .pt-line.done{background:var(--success);}
        
        /* Selector de pedido */
        .pedido-selector{background:linear-gradient(135deg,rgba(43,79,255,.06),rgba(43,79,255,.02));border:1px solid rgba(43,79,255,.2);border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;}
        .ps-label{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:4px;}
        .ps-value{font-family:'Barlow Condensed',sans-serif;font-size:1.1rem;font-weight:800;color:var(--text);}
        
        /* Opción subir imagen */
        .opcion-imagen-card{background:linear-gradient(135deg,rgba(6,214,160,.06),rgba(6,214,160,.02));border:2px dashed rgba(6,214,160,.4);border-radius:12px;padding:16px 20px;margin-bottom:20px;}
        .opcion-imagen-card.modo-imagen{border-style:solid;border-color:var(--success);background:linear-gradient(135deg,rgba(6,214,160,.1),rgba(6,214,160,.05));}
        .opcion-toggle{display:flex;align-items:center;gap:12px;cursor:pointer;}
        .opcion-toggle input[type="checkbox"]{width:20px;height:20px;cursor:pointer;accent-color:var(--success);}
        .opcion-toggle-text{font-family:'Barlow Condensed',sans-serif;font-size:1rem;font-weight:700;color:var(--text);letter-spacing:.5px;}
        .opcion-toggle-text span{font-weight:400;color:var(--muted);font-size:.85rem;}
        .upload-zone{margin-top:16px;border:2px dashed var(--border);border-radius:10px;padding:24px;text-align:center;transition:all .2s;background:white;}
        .upload-zone:hover{border-color:var(--primary);background:rgba(43,79,255,.02);}
        .upload-zone.has-file{border-color:var(--success);background:rgba(6,214,160,.05);}
        .upload-icon{font-size:2.5rem;color:var(--muted);margin-bottom:8px;}
        .upload-zone.has-file .upload-icon{color:var(--success);}
        .upload-text{font-size:.85rem;color:var(--muted);}
        .upload-filename{font-weight:600;color:var(--success);margin-top:8px;}
        .upload-preview{margin-top:12px;max-width:100%;border-radius:8px;max-height:200px;display:none;}
        .upload-preview.show{display:block;}
        
        /* Tabla integrantes */
        .integrantes-header{display:grid;grid-template-columns:2.5fr 1fr 1fr 2fr 1fr 1fr 40px;gap:8px;padding:10px 16px;background:var(--sidebar-bg);border-radius:10px 10px 0 0;}
        .integrantes-header div{font-family:'Barlow Condensed',sans-serif;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.5);text-align:center;}
        .integrantes-header div:first-child{text-align:left;}
        .integrante-row{display:grid;grid-template-columns:2.5fr 1fr 1fr 2fr 1fr 1fr 40px;gap:8px;padding:8px 16px;border-bottom:1px solid var(--border);align-items:center;transition:background .15s;}
        .integrante-row:hover{background:#f8faff;}
        .integrante-row:last-child{border-bottom:none;}
        .integrante-row input,.integrante-row select{width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 8px;font-family:'Barlow',sans-serif;font-size:.82rem;color:var(--text);background:white;outline:none;transition:border-color .2s;}
        .integrante-row input:focus,.integrante-row select:focus{border-color:var(--primary);}
        .btn-del{background:none;border:1px solid var(--border);border-radius:6px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted);transition:all .2s;}
        .btn-del:hover{background:var(--danger);border-color:var(--danger);color:white;}
        
        /* Resumen tallas */
        .size-badge{display:inline-flex;flex-direction:column;align-items:center;justify-content:center;background:white;border:2px solid var(--border);border-radius:10px;min-width:52px;padding:6px 10px;margin:4px;transition:all .2s;}
        .size-badge.active{border-color:var(--primary);background:rgba(43,79,255,.06);}
        .size-name{font-size:.72rem;color:var(--muted);font-weight:700;text-transform:uppercase;}
        .size-count{font-family:'Barlow Condensed',sans-serif;font-size:1.4rem;font-weight:800;color:var(--primary);line-height:1;}
        
        /* Disabled state */
        .disabled-section{opacity:0.5;pointer-events:none;}
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-users" style="color:var(--primary);margin-right:10px;"></i>Registro de Integrantes</h1>
            <p>Paso 2: Ingresa los datos de cada integrante del equipo</p>
        </div>
        <a href="lista-pedidos.php" class="btn-v btn-outline-v"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <!-- Pipeline tracker -->
    <div class="pipeline-tracker">
        <div class="pt-step done"><div class="pt-circle"><i class="fas fa-check"></i></div><div class="pt-label">Contrato</div></div>
        <div class="pt-line done"></div>
        <div class="pt-step active"><div class="pt-circle"><i class="fas fa-users"></i></div><div class="pt-label">Integrantes</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle">3</div><div class="pt-label">Diseño</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle">4</div><div class="pt-label">Planchado</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle">5</div><div class="pt-label">Costura</div></div>
        <div class="pt-line"></div>
        <div class="pt-step pending"><div class="pt-circle">6</div><div class="pt-label">Entrega</div></div>
    </div>

    <?php if (!isset($pedido) && !empty($pedidosPendientes)): ?>
    <!-- Selector de pedido -->
    <div class="pedido-selector">
        <i class="fas fa-file-contract" style="font-size:1.5rem;color:var(--primary);"></i>
        <div style="flex:1;">
            <div class="ps-label">Seleccionar Pedido</div>
            <select class="field-ctrl" onchange="seleccionarPedido(this.value)">
                <option value="">— Seleccionar pedido pendiente —</option>
                <?php foreach ($pedidosPendientes as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo $p['codigo']; ?> · <?php echo htmlspecialchars($p['cliente']); ?> (<?php echo $p['total_integrantes']; ?> registrados)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($pedido)): ?>
    
    <form method="POST" id="formIntegrantes" enctype="multipart/form-data">
        <input type="hidden" name="pedido_id" value="<?php echo $pedido['id']; ?>">
        <input type="hidden" name="integrantes" id="integrantesJson">
        <input type="hidden" name="imagen_lista" id="imagenLista">
        
    <!-- Pedido activo -->
    <div class="pedido-selector">
        <i class="fas fa-file-contract" style="font-size:1.5rem;color:var(--primary);"></i>
        <div style="flex:1;">
            <div class="ps-label">Pedido Activo</div>
            <div class="ps-value">#<?php echo $pedido['codigo']; ?> · <?php echo htmlspecialchars($pedido['cliente']); ?></div>
        </div>
        <div style="text-align:right;">
            <div class="ps-label">Tipo</div>
            <div class="ps-value"><?php echo $pedido['tipo_contrato']; ?></div>
        </div>
    </div>

    <!-- Opción subir imagen -->
    <div class="opcion-imagen-card" id="opcionImagenCard">
        <label class="opcion-toggle">
            <input type="checkbox" id="chkSubirImagen" onchange="toggleModoImagen()">
            <span class="opcion-toggle-text">
                <i class="fas fa-camera" style="margin-right:8px;color:var(--success);"></i>
                Subir lista de integrantes como imagen 
                <span>(opcional - foto de la lista firmada)</span>
            </span>
        </label>
        <div class="upload-zone" id="uploadZone" style="display:none;" onclick="document.getElementById('inputImagen').click()">
            <input type="file" id="inputImagen" accept="image/*" style="display:none;" onchange="handleFileSelect(this)">
            <i class="fas fa-cloud-upload-alt upload-icon"></i>
            <div class="upload-text">Arrastra una imagen aquí o <strong>haz clic para seleccionar</strong></div>
            <div class="upload-filename" id="uploadFilename" style="display:none;"></div>
            <img class="upload-preview" id="uploadPreview" alt="Vista previa">
        </div>
    </div>

    <div class="row g-4">
        <!-- Tabla de integrantes -->
        <div class="col-lg-9">
            <div class="card-v" id="tablaIntegrantesCard">
                <div class="card-v-header">
                    <h5 class="card-v-title"><i class="fas fa-users" style="margin-right:8px;"></i>Lista de Integrantes</h5>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn-v btn-outline-v" style="padding:7px 14px;font-size:.8rem;" onclick="agregarFila()">
                            <i class="fas fa-plus"></i> Agregar fila
                        </button>
                    </div>
                </div>
                <div class="integrantes-header">
                    <div>Nombre y Apellido</div>
                    <div>Talla</div>
                    <div>Número</div>
                    <div>Observación</div>
                    <div>Short</div>
                    <div>Sexo</div>
                    <div><i class="fas fa-cog"></i></div>
                </div>
                <div id="contenedorIntegrantes">
                    <?php foreach ($integrantes as $i): ?>
                    <div class="integrante-row">
                        <input type="text" value="<?php echo htmlspecialchars($i['nombre']); ?>" placeholder="Nombre">
                        <select onchange="actualizarResumen()">
                            <option value="">—</option>
                            <?php foreach ($tallas as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $i['talla'] === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" value="<?php echo htmlspecialchars($i['numero']); ?>" placeholder="#" style="text-align:center;">
                        <input type="text" value="<?php echo htmlspecialchars($i['observacion'] ?? ''); ?>" placeholder="Ej: Arquero...">
                        <select><option <?php echo $i['incluye_short'] ? 'selected' : ''; ?>>SÍ</option><option <?php echo !$i['incluye_short'] ? 'selected' : ''; ?>>NO</option></select>
                        <select class="sexo-select" onchange="actualizarResumen()"><option value="">--</option><option value="Varon" <?php echo $i['sexo'] === 'Varon' ? 'selected' : ''; ?>>Varón</option><option value="Dama" <?php echo $i['sexo'] === 'Dama' ? 'selected' : ''; ?>>Dama</option></select>
                        <button type="button" class="btn-del" onclick="eliminarFila(this)"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:12px 16px;border-top:1px solid var(--border);text-align:center;">
                    <button type="button" class="btn-v btn-outline-v" style="padding:8px 20px;font-size:.82rem;" onclick="agregarFila()">
                        <i class="fas fa-plus-circle"></i> Agregar otro integrante
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel resumen -->
        <div class="col-lg-3">
            <div class="card-v" style="position:sticky;top:20px;">
                <div class="card-v-header" style="background:linear-gradient(to right,#fafbff,#fff);">
                    <h5 class="card-v-title" style="color:var(--muted);"><i class="fas fa-chart-pie" style="margin-right:6px;"></i>Resumen</h5>
                </div>
                <div style="padding:20px;">
                    <div style="background:rgba(43,79,255,.06);border:1px solid rgba(43,79,255,.15);border-radius:10px;padding:16px;text-align:center;margin-bottom:16px;">
                        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:4px;">Total Integrantes</div>
                        <div style="font-family:'Barlow Condensed',sans-serif;font-size:3rem;font-weight:800;color:var(--primary);line-height:1;" id="totalIntegrantes"><?php echo count($integrantes); ?></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
                        <div style="background:rgba(6,182,212,.08);border:2px solid rgba(6,182,212,.3);border-radius:10px;padding:12px;text-align:center;">
                            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:2px;"><i class="fas fa-mars" style="color:#0891b2;margin-right:4px;"></i>Varones</div>
                            <div style="font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:800;color:#0891b2;line-height:1;" id="totalVarones">0</div>
                        </div>
                        <div style="background:rgba(236,72,153,.08);border:2px solid rgba(236,72,153,.3);border-radius:10px;padding:12px;text-align:center;">
                            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:2px;"><i class="fas fa-venus" style="color:#db2777;margin-right:4px;"></i>Damas</div>
                            <div style="font-family:'Barlow Condensed',sans-serif;font-size:2rem;font-weight:800;color:#db2777;line-height:1;" id="totalDamas">0</div>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <button type="submit" class="btn-v btn-success-v" style="width:100%;justify-content:center;">
                            <i class="fas fa-check-double"></i> Confirmar y Guardar
                        </button>
                        <a href="diseno.php?pedido_id=<?php echo $pedido['id']; ?>" class="btn-v btn-primary-v" style="width:100%;justify-content:center;text-decoration:none;">
                            <i class="fas fa-paint-brush"></i> Ir a Diseño
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>
    
    <?php elseif (empty($pedidosPendientes) && !isset($pedido)): ?>
    <div class="card-v">
        <div class="card-v-body" style="text-align:center;padding:60px;">
            <i class="fas fa-check-circle" style="font-size:4rem;color:var(--success);margin-bottom:16px;opacity:0.5;"></i>
            <h3 style="color:var(--text);margin-bottom:8px;">¡No hay pedidos pendientes!</h3>
            <p style="color:var(--muted);">Todos los pedidos tienen sus integrantes registrados.</p>
            <a href="ingreso-pedido.php" class="btn-v btn-primary-v" style="margin-top:20px;"><i class="fas fa-plus"></i> Nuevo Pedido</a>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
const tallas = <?php echo json_encode($tallas); ?>;

function crearFila() {
    const div = document.createElement('div');
    div.className = 'integrante-row';
    div.innerHTML = `
        <input type="text" placeholder="Nombre y Apellido">
        <select class="talla-select" onchange="actualizarResumen()">
            <option value="" disabled selected>—</option>
            ${tallas.map(t=>`<option value="${t}">${t}</option>`).join('')}
        </select>
        <input type="text" placeholder="#" style="text-align:center;">
        <input type="text" placeholder="Ej: Arquero, Capitán...">
        <select><option>SÍ</option><option>NO</option></select>
        <select class="sexo-select" onchange="actualizarResumen()"><option value="">--</option><option value="Varon">Varón</option><option value="Dama">Dama</option></select>
        <button type="button" class="btn-del" onclick="eliminarFila(this)" title="Eliminar"><i class="fas fa-trash"></i></button>
    `;
    return div;
}

function agregarFila() {
    document.getElementById('contenedorIntegrantes').appendChild(crearFila());
    actualizarResumen();
}

function eliminarFila(btn) {
    btn.closest('.integrante-row').remove();
    actualizarResumen();
}

function actualizarResumen() {
    const rows = document.querySelectorAll('.integrante-row');
    let total = 0, varones = 0, damas = 0;
    
    rows.forEach(row => {
        const nombreInput = row.querySelector('input:first-child');
        if (nombreInput && nombreInput.value.trim()) {
            total++;
            const sexo = row.querySelector('.sexo-select')?.value || '';
            if (sexo === 'Varon') varones++;
            else if (sexo === 'Dama') damas++;
        }
    });
    
    document.getElementById('totalIntegrantes').textContent = total;
    document.getElementById('totalVarones').textContent = varones;
    document.getElementById('totalDamas').textContent = damas;
}

function toggleModoImagen() {
    const chk = document.getElementById('chkSubirImagen');
    const uploadZone = document.getElementById('uploadZone');
    const opcionCard = document.getElementById('opcionImagenCard');
    const tablaCard = document.getElementById('tablaIntegrantesCard');

    if (chk.checked) {
        uploadZone.style.display = 'block';
        opcionCard.classList.add('modo-imagen');
        tablaCard.classList.add('disabled-section');
    } else {
        uploadZone.style.display = 'none';
        opcionCard.classList.remove('modo-imagen');
        tablaCard.classList.remove('disabled-section');
    }
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagenLista').value = e.target.result;
            document.getElementById('uploadFilename').textContent = file.name;
            document.getElementById('uploadFilename').style.display = 'block';
            document.getElementById('uploadPreview').src = e.target.result;
            document.getElementById('uploadPreview').classList.add('show');
            document.getElementById('uploadZone').classList.add('has-file');
            document.getElementById('uploadZone').querySelector('.upload-icon').className = 'fas fa-check-circle upload-icon';
        };
        reader.readAsDataURL(file);
    }
}

function seleccionarPedido(id) {
    if (id) window.location.href = '?pedido_id=' + id;
}

// Al enviar formulario
document.getElementById('formIntegrantes')?.addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.integrante-row');
    const integrantes = [];
    rows.forEach(row => {
        const inputs = row.querySelectorAll('input, select');
        if (inputs[0].value.trim()) {
            integrantes.push({
                nombre: inputs[0].value,
                talla: inputs[1].value,
                numero: inputs[2].value,
                observacion: inputs[3].value,
                incluye_short: inputs[4].value === 'SÍ' ? 1 : 0,
                sexo: inputs[5].value
            });
        }
    });
    document.getElementById('integrantesJson').value = JSON.stringify(integrantes);
});

// Iniciar con filas vacías si no hay integrantes
document.addEventListener('DOMContentLoaded', function() {
    actualizarResumen();
    <?php if (empty($integrantes)): ?>
    for (let i = 0; i < 12; i++) agregarFila();
    <?php endif; ?>
});
</script>
</body>
</html>
