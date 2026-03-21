<?php
/**
 * VIZENGO - API de Pedidos
 * CRUD completo para gestión de pedidos
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
startSecureSession();
setCorsHeaders();

// Verificar autenticación para todas las acciones
$user = requireAuth();

// Determinar la acción
$action = $_GET['action'] ?? '';

// Método HTTP
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            listPedidos();
            break;
        case 'get':
            getPedido();
            break;
        case 'create':
            createPedido();
            break;
        case 'update':
            updatePedido();
            break;
        case 'delete':
            deletePedido();
            break;
        case 'update_estado':
            updateEstado();
            break;
        case 'dashboard':
            getDashboard();
            break;
        case 'integrantes':
            handleIntegrantes();
            break;
        case 'diseno':
            handleDiseno();
            break;
        case 'planchado':
            handlePlanchado();
            break;
        case 'costura':
            handleCostura();
            break;
        case 'entrega':
            handleEntrega();
            break;
        default:
            errorResponse('Acción no válida', 400);
    }
} catch (Exception $e) {
    if (DEV_MODE) {
        errorResponse('Error: ' . $e->getMessage(), 500);
    } else {
        errorResponse('Error interno del servidor', 500);
    }
}

/**
 * Listar pedidos con filtros
 */
function listPedidos() {
    global $user;
    $db = getDB();
    
    // Filtros
    $estado = sanitize($_GET['estado'] ?? '');
    $search = sanitize($_GET['search'] ?? '');
    $rol = $user['rol'];
    $userId = $user['id'];
    
    // Query base con joins
    $sql = "SELECT 
                p.id, p.codigo, p.tipo_contrato, p.lugar_entrega,
                p.estado_contrato, p.estado_integrantes, p.estado_diseno,
                p.estado_planchado, p.estado_costura, p.estado_general,
                p.fecha_pedido, p.fecha_entrega,
                p.subtotal, p.adelanto, p.saldo,
                c.nombre as cliente, c.celular as cliente_celular,
                u.nombre as vendedor,
                (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_integrantes
            FROM pedidos p
            LEFT JOIN clientes c ON p.cliente_id = c.id
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Filtro por rol
    if ($rol === 'vendedor') {
        $sql .= " AND p.usuario_id = ?";
        $params[] = $userId;
    } elseif ($rol === 'disenador') {
        $sql .= " AND p.estado_contrato = 'completo' AND p.estado_integrantes = 'completo'";
    }
    
    // Filtro por estado
    if (!empty($estado)) {
        $sql .= " AND p.estado_general = ?";
        $params[] = $estado;
    }
    
    // Búsqueda
    if (!empty($search)) {
        $sql .= " AND (p.codigo LIKE ? OR c.nombre LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY p.fecha_pedido DESC, p.fecha_entrega ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
    
    // Formatear datos
    foreach ($pedidos as &$pedido) {
        $pedido['fecha_pedido_fmt'] = formatDate($pedido['fecha_pedido'], 'd/m/Y H:i');
        $pedido['fecha_entrega_fmt'] = formatDate($pedido['fecha_entrega'], 'd/m/Y');
        $pedido['subtotal_fmt'] = formatCurrency($pedido['subtotal']);
        $pedido['adelanto_fmt'] = formatCurrency($pedido['adelanto']);
        $pedido['saldo_fmt'] = formatCurrency($pedido['saldo']);
    }
    
    successResponse(['pedidos' => $pedidos, 'total' => count($pedidos)]);
}

/**
 * Obtener un pedido específico
 */
function getPedido() {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    
    // Obtener pedido
    $stmt = $db->prepare("SELECT p.*, c.nombre as cliente, c.celular as cliente_celular, 
                          c.email as cliente_email, c.direccion as cliente_direccion,
                          u.nombre as vendedor
                          FROM pedidos p
                          LEFT JOIN clientes c ON p.cliente_id = c.id
                          LEFT JOIN usuarios u ON p.usuario_id = u.id
                          WHERE p.id = ?");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        errorResponse('Pedido no encontrado', 404);
    }
    
    // Obtener kits
    $stmt = $db->prepare("SELECT * FROM kits WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['kits'] = $stmt->fetchAll();
    
    // Obtener integrantes
    $stmt = $db->prepare("SELECT * FROM integrantes WHERE pedido_id = ? ORDER BY id");
    $stmt->execute([$id]);
    $pedido['integrantes'] = $stmt->fetchAll();
    
    // Obtener diseños
    $stmt = $db->prepare("SELECT * FROM disenos_finales WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['disenos'] = $stmt->fetchAll();
    
    // Obtener planchado
    $stmt = $db->prepare("SELECT * FROM planchado WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['planchado'] = $stmt->fetch();
    
    // Obtener costura
    $stmt = $db->prepare("SELECT * FROM costura WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['costura'] = $stmt->fetch();
    
    // Obtener adicionales de talla
    $stmt = $db->prepare("SELECT * FROM adicionales_talla WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['adicionales_talla'] = $stmt->fetchAll();
    
    // Obtener merchandising
    $stmt = $db->prepare("SELECT * FROM merchandising WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['merchandising'] = $stmt->fetchAll();
    
    // Obtener entrega
    $stmt = $db->prepare("SELECT * FROM entregas WHERE pedido_id = ?");
    $stmt->execute([$id]);
    $pedido['entrega'] = $stmt->fetch();
    
    successResponse(['pedido' => $pedido]);
}

/**
 * Crear nuevo pedido
 */
function createPedido() {
    global $user;
    requireRole(['vendedor', 'administrador']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Crear o buscar cliente
        $clienteNombre = sanitize($input['cliente_nombre'] ?? '');
        $clienteCelular = sanitize($input['cliente_celular'] ?? '');
        
        if (empty($clienteNombre)) {
            throw new Exception('El nombre del cliente es requerido');
        }
        
        // Buscar o crear cliente
        $stmt = $db->prepare("SELECT id FROM clientes WHERE nombre = ?");
        $stmt->execute([$clienteNombre]);
        $cliente = $stmt->fetch();
        
        if ($cliente) {
            $clienteId = $cliente['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO clientes (nombre, celular) VALUES (?, ?)");
            $stmt->execute([$clienteNombre, $clienteCelular]);
            $clienteId = $db->lastInsertId();
        }
        
        // Generar código de pedido
        $codigo = generatePedidoCode();
        
        // Insertar pedido
        $stmt = $db->prepare("INSERT INTO pedidos (
            codigo, cliente_id, usuario_id, tipo_contrato, lugar_entrega,
            direccion_envio, vendedor_asignado, celular_cliente,
            observaciones_generales, observaciones_diseno,
            fecha_entrega, subtotal, adelanto, saldo, estado_contrato
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completo')");
        
        $stmt->execute([
            $codigo,
            $clienteId,
            $user['id'],
            sanitize($input['tipo_contrato'] ?? 'PEDIDO'),
            sanitize($input['lugar_entrega'] ?? 'TIENDA VIZENGO'),
            sanitize($input['direccion_envio'] ?? ''),
            sanitize($input['vendedor_asignado'] ?? $user['nombre']),
            sanitize($input['celular_cliente'] ?? $clienteCelular),
            sanitize($input['observaciones_generales'] ?? ''),
            sanitize($input['observaciones_diseno'] ?? ''),
            $input['fecha_entrega'] ?? null,
            floatval($input['subtotal'] ?? 0),
            floatval($input['adelanto'] ?? 0),
            floatval($input['saldo'] ?? 0)
        ]);
        
        $pedidoId = $db->lastInsertId();
        
        // Insertar kits si existen
        if (!empty($input['kits']) && is_array($input['kits'])) {
            $stmtKit = $db->prepare("INSERT INTO kits (
                pedido_id, camiseta_tipo, camiseta_tela, camiseta_talla,
                short_tipo, short_tela, short_talla, medias_tipo, medias_detalles,
                cantidad, precio_unitario
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($input['kits'] as $kit) {
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
                    intval($kit['cantidad'] ?? 1),
                    floatval($kit['precio_unitario'] ?? 0)
                ]);
            }
        }
        
        // Insertar adicionales de talla si existen
        if (!empty($input['adicionales']) && is_array($input['adicionales'])) {
            $stmtAdicional = $db->prepare("INSERT INTO adicionales_talla (
                pedido_id, talla, cantidad, precio_unitario
            ) VALUES (?, ?, ?, ?)");
            
            foreach ($input['adicionales'] as $adicional) {
                $stmtAdicional->execute([
                    $pedidoId,
                    sanitize($adicional['talla'] ?? ''),
                    intval($adicional['cantidad'] ?? 1),
                    floatval($adicional['precio_unitario'] ?? 0)
                ]);
            }
        }
        
        // Insertar merchandising si existe
        if (!empty($input['merchandising']) && is_array($input['merchandising'])) {
            $stmtMerch = $db->prepare("INSERT INTO merchandising (
                pedido_id, articulo, cantidad, precio_unitario, es_regalo, especificaciones
            ) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($input['merchandising'] as $merch) {
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
        
        // Log de actividad
        logActivity($pedidoId, $user['id'], 'PEDIDO_CREADO', "Pedido {$codigo} creado");
        
        $db->commit();
        
        successResponse([
            'pedido_id' => $pedidoId,
            'codigo' => $codigo
        ], 'Pedido creado exitosamente');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Actualizar pedido
 */
function updatePedido() {
    global $user;
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Actualizar campos básicos
        $stmt = $db->prepare("UPDATE pedidos SET 
            tipo_contrato = ?, lugar_entrega = ?, direccion_envio = ?,
            observaciones_generales = ?, observaciones_diseno = ?,
            fecha_entrega = ?, subtotal = ?, adelanto = ?, saldo = ?
            WHERE id = ?");
        
        $stmt->execute([
            sanitize($input['tipo_contrato'] ?? 'PEDIDO'),
            sanitize($input['lugar_entrega'] ?? ''),
            sanitize($input['direccion_envio'] ?? ''),
            sanitize($input['observaciones_generales'] ?? ''),
            sanitize($input['observaciones_diseno'] ?? ''),
            $input['fecha_entrega'] ?? null,
            floatval($input['subtotal'] ?? 0),
            floatval($input['adelanto'] ?? 0),
            floatval($input['saldo'] ?? 0),
            $id
        ]);
        
        logActivity($id, $user['id'], 'PEDIDO_ACTUALIZADO', 'Pedido actualizado');
        
        $db->commit();
        
        successResponse(['pedido_id' => $id], 'Pedido actualizado exitosamente');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Eliminar pedido
 */
function deletePedido() {
    global $user;
    requireRole(['administrador']);
    
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    
    $stmt = $db->prepare("DELETE FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        successResponse([], 'Pedido eliminado exitosamente');
    } else {
        errorResponse('Pedido no encontrado', 404);
    }
}

/**
 * Actualizar estado del pedido
 */
function updateEstado() {
    global $user;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['pedido_id'] ?? 0);
    $etapa = sanitize($input['etapa'] ?? '');
    $estado = sanitize($input['estado'] ?? '');
    
    if ($id <= 0 || empty($etapa) || empty($estado)) {
        errorResponse('Parámetros incompletos');
    }
    
    $db = getDB();
    
    // Validar etapa válida
    $etapasValidas = ['estado_contrato', 'estado_integrantes', 'estado_diseno', 
                      'estado_planchado', 'estado_costura', 'estado_general'];
    
    if (!in_array($etapa, $etapasValidas)) {
        errorResponse('Etapa no válida');
    }
    
    $stmt = $db->prepare("UPDATE pedidos SET {$etapa} = ? WHERE id = ?");
    $stmt->execute([$estado, $id]);
    
    // Verificar si debe actualizar estado general
    if ($etapa !== 'estado_general') {
        actualizarEstadoGeneral($id);
    }
    
    logActivity($id, $user['id'], 'ESTADO_ACTUALIZADO', "{$etapa} cambiado a {$estado}");
    
    successResponse(['pedido_id' => $id], 'Estado actualizado');
}

/**
 * Actualizar estado general automáticamente
 */
function actualizarEstadoGeneral($pedidoId) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT estado_contrato, estado_integrantes, estado_diseno, 
                          estado_planchado, estado_costura FROM pedidos WHERE id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    
    if ($pedido) {
        if ($pedido['estado_contrato'] === 'completo' && 
            $pedido['estado_integrantes'] === 'completo' &&
            $pedido['estado_diseno'] === 'completo' &&
            $pedido['estado_planchado'] === 'completo' &&
            $pedido['estado_costura'] === 'completo') {
            
            $stmt = $db->prepare("UPDATE pedidos SET estado_general = 'listo_entrega' WHERE id = ?");
            $stmt->execute([$pedidoId]);
        }
    }
}

/**
 * Obtener estadísticas del dashboard
 */
function getDashboard() {
    $db = getDB();
    
    // Estadísticas generales
    $stmt = $db->query("SELECT 
        COUNT(*) as total_pedidos,
        SUM(CASE WHEN estado_general = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
        SUM(CASE WHEN estado_general = 'listo_entrega' THEN 1 ELSE 0 END) as listos_entrega,
        SUM(CASE WHEN estado_general = 'entregado' THEN 1 ELSE 0 END) as entregados,
        SUM(CASE WHEN fecha_entrega = CURDATE() AND estado_general != 'entregado' THEN 1 ELSE 0 END) as urgentes_hoy,
        SUM(CASE WHEN fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL 2 DAY) AND estado_general = 'en_proceso' THEN 1 ELSE 0 END) as urgentes
        FROM pedidos");
    $stats = $stmt->fetch();
    
    // Pedidos por etapa
    $stmt = $db->query("SELECT 
        SUM(CASE WHEN estado_contrato != 'completo' THEN 1 ELSE 0 END) as contrato,
        SUM(CASE WHEN estado_contrato = 'completo' AND estado_integrantes != 'completo' THEN 1 ELSE 0 END) as integrantes,
        SUM(CASE WHEN estado_integrantes = 'completo' AND estado_diseno != 'completo' THEN 1 ELSE 0 END) as diseno,
        SUM(CASE WHEN estado_diseno = 'completo' AND estado_planchado != 'completo' THEN 1 ELSE 0 END) as planchado,
        SUM(CASE WHEN estado_planchado = 'completo' AND estado_costura != 'completo' THEN 1 ELSE 0 END) as costura,
        SUM(CASE WHEN estado_general = 'listo_entrega' THEN 1 ELSE 0 END) as listo_entrega
        FROM pedidos WHERE estado_general != 'entregado'");
    $etapas = $stmt->fetch();
    
    // Pedidos urgentes
    $stmt = $db->query("SELECT p.id, p.codigo, p.fecha_entrega, c.nombre as cliente,
                        (SELECT COUNT(*) FROM integrantes i WHERE i.pedido_id = p.id) as total_prendas
                        FROM pedidos p 
                        LEFT JOIN clientes c ON p.cliente_id = c.id
                        WHERE p.fecha_entrega <= DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
                        AND p.estado_general != 'entregado'
                        ORDER BY p.fecha_entrega ASC LIMIT 5");
    $urgentes = $stmt->fetchAll();
    
    // Pedidos recientes
    $stmt = $db->query("SELECT p.id, p.codigo, p.fecha_pedido, p.estado_general,
                        c.nombre as cliente, u.nombre as vendedor
                        FROM pedidos p 
                        LEFT JOIN clientes c ON p.cliente_id = c.id
                        LEFT JOIN usuarios u ON p.usuario_id = u.id
                        ORDER BY p.fecha_pedido DESC LIMIT 5");
    $recientes = $stmt->fetchAll();
    
    successResponse([
        'stats' => $stats,
        'etapas' => $etapas,
        'urgentes' => $urgentes,
        'recientes' => $recientes
    ]);
}

/**
 * Manejar integrantes
 */
function handleIntegrantes() {
    global $user;
    
    $subAction = $_GET['sub'] ?? 'list';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($subAction) {
        case 'save':
            guardarIntegrantes($input);
            break;
        case 'delete':
            eliminarIntegrante($input);
            break;
        default:
            listarIntegrantes();
    }
}

function guardarIntegrantes($input) {
    global $user;
    
    $pedidoId = intval($input['pedido_id'] ?? 0);
    if ($pedidoId <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Si es modo imagen
        if (!empty($input['modo_imagen']) && !empty($input['imagen'])) {
            // Guardar imagen
            $imagenPath = guardarImagen($input['imagen'], 'integrantes');
            if ($imagenPath) {
                $stmt = $db->prepare("INSERT INTO imagenes_integrantes (pedido_id, imagen_path) VALUES (?, ?)
                                      ON DUPLICATE KEY UPDATE imagen_path = ?");
                $stmt->execute([$pedidoId, $imagenPath, $imagenPath]);
            }
        }
        
        // Guardar integrantes
        if (!empty($input['integrantes']) && is_array($input['integrantes'])) {
            // Eliminar integrantes anteriores
            $stmt = $db->prepare("DELETE FROM integrantes WHERE pedido_id = ?");
            $stmt->execute([$pedidoId]);
            
            // Insertar nuevos
            $stmt = $db->prepare("INSERT INTO integrantes (pedido_id, nombre, talla, numero, observacion, incluye_short, sexo)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($input['integrantes'] as $int) {
                $stmt->execute([
                    $pedidoId,
                    sanitize($int['nombre'] ?? ''),
                    sanitize($int['talla'] ?? ''),
                    sanitize($int['numero'] ?? ''),
                    sanitize($int['observacion'] ?? ''),
                    intval($int['incluye_short'] ?? 1),
                    sanitize($int['sexo'] ?? 'Varon')
                ]);
            }
        }
        
        // Actualizar estado
        $stmt = $db->prepare("UPDATE pedidos SET estado_integrantes = 'completo' WHERE id = ?");
        $stmt->execute([$pedidoId]);
        
        logActivity($pedidoId, $user['id'], 'INTEGRANTES_GUARDADOS', 'Integrantes registrados');
        
        $db->commit();
        successResponse(['pedido_id' => $pedidoId], 'Integrantes guardados exitosamente');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function listarIntegrantes() {
    $pedidoId = intval($_GET['pedido_id'] ?? 0);
    if ($pedidoId <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM integrantes WHERE pedido_id = ? ORDER BY id");
    $stmt->execute([$pedidoId]);
    $integrantes = $stmt->fetchAll();
    
    successResponse(['integrantes' => $integrantes]);
}

function eliminarIntegrante($input) {
    $id = intval($input['integrante_id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de integrante no válido');
    }
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM integrantes WHERE id = ?");
    $stmt->execute([$id]);
    
    successResponse([], 'Integrante eliminado');
}

/**
 * Manejar diseño
 */
function handleDiseno() {
    global $user;
    requireRole(['disenador', 'administrador']);
    
    $subAction = $_GET['sub'] ?? 'list';
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($subAction) {
        case 'upload':
            subirDiseno($input);
            break;
        case 'approve':
            aprobarDiseno($input);
            break;
        default:
            listarDisenos();
    }
}

function subirDiseno($input) {
    global $user;
    
    $pedidoId = intval($input['pedido_id'] ?? 0);
    $tipo = sanitize($input['tipo'] ?? '');
    $imagen = $input['imagen'] ?? '';
    
    if ($pedidoId <= 0 || empty($tipo) || empty($imagen)) {
        errorResponse('Parámetros incompletos');
    }
    
    // Guardar imagen
    $imagenPath = guardarImagen($imagen, 'disenos');
    if (!$imagenPath) {
        errorResponse('Error al guardar la imagen');
    }
    
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO disenos_finales (pedido_id, disenador_id, tipo, imagen_path, observaciones, aprobado)
                          VALUES (?, ?, ?, ?, ?, 1)");
    $stmt->execute([
        $pedidoId,
        $user['id'],
        $tipo,
        $imagenPath,
        sanitize($input['observaciones'] ?? '')
    ]);
    
    // Verificar si todos los diseños están listos
    $stmt = $db->prepare("SELECT COUNT(DISTINCT tipo) as tipos FROM disenos_finales WHERE pedido_id = ? AND aprobado = 1");
    $stmt->execute([$pedidoId]);
    $result = $stmt->fetch();
    
    if ($result['tipos'] >= 2) { // Al menos camiseta y short
        $stmt = $db->prepare("UPDATE pedidos SET estado_diseno = 'completo' WHERE id = ?");
        $stmt->execute([$pedidoId]);
    }
    
    logActivity($pedidoId, $user['id'], 'DISENO_SUBIDO', "Diseño {$tipo} subido");
    
    successResponse(['imagen_path' => $imagenPath], 'Diseño subido exitosamente');
}

function aprobarDiseno($input) {
    global $user;
    
    $disenoId = intval($input['diseno_id'] ?? 0);
    if ($disenoId <= 0) {
        errorResponse('ID de diseño no válido');
    }
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE disenos_finales SET aprobado = 1 WHERE id = ?");
    $stmt->execute([$disenoId]);
    
    successResponse([], 'Diseño aprobado');
}

function listarDisenos() {
    $pedidoId = intval($_GET['pedido_id'] ?? 0);
    
    $db = getDB();
    if ($pedidoId > 0) {
        $stmt = $db->prepare("SELECT df.*, u.nombre as disenador FROM disenos_finales df 
                              LEFT JOIN usuarios u ON df.disenador_id = u.id
                              WHERE df.pedido_id = ?");
        $stmt->execute([$pedidoId]);
    } else {
        $stmt = $db->query("SELECT df.*, u.nombre as disenador, p.codigo, c.nombre as cliente 
                           FROM disenos_finales df 
                           LEFT JOIN usuarios u ON df.disenador_id = u.id
                           LEFT JOIN pedidos p ON df.pedido_id = p.id
                           LEFT JOIN clientes c ON p.cliente_id = c.id
                           ORDER BY df.fecha_subida DESC");
    }
    
    $disenos = $stmt->fetchAll();
    successResponse(['disenos' => $disenos]);
}

/**
 * Manejar planchado
 */
function handlePlanchado() {
    global $user;
    requireRole(['disenador', 'administrador']);
    
    $subAction = $_GET['sub'] ?? 'save';
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($subAction === 'save') {
        guardarPlanchado($input);
    }
}

function guardarPlanchado($input) {
    global $user;
    
    $pedidoId = intval($input['pedido_id'] ?? 0);
    if ($pedidoId <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Calcular total
        $total = (intval($input['cant_polos'] ?? 0) * floatval($input['precio_polo'] ?? 1.50)) +
                 (intval($input['cant_shorts'] ?? 0) * floatval($input['precio_short'] ?? 1.00)) +
                 (intval($input['cant_cuellos'] ?? 0) * floatval($input['precio_cuello'] ?? 0.50));
        
        // Insertar/actualizar planchado
        $stmt = $db->prepare("INSERT INTO planchado (
            pedido_id, planchador_nombre, cant_polos, cant_shorts, cant_cuellos,
            precio_polo, precio_short, precio_cuello, total_pago, observaciones, fecha_planchado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            planchador_nombre = VALUES(planchador_nombre),
            cant_polos = VALUES(cant_polos),
            cant_shorts = VALUES(cant_shorts),
            cant_cuellos = VALUES(cant_cuellos),
            precio_polo = VALUES(precio_polo),
            precio_short = VALUES(precio_short),
            precio_cuello = VALUES(precio_cuello),
            total_pago = VALUES(total_pago),
            observaciones = VALUES(observaciones),
            fecha_planchado = VALUES(fecha_planchado)");
        
        $stmt->execute([
            $pedidoId,
            sanitize($input['planchador_nombre'] ?? ''),
            intval($input['cant_polos'] ?? 0),
            intval($input['cant_shorts'] ?? 0),
            intval($input['cant_cuellos'] ?? 0),
            floatval($input['precio_polo'] ?? 1.50),
            floatval($input['precio_short'] ?? 1.00),
            floatval($input['precio_cuello'] ?? 0.50),
            $total,
            sanitize($input['observaciones'] ?? ''),
            $input['fecha_planchado'] ?? date('Y-m-d')
        ]);
        
        // Actualizar estado
        $stmt = $db->prepare("UPDATE pedidos SET estado_planchado = 'completo' WHERE id = ?");
        $stmt->execute([$pedidoId]);
        
        actualizarEstadoGeneral($pedidoId);
        
        logActivity($pedidoId, $user['id'], 'PLANCHADO_COMPLETADO', 'Planchado registrado');
        
        $db->commit();
        successResponse(['total' => $total], 'Planchado guardado exitosamente');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Manejar costura
 */
function handleCostura() {
    global $user;
    requireRole(['disenador', 'administrador']);
    
    $subAction = $_GET['sub'] ?? 'save';
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($subAction === 'save') {
        guardarCostura($input);
    }
}

function guardarCostura($input) {
    global $user;
    
    $pedidoId = intval($input['pedido_id'] ?? 0);
    if ($pedidoId <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Calcular total
        $total = (intval($input['cant_polos'] ?? 0) * floatval($input['precio_polo'] ?? 2.00)) +
                 (intval($input['cant_shorts'] ?? 0) * floatval($input['precio_short'] ?? 1.50));
        
        // Insertar/actualizar costura
        $stmt = $db->prepare("INSERT INTO costura (
            pedido_id, costurero_nombre, cant_polos, cant_shorts,
            precio_polo, precio_short, total_pago, observaciones, fecha_costura
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            costurero_nombre = VALUES(costurero_nombre),
            cant_polos = VALUES(cant_polos),
            cant_shorts = VALUES(cant_shorts),
            precio_polo = VALUES(precio_polo),
            precio_short = VALUES(precio_short),
            total_pago = VALUES(total_pago),
            observaciones = VALUES(observaciones),
            fecha_costura = VALUES(fecha_costura)");
        
        $stmt->execute([
            $pedidoId,
            sanitize($input['costurero_nombre'] ?? ''),
            intval($input['cant_polos'] ?? 0),
            intval($input['cant_shorts'] ?? 0),
            floatval($input['precio_polo'] ?? 2.00),
            floatval($input['precio_short'] ?? 1.50),
            $total,
            sanitize($input['observaciones'] ?? ''),
            $input['fecha_costura'] ?? date('Y-m-d')
        ]);
        
        // Actualizar estado
        $stmt = $db->prepare("UPDATE pedidos SET estado_costura = 'completo' WHERE id = ?");
        $stmt->execute([$pedidoId]);
        
        actualizarEstadoGeneral($pedidoId);
        
        logActivity($pedidoId, $user['id'], 'COSTURA_COMPLETADA', 'Costura registrada');
        
        $db->commit();
        successResponse(['total' => $total], 'Costura guardada exitosamente');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Manejar entrega
 */
function handleEntrega() {
    global $user;
    requireRole(['vendedor', 'administrador']);
    
    $subAction = $_GET['sub'] ?? 'save';
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($subAction === 'save') {
        registrarEntrega($input);
    }
}

function registrarEntrega($input) {
    global $user;
    
    $pedidoId = intval($input['pedido_id'] ?? 0);
    if ($pedidoId <= 0) {
        errorResponse('ID de pedido no válido');
    }
    
    $db = getDB();
    $db->beginTransaction();
    
    try {
        // Insertar entrega
        $stmt = $db->prepare("INSERT INTO entregas (
            pedido_id, usuario_id, lugar_entrega, es_envio, direccion_envio,
            costo_envio, total_cobrado, observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $pedidoId,
            $user['id'],
            sanitize($input['lugar_entrega'] ?? ''),
            intval($input['es_envio'] ?? 0),
            sanitize($input['direccion_envio'] ?? ''),
            floatval($input['costo_envio'] ?? 0),
            floatval($input['total_cobrado'] ?? 0),
            sanitize($input['observaciones'] ?? '')
        ]);
        
        // Actualizar estado del pedido
        $stmt = $db->prepare("UPDATE pedidos SET estado_general = 'entregado', fecha_completado = NOW() WHERE id = ?");
        $stmt->execute([$pedidoId]);
        
        logActivity($pedidoId, $user['id'], 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente');
        
        $db->commit();
        successResponse([], 'Entrega registrada exitosamente');
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Guardar imagen base64
 */
function guardarImagen($base64, $carpeta = 'uploads') {
    try {
        // Extraer datos de la imagen
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = $matches[1];
            $base64 = substr($base64, strpos($base64, ',') + 1);
        } else {
            $extension = 'jpg';
        }
        
        // Decodificar
        $imagenData = base64_decode($base64);
        if ($imagenData === false) {
            return false;
        }
        
        // Crear directorio si no existe
        $directorio = UPLOAD_PATH . $carpeta . '/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }
        
        // Generar nombre único
        $nombreArchivo = uniqid() . '_' . time() . '.' . $extension;
        $rutaCompleta = $directorio . $nombreArchivo;
        
        // Guardar archivo
        if (file_put_contents($rutaCompleta, $imagenData) !== false) {
            return 'uploads/' . $carpeta . '/' . $nombreArchivo;
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}
