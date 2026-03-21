<?php
/**
 * VIZENGO - API de Autenticación
 * Manejo de login, logout y verificación de sesión
 */

require_once __DIR__ . '/../config.php';
startSecureSession();
setCorsHeaders();

// Determinar la acción
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkSession();
        break;
    case 'verify':
        verifyToken();
        break;
    default:
        errorResponse('Acción no válida', 400);
}

/**
 * Manejar login
 */
function handleLogin() {
    // Obtener datos JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    }
    
    $username = sanitize($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        errorResponse('Usuario y contraseña son requeridos');
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password, nombre, email, rol, activo FROM usuarios WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            errorResponse('Usuario o contraseña incorrectos', 401);
        }
        
        if (!$user['activo']) {
            errorResponse('Usuario inactivo. Contacte al administrador.', 403);
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            errorResponse('Usuario o contraseña incorrectos', 401);
        }
        
        // Actualizar último acceso
        $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // Crear sesión
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Generar token CSRF
        $csrfToken = generateCSRFToken();
        
        // Respuesta exitosa
        successResponse([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nombre' => $user['nombre'],
                'rol' => $user['rol']
            ],
            'csrf_token' => $csrfToken
        ], 'Inicio de sesión exitoso');
        
    } catch (PDOException $e) {
        if (DEV_MODE) {
            errorResponse('Error de base de datos: ' . $e->getMessage(), 500);
        } else {
            errorResponse('Error interno del servidor', 500);
        }
    }
}

/**
 * Manejar logout
 */
function handleLogout() {
    session_destroy();
    successResponse([], 'Sesión cerrada correctamente');
}

/**
 * Verificar sesión activa
 */
function checkSession() {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        jsonResponse([
            'logged_in' => false,
            'user' => null
        ]);
    }
    
    // Verificar si la sesión ha expirado
    if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
        session_destroy();
        jsonResponse([
            'logged_in' => false,
            'user' => null,
            'expired' => true
        ]);
    }
    
    jsonResponse([
        'logged_in' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'nombre' => $_SESSION['nombre'],
            'rol' => $_SESSION['rol']
        ],
        'csrf_token' => $_SESSION['csrf_token'] ?? generateCSRFToken()
    ]);
}

/**
 * Verificar token y permisos
 */
function verifyToken() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (empty($token)) {
        errorResponse('Token no proporcionado', 401);
    }
    
    // Verificar sesión
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        errorResponse('Sesión no válida', 401);
    }
    
    successResponse([
        'valid' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'nombre' => $_SESSION['nombre'],
            'rol' => $_SESSION['rol']
        ]
    ]);
}

/**
 * Función helper para verificar si el usuario está autenticado
 */
function requireAuth() {
    startSecureSession();
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        errorResponse('No autorizado', 401);
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nombre' => $_SESSION['nombre'],
        'rol' => $_SESSION['rol']
    ];
}

/**
 * Función helper para verificar rol
 */
function requireRole($roles) {
    $user = requireAuth();
    if (!in_array($user['rol'], (array)$roles)) {
        errorResponse('No tiene permisos para esta acción', 403);
    }
    return $user;
}
