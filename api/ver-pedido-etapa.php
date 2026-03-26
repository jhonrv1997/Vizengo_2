<?php
/**
 * VIZENGO - API para cargar datos de etapas del pedido
 * Este archivo maneja las peticiones AJAX para cargar cada etapa de forma diferida
 * 
 * OPTIMIZACIONES:
 * 1. Solo carga los datos de la etapa solicitada
 * 2. SELECT específico de columnas necesarias
 * 3. Consultas preparadas para seguridad
 */

require_once __DIR__ . '/../config.php';
startSecureSession();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    exit('No autorizado');
}

// Validar parámetros
if (!isset($_GET['pedido_id']) || !isset($_GET['etapa'])) {
    http_response_code(400);
    exit('Parámetros inválidos');
}

$pedidoId = intval($_GET['pedido_id']);
$etapa = $_GET['etapa'];
$db = getDB();

// Funciones helper
/*
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date === '0000-00-00') return '-';
    $d = new DateTime($date);
    return $d->format($format);
}
*/
/*
function formatCurrency($amount) {
    return 'S/ ' . number_format($amount, 2, '.', ',');
}
*/
function getStatusBadge($status, $type = 'contrato') {
    $badgeClass = '';
    $statusText = '';
    
    switch($type) {
        case 'contrato':
        case 'integrantes':
        case 'diseno':
        case 'planchado':
        case 'costura':
            if ($status === 'completo' || $status === 'aprobado') {
                $badgeClass = 'badge-completo';
                $statusText = 'Completo';
            } else {
                $badgeClass = 'badge-pendiente';
                $statusText = 'Pendiente';
            }
            break;
        case 'general':
            switch($status) {
                case 'en_proceso':
                    $badgeClass = 'badge-pendiente';
                    $statusText = 'En Proceso';
                    break;
                case 'listo_entrega':
                    $badgeClass = 'badge-completo';
                    $statusText = 'Listo Entrega';
                    break;
                case 'entregado':
                    $badgeClass = 'badge-completo';
                    $statusText = 'Entregado';
                    break;
                case 'cancelado':
                    $badgeClass = 'badge-urgente';
                    $statusText = 'Cancelado';
                    break;
                default:
                    $badgeClass = 'badge-pendiente';
                    $statusText = 'En Proceso';
            }
            break;
    }
    
    return '<span class="status-badge ' . $badgeClass . '"><span class="dot"></span>' . $statusText . '</span>';
}

// Router de etapas
switch ($etapa) {
    case 'contrato':
        renderEtapaContrato($pedidoId, $db);
        break;
    case 'integrantes':
        renderEtapaIntegrantes($pedidoId, $db);
        break;
    case 'diseno':
        renderEtapaDiseno($pedidoId, $db);
        break;
    case 'planchado':
        renderEtapaPlanchado($pedidoId, $db);
        break;
    case 'costura':
        renderEtapaCostura($pedidoId, $db);
        break;
    case 'entrega':
        renderEtapaEntrega($pedidoId, $db);
        break;
    case 'historial':
        renderHistorial($pedidoId, $db);
        break;
    default:
        http_response_code(400);
        echo '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Etapa no válida</p></div>';
}

// ============================================
// FUNCIONES DE RENDERIZADO POR ETAPA
// ============================================

function renderEtapaContrato($pedidoId, $db) {
    // Consulta optimizada: datos del contrato + pedido en una sola query
    $stmt = $db->prepare("SELECT 
                            p.tipo_contrato, p.lugar_entrega, p.direccion_envio, 
                            p.vendedor_asignado, p.celular_cliente,
                            p.observaciones_generales, p.observaciones_diseno,
                            p.fecha_pedido, p.fecha_entrega, p.hora_entrega,
                            p.subtotal, p.adelanto, p.saldo,
                            c.celular as cliente_celular, u.nombre as vendedor_nombre
                           FROM pedidos p 
                           LEFT JOIN clientes c ON p.cliente_id = c.id
                           LEFT JOIN usuarios u ON p.usuario_id = u.id
                           WHERE p.id = ?");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch();
    
    // Diseños iniciales (solo columnas necesarias)
    $stmt = $db->prepare("SELECT imagen_path, fecha_subida FROM disenos_iniciales WHERE pedido_id = ? ORDER BY id ASC");
    $stmt->execute([$pedidoId]);
    $disenosIniciales = $stmt->fetchAll();
    
    // Kits (solo columnas necesarias)
    $stmt = $db->prepare("SELECT camiseta_tipo, camiseta_tela, camiseta_talla, 
                                 short_tipo, short_tela, short_talla, 
                                 medias_tipo, medias_detalles, cantidad, 
                                 precio_unitario, subtotal 
                          FROM kits WHERE pedido_id = ? ORDER BY id ASC");
    $stmt->execute([$pedidoId]);
    $kits = $stmt->fetchAll();
    
    // Adicionales de talla
    $stmt = $db->prepare("SELECT talla, cantidad, precio_unitario FROM adicionales_talla WHERE pedido_id = ? ORDER BY id ASC");
    $stmt->execute([$pedidoId]);
    $adicionalesTalla = $stmt->fetchAll();
    
    // Merchandising
    $stmt = $db->prepare("SELECT articulo, cantidad, precio_unitario, es_regalo, especificaciones FROM merchandising WHERE pedido_id = ? ORDER BY id ASC");
    $stmt->execute([$pedidoId]);
    $merchandising = $stmt->fetchAll();
    ?>
    
    <!-- Datos del contrato -->
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:16px; margin-bottom:12px;">
        <i class="fas fa-file-signature" style="margin-right:6px;"></i>Datos del Contrato
    </h6>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-item-label">Tipo de Contrato</div>
            <div class="info-item-value"><?php echo htmlspecialchars($pedido['tipo_contrato']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Lugar de Entrega</div>
            <div class="info-item-value"><?php echo htmlspecialchars($pedido['lugar_entrega']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Celular Cliente</div>
            <div class="info-item-value"><?php echo htmlspecialchars($pedido['celular_cliente'] ?: $pedido['cliente_celular'] ?: '-'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Vendedor</div>
            <div class="info-item-value"><?php echo htmlspecialchars($pedido['vendedor_asignado'] ?: $pedido['vendedor_nombre']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Fecha de Pedido</div>
            <div class="info-item-value"><?php echo formatDate($pedido['fecha_pedido'], 'd/m/Y H:i'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Fecha de Entrega</div>
            <div class="info-item-value"><?php echo formatDate($pedido['fecha_entrega'], 'd/m/Y'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Hora de Entrega</div>
            <div class="info-item-value"><?php echo $pedido['hora_entrega'] ? date('H:i', strtotime($pedido['hora_entrega'])) : '-'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Dirección de Envío</div>
            <div class="info-item-value"><?php echo htmlspecialchars($pedido['direccion_envio'] ?: '-'); ?></div>
        </div>
        <?php if (!empty($pedido['observaciones_generales'])): ?>
        <div class="info-item" style="grid-column: 1 / -1;">
            <div class="info-item-label">Observaciones Generales</div>
            <div class="info-item-value"><?php echo nl2br(htmlspecialchars($pedido['observaciones_generales'])); ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($pedido['observaciones_diseno'])): ?>
        <div class="info-item" style="grid-column: 1 / -1;">
            <div class="info-item-label">Observaciones de Diseño</div>
            <div class="info-item-value"><?php echo nl2br(htmlspecialchars($pedido['observaciones_diseno'])); ?></div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Resumen financiero -->
    <div class="totals-summary">
        <div class="total-item">
            <div class="total-item-label">Subtotal</div>
            <div class="total-item-value"><?php echo formatCurrency($pedido['subtotal']); ?></div>
        </div>
        <div class="total-item">
            <div class="total-item-label">Adelanto</div>
            <div class="total-item-value success"><?php echo formatCurrency($pedido['adelanto']); ?></div>
        </div>
        <div class="total-item">
            <div class="total-item-label">Saldo Pendiente</div>
            <div class="total-item-value accent"><?php echo formatCurrency($pedido['saldo']); ?></div>
        </div>
    </div>

    <!-- Botón Generar PDF -->
    <div style="margin-top: 20px; text-align: center;">
        <a href="api/generar-boleta-pdf.php?pedido_id=<?php echo $pedidoId; ?>" 
           target="_blank"
           class="btn btn-primary"
           style="background: linear-gradient(135deg, var(--primary), #4a6cf7); border: none; padding: 12px 30px; font-weight: 600; border-radius: 8px; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(43, 79, 255, 0.3); transition: all 0.3s ease;">
            <i class="fas fa-file-pdf"></i>
            Generar Contrato PDF
        </a>
    </div>

    <!-- Diseños iniciales (referencias) -->
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
        <i class="fas fa-images" style="margin-right:6px;"></i>Diseños de Referencia
    </h6>
    <?php if (!empty($disenosIniciales)): ?>
    <div class="img-gallery">
        <?php foreach ($disenosIniciales as $img): ?>
        <div class="img-gallery-item" onclick="openModal('<?php echo htmlspecialchars($img['imagen_path']); ?>')">
            <img src="<?php echo htmlspecialchars($img['imagen_path']); ?>" alt="Referencia" loading="lazy">
            <div class="img-overlay"><?php echo formatDate($img['fecha_subida'], 'd/m/Y'); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-images"></i>
        <p>No hay diseños de referencia registrados</p>
    </div>
    <?php endif; ?>

    <!-- Kits -->
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
        <i class="fas fa-tshirt" style="margin-right:6px;"></i>Kits / Productos
    </h6>
    <?php if (!empty($kits)): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Camiseta</th>
                <th>Tela</th>
                <th>Short</th>
                <th>Tela Short</th>
                <th>Medias</th>
                <th>Cant.</th>
                <th>P. Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($kits as $kit): ?>
            <tr>
                <td><?php echo htmlspecialchars($kit['camiseta_tipo'] ?: '-'); ?><br><small class="text-muted"><?php echo htmlspecialchars($kit['camiseta_talla'] ?: ''); ?></small></td>
                <td><?php echo htmlspecialchars($kit['camiseta_tela'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($kit['short_tipo'] ?: '-'); ?><br><small class="text-muted"><?php echo htmlspecialchars($kit['short_talla'] ?: ''); ?></small></td>
                <td><?php echo htmlspecialchars($kit['short_tela'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($kit['medias_tipo'] ?: '-'); ?><?php echo $kit['medias_detalles'] ? ' - ' . htmlspecialchars($kit['medias_detalles']) : ''; ?></td>
                <td><strong><?php echo $kit['cantidad']; ?></strong></td>
                <td><?php echo formatCurrency($kit['precio_unitario']); ?></td>
                <td><strong style="color:var(--primary);"><?php echo formatCurrency($kit['subtotal']); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-tshirt"></i>
        <p>No hay kits registrados</p>
    </div>
    <?php endif; ?>

    <!-- Adicionales de talla -->
    <?php if (!empty($adicionalesTalla)): ?>
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
        <i class="fas fa-tags" style="margin-right:6px;"></i>Adicionales Talla Especial
    </h6>
    <table class="data-table">
        <thead>
            <tr>
                <th>Talla</th>
                <th>Cantidad</th>
                <th>Precio Unitario</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($adicionalesTalla as $adicional): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($adicional['talla']); ?></strong></td>
                <td><?php echo $adicional['cantidad']; ?></td>
                <td><?php echo formatCurrency($adicional['precio_unitario']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Merchandising -->
    <?php if (!empty($merchandising)): ?>
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
        <i class="fas fa-flag" style="margin-right:6px;"></i>Merchandising
    </h6>
    <table class="data-table">
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Tipo</th>
                <th>Especificaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($merchandising as $merch): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($merch['articulo']); ?></strong></td>
                <td><?php echo $merch['cantidad']; ?></td>
                <td><?php echo formatCurrency($merch['precio_unitario']); ?></td>
                <td><?php echo $merch['es_regalo'] ? '<span class="regalo-badge"><i class="fas fa-gift"></i> Regalo</span>' : 'Venta'; ?></td>
                <td><?php echo htmlspecialchars($merch['especificaciones'] ?: '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php
}

function renderEtapaIntegrantes($pedidoId, $db) {
    $stmt = $db->prepare("SELECT nombre, talla, numero, observacion, incluye_short, sexo FROM integrantes WHERE pedido_id = ? ORDER BY id ASC");
    $stmt->execute([$pedidoId]);
    $integrantes = $stmt->fetchAll();
    
    $stmt = $db->prepare("SELECT imagen_path FROM imagenes_integrantes WHERE pedido_id = ?");
    $stmt->execute([$pedidoId]);
    $imagenesIntegrantes = $stmt->fetch();
    
    $totalIntegrantes = count($integrantes);
    ?>
    
    <?php if (!empty($integrantes)): ?>
    <!-- Botón Generar PDF de Integrantes -->
    <div style="margin-top: 16px; margin-bottom: 16px; text-align: center;">
        <a href="api/generar-pdf-integrantes.php?pedido_id=<?php echo $pedidoId; ?>" 
           target="_blank"
           class="btn btn-primary"
           style="background: linear-gradient(135deg, var(--primary), #4a6cf7); border: none; padding: 12px 30px; font-weight: 600; border-radius: 8px; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(43, 79, 255, 0.3); transition: all 0.3s ease;">
            <i class="fas fa-file-pdf"></i>
            Generar PDF de Integrantes
        </a>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Nombre</th>
                <th>Talla</th>
                <th>Número</th>
                <th>Sexo</th>
                <th>Short</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($integrantes as $i => $int): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($int['nombre']); ?></strong></td>
                <td><?php echo htmlspecialchars($int['talla'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($int['numero'] ?: '-'); ?></td>
                <td>
                    <span class="sexo-badge sexo-<?php echo strtolower($int['sexo']); ?>">
                        <i class="fas fa-<?php echo $int['sexo'] === 'Varon' ? 'mars' : 'venus'; ?>"></i>
                        <?php echo $int['sexo']; ?>
                    </span>
                </td>
                <td><?php echo $int['incluye_short'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>'; ?></td>
                <td><?php echo htmlspecialchars($int['observacion'] ?: '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Resumen de integrantes -->
    <?php 
    $varones = 0; $damas = 0;
    foreach ($integrantes as $int) {
        if ($int['sexo'] === 'Varon') $varones++;
        else $damas++;
    }
    ?>
    <div class="row mt-3">
        <div class="col-md-4">
            <div class="info-item">
                <div class="info-item-label">Total Integrantes</div>
                <div class="info-item-value"><?php echo $totalIntegrantes; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-item">
                <div class="info-item-label">Varones</div>
                <div class="info-item-value" style="color:var(--primary);"><?php echo $varones; ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-item">
                <div class="info-item-label">Damas</div>
                <div class="info-item-value" style="color:#ec4899;"><?php echo $damas; ?></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <p>No hay integrantes registrados</p>
    </div>
    <?php endif; ?>

    <!-- Imágenes de integrantes -->
    <?php if (!empty($imagenesIntegrantes)): ?>
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:24px; margin-bottom:12px;">
        <i class="fas fa-images" style="margin-right:6px;"></i>Imagen de Referencia de Integrantes
    </h6>
    <div class="img-gallery">
        <div class="img-gallery-item" onclick="openModal('<?php echo htmlspecialchars($imagenesIntegrantes['imagen_path']); ?>')">
            <img src="<?php echo htmlspecialchars($imagenesIntegrantes['imagen_path']); ?>" alt="Integrantes" loading="lazy">
        </div>
    </div>
    <?php endif; ?>
    <?php
}

function renderEtapaDiseno($pedidoId, $db) {
    $stmt = $db->prepare("SELECT df.tipo, df.imagen_path, df.observaciones, df.fecha_subida, df.aprobado, u.nombre as disenador_nombre 
                          FROM disenos_finales df 
                          LEFT JOIN usuarios u ON df.disenador_id = u.id
                          WHERE df.pedido_id = ? ORDER BY df.id ASC");
    $stmt->execute([$pedidoId]);
    $disenosFinales = $stmt->fetchAll();
    ?>
    
    <?php if (!empty($disenosFinales)): ?>
    <div class="img-gallery">
        <?php foreach ($disenosFinales as $diseno): ?>
        <div class="img-gallery-item" onclick="openModal('<?php echo htmlspecialchars($diseno['imagen_path']); ?>')">
            <img src="<?php echo htmlspecialchars($diseno['imagen_path']); ?>" alt="<?php echo htmlspecialchars($diseno['tipo']); ?>" loading="lazy">
            <div class="img-overlay">
                <strong><?php echo ucfirst($diseno['tipo']); ?></strong>
                <?php if ($diseno['aprobado']): ?>
                <i class="fas fa-check-circle" style="color:var(--success); margin-left:4px;"></i>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <table class="data-table" style="margin-top:16px;">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Diseñador</th>
                <th>Observaciones</th>
                <th>Fecha</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($disenosFinales as $diseno): ?>
            <tr>
                <td><strong><?php echo ucfirst(htmlspecialchars($diseno['tipo'])); ?></strong></td>
                <td><?php echo htmlspecialchars($diseno['disenador_nombre'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($diseno['observaciones'] ?: '-'); ?></td>
                <td><?php echo formatDate($diseno['fecha_subida'], 'd/m/Y H:i'); ?></td>
                <td>
                    <?php if ($diseno['aprobado']): ?>
                    <span class="status-badge badge-completo"><span class="dot"></span>Aprobado</span>
                    <?php else: ?>
                    <span class="status-badge badge-pendiente"><span class="dot"></span>Pendiente</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-paint-brush"></i>
        <p>No hay diseños finales registrados</p>
    </div>
    <?php endif; ?>
    <?php
}

function renderEtapaPlanchado($pedidoId, $db) {
    // Consulta optimizada con LEFT JOIN para incluir merchandising
    $stmt = $db->prepare("SELECT p.id, p.planchador_nombre, p.cant_polos, p.cant_shorts, p.cant_cuellos,
                                 p.precio_polo, p.precio_short, p.precio_cuello, p.total_pago, 
                                 p.observaciones, p.fecha_planchado, pl.nombre as planchador_registrado
                          FROM planchado p
                          LEFT JOIN planchadores pl ON p.planchador_id = pl.id
                          WHERE p.pedido_id = ? ORDER BY p.id ASC");
    $stmt->execute([$pedidoId]);
    $planchados = $stmt->fetchAll();
    ?>
    
    <?php if (!empty($planchados)): ?>
    <?php foreach ($planchados as $planchado): ?>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-item-label">Planchador</div>
            <div class="info-item-value"><?php echo htmlspecialchars($planchado['planchador_nombre'] ?: $planchado['planchador_registrado'] ?: '-'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Fecha de Planchado</div>
            <div class="info-item-value"><?php echo formatDate($planchado['fecha_planchado'], 'd/m/Y'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Total a Pagar</div>
            <div class="info-item-value" style="color:var(--success);"><?php echo formatCurrency($planchado['total_pago']); ?></div>
        </div>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><i class="fas fa-tshirt" style="color:var(--primary);margin-right:6px;"></i>Polos</td>
                <td><?php echo $planchado['cant_polos']; ?></td>
                <td><?php echo formatCurrency($planchado['precio_polo']); ?></td>
                <td><?php echo formatCurrency($planchado['cant_polos'] * $planchado['precio_polo']); ?></td>
            </tr>
            <tr>
                <td><i class="fas fa-vest" style="color:var(--accent);margin-right:6px;"></i>Shorts</td>
                <td><?php echo $planchado['cant_shorts']; ?></td>
                <td><?php echo formatCurrency($planchado['precio_short']); ?></td>
                <td><?php echo formatCurrency($planchado['cant_shorts'] * $planchado['precio_short']); ?></td>
            </tr>
            <tr>
                <td><i class="fas fa-circle" style="color:#a855f7;margin-right:6px;"></i>Cuellos</td>
                <td><?php echo $planchado['cant_cuellos']; ?></td>
                <td><?php echo formatCurrency($planchado['precio_cuello']); ?></td>
                <td><?php echo formatCurrency($planchado['cant_cuellos'] * $planchado['precio_cuello']); ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr style="background:#f0f4ff;">
                <td colspan="3"><strong>TOTAL</strong></td>
                <td><strong style="color:var(--success);"><?php echo formatCurrency($planchado['total_pago']); ?></strong></td>
            </tr>
        </tfoot>
    </table>
    
    <?php if ($planchado['observaciones']): ?>
    <div class="info-item" style="margin-top:16px;">
        <div class="info-item-label">Observaciones</div>
        <div class="info-item-value"><?php echo nl2br(htmlspecialchars($planchado['observaciones'])); ?></div>
    </div>
    <?php endif; ?>
    
    <?php 
    // Merchandising de planchado
    $stmt = $db->prepare("SELECT articulo, cantidad, precio_unitario FROM planchado_merchandising WHERE planchado_id = ?");
    $stmt->execute([$planchado['id']]);
    $planchadoMerch = $stmt->fetchAll();
    
    if (!empty($planchadoMerch)): 
    ?>
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:16px; margin-bottom:12px;">
        <i class="fas fa-flag" style="margin-right:6px;"></i>Merchandising Planchado
    </h6>
    <table class="data-table">
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($planchadoMerch as $merch): ?>
            <tr>
                <td><?php echo htmlspecialchars($merch['articulo']); ?></td>
                <td><?php echo $merch['cantidad']; ?></td>
                <td><?php echo formatCurrency($merch['precio_unitario']); ?></td>
                <td><?php echo formatCurrency($merch['cantidad'] * $merch['precio_unitario']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <hr style="margin: 24px 0; border-color: var(--border);">
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-tshirt"></i>
        <p>No hay registros de planchado</p>
    </div>
    <?php endif; ?>
    <?php
}

function renderEtapaCostura($pedidoId, $db) {
    $stmt = $db->prepare("SELECT c.id, c.costurero_nombre, c.cant_polos, c.cant_shorts, 
                                 c.precio_polo, c.precio_short, c.total_pago, c.observaciones, 
                                 c.fecha_costura, cos.nombre as costurero_registrado
                          FROM costura c
                          LEFT JOIN costureros cos ON c.costurero_id = cos.id
                          WHERE c.pedido_id = ? ORDER BY c.id ASC");
    $stmt->execute([$pedidoId]);
    $costuras = $stmt->fetchAll();
    ?>
    
    <?php if (!empty($costuras)): ?>
    <?php foreach ($costuras as $costura): ?>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-item-label">Costurero</div>
            <div class="info-item-value"><?php echo htmlspecialchars($costura['costurero_nombre'] ?: $costura['costurero_registrado'] ?: '-'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Fecha de Costura</div>
            <div class="info-item-value"><?php echo formatDate($costura['fecha_costura'], 'd/m/Y'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Total a Pagar</div>
            <div class="info-item-value" style="color:var(--success);"><?php echo formatCurrency($costura['total_pago']); ?></div>
        </div>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Artículo</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><i class="fas fa-tshirt" style="color:var(--primary);margin-right:6px;"></i>Polos</td>
                <td><?php echo $costura['cant_polos']; ?></td>
                <td><?php echo formatCurrency($costura['precio_polo']); ?></td>
                <td><?php echo formatCurrency($costura['cant_polos'] * $costura['precio_polo']); ?></td>
            </tr>
            <tr>
                <td><i class="fas fa-vest" style="color:var(--accent);margin-right:6px;"></i>Shorts</td>
                <td><?php echo $costura['cant_shorts']; ?></td>
                <td><?php echo formatCurrency($costura['precio_short']); ?></td>
                <td><?php echo formatCurrency($costura['cant_shorts'] * $costura['precio_short']); ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr style="background:#f0f4ff;">
                <td colspan="3"><strong>TOTAL</strong></td>
                <td><strong style="color:var(--success);"><?php echo formatCurrency($costura['total_pago']); ?></strong></td>
            </tr>
        </tfoot>
    </table>
    
    <?php if ($costura['observaciones']): ?>
    <div class="info-item" style="margin-top:16px;">
        <div class="info-item-label">Observaciones</div>
        <div class="info-item-value"><?php echo nl2br(htmlspecialchars($costura['observaciones'])); ?></div>
    </div>
    <?php endif; ?>
    
    <?php 
    // Otros de costura
    $stmt = $db->prepare("SELECT descripcion, cantidad, precio_unitario FROM costura_otros WHERE costura_id = ?");
    $stmt->execute([$costura['id']]);
    $costuraOtros = $stmt->fetchAll();
    
    if (!empty($costuraOtros)): 
    ?>
    <h6 style="font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--primary); margin-top:16px; margin-bottom:12px;">
        <i class="fas fa-plus-circle" style="margin-right:6px;"></i>Otros Trabajos
    </h6>
    <table class="data-table">
        <thead>
            <tr>
                <th>Descripción</th>
                <th>Cantidad</th>
                <th>Precio Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($costuraOtros as $otro): ?>
            <tr>
                <td><?php echo htmlspecialchars($otro['descripcion']); ?></td>
                <td><?php echo $otro['cantidad']; ?></td>
                <td><?php echo formatCurrency($otro['precio_unitario']); ?></td>
                <td><?php echo formatCurrency($otro['cantidad'] * $otro['precio_unitario']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <hr style="margin: 24px 0; border-color: var(--border);">
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-cut"></i>
        <p>No hay registros de costura</p>
    </div>
    <?php endif; ?>
    <?php
}

function renderEtapaEntrega($pedidoId, $db) {
    $stmt = $db->prepare("SELECT e.lugar_entrega, e.es_envio, e.direccion_envio, e.costo_envio, 
                                 e.total_cobrado, e.observaciones, e.fecha_entrega, u.nombre as entregador_nombre
                          FROM entregas e
                          LEFT JOIN usuarios u ON e.usuario_id = u.id
                          WHERE e.pedido_id = ?");
    $stmt->execute([$pedidoId]);
    $entrega = $stmt->fetch();
    ?>
    
    <?php if ($entrega): ?>
    <div class="info-grid">
        <div class="info-item">
            <div class="info-item-label">Lugar de Entrega</div>
            <div class="info-item-value"><?php echo htmlspecialchars($entrega['lugar_entrega']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Entregado por</div>
            <div class="info-item-value"><?php echo htmlspecialchars($entrega['entregador_nombre']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Fecha de Entrega</div>
            <div class="info-item-value"><?php echo formatDate($entrega['fecha_entrega'], 'd/m/Y H:i'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Tipo de Entrega</div>
            <div class="info-item-value">
                <?php if ($entrega['es_envio']): ?>
                    <span class="badge bg-info"><i class="fas fa-truck"></i> Envío</span>
                <?php else: ?>
                    <span class="badge bg-success"><i class="fas fa-store"></i> En Tienda</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($entrega['es_envio'] && $entrega['direccion_envio']): ?>
        <div class="info-item">
            <div class="info-item-label">Dirección de Envío</div>
            <div class="info-item-value"><?php echo htmlspecialchars($entrega['direccion_envio']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-item-label">Costo de Envío</div>
            <div class="info-item-value"><?php echo formatCurrency($entrega['costo_envio']); ?></div>
        </div>
        <?php endif; ?>
        <div class="info-item">
            <div class="info-item-label">Total Cobrado</div>
            <div class="info-item-value" style="color:var(--success); font-size:1.2rem;"><?php echo formatCurrency($entrega['total_cobrado']); ?></div>
        </div>
    </div>
    
    <?php if ($entrega['observaciones']): ?>
    <div class="info-item" style="margin-top:16px;">
        <div class="info-item-label">Observaciones</div>
        <div class="info-item-value"><?php echo nl2br(htmlspecialchars($entrega['observaciones'])); ?></div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-box-open"></i>
        <p>El pedido aún no ha sido entregado</p>
    </div>
    <?php endif; ?>
    <?php
}

function renderHistorial($pedidoId, $db) {
    // Obtener historial general
    $stmt = $db->prepare("SELECT h.accion, h.descripcion, h.fecha_accion, u.nombre as usuario_nombre, u.rol as usuario_rol,
                          'general' as tipo_historial
                          FROM historial_pedidos h
                          LEFT JOIN usuarios u ON h.usuario_id = u.id
                          WHERE h.pedido_id = ?
                          UNION ALL
                          SELECT 
                          CONCAT('MODIFICACIÓN - ', m.tipo_modificacion) as accion,
                          CONCAT('Tabla: ', m.tabla_afectada, 
                                 CASE WHEN m.campo_modificado IS NOT NULL THEN CONCAT(' | Campo: ', m.campo_modificado) ELSE '' END,
                                 CASE WHEN m.valor_anterior IS NOT NULL THEN CONCAT(' | De: ', m.valor_anterior, ' A: ', m.valor_nuevo) ELSE '' END,
                                 CASE WHEN m.motivo IS NOT NULL AND m.motivo != '' THEN CONCAT(' | Motivo: ', m.motivo) ELSE '' END
                          ) as descripcion,
                          m.fecha_modificacion as fecha_accion,
                          u.nombre as usuario_nombre,
                          u.rol as usuario_rol,
                          'modificacion' as tipo_historial
                          FROM modificaciones_pedido m
                          LEFT JOIN usuarios u ON m.usuario_id = u.id
                          WHERE m.pedido_id = ?
                          ORDER BY fecha_accion DESC");
    $stmt->execute([$pedidoId, $pedidoId]);
    $historial = $stmt->fetchAll();
    ?>
    
    <?php if (!empty($historial)): ?>
    <div class="historial-list">
        <?php foreach ($historial as $item): ?>
        <div class="historial-item">
            <div class="historial-icon" style="
                <?php 
                $color = '#6b7280';
                $icon = 'info';
                
                if (strpos($item['accion'], 'CREADO') !== false) {
                    $color = 'var(--success)'; $icon = 'plus';
                } elseif (strpos($item['accion'], 'ENTREGADO') !== false) {
                    $color = 'var(--success)'; $icon = 'check';
                } elseif (strpos($item['accion'], 'ACTUALIZADO') !== false) {
                    $color = 'var(--warning)'; $icon = 'edit';
                } elseif (strpos($item['accion'], 'MODIFICACIÓN') !== false) {
                    if (strpos($item['accion'], 'ADICION') !== false) {
                        $color = 'var(--success)'; $icon = 'plus-circle';
                    } elseif (strpos($item['accion'], 'DISMINUCION') !== false) {
                        $color = 'var(--danger)'; $icon = 'minus-circle';
                    } else {
                        $color = 'var(--warning)'; $icon = 'edit';
                    }
                }
                echo "background: " . $color . "20; color: " . $color . ";";
                ?>
            ">
                <i class="fas fa-<?php echo $icon; ?>"></i>
            </div>
            <div class="historial-content">
                <div class="historial-accion" style="font-weight: 600; color: var(--text);">
                    <?php echo htmlspecialchars($item['accion']); ?>
                    <?php if ($item['tipo_historial'] === 'modificacion'): ?>
                    <span style="background: var(--primary)20; color: var(--primary); padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; margin-left: 6px;">DETALLE</span>
                    <?php endif; ?>
                </div>
                <div class="historial-desc" style="font-size: 0.85rem; color: var(--muted); margin-top: 4px;"><?php echo htmlspecialchars($item['descripcion']); ?></div>
            </div>
            <div class="historial-fecha" style="text-align: right; min-width: 120px;">
                <div style="font-size: 0.85rem; color: var(--text);"><?php echo formatDate($item['fecha_accion'], 'd/m/Y H:i'); ?></div>
                <small style="color: var(--muted);"><?php echo htmlspecialchars($item['usuario_nombre']); ?> (<?php echo $item['usuario_rol']; ?>)</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <style>
        .historial-list { margin-top: 16px; }
        .historial-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px;
            background: #fafbff;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .historial-item:hover {
            border-color: var(--primary);
        }
        .historial-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .historial-content { flex: 1; }
        .historial-fecha { font-size: 0.8rem; }
    </style>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-history"></i>
        <p>No hay historial registrado</p>
    </div>
    <?php endif; ?>
    <?php
}
