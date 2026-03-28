-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql201.infinityfree.com
-- Tiempo de generación: 27-03-2026 a las 20:40:11
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
(3, 9, 'XL', 1, '16.00'),
(4, 5, 'XL', 1, '8.00'),
(5, 11, 'XL', 1, '35.00');

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
(2, 'Nitza', '256249866', '', 'Av. Principal 123', NULL, '2026-03-21 17:16:22'),
(3, 'Lizeth', '', '', 'Av. Principal 123', NULL, '2026-03-21 17:16:22'),
(4, 'Karina', NULL, NULL, NULL, NULL, '2026-03-21 19:00:04'),
(8, 'leslie perez', '996969695', NULL, NULL, NULL, '2026-03-21 23:04:30'),
(9, 'alejandra paredes', '969696316', NULL, NULL, NULL, '2026-03-22 16:51:59'),
(10, 'elena marinez', '969393697', NULL, NULL, NULL, '2026-03-22 20:17:18'),
(11, 'luis marmolejo', '126165456', NULL, NULL, NULL, '2026-03-23 13:56:11'),
(12, 'Liliana Smith', '969669301', NULL, NULL, NULL, '2026-03-23 23:24:44'),
(13, 'stefyi', '969699664', NULL, NULL, NULL, '2026-03-27 00:33:04');

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
(1, 4, NULL, 'Carmen', 6, 6, '2.00', '1.50', '24.00', 'tela adicionales', '2026-03-22', '2026-03-22 21:44:32'),
(2, 5, NULL, 'Maria', 6, 6, '2.00', '1.50', '21.00', 'dsyrery', '2026-03-25', '2026-03-26 02:59:18'),
(3, 6, NULL, 'Carmen', 4, 4, '2.00', '1.50', '14.00', 'wrwrerw', '2026-03-25', '2026-03-26 02:59:44'),
(4, 9, NULL, 'Carmen', 5, 5, '2.00', '1.50', '17.50', 'bueno', '2026-03-25', '2026-03-26 03:00:30'),
(5, 8, NULL, 'Maria', 5, 5, '2.00', '1.50', '17.50', 'wweno', '2026-03-25', '2026-03-26 03:01:44'),
(6, 7, NULL, 'Carmen', 8, 8, '2.00', '1.50', '30.40', 'termino wb', '2026-03-25', '2026-03-26 03:03:29');

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
(1, 1, 'cierre', 6, '0.50'),
(2, 6, 'botones extra', 4, '0.60');

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
(6, 6, 3, 'short', 'uploads/disenos/69c05003887b6_1774211075.jpeg', 'TEXTO MOTIVACIONAL', '2026-03-22 20:24:34', 1),
(7, 9, 6, 'camiseta', 'uploads/disenos/69c4a0adb16de_1774493869.jpeg', 'LOGO A LA DERECHA', '2026-03-26 02:57:49', 1),
(8, 9, 6, 'short', 'uploads/disenos/69c4a0b8bfc89_1774493880.jpeg', 'LOGO A LA DERECHA', '2026-03-26 02:58:00', 1),
(9, 8, 6, 'camiseta', 'uploads/disenos/69c4a16ac133b_1774494058.jpeg', '', '2026-03-26 03:00:58', 1),
(10, 8, 6, 'short', 'uploads/disenos/69c4a172cac56_1774494066.jpeg', '', '2026-03-26 03:01:06', 1),
(11, 7, 6, 'camiseta', 'uploads/disenos/69c4a1abed2fb_1774494123.jpeg', '', '2026-03-26 03:02:04', 1),
(12, 7, 6, 'short', 'uploads/disenos/69c4a1b9cd0f0_1774494137.jpeg', '', '2026-03-26 03:02:17', 1);

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
(7, 9, 'uploads/referencias/69c1cbbbd94f8_1774308283.jpg', '', '2026-03-23 23:24:44'),
(8, 10, 'uploads/referencias/69c5d0404544d_1774571584.jpg', '', '2026-03-27 00:33:04'),
(9, 11, 'uploads/referencias/69c5f45db9af0_1774580829.jpeg', '', '2026-03-27 03:07:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `enlaces_registro`
--

CREATE TABLE `enlaces_registro` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `url_enlace` varchar(255) NOT NULL,
  `estado` enum('pendiente','usado','expirado','cancelado') DEFAULT 'pendiente',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_expiracion` datetime DEFAULT NULL,
  `fecha_uso` datetime DEFAULT NULL,
  `ip_cliente` varchar(45) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

--
-- Volcado de datos para la tabla `enlaces_registro`
--

INSERT INTO `enlaces_registro` (`id`, `pedido_id`, `token`, `url_enlace`, `estado`, `fecha_creacion`, `fecha_expiracion`, `fecha_uso`, `ip_cliente`, `created_by`) VALUES
(1, 10, '6bbbd64f8df93e4fd53837614aa6474da0c1c73e29fe8647d75f3ed572d7b4ad', 'https://pruebasvizengo.gt.tc/registro-cliente.php?token=6bbbd64f8df93e4fd53837614aa6474da0c1c73e29fe8647d75f3ed572d7b4ad', 'usado', '2026-03-28 00:36:04', '2026-03-30 19:36:04', '2026-03-27 19:36:39', '190.233.91.212', 1);

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
(1, 4, 1, 'TIENDA VIZENGO', 0, '', '0.00', '42.00', 'entregado en la tienda', '2026-03-22 21:47:26'),
(2, 6, 1, 'TIENDA VIZENGO', 0, '', '0.00', '44.00', 'vino a tienda', '2026-03-26 03:04:58'),
(3, 8, 1, 'TIENDA VIZENGO', 0, '', '0.00', '165.00', 'entregado', '2026-03-26 03:05:22'),
(4, 5, 1, 'ENVÍO', 1, 'agencia lobato', '14.00', '74.00', 'pago yape', '2026-03-26 03:05:59'),
(5, 7, 1, 'TIENDA VIZENGO', 0, '', '0.00', '338.00', 'listo', '2026-03-26 03:06:26'),
(6, 9, 1, 'TIENDA VIZENGO', 0, '', '0.00', '42.00', 'cobrado y entregagdo', '2026-03-26 03:07:25');

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
(7, 9, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0006 creado', '2026-03-23 23:24:44'),
(8, 5, 1, 'PEDIDO_MODIFICADO', 'Adicional agregar: pedido del cliente adicional', '2026-03-26 02:34:20'),
(9, 6, 1, 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente elena marinez', '2026-03-26 03:04:58'),
(10, 8, 1, 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente Nitza', '2026-03-26 03:05:22'),
(11, 5, 1, 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente alejandra paredes', '2026-03-26 03:05:59'),
(12, 7, 1, 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente luis marmolejo', '2026-03-26 03:06:26'),
(13, 9, 1, 'PEDIDO_ENTREGADO', 'Pedido entregado al cliente Liliana Smith', '2026-03-26 03:07:25'),
(14, 10, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0007 creado', '2026-03-27 00:33:04'),
(15, 11, 1, 'PEDIDO_CREADO', 'Pedido PED-2026-0008 creado', '2026-03-27 03:07:09'),
(16, 11, 1, 'PEDIDO_MODIFICADO', 'Kit modificar: ', '2026-03-27 03:18:54'),
(17, 11, 1, 'PEDIDO_MODIFICADO', 'Integrante agregar: ', '2026-03-27 03:19:18'),
(18, 11, 1, 'PEDIDO_MODIFICADO', 'Integrante agregar: ', '2026-03-27 03:19:38'),
(19, 11, 1, 'PEDIDO_MODIFICADO', 'Integrante agregar: ', '2026-03-27 03:19:55'),
(20, 11, 1, 'PEDIDO_MODIFICADO', 'Datos generales actualizados: ', '2026-03-27 03:20:23'),
(21, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=317eae1e8ac5729bdce19b4236b9b4121bd73feb6f63974467c3483ec120a794', '2026-03-27 23:40:51'),
(22, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=b4f5018182ad3b6f6341f018954f7e3e861eea3523eab4a32760059ad6e7d35c', '2026-03-27 23:40:51'),
(23, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=b01d7688953f3d9c1b9a31b59dde79ca8dd8dc780b76493cd973d732d2c64e9a', '2026-03-27 23:42:17'),
(24, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=a4e10cf3847884b11b7917eefaf0ecf442c7fc52e07481ac436a195528612535', '2026-03-27 23:42:17'),
(25, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=6913eedfbcb4615508f820629eea41be590bb9600526a850556a0a2fd50a5bb4', '2026-03-27 23:58:24'),
(26, 10, 1, 'INTEGRANTES_REGISTRADOS_CLIENTE', 'Integrantes registrados por cliente vía enlace', '2026-03-28 00:01:21'),
(27, 10, 1, 'INTEGRANTES_REGISTRADOS_CLIENTE', 'Integrantes registrados por cliente vía enlace', '2026-03-28 00:05:59'),
(28, 10, 1, 'INTEGRANTES_REGISTRADOS_CLIENTE', 'Integrantes registrados por cliente vía enlace', '2026-03-28 00:09:48'),
(29, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=0e06eaebdfa4d51b903324b4ac40cadf2362596d025ee566039e47d90ac701d4', '2026-03-28 00:31:11'),
(30, 10, 1, 'INTEGRANTES_REGISTRADOS_CLIENTE', 'Integrantes registrados por cliente vía enlace', '2026-03-28 00:33:09'),
(31, 10, 1, 'INTEGRANTES_REGISTRADOS_CLIENTE', 'Integrantes registrados por cliente vía enlace', '2026-03-28 00:34:11'),
(32, 10, 1, 'ENLACE_GENERADO', 'Enlace de registro generado: https://pruebasvizengo.gt.tc/registro-cliente.php?token=6bbbd64f8df93e4fd53837614aa6474da0c1c73e29fe8647d75f3ed572d7b4ad', '2026-03-28 00:36:04'),
(33, 10, 1, 'INTEGRANTES_REGISTRADOS_CLIENTE', 'Integrantes registrados por cliente vía enlace', '2026-03-28 00:36:39');

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

--
-- Volcado de datos para la tabla `imagenes_integrantes`
--

INSERT INTO `imagenes_integrantes` (`id`, `pedido_id`, `imagen_path`, `fecha_subida`) VALUES
(1, 11, 'uploads/integrantes/69c5f4c418744.jpeg', '2026-03-27 03:08:52');

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
(24, 7, 'alberto k.', 'S', '12', '', 1, 'Varon'),
(25, 8, 'lois', '16', '78', 'arquero', 1, 'Dama'),
(26, 8, 'lit', 'S', '8', '', 1, 'Varon'),
(27, 8, 'leny', '14', '7', '', 1, 'Dama'),
(28, 8, 'lina', '14', '5', '', 1, 'Dama'),
(29, 8, 'loin', 'M', '87', '', 1, 'Varon'),
(30, 9, 're', 'S', '02', 'arquerp', 1, 'Dama'),
(31, 9, 'tet', 'M', '4', '', 1, 'Dama'),
(32, 9, 'lindw', 'M', '8', '', 1, 'Varon'),
(33, 9, 'uoini', 'S', '9', '', 1, 'Dama'),
(34, 9, 'sungo', 'L', '11', '', 1, 'Varon'),
(35, 11, 'HGFD', '2', '33', '', 0, 'Dama'),
(36, 11, 'MJNHJ', '14', 'JHGF', '', 1, 'Varon'),
(37, 11, 'LUIS', 'M', '8', '', 1, 'Varon'),
(38, 11, 'DFG', 'M', '5', '', 1, 'Varon'),
(39, 11, 'DFG', 'Z', '8', '', 1, 'Varon'),
(45, 10, 'jhoncito', 'L', '12', 'arquero', 1, 'Varon');

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
(8, 9, 'POLO PUBLICITARIO', 'Tela: ALGODON', 'S,M,L', 'SHORT', 'Tela: FULL LICRA', 'S,M,L', 'NINGUNO', '', 4, '14.00', '56.00'),
(9, 10, 'CAMISETA MANGA CORTA', 'Tela: ESPIGA', 'S,M,L', 'SHORT', 'Tela: ESPIGA', 'S,M,L', 'NINGUNO', '', 1, '35.00', '35.00'),
(10, 11, 'CAMISETA MANGA CORTA', 'Tela: ESPIGA', '-', 'SHORT', 'Tela: ESPIGA', '-', 'NINGUNO', '', 5, '35.00', '175.00');

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
(6, 9, 'BANDEROLA', 1, '0.00', 1, 'FONDO VERDE CLARO'),
(7, 11, 'BANDEROLA', 1, '15.00', 0, '');

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

--
-- Volcado de datos para la tabla `modificaciones_pedido`
--

INSERT INTO `modificaciones_pedido` (`id`, `pedido_id`, `usuario_id`, `tipo_modificacion`, `tabla_afectada`, `registro_id`, `campo_modificado`, `valor_anterior`, `valor_nuevo`, `cantidad_anterior`, `cantidad_nueva`, `precio_anterior`, `precio_nuevo`, `subtotal_anterior`, `subtotal_nuevo`, `motivo`, `fecha_modificacion`) VALUES
(1, 5, 1, 'ADICION', 'adicionales_talla', 4, 'nuevo_adicional', '', 'XL', 0, 1, '0.00', '8.00', '100.00', '100.00', 'pedido del cliente adicional', '2026-03-26 02:34:20'),
(2, 11, 1, 'ADICION', 'kits', 10, 'cantidad_precio', 'Cantidad: 1, Precio: 35', 'Cantidad: 5, Precio: 35', 1, 5, '35.00', '35.00', '225.00', '225.00', '', '2026-03-27 03:18:54'),
(3, 11, 1, 'ADICION', 'integrantes', 37, 'nuevo_integrante', '', 'LUIS', NULL, NULL, NULL, NULL, '225.00', '225.00', '', '2026-03-27 03:19:18'),
(4, 11, 1, 'ADICION', 'integrantes', 38, 'nuevo_integrante', '', 'DFG', NULL, NULL, NULL, NULL, '225.00', '225.00', '', '2026-03-27 03:19:38'),
(5, 11, 1, 'ADICION', 'integrantes', 39, 'nuevo_integrante', '', 'DFG', NULL, NULL, NULL, NULL, '225.00', '225.00', '', '2026-03-27 03:19:55'),
(6, 11, 1, 'CAMBIO', 'pedidos', 11, 'adelanto', 'S/ 50', 'S/ 55', NULL, NULL, '50.00', '55.00', '225.00', '225.00', '', '2026-03-27 03:20:23');

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
(5, 'PED-2026-0002', 9, 1, 'PEDIDO', 'ENVÍO', 'agencia lobato', 'Jhon', '969696316', 'ejecutiva importante', 'LOGO A LA DERECHA BIEN BONITO', 'completo', 'completo', 'completo', 'completo', 'completo', 'entregado', '2026-03-22 16:51:59', '2026-03-25', '15:50:00', NULL, '2026-03-26 03:05:59', '100.00', '40.00', '60.00'),
(6, 'PED-2026-0003', 10, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'Jhon', '969393697', 'institucion evez ser', 'TEXTO MOTIVACIONAL', 'completo', 'completo', 'completo', 'completo', 'completo', 'entregado', '2026-03-22 20:17:18', '2026-03-24', '18:16:00', NULL, '2026-03-26 03:04:58', '64.00', '20.00', '44.00'),
(7, 'PED-2026-0004', 11, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '126165456', 'amigo de betsy', '', 'completo', 'completo', 'completo', 'completo', 'completo', 'entregado', '2026-03-23 13:56:11', '2026-03-25', '14:26:00', NULL, '2026-03-26 03:06:26', '1138.00', '800.00', '338.00'),
(8, 'PED-2026-0005', 2, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '', '', '', 'completo', 'completo', 'completo', 'completo', 'completo', 'entregado', '2026-03-23 22:27:55', '2026-03-24', '16:24:00', NULL, '2026-03-26 03:05:22', '175.00', '10.00', '165.00'),
(9, 'PED-2026-0006', 12, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '969669301', 'clienta ejecutiva', 'LOGO A LA DERECHA', 'completo', 'completo', 'completo', 'completo', 'completo', 'entregado', '2026-03-23 23:24:44', '2026-03-26', '20:21:00', NULL, '2026-03-26 03:07:25', '72.00', '30.00', '42.00'),
(10, 'PED-2026-0007', 13, 1, 'PEDIDO', 'TIENDA VIZENGO', '', 'yohana', '969699664', 'perfecto pedido', 'COLOR AZUL', 'completo', 'completo', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', '2026-03-27 00:33:04', '2026-03-28', '23:33:00', NULL, NULL, '35.00', '10.00', '25.00'),
(11, 'PED-2026-0008', 2, 1, 'COSTURA', 'TIENDA 3', '', 'yohana', '256249866', 'YFHXTFY', 'GFYFXLO6ESK', 'completo', 'completo', 'pendiente', 'pendiente', 'pendiente', 'en_proceso', '2026-03-27 03:07:09', '2026-03-27', '13:09:00', NULL, NULL, '225.00', '55.00', '170.00');

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
(2, 6, NULL, 'Carlos', 4, 4, 4, '1.50', '1.00', '0.50', '14.00', 'planchado en seco', '2026-03-22', '2026-03-22 21:07:44'),
(3, 5, NULL, 'Rosa', 6, 6, 6, '1.50', '1.00', '0.50', '18.00', 'dfdf', '2026-03-25', '2026-03-26 02:59:02'),
(4, 9, NULL, 'Rosa', 5, 5, 5, '1.50', '1.00', '0.50', '15.00', 'seco', '2026-03-25', '2026-03-26 03:00:13'),
(5, 8, NULL, 'Rosa', 5, 5, 5, '1.50', '1.00', '0.50', '15.00', 'niben', '2026-03-25', '2026-03-26 03:01:27'),
(6, 7, NULL, 'Carlos', 8, 8, 6, '1.50', '1.00', '0.50', '23.00', 'humedo', '2026-03-25', '2026-03-26 03:02:41');

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
(1, '71234567', '$2y$10$ZzbdBIDxaYdylOR8dBaIyeVeHZHSa6xPPpGO5KyYqVoiVY7Oo9HRi', 'yohana', '991122597', 'jhon@vizengo.com', 'vendedor', 1, '2026-03-21 17:05:47', '2026-03-28 00:35:55'),
(3, 'carolina', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Carolina', '999999', 'carolina@vizengo.com', 'disenador', 1, '2026-03-21 17:05:47', '2026-03-22 21:42:51'),
(4, 'erick', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Erick', '912366', 'erick@vizengo.com', 'disenador', 1, '2026-03-21 17:05:47', NULL),
(5, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', '99999999', 'admin@vizengo.com', 'administrador', 1, '2026-03-21 17:05:47', '2026-03-22 22:04:34'),
(6, '74207930', '$2y$10$5BmJfp82nfZC.ruijt.ElevcaNC2a4rN.atgEQrBnEuPhIQXNMfy6', 'Andrea Marcos', '997379560', '', 'disenador', 1, '2026-03-22 21:58:35', '2026-03-27 02:06:39');

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
-- Indices de la tabla `enlaces_registro`
--
ALTER TABLE `enlaces_registro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_estado` (`estado`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `costura`
--
ALTER TABLE `costura`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `costura_otros`
--
ALTER TABLE `costura_otros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `costureros`
--
ALTER TABLE `costureros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `disenos_finales`
--
ALTER TABLE `disenos_finales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `disenos_iniciales`
--
ALTER TABLE `disenos_iniciales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `enlaces_registro`
--
ALTER TABLE `enlaces_registro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `entregas`
--
ALTER TABLE `entregas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `historial_pedidos`
--
ALTER TABLE `historial_pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de la tabla `imagenes_integrantes`
--
ALTER TABLE `imagenes_integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `integrantes`
--
ALTER TABLE `integrantes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `kits`
--
ALTER TABLE `kits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `merchandising`
--
ALTER TABLE `merchandising`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `modificaciones_pedido`
--
ALTER TABLE `modificaciones_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `planchado`
--
ALTER TABLE `planchado`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
