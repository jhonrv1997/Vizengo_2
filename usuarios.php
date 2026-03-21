<?php
/**
 * VIZENGO - Gestión de Usuarios
 * Solo accesible para administradores
 */
require_once 'config.php';
startSecureSession();

// Verificar autenticación
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit();
}

$user = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'nombre' => $_SESSION['nombre'],
    'rol' => $_SESSION['rol']
];

// Solo administradores pueden acceder
if ($user['rol'] !== 'administrador') {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear':
            $dni = sanitize($_POST['dni'] ?? '');
            $nombres = sanitize($_POST['nombres'] ?? '');
            $celular = sanitize($_POST['celular'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $rol = sanitize($_POST['rol'] ?? 'vendedor');
            
            if (strlen($dni) !== 8 || !is_numeric($dni)) {
                $error = 'El DNI debe tener 8 dígitos';
            } elseif (empty($nombres)) {
                $error = 'El nombre es requerido';
            } else {
                // Verificar si existe
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE username = ?");
                $stmt->execute([$dni]);
                if ($stmt->fetch()) {
                    $error = 'Ya existe un usuario con ese DNI';
                } else {
                    // Crear usuario (contraseña = DNI)
                    $hashedPassword = password_hash($dni, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO usuarios (username, password, nombre, email, rol) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$dni, $hashedPassword, $nombres, $email, $rol]);
                    $success = 'Usuario creado exitosamente. Contraseña inicial: ' . $dni;
                }
            }
            break;
            
        case 'editar':
            $id = intval($_POST['user_id'] ?? 0);
            $nombres = sanitize($_POST['nombres'] ?? '');
            $celular = sanitize($_POST['celular'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $rol = sanitize($_POST['rol'] ?? 'vendedor');
            $nueva_password = $_POST['nueva_password'] ?? '';
            
            if ($id > 0 && !empty($nombres)) {
                if (!empty($nueva_password)) {
                    $hashedPassword = password_hash($nueva_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, password = ? WHERE id = ?");
                    $stmt->execute([$nombres, $email, $rol, $hashedPassword, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?");
                    $stmt->execute([$nombres, $email, $rol, $id]);
                }
                $success = 'Usuario actualizado exitosamente';
            }
            break;
            
        case 'eliminar':
            $id = intval($_POST['user_id'] ?? 0);
            if ($id > 0 && $id !== $user['id']) {
                $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Usuario eliminado exitosamente';
            } else {
                $error = 'No puedes eliminarte a ti mismo';
            }
            break;
            
        case 'toggle':
            $id = intval($_POST['user_id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Estado actualizado';
            }
            break;
            
        case 'reset_password':
            $id = intval($_POST['user_id'] ?? 0);
            if ($id > 0) {
                // Obtener username (DNI)
                $stmt = $db->prepare("SELECT username FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $u = $stmt->fetch();
                if ($u) {
                    $hashedPassword = password_hash($u['username'], PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $id]);
                    $success = 'Contraseña reseteada. Nueva contraseña: ' . $u['username'];
                }
            }
            break;
    }
}

// Obtener usuarios
$stmt = $db->query("SELECT * FROM usuarios ORDER BY nombre");
$usuarios = $stmt->fetchAll();

// Estadísticas
$stmt = $db->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN rol = 'vendedor' THEN 1 ELSE 0 END) as vendedores,
    SUM(CASE WHEN rol = 'disenador' THEN 1 ELSE 0 END) as disenadores,
    SUM(CASE WHEN rol = 'administrador' THEN 1 ELSE 0 END) as administradores
FROM usuarios WHERE activo = 1");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VIZENGO - Gestión de Usuarios</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Stats cards */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .stat-card.primary::before { background: var(--primary); }
        .stat-card.success::before { background: var(--success); }
        .stat-card.warning::before { background: var(--warning); }
        .stat-card.danger::before { background: var(--danger); }
        .stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 2rem; font-weight: 800; color: var(--text); line-height: 1; }
        .stat-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-top: 6px; }
        .stat-icon { position: absolute; top: 16px; right: 16px; width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .stat-card.primary .stat-icon { background: rgba(43,79,255,0.1); color: var(--primary); }
        .stat-card.success .stat-icon { background: rgba(6,214,160,0.1); color: var(--success); }
        .stat-card.warning .stat-icon { background: rgba(245,158,11,0.1); color: var(--warning); }
        .stat-card.danger .stat-icon { background: rgba(239,71,111,0.1); color: var(--danger); }

        /* Table */
        .tabla-usuarios { width: 100%; border-collapse: collapse; }
        .tabla-usuarios thead th { background: var(--sidebar-bg); color: rgba(255,255,255,0.6); padding: 12px 16px; font-family: 'Barlow Condensed', sans-serif; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; text-align: left; }
        .tabla-usuarios tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
        .tabla-usuarios tbody tr:hover { background: #f8faff; }
        .tabla-usuarios tbody td { padding: 14px 16px; font-size: 0.87rem; vertical-align: middle; }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-cell-avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Barlow Condensed', sans-serif; font-weight: 700; font-size: 1rem; }
        .user-cell-info .user-cell-name { font-weight: 700; color: var(--text); }

        /* Role badges */
        .role-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .role-badge.vendedor { background: rgba(6,214,160,0.12); color: #059669; }
        .role-badge.disenador { background: rgba(245,158,11,0.12); color: #d97706; }
        .role-badge.administrador { background: rgba(239,71,111,0.12); color: #dc2626; }

        /* Status badge */
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-badge.activo { background: rgba(6,214,160,0.12); color: #059669; }
        .status-badge.inactivo { background: rgba(100,116,139,0.1); color: var(--muted); }
        .status-badge .dot { width: 6px; height: 6px; border-radius: 50%; }
        .status-badge.activo .dot { background: #059669; }
        .status-badge.inactivo .dot { background: var(--muted); }

        /* Action buttons */
        .action-btn { background: none; border: 1.5px solid var(--border); border-radius: 6px; padding: 6px 10px; cursor: pointer; color: var(--muted); font-size: 0.8rem; transition: all 0.2s; }
        .action-btn:hover { border-color: var(--primary); color: var(--primary); background: rgba(43,79,255,0.05); }
        .action-btn.edit:hover { border-color: var(--warning); color: var(--warning); background: rgba(245,158,11,0.05); }
        .action-btn.delete:hover { border-color: var(--danger); color: var(--danger); background: rgba(239,71,111,0.05); }
        .action-btn + .action-btn { margin-left: 6px; }

        /* Modal */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.show { display: flex; }
        .modal-box { background: white; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; animation: modalFade 0.25s ease; }
        @keyframes modalFade { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 1.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text); }
        .modal-close { background: none; border: none; font-size: 1.2rem; color: var(--muted); cursor: pointer; padding: 4px; transition: color 0.2s; }
        .modal-close:hover { color: var(--danger); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        /* Role cards */
        .role-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; }
        .role-option { background: var(--bg); border: 2px solid var(--border); border-radius: 12px; padding: 16px; text-align: center; cursor: pointer; transition: all 0.2s; position: relative; }
        .role-option:hover { border-color: var(--primary); background: rgba(43,79,255,0.03); }
        .role-option.selected { border-color: var(--primary); background: rgba(43,79,255,0.08); }
        .role-option.selected::after { content: '✓'; position: absolute; top: 8px; right: 8px; width: 20px; height: 20px; background: var(--primary); color: white; border-radius: 50%; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; }
        .role-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin: 0 auto 10px; }
        .role-option[data-role="vendedor"] .role-icon { background: rgba(6,214,160,0.12); color: var(--success); }
        .role-option[data-role="disenador"] .role-icon { background: rgba(245,158,11,0.12); color: var(--warning); }
        .role-option[data-role="administrador"] .role-icon { background: rgba(239,71,111,0.12); color: var(--danger); }
        .role-name { font-family: 'Barlow Condensed', sans-serif; font-size: 0.95rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .role-desc { font-size: 0.72rem; color: var(--muted); }

        @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .role-selector { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <h1><i class="fas fa-users-cog" style="color:var(--primary);margin-right:10px;"></i>Gestión de Usuarios</h1>
            <p>Administra los usuarios del sistema (Solo Administrador)</p>
        </div>
        <button class="btn-primary-action" onclick="abrirModalNuevo()">
            <i class="fas fa-plus"></i> Nuevo Usuario
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Usuarios</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fas fa-handshake"></i></div>
            <div class="stat-value"><?php echo $stats['vendedores']; ?></div>
            <div class="stat-label">Vendedores</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fas fa-palette"></i></div>
            <div class="stat-value"><?php echo $stats['disenadores']; ?></div>
            <div class="stat-label">Diseñadores</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="stat-value"><?php echo $stats['administradores']; ?></div>
            <div class="stat-label">Administradores</div>
        </div>
    </div>

    <!-- Info banner -->
    <div class="alert-banner" style="background:rgba(43,79,255,0.06);border-color:rgba(43,79,255,0.15);margin-bottom:20px;">
        <div class="alert-banner-icon" style="color:var(--primary);"><i class="fas fa-info-circle"></i></div>
        <div class="alert-banner-text">
            <strong style="color:var(--primary);">Nota:</strong> 
            <span style="color:var(--text);">La contraseña por defecto para nuevos usuarios es su DNI. El usuario deberá cambiarla al iniciar sesión.</span>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if (!empty($success)): ?>
    <div class="alert-banner" style="background:rgba(6,214,160,0.08);border-color:rgba(6,214,160,0.2);margin-bottom:20px;">
        <div class="alert-banner-icon" style="color:var(--success);"><i class="fas fa-check-circle"></i></div>
        <div class="alert-banner-text">
            <strong style="color:#059669;"><?php echo $success; ?></strong>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert-banner" style="background:rgba(239,71,111,0.08);border-color:rgba(239,71,111,0.2);margin-bottom:20px;">
        <div class="alert-banner-icon" style="color:var(--danger);"><i class="fas fa-exclamation-circle"></i></div>
        <div class="alert-banner-text">
            <strong style="color:#dc2626;"><?php echo $error; ?></strong>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla de usuarios -->
    <div class="card-v">
        <div class="card-v-header">
            <h5 class="card-v-title"><i class="fas fa-list" style="margin-right:8px;"></i>Lista de Usuarios</h5>
        </div>
        <div style="overflow-x:auto;">
            <table class="tabla-usuarios">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>DNI</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <?php
                    $avatarColors = [
                        'vendedor' => 'var(--success)',
                        'disenador' => 'var(--warning)',
                        'administrador' => 'var(--danger)'
                    ];
                    ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-cell-avatar" style="background:<?php echo $avatarColors[$u['rol']] ?? 'var(--primary)'; ?>;color:white;">
                                    <?php echo strtoupper(substr($u['nombre'], 0, 1)); ?>
                                </div>
                                <div class="user-cell-info">
                                    <div class="user-cell-name"><?php echo htmlspecialchars($u['nombre']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code style="background:var(--bg);padding:4px 8px;border-radius:4px;font-size:0.85rem;"><?php echo htmlspecialchars($u['username']); ?></code></td>
                        <td><?php echo htmlspecialchars($u['email'] ?? '<span style="color:var(--muted);font-style:italic;">Sin email</span>'); ?></td>
                        <td>
                            <span class="role-badge <?php echo $u['rol']; ?>">
                                <i class="fas fa-<?php echo $u['rol'] === 'vendedor' ? 'handshake' : ($u['rol'] === 'disenador' ? 'palette' : 'shield-alt'); ?>"></i>
                                <?php echo $u['rol']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $u['activo'] ? 'activo' : 'inactivo'; ?>">
                                <span class="dot"></span> <?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td>
                            <button class="action-btn edit" onclick="editarUsuario(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn" onclick="resetPassword(<?php echo $u['id']; ?>)" title="Resetear contraseña">
                                <i class="fas fa-key"></i>
                            </button>
                            <?php if ($u['id'] !== $user['id']): ?>
                            <button class="action-btn delete" onclick="eliminarUsuario(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars($u['nombre']); ?>')" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Nuevo/Editar Usuario -->
<div class="modal-overlay" id="modalUsuario">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle"><i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>Nuevo Usuario</div>
            <button class="modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="formUsuario" method="POST">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="user_id" id="userId" value="">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">DNI <span style="color:var(--danger);">*</span></label>
                            <input type="text" class="field-ctrl" id="inputDni" name="dni" placeholder="12345678" maxlength="8" pattern="[0-9]{8}" required oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            <small style="font-size:0.72rem;color:var(--muted);">Será el ID de usuario y contraseña por defecto</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Nombres <span style="color:var(--danger);">*</span></label>
                            <input type="text" class="field-ctrl" id="inputNombres" name="nombres" placeholder="Juan Pérez García" required>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Celular</label>
                            <input type="tel" class="field-ctrl" id="inputCelular" name="celular" placeholder="999-999-999">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="field-group">
                            <label class="field-lbl">Email</label>
                            <input type="email" class="field-ctrl" id="inputEmail" name="email" placeholder="correo@ejemplo.com">
                        </div>
                    </div>
                </div>

                <div class="field-group mt-3">
                    <label class="field-lbl">Rol <span style="color:var(--danger);">*</span></label>
                    <div class="role-selector">
                        <div class="role-option" data-role="vendedor" onclick="seleccionarRol(this)">
                            <div class="role-icon"><i class="fas fa-handshake"></i></div>
                            <div class="role-name">Vendedor</div>
                            <div class="role-desc">Gestión de pedidos y entregas</div>
                        </div>
                        <div class="role-option" data-role="disenador" onclick="seleccionarRol(this)">
                            <div class="role-icon"><i class="fas fa-palette"></i></div>
                            <div class="role-name">Diseñador</div>
                            <div class="role-desc">Diseños, planchado y costura</div>
                        </div>
                        <div class="role-option" data-role="administrador" onclick="seleccionarRol(this)">
                            <div class="role-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="role-name">Administrador</div>
                            <div class="role-desc">Acceso total al sistema</div>
                        </div>
                    </div>
                    <input type="hidden" name="rol" id="inputRol" value="">
                </div>

                <div class="field-group mt-3" id="passwordGroup" style="display:none;">
                    <label class="field-lbl">Nueva Contraseña</label>
                    <input type="password" class="field-ctrl" id="inputPassword" name="nueva_password" placeholder="Dejar vacío para mantener">
                    <small style="font-size:0.72rem;color:var(--muted);">Dejar vacío para mantener contraseña actual</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-v btn-outline-v" onclick="cerrarModal()">Cancelar</button>
            <button class="btn-v btn-success-v" onclick="document.getElementById('formUsuario').submit();">
                <i class="fas fa-save"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal-overlay" id="modalEliminar">
    <div class="modal-box" style="max-width:400px;">
        <div class="modal-header" style="border-bottom:none;">
            <div class="modal-title" style="color:var(--danger);"><i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Confirmar Eliminación</div>
            <button class="modal-close" onclick="cerrarModalEliminar()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;">
            <i class="fas fa-user-times" style="font-size:3rem;color:var(--danger);opacity:0.5;margin-bottom:16px;"></i>
            <p style="font-size:0.95rem;color:var(--text);margin-bottom:8px;">¿Estás seguro de eliminar al usuario?</p>
            <p style="font-size:0.85rem;color:var(--muted);"><strong id="eliminarNombre"></strong></p>
            <p style="font-size:0.82rem;color:var(--danger);margin-top:12px;">Esta acción no se puede deshacer.</p>
        </div>
        <div class="modal-footer" style="justify-content:center;">
            <button class="btn-v btn-outline-v" onclick="cerrarModalEliminar()">Cancelar</button>
            <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="eliminar">
                <input type="hidden" name="user_id" id="eliminarUserId">
                <button type="submit" class="btn-v btn-danger-v"><i class="fas fa-trash"></i> Eliminar</button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<script>
function seleccionarRol(element) {
    document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('inputRol').value = element.dataset.role;
}

function abrirModalNuevo() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus" style="color:var(--primary);margin-right:8px;"></i>Nuevo Usuario';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('formUsuario').reset();
    document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('inputRol').value = '';
    document.getElementById('inputDni').disabled = false;
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('modalUsuario').classList.add('show');
}

function editarUsuario(userData) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit" style="color:var(--warning);margin-right:8px;"></i>Editar Usuario';
    document.getElementById('formAction').value = 'editar';
    document.getElementById('userId').value = userData.id;
    document.getElementById('inputDni').value = userData.username;
    document.getElementById('inputDni').disabled = true;
    document.getElementById('inputNombres').value = userData.nombre;
    document.getElementById('inputEmail').value = userData.email || '';
    
    // Seleccionar rol
    document.querySelectorAll('.role-option').forEach(el => el.classList.remove('selected'));
    const rolElement = document.querySelector('.role-option[data-role="' + userData.rol + '"]');
    if (rolElement) {
        rolElement.classList.add('selected');
        document.getElementById('inputRol').value = userData.rol;
    }
    
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('inputPassword').value = '';
    document.getElementById('modalUsuario').classList.add('show');
}

function cerrarModal() {
    document.getElementById('modalUsuario').classList.remove('show');
}

function eliminarUsuario(id, nombre) {
    document.getElementById('eliminarUserId').value = id;
    document.getElementById('eliminarNombre').textContent = nombre;
    document.getElementById('modalEliminar').classList.add('show');
}

function cerrarModalEliminar() {
    document.getElementById('modalEliminar').classList.remove('show');
}

function resetPassword(id) {
    if (confirm('¿Deseas resetear la contraseña?\n\nLa nueva contraseña será igual al DNI del usuario.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
</body>
</html>
