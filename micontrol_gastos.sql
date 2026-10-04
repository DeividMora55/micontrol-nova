-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 04-10-2026 a las 22:55:46
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
-- Base de datos: `micontrol_gastos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('Ingreso','Gasto') NOT NULL,
  `categoria` varchar(80) NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `nota` varchar(300) DEFAULT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha` date NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id`, `tipo`, `categoria`, `descripcion`, `nota`, `monto`, `fecha`, `creado_en`) VALUES
(1, 'Ingreso', 'Salario', 'Pago quincenal', 'Ingreso principal', 8500.00, '2026-09-21', '2026-09-22 03:18:32'),
(2, 'Ingreso', 'Freelance', 'Diseño de interfaz', 'Proyecto extra', 2200.00, '2026-09-17', '2026-09-22 03:18:32'),
(3, 'Gasto', 'Comida', 'Supermercado', 'Compra semanal', 1280.50, '2026-09-20', '2026-09-22 03:18:32'),
(4, 'Gasto', 'Transporte', 'Gasolina', 'Carga de combustible', 900.00, '2026-09-18', '2026-09-22 03:18:32'),
(5, 'Gasto', 'Servicios', 'Internet', 'Pago mensual', 599.00, '2026-09-15', '2026-09-22 03:18:32'),
(6, 'Gasto', 'Entretenimiento', 'Cine', 'Salida de fin de semana', 420.00, '2026-09-13', '2026-09-22 03:18:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presupuesto_mensual`
--

CREATE TABLE `presupuesto_mensual` (
  `id` int(10) UNSIGNED NOT NULL,
  `anio` smallint(5) UNSIGNED NOT NULL,
  `mes` tinyint(3) UNSIGNED NOT NULL,
  `limite` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `presupuesto_mensual`
--

INSERT INTO `presupuesto_mensual` (`id`, `anio`, `mes`, `limite`, `actualizado_en`) VALUES
(1, 2026, 9, 6000.00, '2026-09-22 03:18:32');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fecha` (`fecha`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_categoria` (`categoria`);

--
-- Indices de la tabla `presupuesto_mensual`
--
ALTER TABLE `presupuesto_mensual`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_anio_mes` (`anio`,`mes`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `presupuesto_mensual`
--
ALTER TABLE `presupuesto_mensual`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
