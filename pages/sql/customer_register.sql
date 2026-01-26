-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2026 at 06:24 AM
-- Server version: 10.1.30-MariaDB
-- PHP Version: 5.6.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cdd`
--

-- --------------------------------------------------------

--
-- Structure for view `customer_register`
--

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_register`  AS  select `customer`.`id` AS `id`,concat(lpad(`customer`.`customer_source`,2,'0'),'-',lpad(`customer`.`customer_series`,5,'0')) AS `customer_code`,`customer`.`firstName` AS `firstName`,`customer`.`middleName` AS `middleName`,`customer`.`lastName` AS `lastName`,`customer`.`address` AS `address`,`customer`.`storeName` AS `storeName`,`customer`.`type` AS `type`,`customer`.`area` AS `area`,`customer`.`sales_channel` AS `sales_channel`,`customer`.`customer_series` AS `customer_series`,`customer`.`customer_source` AS `customer_source` from `customer` ;

--
-- VIEW  `customer_register`
-- Data: None
--

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
