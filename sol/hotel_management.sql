-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 14-07-2025 a las 06:17:30
-- Versión del servidor: 8.0.17
-- Versión de PHP: 7.3.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hotel_management`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `bloquear_usuario` (IN `user_id` INT, IN `minutos` INT)  BEGIN
    UPDATE usuarios 
    SET intentos_fallidos = intentos_fallidos + 1,
        bloqueado_hasta = DATE_ADD(NOW(), INTERVAL minutos MINUTE)
    WHERE id = user_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `desbloquear_usuario` (IN `user_id` INT)  BEGIN
    UPDATE usuarios 
    SET intentos_fallidos = 0,
        bloqueado_hasta = NULL
    WHERE id = user_id;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividad_usuarios`
--

CREATE TABLE `actividad_usuarios` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `ip` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `actividad_usuarios`
--

INSERT INTO `actividad_usuarios` (`id`, `usuario_id`, `accion`, `descripcion`, `ip`, `user_agent`, `fecha`) VALUES
(1, 1, 'login', 'Inicio de sesión exitoso', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '2025-07-13 05:45:55'),
(2, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 17:41:47'),
(3, 1, 'login', 'Inicio de sesión exitoso', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '2025-07-13 19:57:32'),
(4, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 20:47:26'),
(5, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-13 23:50:44'),
(6, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-14 04:14:10'),
(7, 1, 'login', 'Inicio de sesión exitoso', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '2025-07-14 04:18:52');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agrupaciones`
--

CREATE TABLE `agrupaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `agrupaciones`
--

INSERT INTO `agrupaciones` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Junior Suite', 'La primer habitación con cama King Size, televisión, ventilador, aire acondicionado y baño completo La segunda habitación con 2 camas matrimoniales , ventilador, televisión, aire acondicionado, y baño completo, además sala, comedor y cocina'),
(2, '5 pax', 'ESTANDAR + Bw Chico'),
(3, '5 pax 2', 'ESTANDAR + ESTANDAR'),
(4, 'Habitacion Sencilla 112', '112'),
(5, 'Habitacion Sencilla 113', '113'),
(6, 'Habitacion Sencilla 114', '114'),
(7, 'Habitacion Sencilla con Cama KS', '208'),
(8, 'Bungalow Chico 107', '107'),
(9, 'Bungalow Chico 109', '109'),
(10, 'Bungalow Chico 110', '110'),
(11, 'Bungalow Chico 111', '111'),
(12, 'Bungalow Mediano 108', '108'),
(13, 'Habitacion Standard 102', '102'),
(14, 'Habitacion Standard 103', '103'),
(15, 'Habitacion Standard 105', '105'),
(16, 'Habitacion Standard 106', '106'),
(17, 'Habitacion Standard 202', '202'),
(18, 'Habitacion Standard 203', '203'),
(19, 'Habitacion Standard 204', '204'),
(20, 'Habitacion Standard 205', '205'),
(21, 'Habitacion Standard 206', '206'),
(22, 'Habitacion Standard 207', '207'),
(23, 'Habitacion Standard 302', '302'),
(24, 'Habitacion Standard 303', '303'),
(25, 'Habitacion Standard 304', '304'),
(27, 'Habitacion Standard 305', '305'),
(28, 'Habitacion Standard 306', '306'),
(29, 'Habitacion Standard 307', '307'),
(30, 'Habitacion Standard 402', '402'),
(31, 'Habitacion Standard 403', '403'),
(32, 'Habitacion Standard 404', '404'),
(33, 'Habitacion Standard 405', '405'),
(34, 'Habitacion Standard 406', '406'),
(35, 'Habitacion Standard 407', '407'),
(36, 'Suite 300', '300'),
(37, 'Suite 209', '209'),
(38, 'Master Suite 101', '101'),
(39, 'Master Suite 201', '201'),
(40, 'Master Suite 301', '301'),
(41, 'Master Suite 401', '401');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agrupacion_habitaciones`
--

CREATE TABLE `agrupacion_habitaciones` (
  `id` int(11) NOT NULL,
  `id_agrupacion` int(11) DEFAULT NULL,
  `id_habitacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `agrupacion_habitaciones`
--

INSERT INTO `agrupacion_habitaciones` (`id`, `id_agrupacion`, `id_habitacion`) VALUES
(9, 1, 4),
(10, 1, 33),
(11, 4, 1),
(20, 7, 9),
(31, 5, 2),
(32, 6, 3),
(33, 8, 5),
(34, 9, 6),
(35, 10, 7),
(36, 11, 8),
(37, 13, 10),
(38, 14, 11),
(40, 15, 13),
(41, 16, 14),
(42, 17, 15),
(43, 18, 16),
(44, 19, 17),
(45, 20, 18),
(46, 21, 38),
(47, 23, 20),
(48, 25, 22),
(49, 22, 19),
(50, 24, 21),
(51, 27, 23),
(52, 28, 24),
(53, 29, 25),
(54, 30, 26),
(55, 31, 27),
(56, 32, 28),
(57, 33, 29),
(58, 34, 30),
(59, 35, 31),
(60, 36, 32),
(61, 38, 34),
(62, 39, 35),
(63, 40, 36),
(64, 41, 37);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bloqueos`
--

CREATE TABLE `bloqueos` (
  `id` int(11) NOT NULL,
  `id_habitacion` int(11) NOT NULL,
  `tipo` enum('Libre','Reservado','Apartado','Mantenimiento','Limpieza') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkin_articulos`
--

CREATE TABLE `checkin_articulos` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) DEFAULT NULL,
  `articulo` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkin_personas`
--

CREATE TABLE `checkin_personas` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL,
  `clave` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `valor` text COLLATE utf8mb4_general_ci,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `clave`, `valor`, `descripcion`, `creado_en`) VALUES
(1, 'hotel_nombre', 'Hotel Puesta del Sol', 'Nombre del hotel', '2025-07-05 05:54:09'),
(2, 'hotel_direccion', 'Av. Principal 123, Colima, México', 'Dirección del hotel', '2025-07-05 05:54:09'),
(3, 'hotel_telefono', '+52 312 123 4567', 'Teléfono del hotel', '2025-07-05 05:54:09'),
(4, 'hotel_email', 'info@hotelpuestadelsol.com', 'Email del hotel', '2025-07-05 05:54:09'),
(5, 'moneda', 'MXN', 'Moneda utilizada', '2025-07-05 05:54:09'),
(6, 'simbolo_moneda', '$', 'Símbolo de la moneda', '2025-07-05 05:54:09'),
(7, 'zona_horaria', 'America/Mexico_City', 'Zona horaria', '2025-07-05 05:54:09'),
(8, 'check_in', '15:00', 'Hora de check-in', '2025-07-05 05:54:09'),
(9, 'check_out', '12:00', 'Hora de check-out', '2025-07-05 05:54:09'),
(10, 'iva', '16', 'Porcentaje de IVA', '2025-07-05 05:54:09'),
(11, 'logo_path', 'assets/img/logo.png', 'Ruta del logo del hotel', '2025-07-05 05:54:09'),
(34, 'intentos_login_max', '3', 'Máximo número de intentos de login fallidos', '2025-07-05 19:25:27'),
(35, 'tiempo_bloqueo', '15', 'Tiempo de bloqueo en minutos después de intentos fallidos', '2025-07-05 19:25:27'),
(36, 'tamaño_foto_max', '5242880', 'Tamaño máximo de foto de perfil en bytes (5MB)', '2025-07-05 19:25:27'),
(37, 'formatos_foto_permitidos', 'jpg,jpeg,png,gif', 'Formatos de imagen permitidos para fotos de perfil', '2025-07-05 19:25:27'),
(38, 'password_min_length', '4', 'Longitud mínima de contraseña', '2025-07-05 19:25:27'),
(39, 'sesion_timeout', '3600', 'Tiempo de expiración de sesión en segundos', '2025-07-05 19:25:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `descuentos`
--

CREATE TABLE `descuentos` (
  `id` int(11) NOT NULL,
  `id_agrupacion` int(11) DEFAULT NULL,
  `personas_min` int(11) DEFAULT NULL,
  `personas_max` int(11) DEFAULT NULL,
  `noches_min` int(11) DEFAULT NULL,
  `noches_max` int(11) DEFAULT NULL,
  `monto_descuento` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitaciones`
--

CREATE TABLE `habitaciones` (
  `id` int(11) NOT NULL,
  `numero_habitacion` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_tipo_habitacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `habitaciones`
--

INSERT INTO `habitaciones` (`id`, `numero_habitacion`, `nombre`, `id_tipo_habitacion`) VALUES
(1, '112', 'Sencilla', 1),
(2, '113', 'Sencilla', 1),
(3, '114', 'Sencilla', 1),
(4, '208', 'C/King Size', 2),
(5, '107', 'B-Chico', 3),
(6, '109', 'B-Chico', 3),
(7, '110', 'B-Chico', 3),
(8, '111', 'B-Chico', 3),
(9, '108', 'B-Mediano', 4),
(10, '102', 'Standard ', 5),
(11, '103', 'Standard ', 5),
(12, '104', 'Standard ', 5),
(13, '105', 'Standard ', 5),
(14, '106', 'Standard ', 5),
(15, '202', 'Standard ', 5),
(16, '203', 'Standard ', 5),
(17, '204', 'Standard ', 5),
(18, '205', 'Standard', 5),
(19, '207', 'Standard ', 5),
(20, '302', 'Standard ', 5),
(21, '303', 'Standard ', 5),
(22, '304', 'Standard ', 5),
(23, '305', 'Standard ', 5),
(24, '306', 'Standard ', 5),
(25, '307', 'Standard ', 5),
(26, '402', 'Standard ', 5),
(27, '403', 'Standard ', 5),
(28, '404', 'Standard ', 5),
(29, '405', 'Standard ', 5),
(30, '406', 'Standard ', 5),
(31, '407', 'Standard ', 5),
(32, '300', 'Suite', 6),
(33, '209', 'Suite', 6),
(34, '101', 'Master ', 7),
(35, '201', 'Master', 7),
(36, '301', 'Master', 7),
(37, '401', 'Master', 7),
(38, '206', 'Standard', 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `huespedes`
--

CREATE TABLE `huespedes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `id_nacionalidad` int(11) DEFAULT NULL,
  `auto_marca` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `auto_color` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `huespedes`
--

INSERT INTO `huespedes` (`id`, `nombre`, `telefono`, `correo`, `id_nacionalidad`, `auto_marca`, `auto_color`, `fecha_registro`) VALUES
(1, 'Heriberto Ochoa Estrada', '3141030014', '', 1, '', '', '2025-07-10 21:44:06');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_actividad`
--

CREATE TABLE `logs_actividad` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `detalles` text COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `nacionalidades`
--

CREATE TABLE `nacionalidades` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `nacionalidades`
--

INSERT INTO `nacionalidades` (`id`, `nombre`) VALUES
(1, 'Mexicano'),
(2, 'Español'),
(3, 'Americano'),
(4, 'Canadience');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) DEFAULT NULL,
  `tipo` enum('Anticipo','Pago hotel','Pago extra') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `metodo_pago` enum('Efectivo','Tarjeta','Transferencia','Cheque','Otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `forma_pago` enum('Contado','Crédito','Anticipo') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `clave_pago` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `autorizacion` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `registrado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas`
--

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `id_tarifa` int(11) DEFAULT NULL,
  `id_huesped` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `personas_max` int(3) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `color` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bloqueo` enum('Libre','Reservado','Apartado','Mantenimiento','Limpieza') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarifas`
--

CREATE TABLE `tarifas` (
  `id` int(11) NOT NULL,
  `id_agrupacion` int(11) DEFAULT NULL,
  `id_temporada` int(11) DEFAULT NULL,
  `personas_min` int(11) DEFAULT NULL,
  `personas_max` int(11) DEFAULT NULL,
  `noches_min` int(11) DEFAULT '1',
  `noches_max` int(11) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tarifas`
--

INSERT INTO `tarifas` (`id`, `id_agrupacion`, `id_temporada`, `personas_min`, `personas_max`, `noches_min`, `noches_max`, `precio`) VALUES
(1, 8, 1, 1, 5, 1, NULL, '2100.00'),
(2, 9, 1, 1, 5, 1, NULL, '2100.00'),
(3, 10, 1, 1, 5, 1, NULL, '2100.00'),
(4, 11, 1, 1, 5, 1, NULL, '2100.00'),
(5, 4, 1, 1, 2, 1, NULL, '1500.00'),
(6, 5, 1, 1, 2, 1, NULL, '1500.00'),
(7, 6, 1, 1, 2, 1, NULL, '1500.00'),
(8, 4, 3, 1, 2, 1, NULL, '1200.00'),
(9, 5, 3, 1, 2, 1, NULL, '1200.00'),
(10, 6, 3, 1, 2, 1, NULL, '1200.00'),
(11, 4, 10, 1, 2, 1, NULL, '900.00'),
(12, 5, 10, 1, 2, 1, NULL, '900.00'),
(13, 6, 10, 1, 2, 1, NULL, '900.00'),
(14, 7, 1, 1, 2, 1, NULL, '1800.00'),
(15, 7, 3, 1, 2, 1, NULL, '1400.00'),
(16, 7, 10, 1, 2, 1, NULL, '1100.00'),
(17, 12, 1, 1, 4, 1, NULL, '2500.00'),
(18, 12, 3, 1, 4, 1, NULL, '2000.00'),
(19, 12, 10, 1, 4, 1, NULL, '1500.00'),
(20, 13, 1, 1, 4, 1, NULL, '2200.00'),
(21, 14, 1, 1, 4, 1, NULL, '2200.00'),
(22, 15, 1, 1, 4, 1, NULL, '2200.00'),
(23, 16, 1, 1, 4, 1, NULL, '2200.00'),
(24, 17, 1, 1, 4, 1, NULL, '2200.00'),
(25, 18, 1, 1, 4, 1, NULL, '2200.00'),
(26, 19, 1, 1, 4, 1, NULL, '2200.00'),
(27, 20, 1, 1, 4, 1, NULL, '2200.00'),
(28, 21, 1, 1, 4, 1, NULL, '2200.00'),
(29, 22, 1, 1, 4, 1, NULL, '2200.00'),
(30, 13, 3, 1, 4, 1, NULL, '1800.00'),
(31, 14, 3, 1, 4, 1, NULL, '1800.00'),
(32, 15, 3, 1, 4, 1, NULL, '1800.00'),
(33, 16, 3, 1, 4, 1, NULL, '1800.00'),
(34, 17, 3, 1, 4, 1, NULL, '1800.00'),
(35, 18, 3, 1, 4, 1, NULL, '1800.00'),
(36, 19, 3, 1, 4, 1, NULL, '1800.00'),
(37, 20, 3, 1, 4, 1, NULL, '1800.00'),
(38, 21, 3, 1, 4, 1, NULL, '1800.00'),
(39, 22, 3, 1, 4, 1, NULL, '1800.00'),
(40, 13, 10, 1, 4, 1, NULL, '1400.00'),
(41, 14, 10, 1, 4, 1, NULL, '1400.00'),
(42, 15, 10, 1, 4, 1, NULL, '1400.00'),
(43, 16, 10, 1, 4, 1, NULL, '1400.00'),
(44, 17, 10, 1, 4, 1, NULL, '1400.00'),
(45, 18, 10, 1, 4, 1, NULL, '1400.00'),
(46, 19, 10, 1, 4, 1, NULL, '1400.00'),
(47, 20, 10, 1, 4, 1, NULL, '1400.00'),
(48, 21, 10, 1, 4, 1, NULL, '1400.00'),
(49, 22, 10, 1, 4, 1, NULL, '1400.00'),
(50, 23, 1, 1, 4, 1, NULL, '2200.00'),
(51, 24, 1, 1, 4, 1, NULL, '2200.00'),
(52, 25, 1, 1, 4, 1, NULL, '2200.00'),
(53, 27, 1, 1, 4, 1, NULL, '2200.00'),
(54, 28, 1, 1, 4, 1, NULL, '2200.00'),
(55, 29, 1, 1, 4, 1, NULL, '2200.00'),
(56, 23, 3, 1, 4, 1, NULL, '1800.00'),
(57, 24, 3, 1, 4, 1, NULL, '1800.00'),
(58, 25, 3, 1, 4, 1, NULL, '1800.00'),
(59, 27, 3, 1, 4, 1, NULL, '1800.00'),
(60, 28, 3, 1, 4, 1, NULL, '1800.00'),
(61, 29, 3, 1, 4, 1, NULL, '1800.00'),
(62, 23, 10, 1, 4, 1, NULL, '1400.00'),
(63, 24, 10, 1, 4, 1, NULL, '1400.00'),
(64, 25, 10, 1, 4, 1, NULL, '1400.00'),
(65, 27, 10, 1, 4, 1, NULL, '1400.00'),
(66, 28, 10, 1, 4, 1, NULL, '1400.00'),
(67, 29, 10, 1, 4, 1, NULL, '1400.00'),
(68, 30, 1, 1, 4, 1, NULL, '2200.00'),
(69, 31, 1, 1, 4, 1, NULL, '2200.00'),
(70, 32, 1, 1, 4, 1, NULL, '2200.00'),
(71, 33, 1, 1, 4, 1, NULL, '2200.00'),
(72, 34, 1, 1, 4, 1, NULL, '2200.00'),
(73, 35, 1, 1, 4, 1, NULL, '2200.00'),
(74, 30, 3, 1, 4, 1, NULL, '1800.00'),
(75, 31, 3, 1, 4, 1, NULL, '1800.00'),
(76, 32, 3, 1, 4, 1, NULL, '1800.00'),
(77, 33, 3, 1, 4, 1, NULL, '1800.00'),
(78, 34, 3, 1, 4, 1, NULL, '1800.00'),
(79, 35, 3, 1, 4, 1, NULL, '1800.00'),
(80, 30, 10, 1, 4, 1, NULL, '1400.00'),
(81, 31, 10, 1, 4, 1, NULL, '1400.00'),
(82, 32, 10, 1, 4, 1, NULL, '1400.00'),
(83, 33, 10, 1, 4, 1, NULL, '1400.00'),
(84, 34, 10, 1, 4, 1, NULL, '1400.00'),
(85, 35, 10, 1, 4, 1, NULL, '1400.00'),
(86, 36, 1, 1, 6, 1, NULL, '3500.00'),
(87, 36, 3, 1, 6, 1, NULL, '2800.00'),
(88, 36, 10, 1, 6, 1, NULL, '2200.00'),
(89, 37, 1, 1, 6, 1, NULL, '3500.00'),
(90, 37, 3, 1, 6, 1, NULL, '2800.00'),
(91, 37, 10, 1, 6, 1, NULL, '2200.00'),
(92, 38, 1, 1, 8, 1, NULL, '4500.00'),
(93, 38, 3, 1, 8, 1, NULL, '3600.00'),
(94, 38, 10, 1, 8, 1, NULL, '2800.00'),
(95, 39, 1, 1, 8, 1, NULL, '4500.00'),
(96, 39, 3, 1, 8, 1, NULL, '3600.00'),
(97, 39, 10, 1, 8, 1, NULL, '2800.00'),
(98, 40, 1, 1, 8, 1, NULL, '4500.00'),
(99, 40, 3, 1, 8, 1, NULL, '3600.00'),
(100, 40, 10, 1, 8, 1, NULL, '2800.00'),
(101, 41, 1, 1, 8, 1, NULL, '4500.00'),
(102, 41, 3, 1, 8, 1, NULL, '3600.00'),
(103, 41, 10, 1, 8, 1, NULL, '2800.00'),
(104, 8, 3, 1, 5, 1, NULL, '1700.00'),
(105, 9, 3, 1, 5, 1, NULL, '1700.00'),
(106, 10, 3, 1, 5, 1, NULL, '1700.00'),
(107, 11, 3, 1, 5, 1, NULL, '1700.00'),
(108, 8, 10, 1, 5, 1, NULL, '1300.00'),
(109, 9, 10, 1, 5, 1, NULL, '1300.00'),
(110, 10, 10, 1, 5, 1, NULL, '1300.00'),
(111, 11, 10, 1, 5, 1, NULL, '1300.00'),
(112, 1, 1, 1, 10, 1, NULL, '5500.00'),
(113, 1, 3, 1, 10, 1, NULL, '4400.00'),
(114, 1, 10, 1, 10, 1, NULL, '3300.00'),
(115, 2, 1, 1, 5, 1, NULL, '3000.00'),
(116, 2, 3, 1, 5, 1, NULL, '2400.00'),
(117, 2, 10, 1, 5, 1, NULL, '1800.00'),
(118, 3, 1, 1, 5, 1, NULL, '3000.00'),
(119, 3, 3, 1, 5, 1, NULL, '2400.00'),
(120, 3, 10, 1, 5, 1, NULL, '1800.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temporadas`
--

CREATE TABLE `temporadas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_general_ci DEFAULT '#007bff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `temporadas`
--

INSERT INTO `temporadas` (`id`, `nombre`, `fecha_inicio`, `fecha_fin`, `color`) VALUES
(1, 'Alta', '2025-01-01', '2025-01-04', '#ff0000'),
(2, 'Puentes y Veranos', '2025-01-05', '2025-01-09', '#ffff00'),
(3, 'Media C.D', '2025-01-10', '2025-01-30', '#0000ff'),
(4, 'Puentes y Veranos', '2025-01-31', '2025-01-31', '#ffff00'),
(5, 'Puentes y Veranos C.D', '2025-02-01', '2025-02-03', '#ffff00'),
(6, 'Media C.D', '2025-02-04', '2025-02-28', '#0000ff'),
(7, 'Media C.D', '2025-03-01', '2025-03-13', '#0000ff'),
(8, 'Puentes y Veranos', '2025-03-14', '2025-03-17', '#ffff00'),
(9, 'Baja', '2025-03-18', '2025-03-31', '#808080'),
(10, 'Baja', '2025-04-01', '2025-04-12', '#808080'),
(11, 'E Semana Santa', '2025-04-13', '2025-04-25', '#ffa500'),
(12, 'Baja', '2025-04-26', '2025-04-30', '#808080'),
(13, 'Baja', '2025-06-01', '2025-06-14', '#808080'),
(14, 'Media', '2025-06-15', '2025-06-30', '#0000ff'),
(15, 'Media', '2025-07-01', '2025-07-15', '#0000ff'),
(16, 'Puentes y Veranos', '2025-07-16', '2025-07-31', '#ffff00'),
(17, 'Puentes y Veranos', '2025-08-01', '2025-08-10', '#ffff00'),
(18, 'Media', '2025-08-11', '2025-08-17', '#0000ff'),
(19, 'Baja', '2025-08-18', '2025-08-31', '#808080'),
(20, 'Baja', '2025-09-01', '2025-09-30', '#808080'),
(21, 'Baja', '2025-10-01', '2025-10-31', '#808080'),
(22, 'Baja', '2025-11-01', '2025-11-13', '#808080'),
(23, 'Puentes y Veranos', '2025-11-14', '2025-11-17', '#ffff00'),
(24, 'Baja', '2025-11-18', '2025-11-30', '#808080'),
(25, 'Baja', '2025-12-01', '2025-12-14', '#808080'),
(26, 'Puentes y Veranos', '2025-12-15', '2025-12-22', '#ffff00'),
(27, 'Alta E Diciembre', '2025-12-23', '2025-12-25', '#ffa500'),
(28, 'Alta', '2025-12-26', '2025-12-31', '#ff0000');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_habitacion`
--

CREATE TABLE `tipos_habitacion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_habitacion`
--

INSERT INTO `tipos_habitacion` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Habitacion Sencilla', '1 cama matrimonial, ventilador, mini refrigerador\r\ntelevisión y aire acondicionado'),
(2, 'Habitacion Sencilla con cama KingSize', '1 cama KingSize, television, aire acondicionado y ventilador'),
(3, 'Bungalow Chico', '1 cama matrimonial, un sofa cama, un ventilador, un aire acondicionado, televisor y cocineta'),
(4, 'Bungalow Mediano', '1 cama matrimonial ventilador, aire acondicionado, television y cocineta'),
(5, 'Habitacion Standard', '2 camas matrimoniales\r\nTelevisión\r\nAire Acondicionado\r\nVentilador\r\nCocineta en el balcón\r\nLa mayoría con la vista en la bahia\r\nUbicados en todos los pisos'),
(6, 'Suite', '1 Habitación con una cama King Size, ventiladores, sala, comedor y cocina, televisión, aire acondicionado y baño completo'),
(7, 'Master Suite', '2 habitaciones cada una con 2 camas matrimoniales, ventilador, televisión, aire acondicionado y baño completo, sala comedor y cocina');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `usuario` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `contraseña` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rol` enum('admin','recepcionista') COLLATE utf8mb4_general_ci DEFAULT 'recepcionista',
  `estado` enum('activo','inactivo') COLLATE utf8mb4_general_ci DEFAULT 'activo',
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `intentos_fallidos` int(11) DEFAULT '0',
  `bloqueado_hasta` timestamp NULL DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `usuario`, `correo`, `telefono`, `contraseña`, `rol`, `estado`, `ultimo_acceso`, `foto`, `intentos_fallidos`, `bloqueado_hasta`, `creado_en`) VALUES
(1, 'Administrador', 'admin', 'admin@hotel.com', NULL, 'admin1234', 'admin', 'activo', '2025-07-14 04:18:52', NULL, 0, NULL, '2025-07-04 21:34:00'),
(2, 'Recepcionista', 'user', 'user@hotel.com', NULL, 'user123', 'recepcionista', 'activo', NULL, NULL, 0, NULL, '2025-07-04 21:34:00'),
(3, 'María García', 'maria.garcia', 'maria@hotel.com', '312-555-0001', 'maria123', 'recepcionista', 'activo', NULL, NULL, 0, NULL, '2025-07-05 19:25:27'),
(4, 'Carlos Mendoza', 'carlos.mendoza', 'carlos@hotel.com', '312-555-0002', 'carlos123', 'recepcionista', 'activo', NULL, NULL, 0, NULL, '2025-07-05 19:25:27'),
(5, 'Ana López', 'ana.lopez', 'ana@hotel.com', '312-555-0003', 'ana123', 'admin', 'activo', NULL, NULL, 0, NULL, '2025-07-05 19:25:27');

--
-- Disparadores `usuarios`
--
DELIMITER $$
CREATE TRIGGER `log_usuario_cambios` AFTER UPDATE ON `usuarios` FOR EACH ROW BEGIN
    IF OLD.estado != NEW.estado THEN
        INSERT INTO logs_actividad (usuario_id, accion, detalles, ip_address)
        VALUES (NEW.id, 'estado_cambiado', CONCAT('Estado cambiado de ', OLD.estado, ' a ', NEW.estado), '127.0.0.1');
    END IF;
    
    IF OLD.rol != NEW.rol THEN
        INSERT INTO logs_actividad (usuario_id, accion, detalles, ip_address)
        VALUES (NEW.id, 'rol_cambiado', CONCAT('Rol cambiado de ', OLD.rol, ' a ', NEW.rol), '127.0.0.1');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `verificacion`
--

CREATE TABLE `verificacion` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `metodo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_estadisticas_usuarios`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_estadisticas_usuarios` (
`total_usuarios` bigint(21)
,`usuarios_activos` decimal(23,0)
,`usuarios_inactivos` decimal(23,0)
,`administradores` decimal(23,0)
,`recepcionistas` decimal(23,0)
,`activos_ultimo_mes` decimal(23,0)
,`activos_ultima_semana` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_estadisticas_usuarios`
--
DROP TABLE IF EXISTS `vista_estadisticas_usuarios`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_estadisticas_usuarios`  AS  select count(0) AS `total_usuarios`,sum((case when (`usuarios`.`estado` = 'activo') then 1 else 0 end)) AS `usuarios_activos`,sum((case when (`usuarios`.`estado` = 'inactivo') then 1 else 0 end)) AS `usuarios_inactivos`,sum((case when (`usuarios`.`rol` = 'admin') then 1 else 0 end)) AS `administradores`,sum((case when (`usuarios`.`rol` = 'recepcionista') then 1 else 0 end)) AS `recepcionistas`,sum((case when (`usuarios`.`ultimo_acceso` >= (now() - interval 30 day)) then 1 else 0 end)) AS `activos_ultimo_mes`,sum((case when (`usuarios`.`ultimo_acceso` >= (now() - interval 7 day)) then 1 else 0 end)) AS `activos_ultima_semana` from `usuarios` ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividad_usuarios`
--
ALTER TABLE `actividad_usuarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `agrupaciones`
--
ALTER TABLE `agrupaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `agrupacion_habitaciones`
--
ALTER TABLE `agrupacion_habitaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `bloqueos`
--
ALTER TABLE `bloqueos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `checkin_articulos`
--
ALTER TABLE `checkin_articulos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `checkin_personas`
--
ALTER TABLE `checkin_personas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_reserva` (`id_reserva`);

--
-- Indices de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `huespedes`
--
ALTER TABLE `huespedes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `logs_actividad`
--
ALTER TABLE `logs_actividad`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `nacionalidades`
--
ALTER TABLE `nacionalidades`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tarifas`
--
ALTER TABLE `tarifas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `temporadas`
--
ALTER TABLE `temporadas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tipos_habitacion`
--
ALTER TABLE `tipos_habitacion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `verificacion`
--
ALTER TABLE `verificacion`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividad_usuarios`
--
ALTER TABLE `actividad_usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `agrupaciones`
--
ALTER TABLE `agrupaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `agrupacion_habitaciones`
--
ALTER TABLE `agrupacion_habitaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bloqueos`
--
ALTER TABLE `bloqueos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checkin_articulos`
--
ALTER TABLE `checkin_articulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checkin_personas`
--
ALTER TABLE `checkin_personas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `huespedes`
--
ALTER TABLE `huespedes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `nacionalidades`
--
ALTER TABLE `nacionalidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tarifas`
--
ALTER TABLE `tarifas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT de la tabla `temporadas`
--
ALTER TABLE `temporadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipos_habitacion`
--
ALTER TABLE `tipos_habitacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `verificacion`
--
ALTER TABLE `verificacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
