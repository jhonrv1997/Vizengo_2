<?php
/**
 * VIZENGO - Registro de Integrantes por Cliente
 * Página pública para que el cliente registre sus datos usando un enlace dinámico
 */
require_once 'config.php';
startSecureSession();

$db = getDB();
$token = $_GET['token'] ?? '';
$error = '';
$enlaceInfo = null;
$success = false;

// Validar token
if (!empty($token)) {
    $stmt = $db->prepare("SELECT e.*, p.codigo, p.cliente_id, c.nombre as cliente_nombre,
                          (SELECT SUM(cantidad) FROM kits WHERE pedido_id = e.pedido_id) +
                          (SELECT COALESCE(SUM(cantidad), 0) FROM adicionales_talla WHERE pedido_id = e.pedido_id) as total_kits
                          FROM enlaces_registro e
                          JOIN pedidos p ON e.pedido_id = p.id
                          LEFT JOIN clientes c ON p.cliente_id = c.id
                          WHERE e.token = ?");
    $stmt->execute([$token]);
    $enlaceInfo = $stmt->fetch();

    if (!$enlaceInfo) {
        $error = 'El enlace no existe o es inválido.';
        $enlaceInfo = null;
    } elseif ($enlaceInfo['estado'] === 'usado') {
        $error = 'Este enlace ya ha sido utilizado. No puede volver a usarse.';
        $enlaceInfo = null;
    } elseif ($enlaceInfo['estado'] === 'expirado' || $enlaceInfo['estado'] === 'cancelado') {
        $error = 'Este enlace ya no está disponible.';
        $enlaceInfo = null;
    } elseif ($enlaceInfo['fecha_expiracion'] && strtotime($enlaceInfo['fecha_expiracion']) < time()) {
        // Marcar como expirado
        $stmt = $db->prepare("UPDATE enlaces_registro SET estado = 'expirado' WHERE id = ?");
        $stmt->execute([$enlaceInfo['id']]);
        $error = 'Este enlace ha expirado. Solicite uno nuevo al vendedor.';
        $enlaceInfo = null;
    }
} else {
    $error = 'No se proporcionó un enlace válido.';
}

// Procesar formulario - Solo si el enlace es válido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $enlaceInfo) {
    $pedidoId = intval($enlaceInfo['pedido_id']);
    $integrantesJson = $_POST['integrantes'] ?? '[]';
    $enlaceId = intval($enlaceInfo['id']);

    // VERIFICACIÓN DOBLE: Asegurar que el enlace sigue siendo válido (prevenir race conditions)
    $stmt = $db->prepare("SELECT estado FROM enlaces_registro WHERE id = ? FOR UPDATE");
    $stmt->execute([$enlaceId]);
    $estadoActual = $stmt->fetchColumn();
    
    if ($estadoActual !== 'pendiente') {
        $error = 'Este enlace ya no está disponible para su uso.';
        $enlaceInfo = null;
    } else {
        $db->beginTransaction();
        try {
            // PRIMERO: Marcar enlace como usado INMEDIATAMENTE (antes de procesar)
            // Esto previene que cualquier otra solicitud pueda usar el mismo enlace
            $ipCliente = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt = $db->prepare("UPDATE enlaces_registro SET estado = 'usado', fecha_uso = NOW(), ip_cliente = ? WHERE id = ?");
            $stmt->execute([$ipCliente, $enlaceId]);
            
            $integrantesData = json_decode($integrantesJson, true);
            $cantidadMaximaKits = intval($enlaceInfo['total_kits'] ?? 0);

            if (empty($integrantesData)) {
                throw new Exception("Debe registrar al menos un integrante.");
            }

            // Validación: Verificar límite de integrantes según kits
            if ($cantidadMaximaKits > 0 && count($integrantesData) > $cantidadMaximaKits) {
                throw new Exception("No se pueden registrar más de {$cantidadMaximaKits} integrantes.");
            }

            // Validación: Verificar campos obligatorios
            $erroresValidacion = [];
            $fila = 0;
            foreach ($integrantesData as $int) {
                $fila++;
                if (empty(trim($int['nombre'] ?? ''))) {
                    $erroresValidacion[] = "Fila {$fila}: El campo 'Nombre' es obligatorio.";
                }
                if (empty(trim($int['talla'] ?? ''))) {
                    $erroresValidacion[] = "Fila {$fila}: El campo 'Talla' es obligatorio.";
                }
                if (empty(trim($int['numero'] ?? ''))) {
                    $erroresValidacion[] = "Fila {$fila}: El campo 'Número' es obligatorio.";
                }
                if (empty(trim($int['sexo'] ?? ''))) {
                    $erroresValidacion[] = "Fila {$fila}: El campo 'Sexo' es obligatorio.";
                }
            }

            if (!empty($erroresValidacion)) {
                throw new Exception("Errores de validación:\n" . implode("\n", $erroresValidacion));
            }

            // Eliminar integrantes anteriores
            $stmt = $db->prepare("DELETE FROM integrantes WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);

            // Insertar nuevos integrantes
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

            // Actualizar estado del pedido
            $stmt = $db->prepare("UPDATE pedidos SET estado_integrantes = 'completo' WHERE id = ?");
            $stmt->execute([$pedidoId]);

            // Registrar en historial
            logActivity($pedidoId, $enlaceInfo['created_by'], 'INTEGRANTES_REGISTRADOS_CLIENTE', "Integrantes registrados por cliente vía enlace");

            $db->commit();
            $success = true;
            
            // IMPORTANTE: Invalidar enlaceInfo para que no se pueda volver a usar
            $enlaceInfo = null;

        } catch (Exception $e) {
            $db->rollBack();
            
            // Si hubo error, revertir el estado del enlace a pendiente para que el cliente pueda intentar de nuevo
            $stmt = $db->prepare("UPDATE enlaces_registro SET estado = 'pendiente', fecha_uso = NULL, ip_cliente = NULL WHERE id = ?");
            $stmt->execute([$enlaceId]);
            
            $error = 'Error al guardar: ' . $e->getMessage();
            
            // Recargar información del enlace para mostrar el formulario nuevamente
            $stmt = $db->prepare("SELECT e.*, p.codigo, p.cliente_id, c.nombre as cliente_nombre,
                                  (SELECT SUM(cantidad) FROM kits WHERE pedido_id = e.pedido_id) +
                                  (SELECT COALESCE(SUM(cantidad), 0) FROM adicionales_talla WHERE pedido_id = e.pedido_id) as total_kits
                                  FROM enlaces_registro e
                                  JOIN pedidos p ON e.pedido_id = p.id
                                  LEFT JOIN clientes c ON p.cliente_id = c.id
                                  WHERE e.id = ?");
            $stmt->execute([$enlaceId]);
            $enlaceInfo = $stmt->fetch();
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
    <style>
        :root {
            --primary: #2b4fff;
            --primary-dark: #1e3bd1;
            --success: #06d6a0;
            --warning: #ffc107;
            --danger: #ef4444;
            --text: #1a1a2e;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #ffffff;
            --sidebar-bg: #1a1a2e;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Barlow', sans-serif;
            background: linear-gradient(135deg, #f8faff 0%, #f0f4ff 100%);
            min-height: 100vh;
            color: var(--text);
        }

        .header-public {
            background: linear-gradient(135deg, var(--sidebar-bg), #2d2d44);
            padding: 20px 0;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .header-public .logo {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            letter-spacing: 3px;
        }

        .header-public .logo span {
            color: var(--primary);
        }

        .header-public .subtitle {
            color: rgba(255,255,255,0.7);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .main-container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card-v {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-v-header {
            background: linear-gradient(to right, #fafbff, #fff);
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-v-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-v-body {
            padding: 24px;
        }

        .btn-v {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }

        .btn-success-v {
            background: linear-gradient(135deg, var(--success), #05c896);
            color: white;
        }

        .btn-success-v:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6,214,160,0.3);
        }

        .btn-primary-v {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-outline-v {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
        }

        .btn-outline-v:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: rgba(43,79,255,0.05);
        }

        .field-ctrl {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-family: 'Barlow', sans-serif;
            font-size: 0.95rem;
            color: var(--text);
            background: var(--surface);
            transition: all 0.2s;
            outline: none;
        }

        .field-ctrl:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43,79,255,0.1);
        }

        /* Info card */
        .info-card {
            background: linear-gradient(135deg, rgba(43,79,255,0.08), rgba(43,79,255,0.02));
            border: 1px solid rgba(43,79,255,0.15);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .info-card i {
            font-size: 2rem;
            color: var(--primary);
        }

        .info-card .info-content h4 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.1rem;
            color: var(--text);
            margin-bottom: 4px;
        }

        .info-card .info-content p {
            font-size: 0.85rem;
            color: var(--muted);
            margin: 0;
        }

        /* Tabla integrantes */
        .integrantes-header {
            display: grid;
            grid-template-columns: 2.5fr 1fr 1fr 2fr 1fr 1fr 40px;
            gap: 8px;
            padding: 10px 16px;
            background: var(--sidebar-bg);
            border-radius: 10px 10px 0 0;
        }

        .integrantes-header div {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: rgba(255,255,255,0.5);
            text-align: center;
        }

        .integrantes-header div:first-child {
            text-align: left;
        }

        .integrante-row {
            display: grid;
            grid-template-columns: 2.5fr 1fr 1fr 2fr 1fr 1fr 40px;
            gap: 8px;
            padding: 8px 16px;
            border-bottom: 1px solid var(--border);
            align-items: center;
            transition: background 0.15s;
        }

        .integrante-row:hover {
            background: #f8faff;
        }

        .integrante-row:last-child {
            border-bottom: none;
        }

        .integrante-row input,
        .integrante-row select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 8px;
            font-family: 'Barlow', sans-serif;
            font-size: 0.82rem;
            color: var(--text);
            background: white;
            outline: none;
            transition: border-color 0.2s;
        }

        .integrante-row input:focus,
        .integrante-row select:focus {
            border-color: var(--primary);
        }

        .btn-del {
            background: none;
            border: 1px solid var(--border);
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--muted);
            transition: all 0.2s;
        }

        .btn-del:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: white;
        }

        /* Resumen */
        .size-badge {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: white;
            border: 2px solid var(--border);
            border-radius: 10px;
            min-width: 52px;
            padding: 6px 10px;
            margin: 4px;
            transition: all 0.2s;
        }

        .size-badge.active {
            border-color: var(--primary);
            background: rgba(43,79,255,0.06);
        }

        .size-name {
            font-size: 0.72rem;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
        }

        .size-count {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }

        /* Error state */
        .error-container {
            text-align: center;
            padding: 60px 20px;
        }

        .error-container i {
            font-size: 4rem;
            color: var(--danger);
            margin-bottom: 20px;
        }

        .error-container h2 {
            color: var(--text);
            margin-bottom: 10px;
        }

        .error-container p {
            color: var(--muted);
            max-width: 400px;
            margin: 0 auto 20px;
        }

        /* Success state */
        .success-container {
            text-align: center;
            padding: 60px 20px;
        }

        .success-container i {
            font-size: 5rem;
            color: var(--success);
            margin-bottom: 20px;
            animation: bounce 0.5s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .success-container h2 {
            color: var(--text);
            margin-bottom: 10px;
        }

        .success-container p {
            color: var(--muted);
            max-width: 400px;
            margin: 0 auto;
        }

        .success-warning {
            margin-top: 20px;
            padding: 15px 20px;
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 10px;
            display: inline-block;
        }

        .success-warning p {
            margin: 0;
            color: #b45309;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .integrantes-header,
            .integrante-row {
                grid-template-columns: 2fr 1fr 1fr 1fr 40px;
            }

            .integrantes-header div:nth-child(4),
            .integrantes-header div:nth-child(5),
            .integrante-row input:nth-child(4),
            .integrante-row select:nth-child(4) {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .header-public .logo {
                font-size: 1.8rem;
            }

            .card-v-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header-public">
    <div class="logo">VIZEN<span>GO</span></div>
    <div class="subtitle">Registro de Integrantes</div>
</div>

<div class="main-container">
    <?php if ($success): ?>
    <!-- Success State -->
    <div class="card-v">
        <div class="success-container">
            <i class="fas fa-check-circle"></i>
            <h2>¡Registro Exitoso!</h2>
            <p>Sus datos han sido registrados correctamente. El vendedor se pondrá en contacto con usted pronto.</p>
            <div class="success-warning">
                <p><i class="fas fa-lock" style="margin-right: 8px;"></i><strong>Importante:</strong> Este enlace ya no está disponible. Si necesita hacer cambios, contacte a su vendedor.</p>
            </div>
        </div>
    </div>

    <?php elseif (!$enlaceInfo): ?>
    <!-- Error State -->
    <div class="card-v">
        <div class="error-container">
            <i class="fas fa-exclamation-triangle"></i>
            <h2>Enlace No Válido</h2>
            <p><?php echo htmlspecialchars($error); ?></p>
            <p style="margin-top: 15px; font-size: 0.85rem;">
                Si tiene dudas, contacte a su vendedor de VIZENGO.
            </p>
        </div>
    </div>

    <?php else: ?>
    <!-- Formulario de Registro -->

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="info-card">
        <i class="fas fa-file-contract"></i>
        <div class="info-content">
            <h4>Pedido: #<?php echo htmlspecialchars($enlaceInfo['codigo']); ?></h4>
            <p>Cliente: <?php echo htmlspecialchars($enlaceInfo['cliente_nombre']); ?></p>
        </div>
    </div>

    <form method="POST" id="formIntegrantes">
        <input type="hidden" name="integrantes" id="integrantesJson">

        <div class="row g-4">
            <!-- Tabla de integrantes -->
            <div class="col-lg-9">
                <div class="card-v">
                    <div class="card-v-header">
                        <h5 class="card-v-title">
                            <i class="fas fa-users"></i>
                            Lista de Integrantes
                        </h5>
                        <button type="button" class="btn-v btn-outline-v" style="padding: 7px 14px; font-size: 0.8rem;" onclick="agregarFila()">
                            <i class="fas fa-plus"></i> Agregar fila
                        </button>
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
                    <div id="contenedorIntegrantes"></div>
                    <div style="padding: 12px 16px; border-top: 1px solid var(--border); text-align: center;">
                        <button type="button" class="btn-v btn-outline-v" style="padding: 8px 20px; font-size: 0.82rem;" onclick="agregarFila()">
                            <i class="fas fa-plus-circle"></i> Agregar otro integrante
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel resumen -->
            <div class="col-lg-3">
                <div class="card-v" style="position: sticky; top: 20px;">
                    <div class="card-v-header" style="background: linear-gradient(to right, #fafbff, #fff);">
                        <h5 class="card-v-title" style="color: var(--muted);">
                            <i class="fas fa-chart-pie" style="margin-right: 6px;"></i>
                            Resumen
                        </h5>
                    </div>
                    <div style="padding: 20px;">
                        <div style="background: rgba(43,79,255,0.06); border: 1px solid rgba(43,79,255,0.15); border-radius: 10px; padding: 16px; text-align: center; margin-bottom: 16px;">
                            <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 4px;">Total Integrantes</div>
                            <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 3rem; font-weight: 800; color: var(--primary); line-height: 1;" id="totalIntegrantes">0</div>
                            <?php if ($enlaceInfo['total_kits'] > 0): ?>
                            <div style="margin-top: 8px; font-size: 0.85rem; color: var(--muted);">
                                de <strong style="color: var(--primary);"><?php echo intval($enlaceInfo['total_kits']); ?></strong> permitidos
                            </div>
                            <?php endif; ?>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px;">
                            <div style="background: rgba(6,182,212,0.08); border: 2px solid rgba(6,182,212,0.3); border-radius: 10px; padding: 12px; text-align: center;">
                                <div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 2px;">
                                    <i class="fas fa-mars" style="color: #0891b2; margin-right: 4px;"></i>Varones
                                </div>
                                <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 2rem; font-weight: 800; color: #0891b2; line-height: 1;" id="totalVarones">0</div>
                            </div>
                            <div style="background: rgba(236,72,153,0.08); border: 2px solid rgba(236,72,153,0.3); border-radius: 10px; padding: 12px; text-align: center;">
                                <div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 2px;">
                                    <i class="fas fa-venus" style="color: #db2777; margin-right: 4px;"></i>Damas
                                </div>
                                <div style="font-family: 'Barlow Condensed', sans-serif; font-size: 2rem; font-weight: 800; color: #db2777; line-height: 1;" id="totalDamas">0</div>
                            </div>
                        </div>

                        <button type="submit" class="btn-v btn-success-v" style="width: 100%; justify-content: center;" id="btnEnviar">
                            <i class="fas fa-check-double"></i> Enviar Registro
                        </button>
                        <p style="text-align: center; margin-top: 12px; font-size: 0.75rem; color: var(--muted);">
                            <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                            Al enviar, este enlace quedará inhabilitado
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tallas = <?php echo json_encode($tallas); ?>;
const cantidadMaximaKits = <?php echo intval($enlaceInfo['total_kits'] ?? 0); ?>;

function crearFila() {
    const div = document.createElement('div');
    div.className = 'integrante-row';
    div.innerHTML = `
        <input type="text" placeholder="Nombre y Apellido">
        <select class="talla-select" onchange="actualizarResumen()">
            <option value="" disabled selected>—</option>
            ${tallas.map(t => `<option value="${t}">${t}</option>`).join('')}
        </select>
        <input type="text" placeholder="#" style="text-align: center;">
        <input type="text" placeholder="Ej: Arquero, Capitán...">
        <select><option>SÍ</option><option>NO</option></select>
        <select class="sexo-select" onchange="actualizarResumen()">
            <option value="">--</option>
            <option value="Varon">Varón</option>
            <option value="Dama">Dama</option>
        </select>
        <button type="button" class="btn-del" onclick="eliminarFila(this)" title="Eliminar">
            <i class="fas fa-trash"></i>
        </button>
    `;
    return div;
}

function agregarFila() {
    const totalActual = contarIntegrantesValidos();
    if (cantidadMaximaKits > 0 && totalActual >= cantidadMaximaKits) {
        alert(`No puede agregar más integrantes. El límite es de ${cantidadMaximaKits} integrantes.`);
        return;
    }
    document.getElementById('contenedorIntegrantes').appendChild(crearFila());
    actualizarResumen();
}

function contarIntegrantesValidos() {
    const rows = document.querySelectorAll('.integrante-row');
    let total = 0;
    rows.forEach(row => {
        const nombreInput = row.querySelector('input:first-child');
        if (nombreInput && nombreInput.value.trim()) {
            total++;
        }
    });
    return total;
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

// Al enviar formulario
document.getElementById('formIntegrantes')?.addEventListener('submit', function(e) {
    const rows = document.querySelectorAll('.integrante-row');
    const integrantes = [];
    const errores = [];

    rows.forEach((row, index) => {
        const inputs = row.querySelectorAll('input, select');
        const numFila = index + 1;
        const nombre = inputs[0].value.trim();

        if (nombre || inputs[1].value || inputs[2].value || inputs[5].value) {
            if (!nombre) {
                errores.push(`Fila ${numFila}: El campo 'Nombre' es obligatorio.`);
            }
            if (!inputs[1].value) {
                errores.push(`Fila ${numFila}: El campo 'Talla' es obligatorio.`);
            }
            if (!inputs[2].value.trim()) {
                errores.push(`Fila ${numFila}: El campo 'Número' es obligatorio.`);
            }
            if (!inputs[5].value) {
                errores.push(`Fila ${numFila}: El campo 'Sexo' es obligatorio.`);
            }

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

    if (integrantes.length === 0) {
        e.preventDefault();
        alert('Error: Debe registrar al menos un integrante con todos los campos obligatorios completos.');
        return false;
    }

    if (cantidadMaximaKits > 0 && integrantes.length > cantidadMaximaKits) {
        e.preventDefault();
        alert(`Error: Está intentando registrar ${integrantes.length} integrantes, pero el límite es de ${cantidadMaximaKits}.`);
        return false;
    }

    if (errores.length > 0) {
        e.preventDefault();
        alert('Errores de validación:\n\n' + errores.join('\n'));
        return false;
    }

    // Deshabilitar botón para evitar doble envío
    const btn = document.getElementById('btnEnviar');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    }

    document.getElementById('integrantesJson').value = JSON.stringify(integrantes);
});

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    const filasIniciales = cantidadMaximaKits > 0 ? cantidadMaximaKits : 5;
    for (let i = 0; i < filasIniciales; i++) {
        agregarFila();
    }
});
</script>
</body>
</html>
