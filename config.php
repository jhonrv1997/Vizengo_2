<?php
/**
 * VIZENGO - Configuración Principal
 * Sistema de Gestión de Pedidos
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'vizengo_db');
define('DB_USER', 'root'); // Cambiar en producción
define('DB_PASS', '');     // Cambiar en producción
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_NAME', 'VIZENGO');
define('SITE_URL', 'http://localhost/vizengo'); // Cambiar en producción
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Configuración de sesión
define('SESSION_NAME', 'vizengo_session');
define('SESSION_LIFETIME', 86400); // 24 horas

// Zona horaria
date_default_timezone_set('America/Lima');

// Modo de desarrollo (cambiar a false en producción)
define('DEV_MODE', true);

// Errores
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Conexión a la base de datos
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEV_MODE) {
                die("Error de conexión: " . $e->getMessage());
            } else {
                die("Error de conexión a la base de datos.");
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevenir clonación
    private function __clone() {}

    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Función helper para obtener conexión
function getDB() {
    return Database::getInstance()->getConnection();
}

// CORS Headers para API
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=utf-8');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Respuesta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

// Respuesta de error
function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'error' => $message], $status);
}

// Respuesta de éxito
function successResponse($data = [], $message = 'Operación exitosa') {
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

// Sanitizar entrada
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Generar token CSRF
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verificar token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Iniciar sesión segura
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => !DEV_MODE,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Generar código de pedido
function generatePedidoCode() {
    $year = date('Y');
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM pedidos WHERE YEAR(fecha_pedido) = ?");
    $stmt->execute([$year]);
    $result = $stmt->fetch();
    $count = $result['count'] + 1;
    return "PED-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Formatear fecha
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date === '0000-00-00') return '-';
    $d = new DateTime($date);
    return $d->format($format);
}

// Formatear moneda
function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2, '.', ',');
}

// Log de actividades
function logActivity($pedidoId, $usuarioId, $accion, $descripcion = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO historial_pedidos (pedido_id, usuario_id, accion, descripcion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$pedidoId, $usuarioId, $accion, $descripcion]);
    } catch (Exception $e) {
        // Silenciar error en producción
        if (DEV_MODE) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}
