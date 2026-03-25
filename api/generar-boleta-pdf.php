<?php
/**
 * VIZENGO - Generador de Boleta de Venta en PDF
 * Genera un archivo PDF con los datos del pedido
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../libs/fpdf.php';

startSecureSession();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    exit('No autorizado');
}

// Validar ID del pedido
if (!isset($_GET['pedido_id']) || !is_numeric($_GET['pedido_id'])) {
    http_response_code(400);
    exit('ID de pedido inválido');
}

$pedidoId = intval($_GET['pedido_id']);
$db = getDB();

// Obtener datos principales del pedido
$stmt = $db->prepare("SELECT 
                        p.id, p.codigo, p.tipo_contrato, p.lugar_entrega, 
                        p.direccion_envio, p.vendedor_asignado, p.celular_cliente,
                        p.observaciones_generales, p.observaciones_diseno,
                        p.fecha_pedido, p.fecha_entrega, p.hora_entrega,
                        p.subtotal, p.adelanto, p.saldo,
                        c.nombre as cliente_nombre, c.celular as cliente_celular, 
                        u.nombre as vendedor_nombre
                       FROM pedidos p 
                       LEFT JOIN clientes c ON p.cliente_id = c.id
                       LEFT JOIN usuarios u ON p.usuario_id = u.id
                       WHERE p.id = ?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    http_response_code(404);
    exit('Pedido no encontrado');
}

// Obtener diseños iniciales (referencias) - obtener todas las imágenes
$stmt = $db->prepare("SELECT imagen_path FROM disenos_iniciales WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$disenosIniciales = $stmt->fetchAll();

// Obtener kits
$stmt = $db->prepare("SELECT camiseta_tipo, camiseta_tela, camiseta_talla, 
                             short_tipo, short_tela, short_talla, 
                             medias_tipo, medias_detalles, cantidad, 
                             precio_unitario, subtotal 
                      FROM kits WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$kits = $stmt->fetchAll();

// Obtener adicionales de talla
$stmt = $db->prepare("SELECT talla, cantidad, precio_unitario FROM adicionales_talla WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$adicionalesTalla = $stmt->fetchAll();

// Obtener merchandising
$stmt = $db->prepare("SELECT articulo, cantidad, precio_unitario, es_regalo, especificaciones FROM merchandising WHERE pedido_id = ? ORDER BY id ASC");
$stmt->execute([$pedidoId]);
$merchandising = $stmt->fetchAll();

// Función para convertir texto UTF-8 a ISO-8859-1 para FPDF
function txt($texto) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto ?: '');
}

// Función para formatear moneda
function formatoMoneda($amount) {
    return 'S/ ' . number_format($amount, 2, '.', ',');
}

// Crear clase personalizada para el PDF
class BoletaPDF extends FPDF {
    private $pedidoCodigo;
    
    function __construct($pedidoCodigo) {
        parent::__construct();
        $this->pedidoCodigo = $pedidoCodigo;
    }
    
    function Header() {
        // Logo o nombre de empresa
        $this->SetFont('Helvetica', 'B', 24);
        $this->SetTextColor(43, 79, 255); // Color primario
        $this->Cell(0, 12, txt('VIZENGO'), 0, 1, 'C');
        
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, txt('Tienda de Ropa Deportiva'), 0, 1, 'C');
        
        $this->Ln(5);
        
        // Linea separadora
        $this->SetDrawColor(43, 79, 255);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->Ln(8);
        
        // Titulo del documento
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 10, txt('BOLETA DE VENTA'), 0, 1, 'C');
        
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 6, txt('Codigo: ' . $this->pedidoCodigo), 0, 1, 'C');
        
        $this->Ln(8);
    }
    
    function Footer() {
        $this->SetY(-25);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->Ln(5);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, txt('Documento generado automaticamente - VIZENGO'), 0, 1, 'C');
        $this->Cell(0, 5, txt('Fecha de generacion: ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }
}

// Crear el PDF
$pdf = new BoletaPDF($pedido['codigo']);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 30);

// =====================================================
// INFORMACION DEL CLIENTE
// =====================================================
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetFillColor(43, 79, 255);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  ' . txt('DATOS DEL CLIENTE'), 0, 1, 'L', true);

$pdf->Ln(3);
$pdf->SetTextColor(30, 30, 30);

// Tabla de informacion del cliente
$pdf->SetFont('Helvetica', '', 10);
$clientInfo = [
    [txt('Cliente'), txt($pedido['cliente_nombre'])],
    [txt('Celular'), txt($pedido['celular_cliente'] ?: $pedido['cliente_celular'] ?: '-')],
    [txt('Fecha de Pedido'), date('d/m/Y', strtotime($pedido['fecha_pedido']))],
    [txt('Fecha de Entrega'), $pedido['fecha_entrega'] ? date('d/m/Y', strtotime($pedido['fecha_entrega'])) : '-'],
    [txt('Vendedor'), txt($pedido['vendedor_asignado'] ?: $pedido['vendedor_nombre'])]
];

foreach ($clientInfo as $info) {
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(45, 6, $info[0] . ':', 0, 0);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 6, $info[1], 0, 1);
}

$pdf->Ln(8);

// =====================================================
// IMAGENES DE REFERENCIA (si existen)
// =====================================================
if (!empty($disenosIniciales)) {
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetFillColor(43, 79, 255);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, '  ' . txt('IMAGENES DE REFERENCIA'), 0, 1, 'L', true);
    $pdf->Ln(5);
    
    $pdf->SetTextColor(30, 30, 30);
    
    // Calcular espacio disponible y dimensiones de imagen
    $imagenAncho = 50;  // Ancho fijo para cada imagen
    $imagenAlto = 50;   // Alto fijo para cada imagen
    $margenEntreImagenes = 10;
    $totalImagenes = count($disenosIniciales);
    
    // Calcular posición X inicial para centrar las imágenes
    $anchoTotalImagenes = ($totalImagenes * $imagenAncho) + (($totalImagenes - 1) * $margenEntreImagenes);
    $posXInicial = (210 - $anchoTotalImagenes) / 2; // 210 es el ancho de página A4
    
    // Posición Y antes de colocar las imágenes
    $posY = $pdf->GetY();
    
    $posX = $posXInicial;
    foreach ($disenosIniciales as $diseno) {
        if (!empty($diseno['imagen_path'])) {
            $imagenPath = __DIR__ . '/../' . $diseno['imagen_path'];
            if (file_exists($imagenPath)) {
                // Colocar imagen en posición específica
                $pdf->Image($imagenPath, $posX, $posY, $imagenAncho, $imagenAlto);
                $posX += $imagenAncho + $margenEntreImagenes;
            }
        }
    }
    
    // Mover el cursor Y después de las imágenes (importante: usar SetY para saltar el espacio de las imágenes)
    $pdf->SetY($posY + $imagenAlto + 10);
}

// =====================================================
// DETALLE DE PRODUCTOS
// =====================================================
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetFillColor(43, 79, 255);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  ' . txt('DETALLE DE PRODUCTOS'), 0, 1, 'L', true);
$pdf->Ln(3);

// Encabezados de tabla
$pdf->SetFillColor(240, 244, 255);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Helvetica', 'B', 8);

// Anchos de columna: Cantidad(15), Descripcion(95), P.Unit(25), Subtotal(25)
$pdf->Cell(15, 7, txt('Cant.'), 1, 0, 'C', true);
$pdf->Cell(95, 7, txt('Descripcion'), 1, 0, 'C', true);
$pdf->Cell(25, 7, txt('P. Unit.'), 1, 0, 'C', true);
$pdf->Cell(25, 7, txt('Subtotal'), 1, 1, 'C', true);

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(30, 30, 30);

// Items - Kits
if (!empty($kits)) {
    foreach ($kits as $kit) {
        // Construir descripcion del kit
        $descripcion = '';
        if ($kit['camiseta_tipo']) {
            $descripcion .= $kit['camiseta_tipo'];
            if ($kit['camiseta_tela']) $descripcion .= ' - ' . $kit['camiseta_tela'];
            if ($kit['camiseta_talla']) $descripcion .= ' (Talla: ' . $kit['camiseta_talla'] . ')';
        }
        if ($kit['short_tipo']) {
            $descripcion .= ' | Short: ' . $kit['short_tipo'];
            if ($kit['short_tela']) $descripcion .= ' - ' . $kit['short_tela'];
        }
        if ($kit['medias_tipo'] && $kit['medias_tipo'] != 'NINGUNO') {
            $descripcion .= ' | Medias: ' . $kit['medias_tipo'];
            if ($kit['medias_detalles']) $descripcion .= ' ' . $kit['medias_detalles'];
        }
        
        // Calcular subtotal
        $subtotal = $kit['cantidad'] * $kit['precio_unitario'];
        
        $pdf->Cell(15, 6, $kit['cantidad'], 1, 0, 'C');
        $pdf->Cell(95, 6, txt(substr($descripcion, 0, 70)), 1, 0, 'L');
        $pdf->Cell(25, 6, formatoMoneda($kit['precio_unitario']), 1, 0, 'R');
        $pdf->Cell(25, 6, formatoMoneda($subtotal), 1, 1, 'R');
    }
}

// Items - Adicionales de talla
if (!empty($adicionalesTalla)) {
    foreach ($adicionalesTalla as $adicional) {
        $descripcion = 'Adicional Talla Especial: ' . $adicional['talla'];
        $subtotal = $adicional['cantidad'] * $adicional['precio_unitario'];
        
        $pdf->Cell(15, 6, $adicional['cantidad'], 1, 0, 'C');
        $pdf->Cell(95, 6, txt($descripcion), 1, 0, 'L');
        $pdf->Cell(25, 6, formatoMoneda($adicional['precio_unitario']), 1, 0, 'R');
        $pdf->Cell(25, 6, formatoMoneda($subtotal), 1, 1, 'R');
    }
}

// Items - Merchandising
if (!empty($merchandising)) {
    foreach ($merchandising as $merch) {
        $descripcion = 'Merchandising: ' . $merch['articulo'];
        if ($merch['especificaciones']) {
            $descripcion .= ' - ' . $merch['especificaciones'];
        }
        if ($merch['es_regalo']) {
            $descripcion .= ' (REGALO)';
        }
        
        $precioUnit = $merch['es_regalo'] ? 0 : $merch['precio_unitario'];
        $subtotal = $merch['cantidad'] * $precioUnit;
        
        $pdf->Cell(15, 6, $merch['cantidad'], 1, 0, 'C');
        $pdf->Cell(95, 6, txt(substr($descripcion, 0, 70)), 1, 0, 'L');
        $pdf->Cell(25, 6, $merch['es_regalo'] ? txt('REGALO') : formatoMoneda($precioUnit), 1, 0, 'R');
        $pdf->Cell(25, 6, formatoMoneda($subtotal), 1, 1, 'R');
    }
}

$pdf->Ln(8);

// =====================================================
// RESUMEN FINANCIERO
// =====================================================
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetFillColor(43, 79, 255);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  ' . txt('RESUMEN FINANCIERO'), 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetTextColor(30, 30, 30);

// Total
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(135, 7, '', 0, 0);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(25, 7, txt('TOTAL:'), 0, 0, 'R');
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor(43, 79, 255);
$pdf->Cell(25, 7, formatoMoneda($pedido['subtotal']), 0, 1, 'R');

$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(135, 7, '', 0, 0);
$pdf->SetFont('Helvetica', '', 10);
$pdf->Cell(25, 7, txt('Adelanto:'), 0, 0, 'R');
$pdf->SetTextColor(6, 214, 160); // Verde
$pdf->Cell(25, 7, formatoMoneda($pedido['adelanto']), 0, 1, 'R');

$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(135, 7, '', 0, 0);
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(25, 7, txt('SALDO:'), 0, 0, 'R');
$pdf->SetTextColor(255, 107, 107); // Rojo/Accento
$pdf->Cell(25, 7, formatoMoneda($pedido['saldo']), 0, 1, 'R');

$pdf->Ln(10);

// =====================================================
// OBSERVACIONES
// =====================================================
if (!empty($pedido['observaciones_generales']) || !empty($pedido['observaciones_diseno'])) {
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetFillColor(43, 79, 255);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 8, '  ' . txt('OBSERVACIONES'), 0, 1, 'L', true);
    $pdf->Ln(3);
    
    $pdf->SetTextColor(30, 30, 30);
    $pdf->SetFont('Helvetica', '', 9);
    
    if (!empty($pedido['observaciones_generales'])) {
        $pdf->MultiCell(0, 5, txt('Generales: ' . $pedido['observaciones_generales']));
    }
    if (!empty($pedido['observaciones_diseno'])) {
        $pdf->MultiCell(0, 5, txt('Diseno: ' . $pedido['observaciones_diseno']));
    }
    
    $pdf->Ln(5);
}

// =====================================================
// FIRMA Y SELLO
// =====================================================
$pdf->Ln(15);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(30, $pdf->GetY(), 80, $pdf->GetY());
$pdf->Line(130, $pdf->GetY(), 180, $pdf->GetY());

$pdf->Ln(2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(60, 5, txt('Firma del Cliente'), 0, 0, 'C');
$pdf->Cell(70, 5, '', 0, 0);
$pdf->Cell(60, 5, txt('Firma del Vendedor'), 0, 1, 'C');

// Generar el PDF
$filename = 'Boleta_' . $pedido['codigo'] . '_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
?>
