<?php
/**
 * VIZENGO - Generador de PDF de Integrantes
 * Genera un archivo PDF con el listado de integrantes del pedido
 * 
 * Contenido:
 * - Parte 01: Resumen de tallas dividido por sexo (Hombres y Mujeres)
 * - Parte 02: Tabla de integrantes con datos completos
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
                        p.id, p.codigo, 
                        c.nombre as cliente_nombre,
                        c.celular as cliente_celular,
                        p.vendedor_asignado
                       FROM pedidos p 
                       LEFT JOIN clientes c ON p.cliente_id = c.id
                       WHERE p.id = ?");
$stmt->execute([$pedidoId]);
$pedido = $stmt->fetch();

if (!$pedido) {
    http_response_code(404);
    exit('Pedido no encontrado');
}

// Obtener todos los integrantes del pedido
$stmt = $db->prepare("SELECT nombre, talla, numero, observacion, incluye_short, sexo 
                      FROM integrantes 
                      WHERE pedido_id = ? 
                      ORDER BY sexo, id ASC");
$stmt->execute([$pedidoId]);
$integrantes = $stmt->fetchAll();

if (empty($integrantes)) {
    http_response_code(400);
    exit('No hay integrantes registrados para este pedido');
}

// Función para convertir texto UTF-8 a ISO-8859-1 para FPDF
function txt($texto) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto ?: '');
}

// Separar integrantes por sexo
$varones = array_filter($integrantes, function($i) { return $i['sexo'] === 'Varon'; });
$damas = array_filter($integrantes, function($i) { return $i['sexo'] === 'Dama'; });

// Contar tallas por sexo
function contarTallas($integrantes) {
    $tallas = [];
    foreach ($integrantes as $int) {
        $talla = strtoupper(trim($int['talla'] ?? 'S/T'));
        if (!isset($tallas[$talla])) {
            $tallas[$talla] = 0;
        }
        $tallas[$talla]++;
    }
    // Ordenar tallas de forma lógica
    $ordenTallas = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'S/T'];
    uksort($tallas, function($a, $b) use ($ordenTallas) {
        $posA = array_search($a, $ordenTallas);
        $posB = array_search($b, $ordenTallas);
        if ($posA === false) $posA = 999;
        if ($posB === false) $posB = 999;
        return $posA - $posB;
    });
    return $tallas;
}

$tallasVarones = contarTallas($varones);
$tallasDamas = contarTallas($damas);

// Crear clase personalizada para el PDF
class IntegrantesPDF extends FPDF {
    private $pedidoCodigo;
    private $clienteNombre;
    private $clienteCelular;
    private $vendedorAsignado;
    
    function __construct($pedidoCodigo, $clienteNombre, $clienteCelular, $vendedorAsignado) {
        parent::__construct();
        $this->pedidoCodigo = $pedidoCodigo;
        $this->clienteNombre = $clienteNombre;
        $this->clienteCelular = $clienteCelular;
        $this->vendedorAsignado = $vendedorAsignado;
    }
    
    function Header() {
        // Nombre de empresa
       // $this->SetFont('Helvetica', 'B', 18);
      //  $this->SetTextColor(43, 79, 255); // Color primario
      //  $this->Cell(0, 10, txt('VIZENGO'), 0, 1, 'C');
        
      //  $this->SetFont('Helvetica', '', 10);
    //    $this->SetTextColor(100, 100, 100);
     //   $this->Cell(0, 6, txt('Tienda de Ropa Deportiva'), 0, 1, 'C');
        
      //  $this->Ln(3);
        
        // Linea separadora
     //   $this->SetDrawColor(43, 79, 255);
    //    $this->SetLineWidth(0.5);
    //    $this->Line(10, $this->GetY(), 200, $this->GetY());
        
   //     $this->Ln(6);
        
        // Titulo del documento
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 8, txt('LISTA DE INTEGRANTES'), 0, 1, 'C');
        
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, txt('Pedido: ' . $this->pedidoCodigo . ' | Cliente: ' . $this->clienteNombre), 0, 1, 'C');
        
        // Mostrar celular y vendedor asignado
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 100, 100);
        $infoExtra = '';
        if ($this->clienteCelular) {
            $infoExtra .= 'Celular de Cliente: ' . $this->clienteCelular;
        }
        if ($this->vendedorAsignado) {
            $infoExtra .= ($infoExtra ? ' | ' : '') . 'Vendedor: ' . $this->vendedorAsignado;
        }
        if ($infoExtra) {
            $this->Cell(0, 5, txt($infoExtra), 0, 1, 'C');
        }
        
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-28);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->Ln(3);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, txt('Todo contrato se realiza con el 50% de adelanto y el 50% al momento de la entrega.'), 0, 1, 'C');
		$this->Cell(0, 5, txt('Una vez aprobado el diseño previo; No hay derecho a correcciones, reclamos y/o devoluciones.'), 0, 1, 'C');
	    $this->Cell(0, 5, txt('Dentro del diseño digital se incluye el logo de la tienda.'), 0, 1, 'C');
		$this->Cell(0, 5, txt('No colocamos marcas registradas. Pasado los 15 dias, No hay lugar a reclamo.'), 0, 1, 'C');
        $this->Cell(0, 5, txt('Fecha de generacion: ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }
}

// Crear el PDF
$pdf = new IntegrantesPDF($pedido['codigo'], $pedido['cliente_nombre'], $pedido['cliente_celular'], $pedido['vendedor_asignado']);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 25);

// =====================================================
// PARTE 01: RESUMEN DE TALLAS POR SEXO
// =====================================================
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetFillColor(43, 79, 255);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  ' . txt('PARTE 01 - RESUMEN DE TALLAS'), 0, 1, 'L', true);
$pdf->Ln(5);

// Calcular anchos para las tablas de resumen
$anchoTalla = 20;
$anchoCantidad = 20;
$anchoTabla = $anchoTalla + $anchoCantidad;

// Contenedor para ambas tablas lado a lado
$posY = $pdf->GetY();
$posXVarones = 15;
$posXDamas = 110;

// ---- Tabla de VARONES ----
$pdf->SetXY($posXVarones, $posY);

// Titulo Varones
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetFillColor(43, 79, 255);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($anchoTabla, 7, txt('VARONES (' . count($varones) . ')'), 1, 1, 'C', true);

$pdf->SetX($posXVarones);
// Encabezados
$pdf->SetFillColor(240, 244, 255);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell($anchoTalla, 6, txt('Talla'), 1, 0, 'C', true);
$pdf->Cell($anchoCantidad, 6, txt('Cant.'), 1, 1, 'C', true);

// Datos varones
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(30, 30, 30);
foreach ($tallasVarones as $talla => $cantidad) {
    $pdf->SetX($posXVarones);
    $pdf->Cell($anchoTalla, 6, txt($talla), 1, 0, 'C');
    $pdf->Cell($anchoCantidad, 6, $cantidad, 1, 1, 'C');
}

// Total varones
$pdf->SetX($posXVarones);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(240, 244, 255);
$pdf->Cell($anchoTalla, 6, txt('TOTAL'), 1, 0, 'C', true);
$pdf->Cell($anchoCantidad, 6, count($varones), 1, 1, 'C', true);

// ---- Tabla de DAMAS ----
$pdf->SetXY($posXDamas, $posY);

// Titulo Damas
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetFillColor(236, 72, 153); // Color rosa
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($anchoTabla, 7, txt('DAMAS (' . count($damas) . ')'), 1, 1, 'C', true);

$pdf->SetX($posXDamas);
// Encabezados
$pdf->SetFillColor(255, 240, 245);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell($anchoTalla, 6, txt('Talla'), 1, 0, 'C', true);
$pdf->Cell($anchoCantidad, 6, txt('Cant.'), 1, 1, 'C', true);

// Datos damas
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(30, 30, 30);
foreach ($tallasDamas as $talla => $cantidad) {
    $pdf->SetX($posXDamas);
    $pdf->Cell($anchoTalla, 6, txt($talla), 1, 0, 'C');
    $pdf->Cell($anchoCantidad, 6, $cantidad, 1, 1, 'C');
}

// Total damas
$pdf->SetX($posXDamas);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetFillColor(255, 240, 245);
$pdf->Cell($anchoTalla, 6, txt('TOTAL'), 1, 0, 'C', true);
$pdf->Cell($anchoCantidad, 6, count($damas), 1, 1, 'C', true);

// Calcular altura máxima de las tablas de resumen
$alturaVarones = 7 + 6 + (count($tallasVarones) * 6) + 6; // titulo + header + filas + total
$alturaDamas = 7 + 6 + (count($tallasDamas) * 6) + 6;
$alturaMaxima = max($alturaVarones, $alturaDamas);

// Mover el cursor después de las tablas
$pdf->SetY($posY + $alturaMaxima + 10);

// =====================================================
// RESUMEN GENERAL
// =====================================================
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(30, 30, 30);
$totalIntegrantes = count($integrantes);
$pdf->Cell(0, 6, txt('Total General de Integrantes: ' . $totalIntegrantes), 0, 1, 'C');

$pdf->Ln(8);

// =====================================================
// PARTE 02: TABLA DE INTEGRANTES
// =====================================================
$pdf->SetFont('Helvetica', 'B', 11);
$pdf->SetFillColor(43, 79, 255);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 8, '  ' . txt('PARTE 02 - LISTA DE INTEGRANTES'), 0, 1, 'L', true);
$pdf->Ln(3);

// Encabezados de tabla
$pdf->SetFillColor(240, 244, 255);
$pdf->SetTextColor(30, 30, 30);
$pdf->SetFont('Helvetica', 'B', 8);

// Anchos de columna: #(8), Nombre(55), Talla(15), Numero(15), Sexo(18), Short(15), Observacion(64)
$pdf->Cell(8, 7, txt('Nro'), 1, 0, 'C', true);
$pdf->Cell(55, 7, txt('Nombre'), 1, 0, 'C', true);
$pdf->Cell(15, 7, txt('Talla'), 1, 0, 'C', true);
$pdf->Cell(15, 7, txt('Num.'), 1, 0, 'C', true);
$pdf->Cell(18, 7, txt('Sexo'), 1, 0, 'C', true);
$pdf->Cell(15, 7, txt('Short'), 1, 0, 'C', true);
$pdf->Cell(64, 7, txt('Observacion'), 1, 1, 'C', true);

$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(30, 30, 30);

// Filas de integrantes
$correlativo = 1;
foreach ($integrantes as $int) {
    // Verificar si necesitamos una nueva página
    if ($pdf->GetY() > 260) {
        $pdf->AddPage();
        // Repetir encabezados en nueva página
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(240, 244, 255);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell(8, 7, txt('Nro'), 1, 0, 'C', true);
        $pdf->Cell(55, 7, txt('Nombre'), 1, 0, 'C', true);
        $pdf->Cell(15, 7, txt('Talla'), 1, 0, 'C', true);
        $pdf->Cell(15, 7, txt('Num.'), 1, 0, 'C', true);
        $pdf->Cell(18, 7, txt('Sexo'), 1, 0, 'C', true);
        $pdf->Cell(15, 7, txt('Short'), 1, 0, 'C', true);
        $pdf->Cell(64, 7, txt('Observacion'), 1, 1, 'C', true);
        $pdf->SetFont('Helvetica', '', 8);
    }
    
    // Color de fondo alternado para mejor lectura
    if ($correlativo % 2 == 0) {
        $pdf->SetFillColor(248, 250, 255);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    // Preparar datos
    $nombre = txt(substr($int['nombre'], 0, 30));
    $talla = txt(strtoupper($int['talla'] ?? '-'));
    $numero = txt($int['numero'] ?? '-');
    $sexo = $int['sexo'] === 'Varon' ? txt('Varon') : txt('Dama');
    $short = $int['incluye_short'] ? txt('Si') : txt('No');
    $observacion = txt(substr($int['observacion'] ?? '-', 0, 35));
    
    // Colorear según sexo
    if ($int['sexo'] === 'Varon') {
        $pdf->SetTextColor(43, 79, 255); // Azul para varones
    } else {
        $pdf->SetTextColor(236, 72, 153); // Rosa para damas
    }
    
    $pdf->Cell(8, 6, $correlativo, 1, 0, 'C', true);
    $pdf->SetTextColor(30, 30, 30); // Reset color para nombre
    $pdf->Cell(55, 6, $nombre, 1, 0, 'L', true);
    $pdf->Cell(15, 6, $talla, 1, 0, 'C', true);
    $pdf->Cell(15, 6, $numero, 1, 0, 'C', true);
    if ($int['sexo'] === 'Varon') {
        $pdf->SetTextColor(43, 79, 255);
    } else {
        $pdf->SetTextColor(236, 72, 153);
    }
    $pdf->Cell(18, 6, $sexo, 1, 0, 'C', true);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell(15, 6, $short, 1, 0, 'C', true);
    $pdf->Cell(64, 6, $observacion, 1, 1, 'L', true);
    
    $correlativo++;
}

// =====================================================
// TOTALES FINALES
// =====================================================
$pdf->Ln(8);

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetFillColor(240, 244, 255);
$pdf->SetTextColor(43, 79, 255);

// Resumen en una sola linea
$pdf->Cell(0, 8, txt('RESUMEN: ' . count($varones) . ' Varones | ' . count($damas) . ' Damas | ' . $totalIntegrantes . ' Total'), 0, 1, 'C');

// Contar shorts
$totalShorts = 0;
foreach ($integrantes as $int) {
    if ($int['incluye_short']) {
        $totalShorts++;
    }
}
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(0, 6, txt('Integrantes con Short: ' . $totalShorts . ' | Sin Short: ' . ($totalIntegrantes - $totalShorts)), 0, 1, 'C');

// Generar el PDF
$filename = 'Integrantes_' . $pedido['codigo'] . '_' . date('Ymd_His') . '.pdf';
$pdf->Output('D', $filename);
?>
