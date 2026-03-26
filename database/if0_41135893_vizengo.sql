-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql201.infinityfree.com
-- Tiempo de generación: 25-03-2026 a las 21:31:19
-- Versión del servidor: 11.4.10-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `if0_41135893_vizengo`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adicionales_talla`
--

CREATE TABLE `adicionales_talla` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `talla` varchar(10) NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `adicionales_talla`
--

INSERT INTO `adicionales_talla` (`id`, `pedido_id`, `talla`, `cantidad`, `precio_unitario`) VALUES
(2, 4, 'XL', 1, '12.00'),
(3, 9, 'XL', 1, '16.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `celular`, `email`, `direccion`, `observaciones`, `fecha_creacion`) VALUES
(1, 'Yanina', '', '', 'Av. Principal 123', NULL, '2026-03-21 17:16:22'),
(2, 'Nitza', '', '', 'Av. Principal 123', NULL, '2026-03-21 17:16:22'),
(3, 'Lizeth', '', '', 'Av. Principal 123', NULL, '2026-03-21 17:16:22'),
(4, 'Karina', NULL, NULL, NULL, NULL, '2026-03-21 19:00:04'),
(8, 'leslie perez', '996969695', NULL, NULL, NULL, '2026-03-21 23:04:30'),
(9, 'alejandra paredes', '969696316', NULL, NULL, NULL, '2026-03-22 16:51:59'),
(10, 'elena marinez', '969393697', NULL, NULL, NULL, '2026-03-22 20:17:18'),
(11, 'luis marmolejo', '126165456', NULL, NULL, NULL, '2026-03-23 13:56:11'),
(12, 'Liliana Smith', '969669301', NULL, NULL, NULL, '2026-03-23 23:24:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costura`
--

CREATE TABLE `costura` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `costurero_id` int(11) DEFAULT NULL,
  `costurero_nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre si es eventual',
  `cant_polos` int(11) DEFAULT 0,
  `cant_shorts` int(11) DEFAULT 0,
  `precio_polo` decimal(10,2) DEFAULT 2.00,
  `precio_short` decimal(10,2) DEFAULT 1.50,
  `total_pago` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `fecha_costura` date DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `costura`
--

INSERT INTO `costura` (`id`, `pedido_id`, `costurero_id`, `costurero_nombre`, `cant_polos`, `cant_shorts`, `precio_polo`, `precio_short`, `total_pago`, `observaciones`, `fecha_costura`, `fecha_registro`) VALUES
(1, 4, NULL, 'Carmen', 6, 6, '2.00', '1.50', '24.00', 'tela adicionales', '2026-03-22', '2026-03-22 21:44:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costura_otros`
--

CREATE TABLE `costura_otros` (
  `id` int(11) NOT NULL,
  `costura_id` int(11) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `costura_otros`
--

INSERT INTO `costura_otros` (`id`, `costura_id`, `descripcion`, `cantidad`, `precio_unitario`) VALUES
(1, 1, 'cierre', 6, '0.50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `costureros`
--

CREATE TABLE `costureros` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `precio_polo` decimal(10,2) DEFAULT 2.00,
  `precio_short` decimal(10,2) DEFAULT 1.50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `costureros`
--

INSERT INTO `costureros` (`id`, `nombre`, `activo`, `precio_polo`, `precio_short`) VALUES
(1, 'Maria', 1, '2.00', '1.50'),
(2, 'Juan', 1, '2.00', '1.50'),
(3, 'Carmen', 1, '2.00', '1.50'),
(4, 'Luis', 1, '2.00', '1.50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disenos_finales`
--

CREATE TABLE `disenos_finales` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `disenador_id` int(11) NOT NULL,
  `tipo` enum('camiseta','short','banderola','logo') NOT NULL,
  `imagen_path` varchar(255) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp(),
  `aprobado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `disenos_finales`
--

INSERT INTO `disenos_finales` (`id`, `pedido_id`, `disenador_id`, `tipo`, `imagen_path`, `observaciones`, `fecha_subida`, `aprobado`) VALUES
(1, 4, 3, 'camiseta', 'uploads/disenos/69c002fae2fc4_1774191354.jpeg', 'LOGO A LA IZQUIERDA', '2026-03-22 14:55:55', 1),
(2, 4, 3, 'short', 'uploads/disenos/69c0030a70f16_1774191370.jpeg', 'LOGO A LA IZQUIERDA', '2026-03-22 14:56:10', 1),
(3, 5, 3, 'camiseta', 'uploads/disenos/69c02423a8e10_1774199843.jpeg', 'LOGO A LA DERECHA BIEN BONITO', '2026-03-22 17:17:24', 1),
(4, 5, 3, 'short', 'uploads/disenos/69c0243237e24_1774199858.jpeg', 'LOGO A LA DERECHA BIEN BONITO', '2026-03-22 17:17:38', 1),
(5, 6, 3, 'camiseta', 'uploads/disenos/69c04ff514a36_1774211061.jpeg', 'TEXTO MOTIVACIONAL ya cumpli', '2026-03-22 20:24:20', 1),
(6, 6, 3, 'short', 'uploads/disenos/69c05003887b6_1774211075.jpeg', 'TEXTO MOTIVACIONAL', '2026-03-22 20:24:34', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disenos_iniciales`
--

CREATE TABLE `disenos_iniciales` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `imagen_path` varchar(255) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `disenos_iniciales`
--

INSERT INTO `disenos_iniciales` (`id`, `pedido_id`, `imagen_path`, `observaciones`, `fecha_subida`) VALUES
(1, 4, 'uploads/referencias/69bf23fe844f7_1774134270.jpg', '', '2026-03-21 23:04:30'),
(2, 4, 'uploads/referencias/69bf23fe84863_1774134270.jpg', '', '2026-03-21 23:04:30'),
(3, 5, 'uploads/referencias/69c01e2eaf090_1774198318.jpg', '', '2026-03-22 16:51:59'),
(4, 5, 'uploads/referencias/69c01e2eaf31c_1774198318.jpg', '', '2026-03-22 16:51:59'),
(5, 6, 'uploads/referencias/69c04e4f93035_1774210639.jpg', '', '2026-03-22 20:17:18'),
(6, 9, 'uploads/referencias/69c1cbbbd89bc_1774308283.jpg', '', '2026-03-23 23:24:44'),
(7, 9, 'uploads/referencias/69c1cbbbd94f8_1774308283.jpg', '', '2026-03-23 23:24:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entregas`
--

CREATE TABLE `entregas` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Vendedor que entrega',
  `lugar_entrega` varchar(100) NOT NULL,
  `es_envio` tinyint(1) DEFAULT 0,
  `direccion_envio` text DEFAULT NULL,
  `costo_envio` decimal(10,2) DEFAULT 0.00,
  `total_cobrado` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `fecha_entrega` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `entregas`
--

INSERT INTO `entregas` (`id`, `pedido_id`, `usuario_id`, `lugar_entrega`, `es_envio`, `direccion_envio`, `costo_envio`, `total_cobrado`, `observaciones`, `fecha_entrega`) VALUES
(1, 4, 1, 'TIENDA VIZENGO', 0, '', '0.00', '42.00', 'entregado en la tienda', '2026-03-22 21:47:26');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_pedidos`
--

CREATE TABLE `historial_pedidos` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_accion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `historial_pedidos`
--

INSERT INTO `historial_pedidos` (`id`, `pedido_id`, `usuario_id`, `accion`, `descripcion`, `fecha_accion`) VALUES
(1, 4, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0001 creado', '2026-03-21 23:04:30'),
(2, 5, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0002 creado', '2026-03-22 16:51:59'),
(3, 6, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0003 creado', '2026-03-22 20:17:18'),
(4, 4, 1, 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente leslie perez', '2026-03-22 21:47:26'),
(5, 7, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0004 creado', '2026-03-23 13:56:11'),
(6, 8, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0005 creado', '2026-03-23 22:27:55'),
(7, 9, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0006 creado', '2026-03-23 23:24:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `imagenes_integrantes`
--

CREATE TABLE `imagenes_integrantes` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `imagen_path` varchar(255) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `integrantes`
--

CREATE TABLE `integrantes` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `talla` varchar(10) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `incluye_short` tinyint(1) DEFAULT 1,
  `sexo` enum('Varon','Dama') DEFAULT 'Varon'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `integrantes`
--

INSERT INTO `integrantes` (`id`, `pedido_id`, `nombre`, `talla`, `numero`, `observacion`, `incluye_short`, `sexo`) VALUES
(1, 4, 'jihi', 'S', '2', 'arquero', 1, 'Varon'),
(2, 4, 'hild', 'M', '4', '', 1, 'Dama'),
(3, 4, 'leid', 'S', '8', '', 1, 'Dama'),
(4, 4, 'alejdanra', 'L', '9', '', 1, 'Dama'),
(5, 4, 'liskdwe', 'L', '7', '', 1, 'Varon'),
(6, 4, 'waler', 'M', '96', '', 1, 'Varon'),
(7, 5, 'lila', '14', '4', 'arquera', 1, 'Dama'),
(8, 5, 'loli', '12', '7', '', 1, 'Dama'),
(9, 5, 'otoi', 'M', '8', '', 1, 'Varon'),
(10, 5, 'uila', 'L', '9', '', 1, 'Dama'),
(11, 5, 'osil', 'S', '87', '', 1, 'Varon'),
(12, 5, 'ilio', 'M', '12', '', 1, 'Varon'),
(13, 6, 'lin', 'S', '1', 'arquera', 1, 'Dama'),
(14, 6, 'waren', 'M', '4', '', 1, 'Varon'),
(15, 6, 'song', 'M', '9', 'talla ancha', 1, 'Dama'),
(16, 6, 'leny', 'L', '98', '', 1, 'Dama'),
(17, 7, 'pedro', 'M', '12', '', 1, 'Varon'),
(18, 7, 'luis', 'M', '14', '', 1, 'Varon'),
(19, 7, 'alberto', 'L', '15', '', 1, 'Varon'),
(20, 7, 'luis a.', '14', '12', '', 1, 'Dama'),
(21, 7, 'alberto j.', 'S', '14', '', 1, 'Varon'),
(22, 7, 'alberto l.', 'L', '08', '', 1, 'Dama'),
(23, 7, 'alberto  ñ.', 'M', '11', '', 1, 'Varon'),
(24, 7, 'alberto k.', 'S', '12', '', 1, 'Varon');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kits`
--

CREATE TABLE `kits` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `camiseta_tipo` varchar(100) DEFAULT NULL,
  `camiseta_tela` varchar(100) DEFAULT NULL,
  `camiseta_talla` varchar(20) DEFAULT NULL,
  `short_tipo` varchar(100) DEFAULT NULL,
  `short_tela` varchar(100) DEFAULT NULL,
  `short_talla` varchar(20) DEFAULT NULL,
  `medias_tipo` varchar(100) DEFAULT NULL,
  `medias_detalles` varchar(255) DEFAULT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `kits`
--

INSERT INTO `kits` (`id`, `pedido_id`, `camiseta_tipo`, `camiseta_tela`, `camiseta_talla`, `short_tipo`, `short_tela`, `short_talla`, `medias_tipo`, `medias_detalles`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(2, 4, 'CAMISETA MANGA CORTA', 'Tela: ESPIGA', 's, m, l', 'SHORT', 'Tela: MARATHON', 'S, M, L', 'NINGUNO', '', 6, '10.00', '60.00'),
(3, 5, 'CAMISETA BASQUET', 'Tela: ALGODON', '12,14,16', 'SHORT', 'Tela: DRY', '12,14,16', 'NINGUNO', '', 4, '14.00', '56.00'),
(4, 5, 'CAMISETA BASQUET', 'Tela: ALGODON', 'S,M,L', 'SHORT', 'Tela: DRY', 'S,M,L', 'NINGUNO', '', 2, '14.00', '28.00'),
(5, 6, 'CAMISETA MANGA CORTA', 'Tela: ALGODON', 'S,M,L', 'SHORT', 'Tela: DRY', 'S,M,L', 'RODILLERA', 'COLOR VERDE', 4, '14.00', '56.00'),
(6, 7, 'CAMISETA MANGA CORTA', 'Tela: ESPIGA', 's,m,l', 'SHORT', 'Tela: NOVA', 's,m,l', 'RODILLERA', '', 20, '55.00', '1100.00'),
(7, 8, 'CAMISETA MANGA CORTA', 'Tela: ESPIGA', '-', 'SHORT', 'Tela: ESPIGA', '-', 'NINGUNO', '', 5, '35.00', '175.00'),
(8, 9, 'POLO PUBLICITARIO', 'Tela: ALGODON', 'S,M,L', 'SHORT', 'Tela: FULL LICRA', 'S,M,L', 'NINGUNO', '', 4, '14.00', '56.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `merchandising`
--

CREATE TABLE `merchandising` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `articulo` varchar(100) NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `es_regalo` tinyint(1) DEFAULT 0,
  `especificaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `merchandising`
--

INSERT INTO `merchandising` (`id`, `pedido_id`, `articulo`, `cantidad`, `precio_unitario`, `es_regalo`, `especificaciones`) VALUES
(2, 4, 'BANDEROLA', 1, '0.00', 1, '1.4 M X 8 M'),
(3, 5, 'BANDERA', 4, '2.00', 0, 'BANDERA PERUANA'),
(4, 6, 'BANDEROLA', 1, '8.00', 0, 'FONDO VERDE OSCURO'),
(5, 7, 'BANDEROLA', 1, '38.00', 0, ''),
(6, 9, 'BANDEROLA', 1, '0.00', 1, 'FONDO VERDE CLARO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modificaciones_pedido`
--

CREATE TABLE `modificaciones_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Usuario que realiza la modificación',
  `tipo_modificacion` enum('ADICION','DISMINUCION','CAMBIO') NOT NULL,
  `tabla_afectada` varchar(50) NOT NULL COMMENT 'Tabla donde se hizo el cambio (kits, integrantes, merchandising, etc)',
  `registro_id` int(11) DEFAULT NULL COMMENT 'ID del registro afectado',
  `campo_modificado` varchar(50) DEFAULT NULL COMMENT 'Campo específico modificado',
  `valor_anterior` text DEFAULT NULL COMMENT 'Valor antes del cambio',
  `valor_nuevo` text DEFAULT NULL COMMENT 'Valor después del cambio',
  `cantidad_anterior` int(11) DEFAULT NULL COMMENT 'Cantidad anterior (para cambios de cantidad)',
  `cantidad_nueva` int(11) DEFAULT NULL COMMENT 'Nueva cantidad',
  `precio_anterior` decimal(10,2) DEFAULT NULL COMMENT 'Precio anterior',
  `precio_nuevo` decimal(10,2) DEFAULT NULL COMMENT 'Nuevo precio',
  `subtotal_anterior` decimal(10,2) DEFAULT NULL COMMENT 'Subtotal anterior del pedido',
  `subtotal_nuevo` decimal(10,2) DEFAULT NULL COMMENT 'Nuevo subtotal del pedido',
  `motivo` text DEFAULT NULL COMMENT 'Motivo o razón de la modificación',
  `fecha_modificacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci COMMENT='Historial de modificaciones de pedidos';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL COMMENT 'Vendedor que registra el pedido',
  `tipo_contrato` enum('PEDIDO','SERVICIO','COSTURA','ESTAMPADO','PLANCHADO') DEFAULT 'PEDIDO',
  `lugar_entrega` varchar(100) NOT NULL,
  `direccion_envio` text DEFAULT NULL,
  `vendedor_asignado` varchar(50) DEFAULT NULL,
  `celular_cliente` varchar(20) DEFAULT NULL,
  `observaciones_generales` text DEFAULT NULL,
  `observaciones_diseno` text DEFAULT NULL,
  `estado_contrato` enum('pendiente','completo') DEFAULT 'pendiente',
  `estado_integrantes` enum('pendiente','completo') DEFAULT 'pendiente',
  `estado_diseno` enum('pendiente','aprobado','completo') DEFAULT 'pendiente',
  `estado_planchado` enum('pendiente','completo') DEFAULT 'pendiente',
  `estado_costura` enum('pendiente','completo') DEFAULT 'pendiente',
  `estado_general` enum('en_proceso','listo_entrega','entregado','cancelado') DEFAULT 'en_proceso',
  `fecha_pedido` timestamp NULL DEFAULT current_timestamp(),
  `fecha_entrega` date DEFAULT NULL,
  `hora_entrega` time DEFAULT NULL,
  `fecha_limite` date DEFAULT NULL,
  `fecha_completado` timestamp NULL DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `adelanto` decimal(10,2) DEFAULT 0.00,
  `saldo` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `pedidos`
--

INSERT INTO `pedidos` (`id`, `codigo`, `cliente_id`, `usuario_id`, `tipo_contrato`, `lugar_entrega`, `direccion_envio`, `vendedor_asignado`, `celular_cliente`, `observaciones_generales`, `observaciones_diseno`, `estado_contrato`, `estado_integrantes`, `estado_diseno`, `estado_planchado`, `estado_costura`, `estado_general`, `fecha_pedido`, `fecha_entrega`, `hora_entrega`, `fecha_limite`, `fecha_completado`, `subtotal`, `adelanto`, `saldo`) VALUES
(4, 'PED-2026-0001', 8, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'Jhon', '996969695', 'directora de perene', 'LOGO A LA IZQUIERDA', 'completo', 'completo', 'completo', 'completo', 'completo', 'entregado', '2026-03-21 23:04:30', '2026-03-23', '20:04:00', NULL, '2026-03-22 21:47:26', '72.00', '30.00', '42.00'),
(5, 'PED-2026-0002', 9, 1, 'PEDIDO', 'ENVÍO', 'agencia lobato', 'Jhon', '969696316', 'ejecutiva importante', 'LOGO A LA DERECHA BIEN BONITO', 'completo', 'completo', 'completo', 'pendiente', 'pendiente', 'en_proceso', '2026-03-22 16:51:59', '2026-03-25', '15:50:00', NULL, NULL, '92.00', '40.00', '52.00'),
(6, 'PED-2026-0003', 10, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'Jhon', '969393697', 'institucion evez ser', 'TEXTO MOTIVACIONAL', 'completo', 'completo', 'completo', 'completo', 'pendiente', 'en_proceso', '2026-03-22 20:17:18', '2026-03-24', '18:16:00', NULL, NULL, '64.00', '20.00', '44.00'),
(7, 'PED-2026-0004', 11, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '126165456', 'amigo de betsy', '', 'completo', 'completo', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', '2026-03-23 13:56:11', '2026-03-25', '14:26:00', NULL, NULL, '1138.00', '800.00', '338.00'),
(8, 'PED-2026-0005', 2, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '', '', '', 'completo', 'pendiente', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', '2026-03-23 22:27:55', '2026-03-24', '16:24:00', NULL, NULL, '175.00', '10.00', '165.00'),
(9, 'PED-2026-0006', 12, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '969669301', 'clienta ejecutiva', 'LOGO A LA DERECHA', 'completo', 'pendiente', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', '2026-03-23 23:24:44', '2026-03-26', '20:21:00', NULL, NULL, '72.00', '30.00', '42.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planchado`
--

CREATE TABLE `planchado` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `planchador_id` int(11) DEFAULT NULL,
  `planchador_nombre` varchar(100) DEFAULT NULL COMMENT 'Nombre si es eventual',
  `cant_polos` int(11) DEFAULT 0,
  `cant_shorts` int(11) DEFAULT 0,
  `cant_cuellos` int(11) DEFAULT 0,
  `precio_polo` decimal(10,2) DEFAULT 1.50,
  `precio_short` decimal(10,2) DEFAULT 1.00,
  `precio_cuello` decimal(10,2) DEFAULT 0.50,
  `total_pago` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  `fecha_planchado` date DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `planchado`
--

INSERT INTO `planchado` (`id`, `pedido_id`, `planchador_id`, `planchador_nombre`, `cant_polos`, `cant_shorts`, `cant_cuellos`, `precio_polo`, `precio_short`, `precio_cuello`, `total_pago`, `observaciones`, `fecha_planchado`, `fecha_registro`) VALUES
(1, 4, NULL, 'Miguel', 6, 6, 6, '2.00', '1.00', '0.50', '21.00', 'quiero planchado perfecto', '2026-03-22', '2026-03-22 20:37:59'),
(2, 6, NULL, 'Carlos', 4, 4, 4, '1.50', '1.00', '0.50', '14.00', 'planchado en seco', '2026-03-22', '2026-03-22 21:07:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planchadores`
--

CREATE TABLE `planchadores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `precio_polo` decimal(10,2) DEFAULT 1.50,
  `precio_short` decimal(10,2) DEFAULT 1.00,
  `precio_cuello` decimal(10,2) DEFAULT 0.50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `planchadores`
--

INSERT INTO `planchadores` (`id`, `nombre`, `activo`, `precio_polo`, `precio_short`, `precio_cuello`) VALUES
(1, 'Carlos', 1, '1.50', '1.00', '0.50'),
(2, 'Miguel', 1, '1.50', '1.00', '0.50'),
(3, 'Rosa', 1, '1.50', '1.00', '0.50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planchado_merchandising`
--

CREATE TABLE `planchado_merchandising` (
  `id` int(11) NOT NULL,
  `planchado_id` int(11) NOT NULL,
  `articulo` varchar(100) NOT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `planchado_merchandising`
--

INSERT INTO `planchado_merchandising` (`id`, `planchado_id`, `articulo`, `cantidad`, `precio_unitario`) VALUES
(1, 2, 'Banderola', 1, '2.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `referencias_pedido`
--

CREATE TABLE `referencias_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `imagen_path` varchar(255) NOT NULL,
  `fecha_subida` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rol` enum('vendedor','disenador','administrador') NOT NULL DEFAULT 'vendedor',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `ultimo_acceso` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password`, `nombre`, `celular`, `email`, `rol`, `activo`, `fecha_creacion`, `ultimo_acceso`) VALUES
(1, '71234567', '$2y$10$ZzbdBIDxaYdylOR8dBaIyeVeHZHSa6xPPpGO5KyYqVoiVY7Oo9HRi', 'yohana', '991122597', 'jhon@vizengo.com', 'vendedor', 1, '2026-03-21 17:05:47', '2026-03-26 01:24:05'),
(3, 'carolina', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carolina', '999999', 'carolina@vizengo.com', 'disenador', 1, '2026-03-21 17:05:47', '2026-03-22 21:42:51'),
(4, 'erick', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Erick', '912366', 'erick@vizengo.com', 'disenador', 1, '2026-03-21 17:05:47', NULL),
(5, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', '99999999', 'admin@vizengo.com', 'administrador', 1, '2026-03-21 17:05:47', '2026-03-22 22:04:34'),
(6, '74207930', '$2y$10$5BmJfp82nfZC.ruijt.ElevcaNC2a4rN.atgEQrBnEuPhIQXNMfy6', 'Andrea Marcos', '997379560', '', 'disenador', 1, '2026-03-22 21:58:35', '2026-03-23 22:53:54');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `adicionales_talla`
--
ALTER TABLE `adicionales_talla`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nombre` (`nombre`);

--
-- Indices de la tabla `costura`
--
ALTER TABLE `costura`
  ADD PRIMARY KEY (`id`),
  ADD KEY `costurero_id` (`costurero_id`),
  ADD KEY `idx_pedido` (`pedido_id`);

--
-- Indices de la tabla `costura_otros`
--
ALTER TABLE `costura_otros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `costura_id` (`costura_id`);

--
-- Indices de la tabla `costureros`
--
ALTER TABLE `costureros`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `disenos_finales`
--
ALTER TABLE `disenos_finales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `disenador_id` (`disenador_id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_pedido_aprobado` (`pedido_id`,`aprobado`);

--
-- Indices de la tabla `disenos_iniciales`
--
ALTER TABLE `disenos_iniciales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`);

--
-- Indices de la tabla `entregas`
--
ALTER TABLE `entregas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_pedido` (`pedido_id`);

--
-- Indices de la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_pedido_fecha` (`pedido_id`,`fecha_accion`);

--
-- Indices de la tabla `imagenes_integrantes`
--
ALTER TABLE `imagenes_integrantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pedido_id` (`pedido_id`);

--
-- Indices de la tabla `integrantes`
--
ALTER TABLE `integrantes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_talla` (`talla`),
  ADD KEY `idx_sexo` (`sexo`),
  ADD KEY `idx_pedido_sexo` (`pedido_id`,`sexo`);

--
-- Indices de la tabla `kits`
--
ALTER TABLE `kits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido` (`pedido_id`);

--
-- Indices de la tabla `merchandising`
--
ALTER TABLE `merchandising`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`);

--
-- Indices de la tabla `modificaciones_pedido`
--
ALTER TABLE `modificaciones_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_tipo` (`tipo_modificacion`),
  ADD KEY `idx_fecha` (`fecha_modificacion`),
  ADD KEY `idx_mod_pedido_fecha` (`pedido_id`,`fecha_modificacion`),
  ADD KEY `idx_mod_usuario_fecha` (`usuario_id`,`fecha_modificacion`);

--
-- Indices de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_estado` (`estado_general`),
  ADD KEY `idx_fecha_entrega` (`fecha_entrega`),
  ADD KEY `idx_estado_general_fecha` (`estado_general`,`fecha_entrega`);

--
-- Indices de la tabla `planchado`
--
ALTER TABLE `planchado`
  ADD PRIMARY KEY (`id`),
  ADD KEY `planchador_id` (`planchador_id`),
  ADD KEY `idx_pedido` (`pedido_id`);

--
-- Indices de la tabla `planchadores`
--
ALTER TABLE `planchadores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `planchado_merchandising`
--
ALTER TABLE `planchado_merchandising`
  ADD PRIMARY KEY (`id`),
  ADD KEY `planchado_id` (`planchado_id`);

--
-- Indices de la tabla `referencias_pedido`
--
ALTER TABLE `referencias_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pedido` (`pedido_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_rol` (`rol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adicionales_talla`
--
ALTER TABLE `adicionales_talla`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `costura`
--
ALTER TABLE `costura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `costura_otros`
--
ALTER TABLE `costura_otros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `costureros`
--
ALTER TABLE `costureros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `disenos_finales`
--
ALTER TABLE `disenos_finales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `disenos_iniciales`
--
ALTER TABLE `disenos_iniciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `imagenes_integrantes`
--
ALTER TABLE `imagenes_integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `integrantes`
--
ALTER TABLE `integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `kits`
--
ALTER TABLE `kits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `merchandising`
--
ALTER TABLE `merchandising`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `modificaciones_pedido`
--
ALTER TABLE `modificaciones_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `planchado`
--
ALTER TABLE `planchado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `planchadores`
--
ALTER TABLE `planchadores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `planchado_merchandising`
--
ALTER TABLE `planchado_merchandising`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `referencias_pedido`
--
ALTER TABLE `referencias_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `adicionales_talla`
--
ALTER TABLE `adicionales_talla`
  ADD CONSTRAINT `adicionales_talla_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `costura`
--
ALTER TABLE `costura`
  ADD CONSTRAINT `costura_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `costura_ibfk_2` FOREIGN KEY (`costurero_id`) REFERENCES `costureros` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `costura_otros`
--
ALTER TABLE `costura_otros`
  ADD CONSTRAINT `costura_otros_ibfk_1` FOREIGN KEY (`costura_id`) REFERENCES `costura` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `disenos_finales`
--
ALTER TABLE `disenos_finales`
  ADD CONSTRAINT `disenos_finales_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disenos_finales_ibfk_2` FOREIGN KEY (`disenador_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `disenos_iniciales`
--
ALTER TABLE `disenos_iniciales`
  ADD CONSTRAINT `disenos_iniciales_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `entregas`
--
ALTER TABLE `entregas`
  ADD CONSTRAINT `entregas_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entregas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  ADD CONSTRAINT `historial_pedidos_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `historial_pedidos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `imagenes_integrantes`
--
ALTER TABLE `imagenes_integrantes`
  ADD CONSTRAINT `imagenes_integrantes_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `integrantes`
--
ALTER TABLE `integrantes`
  ADD CONSTRAINT `integrantes_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `kits`
--
ALTER TABLE `kits`
  ADD CONSTRAINT `kits_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `merchandising`
--
ALTER TABLE `merchandising`
  ADD CONSTRAINT `merchandising_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `modificaciones_pedido`
--
ALTER TABLE `modificaciones_pedido`
  ADD CONSTRAINT `modificaciones_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `modificaciones_pedido_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `planchado`
--
ALTER TABLE `planchado`
  ADD CONSTRAINT `planchado_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `planchado_ibfk_2` FOREIGN KEY (`planchador_id`) REFERENCES `planchadores` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `planchado_merchandising`
--
ALTER TABLE `planchado_merchandising`
  ADD CONSTRAINT `planchado_merchandising_ibfk_1` FOREIGN KEY (`planchado_id`) REFERENCES `planchado` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `referencias_pedido`
--
ALTER TABLE `referencias_pedido`
  ADD CONSTRAINT `referencias_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
