-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 01-08-2025 a las 01:36:07
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
-- Base de datos: `hotel_magnament`
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

CREATE DEFINER=`root`@`localhost` PROCEDURE `obtener_siguiente_folio` (IN `p_año` INT, OUT `p_siguiente_folio` INT)  BEGIN
    DECLARE ultimo_folio INT DEFAULT 0;
    
    SELECT COALESCE(MAX(folio_ticket), 0) INTO ultimo_folio
    FROM tickets 
    WHERE YEAR(fecha_creacion_ticket) = p_año;
    
    SET p_siguiente_folio = ultimo_folio + 1;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `actualizar_ultimo_acceso` (`user_id` INT) RETURNS TINYINT(1) MODIFIES SQL DATA
    DETERMINISTIC
BEGIN
    UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = user_id;
    RETURN TRUE;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `calcular_ocupacion_fecha` (`fecha_consulta` DATE) RETURNS DECIMAL(5,2) READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE total_agrupaciones INT DEFAULT 0;
    DECLARE agrupaciones_ocupadas INT DEFAULT 0;
    DECLARE porcentaje_ocupacion DECIMAL(5,2) DEFAULT 0.00;
    
    -- Contar total de agrupaciones
    SELECT COUNT(*) INTO total_agrupaciones FROM agrupaciones;
    
    -- Contar agrupaciones ocupadas en la fecha
    SELECT COUNT(DISTINCT t.id_agrupacion) 
    INTO agrupaciones_ocupadas
    FROM reservas r
    INNER JOIN tarifas t ON r.id_tarifa = t.id
    WHERE fecha_consulta BETWEEN r.start_date AND DATE_SUB(r.end_date, INTERVAL 1 DAY)
    AND r.status IN ('confirmada', 'activa');
    
    -- Calcular porcentaje
    IF total_agrupaciones > 0 THEN
        SET porcentaje_ocupacion = (agrupaciones_ocupadas / total_agrupaciones) * 100;
    END IF;
    
    RETURN porcentaje_ocupacion;
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
(0, 1, 'login', 'Inicio de sesión exitoso', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '2025-07-12 18:46:02'),
(1, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 18:31:31'),
(2, 1, 'tarifas_masivas_creadas', '3 tarifas creadas para tipo de habitación ID 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 18:44:38'),
(3, 1, 'tarifa_creada', 'Tarifa creada para agrupación ID 1 y temporada ID 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 18:45:41'),
(4, 1, 'tarifa_editada', 'Tarifa ID 1 editada', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 18:47:15'),
(5, 1, 'tarifa_creada', 'Tarifa creada para agrupación ID 1 y temporada ID 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 18:53:45'),
(6, 1, 'tarifas_masivas_creadas', '4 tarifas creadas para tipo de habitación ID 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 19:05:21'),
(7, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 19:11:16'),
(8, 1, 'login', 'Inicio de sesión exitoso', '192.168.10.47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-07-11 19:16:58'),
(9, 1, 'tarifas_masivas_creadas', '22 tarifas creadas para tipo de habitación ID 5', '192.168.10.47', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36', '2025-07-11 19:31:29'),
(10, 1, 'login', 'Inicio de sesión exitoso', '192.168.10.21', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Mobile Safari/537.36', '2025-07-11 20:42:04'),
(11, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-11 22:07:35'),
(12, 1, 'tarifas_masivas_creadas', '4 tarifas creadas para tipo de habitación ID 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-12 00:35:38'),
(13, 1, 'login', 'Inicio de sesión exitoso', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '2025-07-12 00:38:41'),
(14, 1, 'tarifas_masivas_creadas', '4 tarifas creadas para tipo de habitación ID 3', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', '2025-07-12 00:48:52'),
(15, 1, 'login', 'Inicio de sesión exitoso', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-12 18:33:38'),
(16, 1, 'logout', 'Cierre de sesión', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-12 18:33:47');

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
(1, 'Junior Suite 208 y 209', 'La primer habitación con cama King Size, televisión, ventilador, aire acondicionado y baño completo La segunda habitación con 2 camas matrimoniales , ventilador, televisión, aire acondicionado, y baño completo, además sala, comedor y cocina'),
(2, '5 pax', 'ESTANDAR + Bw Chico'),
(3, '5 pax 2', 'ESTANDAR + ESTANDAR'),
(4, 'Habitacion Sencilla 112', '112'),
(5, 'Habitacion Sencilla 113', '113'),
(6, 'Habitacion Sencilla 114', '114'),
(7, 'Habitacion Sencilla con cama KingSize', '208'),
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
(1, 7, 4),
(2, 12, 9),
(3, 37, 33),
(9, 1, 4),
(10, 1, 33),
(11, 4, 1),
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
  `tipo` enum('apartado','ocupado','mantenimiento','limpieza') COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
(39, 'sesion_timeout', '3600', 'Tiempo de expiración de sesión en segundos', '2025-07-05 19:25:27'),
(0, 'ticket_folio_inicial', '1', 'Folio inicial para tickets del año', '2025-07-30 15:27:33'),
(0, 'ticket_prefijo', '', 'Prefijo para folios de tickets', '2025-07-30 15:27:33'),
(0, 'ticket_reiniciar_anual', '1', 'Reiniciar foliado cada año (1=sí, 0=no)', '2025-07-30 15:27:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cortesias`
--

CREATE TABLE `cortesias` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) NOT NULL,
  `motivo` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `autorizado_por` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cortesias`
--

INSERT INTO `cortesias` (`id`, `id_reserva`, `motivo`, `descripcion`, `autorizado_por`, `registrado_por`, `fecha_registro`) VALUES
(1, 2, 'Otro', 'futbol liga', 'Ignacio Anaya', 1, '2025-07-30 22:24:26');

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
-- Estructura de tabla para la tabla `descuentos_inapam`
--

CREATE TABLE `descuentos_inapam` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) NOT NULL,
  `credencial_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `monto_descuento` decimal(10,2) NOT NULL DEFAULT '50.00',
  `registrado_por` int(11) DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `descuentos_inapam`
--

INSERT INTO `descuentos_inapam` (`id`, `id_reserva`, `credencial_id`, `monto_descuento`, `registrado_por`, `fecha_registro`) VALUES
(1, 3, '6451274325754796', '50.00', 1, '2025-07-31 17:55:58');

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

--
-- Volcado de datos para la tabla `logs_actividad`
--

INSERT INTO `logs_actividad` (`id`, `usuario_id`, `accion`, `detalles`, `ip_address`, `user_agent`, `fecha`) VALUES
(0, 1, 'login', 'Inicio de sesión exitoso', NULL, NULL, '2025-07-10 19:04:33'),
(1, 1, 'usuario_creado', 'Usuario administrador creado', '127.0.0.1', NULL, '2025-07-05 19:25:27'),
(2, 2, 'usuario_creado', 'Usuario recepcionista creado', '127.0.0.1', NULL, '2025-07-05 19:25:27'),
(3, 1, 'login', 'Inicio de sesión exitoso', '127.0.0.1', NULL, '2025-07-05 19:25:27'),
(4, 2, 'login', 'Inicio de sesión exitoso', '127.0.0.1', NULL, '2025-07-05 19:25:27'),
(5, 1, 'login', 'Inicio de sesión exitoso', NULL, NULL, '2025-07-05 20:04:12'),
(6, 1, 'login', 'Inicio de sesión exitoso', NULL, NULL, '2025-07-07 19:57:34'),
(7, 1, 'login', 'Inicio de sesión exitoso', NULL, NULL, '2025-07-07 21:03:10');

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
  `tipo` enum('anticipo','pago_hotel','pago_extra') COLLATE utf8mb4_general_ci NOT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `metodo_pago` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `clave_pago` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `autorizacion` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` enum('pendiente','procesado','rechazado') COLLATE utf8mb4_general_ci DEFAULT 'procesado',
  `notas` text COLLATE utf8mb4_general_ci,
  `fecha_pago` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `registrado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `id_reserva`, `tipo`, `monto`, `metodo_pago`, `clave_pago`, `autorizacion`, `estado`, `notas`, `fecha_pago`, `registrado_por`) VALUES
(14, 1, 'anticipo', '1000.00', 'Efectivo', '12398', '42846', '', '', '2025-07-30 17:20:29', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservas`
--

CREATE TABLE `reservas` (
  `id` int(11) NOT NULL,
  `id_tarifa` int(11) DEFAULT NULL,
  `id_huesped` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `id_agrupacion` int(11) DEFAULT NULL,
  `personas_max` int(11) DEFAULT NULL,
  `noches` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT '0.00',
  `tipo_reserva` enum('walking','previa','cortesia') COLLATE utf8mb4_general_ci DEFAULT 'previa',
  `descuento_inapam` decimal(10,2) DEFAULT '0.00',
  `es_cortesia` tinyint(1) DEFAULT '0',
  `total_original` decimal(10,2) DEFAULT '0.00',
  `total_descuentos` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reservas`
--

INSERT INTO `reservas` (`id`, `id_tarifa`, `id_huesped`, `id_usuario`, `start_date`, `end_date`, `status`, `created_at`, `id_agrupacion`, `personas_max`, `noches`, `total`, `tipo_reserva`, `descuento_inapam`, `es_cortesia`, `total_original`, `total_descuentos`) VALUES
(1, NULL, 1, 1, '2025-07-30', '2025-07-31', 'confirmada', '2025-07-29 20:26:21', 10, 2, 1, '1450.00', 'previa', '0.00', 0, '0.00', '0.00'),
(2, NULL, 1, 1, '2025-08-01', '2025-08-04', 'confirmada', '2025-07-30 22:24:26', 12, 10, 3, '0.00', 'cortesia', '0.00', 1, '4500.00', '0.00'),
(3, NULL, 1, 1, '2025-07-25', '2025-07-26', 'confirmada', '2025-07-31 17:55:58', 12, 1, 1, '2950.00', 'previa', '50.00', 0, '3000.00', '50.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva_articulos`
--

CREATE TABLE `reserva_articulos` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) DEFAULT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `notas` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva_personas`
--

CREATE TABLE `reserva_personas` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `edad` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarifas`
--

CREATE TABLE `tarifas` (
  `id` int(11) NOT NULL,
  `id_agrupacion` int(11) DEFAULT NULL,
  `id_tipo_habitacion` int(11) DEFAULT NULL,
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

INSERT INTO `tarifas` (`id`, `id_agrupacion`, `id_tipo_habitacion`, `id_temporada`, `personas_min`, `personas_max`, `noches_min`, `noches_max`, `precio`) VALUES
(1, 4, NULL, 15, 1, 2, 1, 2, '1200.00'),
(2, 5, NULL, 15, 1, 2, 1, 2, '1200.00'),
(3, 6, NULL, 15, 1, 2, 1, 2, '1200.00'),
(4, 7, NULL, 15, 1, 2, 1, 2, '1200.00'),
(5, 1, NULL, 15, 1, 3, 1, 2, '3150.00'),
(6, 8, NULL, 15, 1, 2, 1, 2, '1200.00'),
(7, 9, NULL, 15, 1, 3, 1, 2, '1200.00'),
(8, 10, NULL, 15, 1, 3, 1, 2, '1200.00'),
(9, 11, NULL, 15, 1, 3, 1, 2, '1200.00'),
(10, 12, NULL, 15, 1, 2, 1, 2, '1650.00'),
(11, 13, NULL, 15, 1, 2, 1, 2, '1400.00'),
(12, 14, NULL, 15, 1, 2, 1, 2, '1400.00'),
(13, 15, NULL, 15, 1, 2, 1, 2, '1400.00'),
(14, 16, NULL, 15, 1, 2, 1, 2, '1400.00'),
(15, 17, NULL, 15, 1, 2, 1, 2, '1400.00'),
(16, 18, NULL, 15, 1, 2, 1, 2, '1400.00'),
(17, 19, NULL, 15, 1, 2, 1, 2, '1400.00'),
(18, 20, NULL, 15, 1, 2, 1, 2, '1400.00'),
(19, 21, NULL, 15, 1, 2, 1, 2, '1400.00'),
(20, 23, NULL, 15, 1, 2, 1, 2, '1400.00'),
(21, 25, NULL, 15, 1, 2, 1, 2, '1400.00'),
(22, 22, NULL, 15, 1, 2, 1, 2, '1400.00'),
(23, 24, NULL, 15, 1, 2, 1, 2, '1400.00'),
(24, 27, NULL, 15, 1, 2, 1, 2, '1400.00'),
(25, 28, NULL, 15, 1, 2, 1, 2, '1400.00'),
(26, 29, NULL, 15, 1, 2, 1, 2, '1400.00'),
(27, 30, NULL, 15, 1, 2, 1, 2, '1400.00'),
(28, 31, NULL, 15, 1, 2, 1, 2, '1400.00'),
(29, 32, NULL, 15, 1, 2, 1, 2, '1400.00'),
(30, 33, NULL, 15, 1, 2, 1, 2, '1400.00'),
(31, 34, NULL, 15, 1, 2, 1, 2, '1400.00'),
(32, 35, NULL, 15, 1, 2, 1, 2, '1400.00'),
(33, 13, NULL, 15, 3, 4, 1, 2, '1600.00'),
(34, 14, NULL, 15, 3, 4, 1, 2, '1600.00'),
(35, 15, NULL, 15, 3, 4, 1, 2, '1600.00'),
(36, 16, NULL, 15, 3, 4, 1, 2, '1600.00'),
(37, 17, NULL, 15, 3, 4, 1, 2, '1600.00'),
(38, 18, NULL, 15, 3, 4, 1, 2, '1600.00'),
(39, 19, NULL, 15, 3, 4, 1, 2, '1600.00'),
(40, 20, NULL, 15, 3, 4, 1, 2, '1600.00'),
(41, 21, NULL, 15, 3, 4, 1, 2, '1600.00'),
(42, 23, NULL, 15, 3, 4, 1, 2, '1600.00'),
(43, 25, NULL, 15, 3, 4, 1, 2, '1600.00'),
(44, 22, NULL, 15, 3, 4, 1, 2, '1600.00'),
(45, 24, NULL, 15, 3, 4, 1, 2, '1600.00'),
(46, 27, NULL, 15, 3, 4, 1, 2, '1600.00'),
(47, 28, NULL, 15, 3, 4, 1, 2, '1600.00'),
(48, 29, NULL, 15, 3, 4, 1, 2, '1600.00'),
(49, 30, NULL, 15, 3, 4, 1, 2, '1600.00'),
(50, 31, NULL, 15, 3, 4, 1, 2, '1600.00'),
(51, 32, NULL, 15, 3, 4, 1, 2, '1600.00'),
(52, 33, NULL, 15, 3, 4, 1, 2, '1600.00'),
(53, 34, NULL, 15, 3, 4, 1, 2, '1600.00'),
(54, 35, NULL, 15, 3, 4, 1, 2, '1600.00'),
(55, 37, NULL, 15, 1, 2, 1, 2, '2100.00'),
(56, 36, NULL, 15, 1, 2, 1, 2, '2100.00'),
(57, 37, NULL, 15, 3, 4, 1, 2, '2300.00'),
(58, 36, NULL, 15, 3, 4, 1, 2, '2300.00'),
(59, 1, NULL, 15, 4, 6, 1, 2, '3500.00'),
(60, 38, NULL, 15, 1, 5, 1, 2, '3150.00'),
(61, 39, NULL, 15, 1, 5, 1, 2, '3150.00'),
(62, 40, NULL, 15, 1, 5, 1, 2, '3150.00'),
(63, 41, NULL, 15, 1, 5, 1, 2, '3150.00'),
(64, 38, NULL, 15, 6, 8, 1, 2, '3500.00'),
(65, 39, NULL, 15, 6, 8, 1, 2, '3500.00'),
(66, 40, NULL, 15, 6, 8, 1, 2, '3500.00'),
(67, 41, NULL, 15, 6, 8, 1, 2, '3500.00'),
(68, 4, NULL, 16, 1, 2, 1, 2, '1300.00'),
(69, 5, NULL, 16, 1, 2, 1, 2, '1300.00'),
(70, 6, NULL, 16, 1, 2, 1, 2, '1300.00'),
(71, 7, NULL, 16, 1, 2, 1, 2, '1300.00'),
(72, 8, NULL, 16, 1, 3, 1, 2, '1450.00'),
(73, 9, NULL, 16, 1, 3, 1, 2, '1450.00'),
(74, 10, NULL, 16, 1, 3, 1, 2, '1450.00'),
(75, 11, NULL, 16, 1, 3, 1, 2, '1450.00'),
(76, 13, NULL, 16, 1, 3, 1, 2, '3000.00'),
(77, 14, NULL, 16, 1, 3, 1, 2, '3000.00'),
(78, 15, NULL, 16, 1, 3, 1, 2, '3000.00'),
(79, 16, NULL, 16, 1, 3, 1, 2, '3000.00'),
(80, 17, NULL, 16, 1, 3, 1, 2, '3000.00'),
(81, 18, NULL, 16, 1, 3, 1, 2, '3000.00'),
(82, 19, NULL, 16, 1, 3, 1, 2, '3000.00'),
(83, 20, NULL, 16, 1, 3, 1, 2, '3000.00'),
(84, 21, NULL, 16, 1, 3, 1, 2, '3000.00'),
(85, 23, NULL, 16, 1, 3, 1, 2, '3000.00'),
(86, 25, NULL, 16, 1, 3, 1, 2, '3000.00'),
(87, 22, NULL, 16, 1, 3, 1, 2, '3000.00'),
(88, 24, NULL, 16, 1, 3, 1, 2, '3000.00'),
(89, 27, NULL, 16, 1, 3, 1, 2, '3000.00'),
(90, 28, NULL, 16, 1, 3, 1, 2, '3000.00'),
(91, 29, NULL, 16, 1, 3, 1, 2, '3000.00'),
(92, 30, NULL, 16, 1, 3, 1, 2, '3000.00'),
(93, 31, NULL, 16, 1, 3, 1, 2, '3000.00'),
(94, 32, NULL, 16, 1, 3, 1, 2, '3000.00'),
(95, 33, NULL, 16, 1, 3, 1, 2, '3000.00'),
(96, 34, NULL, 16, 1, 3, 1, 2, '3000.00'),
(97, 35, NULL, 16, 1, 3, 1, 2, '3000.00'),
(98, 12, NULL, 17, 1, 50, 1, 365, '1500.00'),
(99, 12, NULL, 16, 1, 50, 1, 365, '3000.00'),
(100, 4, NULL, 18, 1, 50, 1, 365, '6000.00'),
(101, 5, NULL, 18, 1, 50, 1, 365, '6000.00'),
(102, 6, NULL, 18, 1, 50, 1, 365, '6000.00'),
(103, 4, NULL, 17, 1, 50, 1, 365, '5000.00'),
(104, 5, NULL, 17, 1, 50, 1, 365, '5000.00'),
(105, 6, NULL, 17, 1, 50, 1, 365, '5000.00'),
(106, 4, NULL, 18, 1, 50, 1, 360, '2000.00'),
(107, 5, NULL, 18, 1, 50, 1, 360, '2000.00'),
(108, 6, NULL, 18, 1, 50, 1, 360, '2000.00');

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
-- Estructura de tabla para la tabla `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `id_reserva` int(11) NOT NULL,
  `folio_ticket` int(11) NOT NULL,
  `fecha_creacion_ticket` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_creacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'sistema',
  `fecha_impresion` datetime DEFAULT NULL,
  `veces_impreso` int(11) DEFAULT '0',
  `status_ticket` enum('activo','cancelado','reimpreso') COLLATE utf8mb4_unicode_ci DEFAULT 'activo',
  `notas_ticket` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tickets`
--

INSERT INTO `tickets` (`id`, `id_reserva`, `folio_ticket`, `fecha_creacion_ticket`, `usuario_creacion`, `fecha_impresion`, `veces_impreso`, `status_ticket`, `notas_ticket`, `created_at`) VALUES
(1, 1, 1, '2025-07-30 11:12:52', 'admin', NULL, 0, 'activo', NULL, '2025-07-30 17:12:52'),
(2, 2, 2, '2025-07-30 18:01:49', 'admin', NULL, 0, 'activo', NULL, '2025-07-31 00:01:49'),
(3, 3, 3, '2025-07-31 12:09:30', 'admin', NULL, 0, 'activo', NULL, '2025-07-31 18:09:30');

--
-- Disparadores `tickets`
--
DELIMITER $$
CREATE TRIGGER `update_impresion_count` AFTER UPDATE ON `tickets` FOR EACH ROW BEGIN
    IF NEW.fecha_impresion != OLD.fecha_impresion AND NEW.fecha_impresion IS NOT NULL THEN
        UPDATE tickets SET veces_impreso = veces_impreso + 1 WHERE id = NEW.id;
    END IF;
END
$$
DELIMITER ;

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
(2, 'Habitacion Sencilla con cama KingSize 208', '1 cama KingSize, television, aire acondicionado y ventilador'),
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
(1, 'Administrador', 'admin', 'admin@hotel.com', NULL, 'admin1234', 'admin', 'activo', '2025-07-31 15:27:47', NULL, 0, NULL, '2025-07-04 21:34:00'),
(2, 'Recepcionista', 'user', 'user@hotel.com', NULL, 'user123', 'recepcionista', 'activo', NULL, NULL, 0, NULL, '2025-07-04 21:34:00'),
(3, 'María García', 'maria.garcia', 'maria@hotel.com', '312-555-0001', 'maria123', 'recepcionista', 'activo', NULL, NULL, 0, NULL, '2025-07-05 19:25:27'),
(4, 'Carlos Mendoza', 'carlos.mendoza', 'carlos@hotel.com', '312-555-0002', 'carlos123', 'recepcionista', 'activo', '2025-07-28 15:45:41', NULL, 0, NULL, '2025-07-05 19:25:27'),
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
-- Estructura Stand-in para la vista `vista_tickets_completa`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_tickets_completa` (
`ticket_id` int(11)
,`folio_ticket` int(11)
,`fecha_creacion_ticket` datetime
,`usuario_creacion` varchar(100)
,`fecha_impresion` datetime
,`veces_impreso` int(11)
,`status_ticket` enum('activo','cancelado','reimpreso')
,`reserva_id` int(11)
,`status_reserva` varchar(50)
,`total` decimal(10,2)
,`nombre_huesped` varchar(100)
,`nombre_agrupacion` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_tickets_completa`
--
DROP TABLE IF EXISTS `vista_tickets_completa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_tickets_completa`  AS  select `t`.`id` AS `ticket_id`,`t`.`folio_ticket` AS `folio_ticket`,`t`.`fecha_creacion_ticket` AS `fecha_creacion_ticket`,`t`.`usuario_creacion` AS `usuario_creacion`,`t`.`fecha_impresion` AS `fecha_impresion`,`t`.`veces_impreso` AS `veces_impreso`,`t`.`status_ticket` AS `status_ticket`,`r`.`id` AS `reserva_id`,`r`.`status` AS `status_reserva`,`r`.`total` AS `total`,coalesce(`h`.`nombre`,'No especificado') AS `nombre_huesped`,coalesce(`a`.`nombre`,'No especificado') AS `nombre_agrupacion` from (((`tickets` `t` left join `reservas` `r` on((`t`.`id_reserva` = `r`.`id`))) left join `huespedes` `h` on((`r`.`id_huesped` = `h`.`id`))) left join `agrupaciones` `a` on((`r`.`id_agrupacion` = `a`.`id`))) ;

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
-- Indices de la tabla `cortesias`
--
ALTER TABLE `cortesias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reserva` (`id_reserva`);

--
-- Indices de la tabla `descuentos_inapam`
--
ALTER TABLE `descuentos_inapam`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reserva` (`id_reserva`);

--
-- Indices de la tabla `huespedes`
--
ALTER TABLE `huespedes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reserva_tipo` (`id_reserva`,`tipo`),
  ADD KEY `idx_fecha_pago` (`fecha_pago`);

--
-- Indices de la tabla `reservas`
--
ALTER TABLE `reservas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fechas` (`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_agrupacion_fechas` (`id_agrupacion`,`start_date`,`end_date`);

--
-- Indices de la tabla `reserva_articulos`
--
ALTER TABLE `reserva_articulos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `reserva_personas`
--
ALTER TABLE `reserva_personas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tarifas`
--
ALTER TABLE `tarifas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `folio_unico_por_año` (`folio_ticket`,`fecha_creacion_ticket`),
  ADD KEY `idx_reserva` (`id_reserva`),
  ADD KEY `idx_folio` (`folio_ticket`),
  ADD KEY `idx_fecha_creacion` (`fecha_creacion_ticket`),
  ADD KEY `idx_usuario_fecha` (`usuario_creacion`,`fecha_creacion_ticket`),
  ADD KEY `idx_status_fecha` (`status_ticket`,`fecha_creacion_ticket`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `agrupaciones`
--
ALTER TABLE `agrupaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `agrupacion_habitaciones`
--
ALTER TABLE `agrupacion_habitaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `bloqueos`
--
ALTER TABLE `bloqueos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cortesias`
--
ALTER TABLE `cortesias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `descuentos_inapam`
--
ALTER TABLE `descuentos_inapam`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `huespedes`
--
ALTER TABLE `huespedes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `reservas`
--
ALTER TABLE `reservas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `reserva_articulos`
--
ALTER TABLE `reserva_articulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `reserva_personas`
--
ALTER TABLE `reserva_personas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tarifas`
--
ALTER TABLE `tarifas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT de la tabla `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cortesias`
--
ALTER TABLE `cortesias`
  ADD CONSTRAINT `cortesias_ibfk_1` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `descuentos_inapam`
--
ALTER TABLE `descuentos_inapam`
  ADD CONSTRAINT `descuentos_inapam_ibfk_1` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_reserva` FOREIGN KEY (`id_reserva`) REFERENCES `reservas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
