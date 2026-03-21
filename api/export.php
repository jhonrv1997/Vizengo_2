<?php
/**
 * VIZENGO - Exportación de Datos
 * Genera reportes en formato CSV
 */
require_once __DIR__ . '/../config.php';
startSecureSession();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: ../index.php');
    exit();
}

$tipo = $_GET['tipo'] ?? 'pedidos';
$db = getDB();

switch ($tipo) {
    case 'pedidos':
        exportPedidos($db);
        break;
    case 'integrantes':
        exportIntegrantes($db);
        break;
    case 'usuarios':
        exportUsuarios($db);
        break;
    default:
        die('Tipo de exportación no válido');
}

function exportPedidos($db) {
    $stmt = $db->query("SELECT 
        p.codigo, c.nombre as cliente, c.celular, p.tipo_contrato,
        p.lugar_entrega, p.estado_general, p.fecha_pedido, p.fecha_entrega,
        p.subtotal, p.adelanto, p.saldo, u.nombre as vendedor
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN usuarios u ON p.usuario_id = u.id
        ORDER BY p.fecha_pedido DESC");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    downloadCSV($data, 'pedidos_vizengo_' . date('Y-m-d') . '.csv');
}

function exportIntegrantes($db) {
    $stmt = $db->query("SELECT 
        p.codigo, c.nombre as cliente, i.nombre as integrante,
        i.talla, i.numero, i.observacion, i.sexo
        FROM integrantes i
        LEFT JOIN pedidos p ON i.pedido_id = p.id
        LEFT JOIN clientes c ON p.cliente_id = c.id
        ORDER BY p.codigo, i.id");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    downloadCSV($data, 'integrantes_vizengo_' . date('Y-m-d') . '.csv');
}

function exportUsuarios($db) {
    // Solo administradores pueden exportar usuarios
    if ($_SESSION['rol'] !== 'administrador') {
        die('No tiene permisos para esta acción');
    }
    
    $stmt = $db->query("SELECT 
        username, nombre, email, rol, activo, fecha_creacion
        FROM usuarios
        ORDER BY nombre");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    downloadCSV($data, 'usuarios_vizengo_' . date('Y-m-d') . '.csv');
}

function downloadCSV($data, $filename) {
    if (empty($data)) {
        die('No hay datos para exportar');
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Headers
    fputcsv($output, array_keys($data[0]), ';');
    
    // Data
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit();
}
