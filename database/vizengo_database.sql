-- ============================================
-- VIZENGO - Sistema de Gestión de Pedidos
-- Base de datos MySQL para Cpanel
-- ============================================

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS vizengo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci;
USE vizengo_db;

-- ============================================
-- TABLA: usuarios
-- Usuarios del sistema con roles
-- ============================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
	celular VARCHAR(20),
    email VARCHAR(100),
    rol ENUM('vendedor', 'disenador', 'administrador') NOT NULL DEFAULT 'vendedor',
    activo TINYINT(1) DEFAULT 1,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Insertar usuarios por defecto (contraseña: password)
INSERT INTO usuarios (username, password, nombre, celular, email, rol) VALUES
('jhon', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jhon','991122597' ,'jhon@vizengo.com', 'vendedor'),
('karina', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Karina','99898998', 'karina@vizengo.com', 'vendedor'),
('carolina', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carolina','999999' ,'carolina@vizengo.com', 'disenador'),
('erick', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Erick','912366', 'erick@vizengo.com', 'disenador'),
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador','99999999', 'admin@vizengo.com', 'administrador');

-- ============================================
-- TABLA: clientes
-- Clientes que realizan pedidos
-- ============================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    celular VARCHAR(20),
    email VARCHAR(100),
    direccion TEXT,
    observaciones TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: pedidos
-- Pedidos principales del sistema
-- ============================================
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    cliente_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'Vendedor que registra el pedido',
    
    -- Datos del contrato
    tipo_contrato ENUM('PEDIDO', 'SERVICIO', 'COSTURA', 'ESTAMPADO', 'PLANCHADO') DEFAULT 'PEDIDO',
    lugar_entrega VARCHAR(100) NOT NULL,
    direccion_envio TEXT,
    vendedor_asignado VARCHAR(50),
    celular_cliente VARCHAR(20),
    observaciones_generales TEXT,
    observaciones_diseno TEXT,
    
    -- Estado del pedido (6 etapas)
    estado_contrato ENUM('pendiente', 'completo') DEFAULT 'pendiente',
    estado_integrantes ENUM('pendiente', 'completo') DEFAULT 'pendiente',
    estado_diseno ENUM('pendiente', 'aprobado', 'completo') DEFAULT 'pendiente',
    estado_planchado ENUM('pendiente', 'completo') DEFAULT 'pendiente',
    estado_costura ENUM('pendiente', 'completo') DEFAULT 'pendiente',
    estado_general ENUM('en_proceso', 'listo_entrega', 'entregado', 'cancelado') DEFAULT 'en_proceso',
    
    -- Fechas
    fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega DATE,
	hora_entrega time,
    fecha_limite DATE,
    fecha_completado TIMESTAMP NULL,
    
    -- Totales
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    adelanto DECIMAL(10,2) DEFAULT 0.00,
    saldo DECIMAL(10,2) DEFAULT 0.00,
    
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_codigo (codigo),
    INDEX idx_estado (estado_general),
    INDEX idx_fecha_entrega (fecha_entrega)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: kits
-- Kits/Productos de cada pedido
-- ============================================
CREATE TABLE IF NOT EXISTS kits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    
    -- Camiseta/Superior
    camiseta_tipo VARCHAR(100),
    camiseta_tela VARCHAR(100),
    camiseta_talla VARCHAR(20),
    
    -- Short/Inferior
    short_tipo VARCHAR(100),
    short_tela VARCHAR(100),
    short_talla VARCHAR(20),
    
    -- Medias/Otros
    medias_tipo VARCHAR(100),
    medias_detalles VARCHAR(255),
    
    -- Cantidad y precio
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
	
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: adicionales_talla
-- Adicionales por talla especial
-- ============================================
CREATE TABLE IF NOT EXISTS adicionales_talla (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    talla VARCHAR(10) NOT NULL,
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: merchandising
-- Artículos de merchandising/banderolas
-- ============================================
CREATE TABLE IF NOT EXISTS merchandising (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    articulo VARCHAR(100) NOT NULL,
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    es_regalo TINYINT(1) DEFAULT 0,
    especificaciones TEXT,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: disenos_iniciales
-- Diseños subidos por el vendedor al crear el pedido
-- ============================================
CREATE TABLE IF NOT EXISTS disenos_iniciales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    imagen_path VARCHAR(255) NOT NULL,
    observaciones TEXT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: integrantes
-- Integrantes del equipo (tallas y números)
-- ============================================
CREATE TABLE IF NOT EXISTS integrantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    talla VARCHAR(10),
    numero VARCHAR(10),
    observacion VARCHAR(255),
    incluye_short TINYINT(1) DEFAULT 1,
    sexo ENUM('Varon', 'Dama') DEFAULT 'Varon',
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    INDEX idx_pedido (pedido_id),
    INDEX idx_talla (talla),
    INDEX idx_sexo (sexo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: imagenes_integrantes
-- Imagen de lista de integrantes (alternativa a tabla)
-- ============================================
CREATE TABLE IF NOT EXISTS imagenes_integrantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL UNIQUE,
    imagen_path VARCHAR(255) NOT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: disenos_finales
-- Diseños finales subidos por el diseñador
-- ============================================
CREATE TABLE IF NOT EXISTS disenos_finales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    disenador_id INT NOT NULL,
    tipo ENUM('camiseta', 'short', 'banderola', 'logo') NOT NULL,
    imagen_path VARCHAR(255) NOT NULL,
    observaciones TEXT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aprobado TINYINT(1) DEFAULT 0,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (disenador_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_pedido (pedido_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: planchadores
-- Trabajadores de planchado
-- ============================================
CREATE TABLE IF NOT EXISTS planchadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    precio_polo DECIMAL(10,2) DEFAULT 1.50,
    precio_short DECIMAL(10,2) DEFAULT 1.00,
    precio_cuello DECIMAL(10,2) DEFAULT 0.50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Insertar planchadores por defecto
INSERT INTO planchadores (nombre, precio_polo, precio_short, precio_cuello) VALUES
('Carlos', 1.50, 1.00, 0.50),
('Miguel', 1.50, 1.00, 0.50),
('Rosa', 1.50, 1.00, 0.50);

-- ============================================
-- TABLA: planchado
-- Registro de planchado por pedido
-- ============================================
CREATE TABLE IF NOT EXISTS planchado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    planchador_id INT,
    planchador_nombre VARCHAR(100) COMMENT 'Nombre si es eventual',
    
    cant_polos INT DEFAULT 0,
    cant_shorts INT DEFAULT 0,
    cant_cuellos INT DEFAULT 0,
    
    precio_polo DECIMAL(10,2) DEFAULT 1.50,
    precio_short DECIMAL(10,2) DEFAULT 1.00,
    precio_cuello DECIMAL(10,2) DEFAULT 0.50,
    
    total_pago DECIMAL(10,2) DEFAULT 0.00,
    observaciones TEXT,
    fecha_planchado DATE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (planchador_id) REFERENCES planchadores(id) ON DELETE SET NULL,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: planchado_merchandising
-- Merchandising adicional en planchado
-- ============================================
CREATE TABLE IF NOT EXISTS planchado_merchandising (
    id INT AUTO_INCREMENT PRIMARY KEY,
    planchado_id INT NOT NULL,
    articulo VARCHAR(100) NOT NULL,
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    
    FOREIGN KEY (planchado_id) REFERENCES planchado(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: costureros
-- Trabajadores de costura
-- ============================================
CREATE TABLE IF NOT EXISTS costureros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    precio_polo DECIMAL(10,2) DEFAULT 2.00,
    precio_short DECIMAL(10,2) DEFAULT 1.50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Insertar costureros por defecto
INSERT INTO costureros (nombre, precio_polo, precio_short) VALUES
('Maria', 2.00, 1.50),
('Juan', 2.00, 1.50),
('Carmen', 2.00, 1.50),
('Luis', 2.00, 1.50);

-- ============================================
-- TABLA: costura
-- Registro de costura por pedido
-- ============================================
CREATE TABLE IF NOT EXISTS costura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    costurero_id INT,
    costurero_nombre VARCHAR(100) COMMENT 'Nombre si es eventual',
    
    cant_polos INT DEFAULT 0,
    cant_shorts INT DEFAULT 0,
    
    precio_polo DECIMAL(10,2) DEFAULT 2.00,
    precio_short DECIMAL(10,2) DEFAULT 1.50,
    
    total_pago DECIMAL(10,2) DEFAULT 0.00,
    observaciones TEXT,
    fecha_costura DATE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (costurero_id) REFERENCES costureros(id) ON DELETE SET NULL,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: costura_otros
-- Otros costos adicionales en costura
-- ============================================
CREATE TABLE IF NOT EXISTS costura_otros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    costura_id INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) DEFAULT 0.00,
    
    FOREIGN KEY (costura_id) REFERENCES costura(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: entregas
-- Registro de entregas
-- ============================================
CREATE TABLE IF NOT EXISTS entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'Vendedor que entrega',
    
    lugar_entrega VARCHAR(100) NOT NULL,
    es_envio TINYINT(1) DEFAULT 0,
    direccion_envio TEXT,
    costo_envio DECIMAL(10,2) DEFAULT 0.00,
    
    total_cobrado DECIMAL(10,2) DEFAULT 0.00,
    observaciones TEXT,
    
    fecha_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- TABLA: historial_pedidos
-- Historial de cambios de estado
-- ============================================
CREATE TABLE IF NOT EXISTS historial_pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_accion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_pedido (pedido_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- ============================================
-- DATOS DE EJEMPLO
-- ============================================

-- Insertar clientes de ejemplo
INSERT INTO clientes (nombre, celular, email, direccion) VALUES
('Yanina', '', '', 'Av. Principal 123'),
('Nitza', '', '', 'Av. Principal 123'),
('Lizeth', '', '', 'Av. Principal 123'),

('JOSE CARDENAS', '999-123-456', 'jose@email.com', 'Calle Los Olivos 456'),
('ALBERTO YAPIAS', '999-555-333', 'alberto@email.com', 'Jr. Las Flores 789'),
('TERESA SALAS', '999-444-222', 'teresa@email.com', 'Av. San Martin 321');

-- Insertar pedidos de ejemplo
INSERT INTO pedidos (codigo, cliente_id, usuario_id, tipo_contrato, lugar_entrega, estado_contrato, estado_integrantes, estado_diseno, estado_planchado, estado_costura, estado_general, fecha_entrega, subtotal, adelanto, saldo) VALUES
('PED-2025-0001', 1, 1, 'PEDIDO', 'TIENDA VIZENGO', 'completo', 'completo', 'completo', 'completo', 'completo', 'listo_entrega', CURDATE(), 150.00, 50.00, 100.00),
('PED-2025-0002', 2, 2, 'PEDIDO', 'ENVIO', 'completo', 'completo', 'completo', 'completo', 'pendiente', 'en_proceso', CURDATE(), 120.00, 30.00, 90.00),
('PED-2025-0003', 3, 2, 'PEDIDO', 'TIENDA 2', 'completo', 'completo', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 95.00, 25.00, 70.00),
('PED-2025-0004', 4, 1, 'PEDIDO', 'TIENDA VIZENGO', 'completo', 'pendiente', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 85.00, 0.00, 85.00);

-- Insertar kits de ejemplo
INSERT INTO kits (pedido_id, camiseta_tipo, camiseta_tela, camiseta_talla, short_tipo, short_tela, short_talla, cantidad, precio_unitario) VALUES
(1, 'CAMISETA MANGA CORTA', 'ESPIGA', 'M', 'SHORT', 'MARATHON', 'M', 30, 35.00),
(2, 'POLO 20/1', 'DRY', 'L', 'SHORT', 'DRY', 'L', 12, 40.00),
(3, 'CAMISETA REPLICA', 'MARATHON', 'S', 'SHORT', 'NOVA', 'S', 12, 38.00),
(4, 'CAMISETA MANGA CORTA', 'ESPIGA', 'L', 'NINGUNO', NULL, NULL, 12, 30.00);

-- Insertar integrantes de ejemplo
INSERT INTO integrantes (pedido_id, nombre, talla, numero, observacion, incluye_short, sexo) VALUES
(1, 'Juan Perez', 'M', '10', 'Capitan', 1, 'Varon'),
(1, 'Maria Garcia', 'S', '7', 'Arquera', 1, 'Dama'),
(1, 'Carlos Lopez', 'L', '5', NULL, 1, 'Varon'),
(1, 'Ana Torres', 'XL', '9', 'Talla grande', 1, 'Dama'),
(2, 'Pedro Ruiz', 'S', '11', NULL, 1, 'Varon'),
(2, 'Laura Mendoza', 'M', '8', NULL, 1, 'Dama'),
(2, 'Roberto Diaz', 'XL', '3', 'Portero', 1, 'Varon'),
(2, 'Carmen Vega', 'L', '14', NULL, 1, 'Dama');

SELECT 'Base de datos VIZENGO creada correctamente' as mensaje;
