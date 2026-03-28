<?php
/**
 * VIZENGO - API de Enlaces de Registro
 * Gestión de enlaces dinámicos para registro de integrantes por cliente
 */
require_once '../config.php';
setCorsHeaders();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Función para generar token único
function generarTokenUnico() {
    return bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
}

// Función para generar URL del enlace
function generarUrlEnlace($token) {
    return SITE_URL . '/registro-cliente.php?token=' . $token;
}

switch ($method) {
    case 'GET':
        // Validar enlace o listar enlaces de un pedido
        if (isset($_GET['token'])) {
            // Validar un enlace específico
            validarEnlace($_GET['token']);
        } elseif (isset($_GET['pedido_id'])) {
            // Listar enlaces de un pedido
            listarEnlacesPedido(intval($_GET['pedido_id']));
        } else {
            errorResponse('Parámetro requerido: token o pedido_id');
        }
        break;

    case 'POST':
        // Generar nuevo enlace
        generarEnlace($input);
        break;

    case 'PUT':
        // Marcar enlace como usado o cancelar
        actualizarEnlace($input);
        break;

    case 'DELETE':
        // Cancelar enlace
        if (isset($_GET['id'])) {
            cancelarEnlace(intval($_GET['id']));
        } else {
            errorResponse('ID de enlace requerido');
        }
        break;

    default:
        errorResponse('Método no permitido', 405);
}

/**
 * Validar si un enlace es válido y está activo
 */
function validarEnlace($token) {
    global $db;

    if (empty($token)) {
        errorResponse('Token requerido');
    }

    $stmt = $db->prepare("SELECT e.*, p.codigo, p.cliente_id, c.nombre as cliente_nombre,
                          (SELECT SUM(cantidad) FROM kits WHERE pedido_id = e.pedido_id) +
                          (SELECT SUM(cantidad) FROM adicionales_talla WHERE pedido_id = e.pedido_id) as total_kits
                          FROM enlaces_registro e
                          JOIN pedidos p ON e.pedido_id = p.id
                          LEFT JOIN clientes c ON p.cliente_id = c.id
                          WHERE e.token = ?");
    $stmt->execute([$token]);
    $enlace = $stmt->fetch();

    if (!$enlace) {
        errorResponse('Enlace no encontrado', 404);
    }

    // Verificar estado
    if ($enlace['estado'] === 'usado') {
        errorResponse('Este enlace ya ha sido utilizado', 410);
    }

    if ($enlace['estado'] === 'expirado' || $enlace['estado'] === 'cancelado') {
        errorResponse('Este enlace ya no está disponible', 410);
    }

    // Verificar fecha de expiración
    if ($enlace['fecha_expiracion'] && strtotime($enlace['fecha_expiracion']) < time()) {
        // Marcar como expirado
        $stmt = $db->prepare("UPDATE enlaces_registro SET estado = 'expirado' WHERE id = ?");
        $stmt->execute([$enlace['id']]);
        errorResponse('Este enlace ha expirado', 410);
    }

    // Devolver información del enlace
    successResponse([
        'valido' => true,
        'enlace' => [
            'id' => $enlace['id'],
            'pedido_codigo' => $enlace['codigo'],
            'cliente_nombre' => $enlace['cliente_nombre'],
            'total_kits' => intval($enlace['total_kits'] ?? 0),
            'fecha_expiracion' => $enlace['fecha_expiracion']
        ]
    ], 'Enlace válido');
}

/**
 * Listar enlaces de un pedido
 */
function listarEnlacesPedido($pedidoId) {
    global $db;

    $stmt = $db->prepare("SELECT e.*, u.nombre as creado_por_nombre
                          FROM enlaces_registro e
                          LEFT JOIN usuarios u ON e.created_by = u.id
                          WHERE e.pedido_id = ?
                          ORDER BY e.fecha_creacion DESC");
    $stmt->execute([$pedidoId]);
    $enlaces = $stmt->fetchAll();

    successResponse(['enlaces' => $enlaces], 'Enlaces encontrados');
}

/**
 * Generar nuevo enlace de registro
 */
function generarEnlace($input) {
    global $db;

    // Verificar autenticación
    startSecureSession();
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        errorResponse('No autorizado', 401);
    }

    $pedidoId = intval($input['pedido_id'] ?? 0);
    $expiracionHoras = intval($input['expiracion_horas'] ?? 72); // Default 72 horas

    if ($pedidoId <= 0) {
        errorResponse('ID de pedido requerido');
    }

    // Verificar que el pedido existe
    $stmt = $db->prepare("SELECT id, codigo FROM pedidos WHERE id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        errorResponse('Pedido no encontrado', 404);
    }

    // Generar token único
    $token = generarTokenUnico();
    $urlEnlace = generarUrlEnlace($token);
    $fechaExpiracion = date('Y-m-d H:i:s', strtotime("+{$expiracionHoras} hours"));

    // Insertar enlace
    $stmt = $db->prepare("INSERT INTO enlaces_registro (pedido_id, token, url_enlace, fecha_expiracion, created_by)
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $pedidoId,
        $token,
        $urlEnlace,
        $fechaExpiracion,
        $_SESSION['user_id']
    ]);

    $enlaceId = $db->lastInsertId();

    // Registrar en historial
    logActivity($pedidoId, $_SESSION['user_id'], 'ENLACE_GENERADO', "Enlace de registro generado: {$urlEnlace}");

    successResponse([
        'id' => $enlaceId,
        'token' => $token,
        'url_enlace' => $urlEnlace,
        'fecha_expiracion' => $fechaExpiracion,
        'pedido_codigo' => $pedido['codigo']
    ], 'Enlace generado exitosamente');
}

/**
 * Actualizar estado de enlace (marcar como usado)
 */
function actualizarEnlace($input) {
    global $db;

    $token = $input['token'] ?? '';
    $accion = $input['accion'] ?? '';

    if (empty($token)) {
        errorResponse('Token requerido');
    }

    // Obtener enlace
    $stmt = $db->prepare("SELECT * FROM enlaces_registro WHERE token = ?");
    $stmt->execute([$token]);
    $enlace = $stmt->fetch();

    if (!$enlace) {
        errorResponse('Enlace no encontrado', 404);
    }

    if ($accion === 'usar') {
        // Marcar como usado
        $ipCliente = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $db->prepare("UPDATE enlaces_registro SET estado = 'usado', fecha_uso = NOW(), ip_cliente = ? WHERE id = ?");
        $stmt->execute([$ipCliente, $enlace['id']]);

        successResponse(['enlace_id' => $enlace['id']], 'Enlace marcado como usado');
    } else {
        errorResponse('Acción no válida');
    }
}

/**
 * Cancelar enlace
 */
function cancelarEnlace($enlaceId) {
    global $db;

    // Verificar autenticación
    startSecureSession();
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        errorResponse('No autorizado', 401);
    }

    $stmt = $db->prepare("UPDATE enlaces_registro SET estado = 'cancelado' WHERE id = ? AND estado = 'pendiente'");
    $stmt->execute([$enlaceId]);

    if ($stmt->rowCount() > 0) {
        successResponse(['enlace_id' => $enlaceId], 'Enlace cancelado');
    } else {
        errorResponse('No se pudo cancelar el enlace (puede que ya no esté pendiente)', 400);
    }
}
