<?php
/**
 * VIZENGO - API de Usuarios
 * Gestión de usuarios del sistema
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
startSecureSession();
setCorsHeaders();

// Solo administradores pueden gestionar usuarios
$user = requireRole(['administrador']);

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listarUsuarios();
            break;
        case 'get':
            obtenerUsuario();
            break;
        case 'create':
            crearUsuario();
            break;
        case 'update':
            actualizarUsuario();
            break;
        case 'delete':
            eliminarUsuario();
            break;
        case 'planchadores':
            listarPlanchadores();
            break;
        case 'costureros':
            listarCostureros();
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

function listarUsuarios() {
    $db = getDB();
    $stmt = $db->query("SELECT id, username, nombre, celular, email, rol, activo, fecha_creacion, ultimo_acceso 
                        FROM usuarios ORDER BY nombre ASC");
    $usuarios = $stmt->fetchAll();
    
    foreach ($usuarios as &$u) {
        $u['fecha_creacion_fmt'] = formatDate($u['fecha_creacion'], 'd/m/Y H:i');
        $u['ultimo_acceso_fmt'] = $u['ultimo_acceso'] ? formatDate($u['ultimo_acceso'], 'd/m/Y H:i') : 'Nunca';
    }
    
    successResponse(['usuarios' => $usuarios]);
}

function obtenerUsuario() {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de usuario no válido');
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, nombre, celular, email, rol, activo, fecha_creacion, ultimo_acceso 
                          FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        errorResponse('Usuario no encontrado', 404);
    }
    
    successResponse(['usuario' => $usuario]);
}

function crearUsuario() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $nombre = sanitize($input['nombre'] ?? '');
    $celular = sanitize($input['celular'] ?? '');
    $email = sanitize($input['email'] ?? '');
    $rol = sanitize($input['rol'] ?? 'vendedor');
    
    if (empty($username) || empty($password) || empty($nombre)) {
        errorResponse('Usuario, contraseña y nombre son requeridos');
    }
    
    // Validar rol
    $rolesValidos = ['vendedor', 'disenador', 'administrador'];
    if (!in_array($rol, $rolesValidos)) {
        errorResponse('Rol no válido');
    }
    
    $db = getDB();
    
    // Verificar si el username ya existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        errorResponse('El nombre de usuario ya existe');
    }
    
    // Crear usuario
    $hashPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO usuarios (username, password, nombre, celular, email, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$username, $hashPassword, $nombre, $celular, $email, $rol]);
    
    successResponse(['id' => $db->lastInsertId()], 'Usuario creado exitosamente');
}

function actualizarUsuario() {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de usuario no válido');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $nombre = sanitize($input['nombre'] ?? '');
    $celular = sanitize($input['celular'] ?? '');
    $email = sanitize($input['email'] ?? '');
    $rol = sanitize($input['rol'] ?? '');
    $activo = intval($input['activo'] ?? 1);
    $password = $input['password'] ?? '';
    
    $db = getDB();
    
    $sql = "UPDATE usuarios SET nombre = ?, celular = ?, email = ?, rol = ?, activo = ?";
    $params = [$nombre, $celular, $email, $rol, $activo];
    
    if (!empty($password)) {
        $sql .= ", password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $id;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    successResponse([], 'Usuario actualizado exitosamente');
}

function eliminarUsuario() {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('ID de usuario no válido');
    }
    
    // No permitir eliminar el usuario admin
    if ($id == 5) { // ID del admin por defecto
        errorResponse('No se puede eliminar el usuario administrador principal');
    }
    
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        successResponse([], 'Usuario eliminado exitosamente');
    } else {
        errorResponse('Usuario no encontrado', 404);
    }
}

function listarPlanchadores() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM planchadores WHERE activo = 1 ORDER BY nombre ASC");
    $planchadores = $stmt->fetchAll();
    successResponse(['planchadores' => $planchadores]);
}

function listarCostureros() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM costureros WHERE activo = 1 ORDER BY nombre ASC");
    $costureros = $stmt->fetchAll();
    successResponse(['costureros' => $costureros]);
}
