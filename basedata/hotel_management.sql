-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-07-2025 a las 20:22:20
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `hotel_management`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `username`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 0, 'guest', 'LOGIN_FAILED', 'Invalid password for user: admin', '::1', '2025-06-29 21:30:06'),
(2, 4, 'admin', 'LOGIN', 'User logged in successfully', '::1', '2025-06-29 21:47:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agrupaciones`
--

CREATE TABLE `agrupaciones` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agrupadetalle`
--

CREATE TABLE `agrupadetalle` (
  `idagrupaciones` int(11) NOT NULL,
  `id_habitacion` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `anticipos`
--

CREATE TABLE `anticipos` (
  `id` int(11) NOT NULL,
  `guest` varchar(100) DEFAULT NULL,
  `reserva_id` int(11) DEFAULT NULL,
  `entrada` date DEFAULT NULL,
  `salida` date DEFAULT NULL,
  `tipoHabitacion` varchar(100) DEFAULT NULL,
  `personas` int(11) DEFAULT NULL,
  `tarifa` decimal(10,2) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `anticipo` decimal(10,2) DEFAULT NULL,
  `saldo` decimal(10,2) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `ticket` varchar(100) DEFAULT NULL,
  `tasa_cambio` decimal(10,2) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `hora_impresion` datetime DEFAULT NULL,
  `selectMoneda` varchar(10) DEFAULT 'MXN'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL,
  `iva` decimal(5,2) DEFAULT 16.00,
  `ish` decimal(5,2) DEFAULT 3.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `iva`, `ish`) VALUES
(1, 16.00, 3.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `guests`
--

CREATE TABLE `guests` (
  `id` bigint(20) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `nacionalidad` varchar(255) DEFAULT NULL,
  `calle` varchar(255) DEFAULT NULL,
  `ciudad` varchar(255) DEFAULT NULL,
  `estado` varchar(255) DEFAULT NULL,
  `cp` varchar(20) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `rfc` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `auto` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `guests`
--

INSERT INTO `guests` (`id`, `nombre`, `nacionalidad`, `calle`, `ciudad`, `estado`, `cp`, `telefono`, `rfc`, `email`, `auto`) VALUES
(1, 'Julian Almeida Diaz', 'Mexicana', 'Av Siempre Viva 123', 'Springfield', 'SP', '12345', '555-1234', 'ALDJ800101ABC', 'julian.a@example.com', NULL),
(2, 'Shirley R. Cartwright', 'Estadounidense', '1 Infinite Loop', 'Cupertino', 'CA', '95014', '555-5678', 'CART850202XYZ', 'shirley.c@example.com', NULL),
(3, 'Horváth Darda', 'Húngara', 'Santa Carolina XD', 'Budapest', 'Colima', '202246', '555-9988', 'HD381848EN1', 'h.darda@sample.net', NULL),
(4, 'Delia Pineda', 'Mexicana', 'Santa Carolina XD', 'Polaco', 'Colima', '28219', '555-9988', 'HD381848EN1', 'delia.aldaco.m@gmail.com', NULL),
(5, 'Valeria Elizabeth', 'Venezolana', 'Castillo perez', 'Manzanillo', 'Colima', '3422', '31433513', 'fdfdgfdefg12', 'valery23@gmail.com', NULL),
(6, 'Emiliano ', 'Venezolana', 'PORFAVOR', 'Polanco ', 'Mexico', '2344', '3143351365', 'fdfdgfdefg12', 'emy23@gmail.com', NULL),
(7, 'Sabina', 'Europea', 'Alavarez1244', 'Valencia', 'España', '2355', '314134666', 'SANC2394EB1', 'sabi23@gmail.com', NULL),
(8, 'Mauricio Chavez', 'Mexicana', 'Neptuno 16', 'Manzanillo', 'Colima', '', '3143312980', 'MACM1308ENQ10', 'mchavez15@gmail.com', 'AVANZA'),
(9, 'Raul', 'Colombiana', 'Alavarez1244', 'Valencia', 'España', '2820', '912i03i023', '9ih8e8r3', 'ddddd@3333c.com', 'Journery');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paquetes_tarifas`
--

CREATE TABLE `paquetes_tarifas` (
  `id` int(11) NOT NULL,
  `tipo_paquete` varchar(100) DEFAULT NULL,
  `pax` int(11) DEFAULT NULL,
  `noches_min` int(11) DEFAULT NULL,
  `noches_max` int(11) DEFAULT NULL,
  `tarifa` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paquetes_tarifas`
--

INSERT INTO `paquetes_tarifas` (`id`, `tipo_paquete`, `pax`, `noches_min`, `noches_max`, `tarifa`) VALUES
(1, 'Bw Chico + Standard', 5, 1, 2, 2550.00),
(2, 'Bw Chico + Standard', 5, 3, 6, 2510.00),
(3, 'Bw Chico + Standard', 5, 7, 14, 2470.00),
(4, 'Bw Chico + Standard', 5, 15, 20, 2430.00),
(5, 'Bw Chico + Standard', 5, 21, 999, 2390.00),
(6, 'Standard + Standard', 5, 1, 2, 2650.00),
(7, 'Standard + Standard', 5, 3, 6, 2610.00),
(8, 'Standard + Standard', 5, 7, 14, 2570.00),
(9, 'Standard + Standard', 5, 15, 20, 2530.00),
(10, 'Standard + Standard', 5, 21, 999, 2490.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `resourceId` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `color` varchar(20) DEFAULT '#FFD700',
  `guestId` int(11) DEFAULT NULL,
  `guestNameManual` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'RESERVACION_PREVIA',
  `rate` decimal(10,2) DEFAULT 0.00,
  `iva` decimal(5,2) DEFAULT 16.00,
  `ish` decimal(5,2) DEFAULT 3.00,
  `inapamDiscount` tinyint(1) DEFAULT 0,
  `inapamCredential` varchar(50) DEFAULT NULL,
  `inapamDiscountValue` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `anticipo` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`anticipo`)),
  `pagosHotel` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pagosHotel`)),
  `pagosExtra` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pagosExtra`)),
  `verification` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`verification`)),
  `checkinGuests` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checkinGuests`)),
  `checkinItems` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checkinItems`)),
  `receptionistName` varchar(255) DEFAULT NULL,
  `totalReserva` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `inapamDiscountType` varchar(20) DEFAULT 'porcentaje',
  `temporada_id` int(11) DEFAULT NULL,
  `unificacion_id` int(11) DEFAULT NULL,
  `descuentos_aplicados` text DEFAULT NULL,
  `calculo_tarifa` text DEFAULT NULL,
  `grupo_reserva` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reservations`
--

INSERT INTO `reservations` (`id`, `resourceId`, `title`, `start_date`, `end_date`, `color`, `guestId`, `guestNameManual`, `status`, `rate`, `iva`, `ish`, `inapamDiscount`, `inapamCredential`, `inapamDiscountValue`, `notes`, `anticipo`, `pagosHotel`, `pagosExtra`, `verification`, `checkinGuests`, `checkinItems`, `receptionistName`, `totalReserva`, `created_at`, `updated_at`, `inapamDiscountType`, `temporada_id`, `unificacion_id`, `descuentos_aplicados`, `calculo_tarifa`, `grupo_reserva`) VALUES
(10, 6, 'Reserva Delia Pineda', '2025-06-01', '2025-06-10', '#ffd700', 4, 'Delia Pineda', 'RESERVACION_PREVIA', 0.00, 0.00, 0.00, 0, '', 0.00, '', '{\"monto\":\"\",\"metodo\":\"\",\"ticket\":\"\"}', '[]', '[]', '{\"dateTime\":\"\",\"whatsAppVerified\":\"No\",\"senderName\":\"\"}', '[]', '{\"loza\":{\"name\":\"Loza (Utensils)\",\"delivered\":false,\"price\":200},\"licuadora\":{\"name\":\"Licuadora (Blender)\",\"delivered\":false,\"price\":200},\"cafetera\":{\"name\":\"Cafetera (Coffee Maker)\",\"delivered\":false,\"price\":200},\"controltv\":{\"name\":\"Control TV\",\"delivered\":false,\"price\":0},\"controlaa\":{\"name\":\"Control AA\",\"delivered\":false,\"price\":0},\"toallashab\":{\"name\":\"Toallas Habitaci\\u00f3n\",\"delivered\":false,\"price\":0},\"toallasalb\":{\"name\":\"Toallas Alberca\",\"delivered\":false,\"price\":0}}', 'Admin User', 0.00, '2025-06-09 21:56:12', '2025-06-09 21:56:12', 'porcentaje', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `capacidad_min` int(11) NOT NULL DEFAULT 1,
  `capacidad_max` int(11) NOT NULL,
  `piso` int(11) DEFAULT NULL,
  `vista` varchar(50) DEFAULT NULL,
  `amenidades` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenidades`)),
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_tipo` int(11) NOT NULL,
  `status` enum('Disponible','Ocupado','Mantenimiento','Fuera_Servicio') DEFAULT 'Disponible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarifas_habitacion_temporada`
--

CREATE TABLE `tarifas_habitacion_temporada` (
  `id` int(11) NOT NULL,
  `idagrupaciones` int(11) NOT NULL,
  `temporada_id` int(11) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temporadas`
--

CREATE TABLE `temporadas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL COMMENT 'Nombre de la temporada (ej: Temporada Alta, Navidad)',
  `descripcion` text DEFAULT NULL COMMENT 'Descripción detallada de la temporada',
  `fecha_inicio` date NOT NULL COMMENT 'Fecha de inicio de la temporada',
  `fecha_fin` date NOT NULL COMMENT 'Fecha de fin de la temporada',
  `factor_precio` decimal(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Multiplicador de precio (1.5 = 50% más caro)',
  `activa` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Si la temporada está activa',
  `prioridad` int(11) NOT NULL DEFAULT 1 COMMENT 'Prioridad para resolver conflictos entre temporadas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `color` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Gestión de temporadas vacacionales y sus factores de precio';

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_habitacion`
--

CREATE TABLE `tipos_habitacion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `capacidad_min` int(11) NOT NULL DEFAULT 1,
  `capacidad_max` int(11) NOT NULL,
  `caracteristicas` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullName` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullName`, `email`, `role`, `password_hash`, `created_at`, `status`) VALUES
(4, 'admin', '$2y$10$fdBWALG4jvjfrSRtZFyg8OX3KqkQDXMslUumZYLr3XNYY1JniguKa', 'Admin User', 'admin@hotel.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2025-06-29 23:22:50', 'active'),
(10, 'NadiaM', '', 'Nadia Michelle', 'nady12@outlook.com', 'user', '$2y$10$qFPILoVWIRJI7Ax5FKfGu.B3tCSsdfmQmfsaed5gCcyTUu/sjOUFy', '2025-06-29 23:22:50', 'active'),
(11, 'Mariana', '', 'Mariana Fletes', 'mary123@gmail.com', 'user', '$2y$10$O06sf82yO2l7nj8KlQZKkuSa5ksim2uAQI8fox.NlfXrDCzvlyLgi', '2025-06-29 23:22:50', 'active');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `anticipos`
--
ALTER TABLE `anticipos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `guests`
--
ALTER TABLE `guests`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `paquetes_tarifas`
--
ALTER TABLE `paquetes_tarifas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fechas` (`start_date`,`end_date`),
  ADD KEY `idx_temporada` (`temporada_id`),
  ADD KEY `idx_unificacion` (`unificacion_id`);

--
-- Indices de la tabla `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_tipo` (`id_tipo`),
  ADD KEY `idx_capacidad` (`capacidad_min`,`capacidad_max`),
  ADD KEY `idx_piso` (`piso`),
  ADD KEY `idx_status_tipo` (`status`,`id_tipo`);

--
-- Indices de la tabla `tarifas_habitacion_temporada`
--
ALTER TABLE `tarifas_habitacion_temporada`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tipo_temporada` (`idagrupaciones`,`temporada_id`),
  ADD KEY `temporada_id` (`temporada_id`);

--
-- Indices de la tabla `temporadas`
--
ALTER TABLE `temporadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fechas` (`fecha_inicio`,`fecha_fin`),
  ADD KEY `idx_activa` (`activa`);

--
-- Indices de la tabla `tipos_habitacion`
--
ALTER TABLE `tipos_habitacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `anticipos`
--
ALTER TABLE `anticipos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `guests`
--
ALTER TABLE `guests`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `paquetes_tarifas`
--
ALTER TABLE `paquetes_tarifas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `tarifas_habitacion_temporada`
--
ALTER TABLE `tarifas_habitacion_temporada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `temporadas`
--
ALTER TABLE `temporadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `tipos_habitacion`
--
ALTER TABLE `tipos_habitacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`id_tipo`) REFERENCES `tipos_habitacion` (`id`);

--
-- Filtros para la tabla `tarifas_habitacion_temporada`
--
ALTER TABLE `tarifas_habitacion_temporada`
  ADD CONSTRAINT `tarifas_habitacion_temporada_ibfk_1` FOREIGN KEY (`idagrupaciones`) REFERENCES `tipos_habitacion` (`id`),
  ADD CONSTRAINT `tarifas_habitacion_temporada_ibfk_2` FOREIGN KEY (`temporada_id`) REFERENCES `temporadas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
