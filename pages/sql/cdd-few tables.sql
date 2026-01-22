-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 22, 2026 at 02:07 AM
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
-- Table structure for table `ice_machine`
--

CREATE TABLE `ice_machine` (
  `id` int(11) NOT NULL,
  `machine_type` int(11) NOT NULL,
  `machine_description` varchar(100) NOT NULL,
  `datecreated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `ice_machine`
--

INSERT INTO `ice_machine` (`id`, `machine_type`, `machine_description`, `datecreated`) VALUES
(1, 1, 'PLATE MACHINE _ TON', '2026-01-21 20:58:58');

-- --------------------------------------------------------

--
-- Table structure for table `ice_production`
--

CREATE TABLE `ice_production` (
  `id` int(11) NOT NULL,
  `date_production` date NOT NULL,
  `production_code` varchar(50) NOT NULL,
  `machine` tinyint(1) NOT NULL DEFAULT '0',
  `operator_name` varchar(100) NOT NULL,
  `assistant1_name` varchar(100) DEFAULT NULL,
  `assistant2_name` varchar(100) DEFAULT NULL,
  `drop_no` int(11) NOT NULL,
  `time_dropped` time DEFAULT NULL,
  `finished_ice_qty` int(11) NOT NULL DEFAULT '0',
  `loose_ice_qty` decimal(10,2) NOT NULL DEFAULT '0.00',
  `remarks` varchar(255) DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `umo` varchar(100) NOT NULL,
  `umu` varchar(100) NOT NULL,
  `product_type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `ice_product_type`
--

CREATE TABLE `ice_product_type` (
  `id` int(11) NOT NULL,
  `product_type_code` int(11) NOT NULL,
  `umo_per` int(11) NOT NULL,
  `umo` varchar(100) NOT NULL,
  `umd_per` int(11) NOT NULL,
  `umd` varchar(100) NOT NULL,
  `umi_per` int(11) NOT NULL,
  `umi` varchar(100) NOT NULL,
  `umu_per` int(11) NOT NULL,
  `umu` varchar(100) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `product_type` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `ice_product_type`
--

INSERT INTO `ice_product_type` (`id`, `product_type_code`, `umo_per`, `umo`, `umd_per`, `umd`, `umi_per`, `umi`, `umu_per`, `umu`, `date_created`, `product_type`) VALUES
(1, 1, 1, 'SACK', 1, 'SACK', 1, 'SACK', 40, 'KG', '2026-01-21 20:30:24', 'PLATE'),
(2, 2, 1, 'SACK', 1, 'SACK', 1, 'SACK', 40, 'KG', '2026-01-21 20:30:24', 'CUBE'),
(3, 3, 1, 'SACK', 1, 'SACK', 1, 'SACK', 40, 'KG', '2026-01-21 20:30:24', ''),
(4, 4, 1, 'PACK', 1, 'PACK', 1, 'PACK', 2, 'KG', '2026-01-21 20:30:24', 'REPACKED'),
(5, 5, 1, 'SACK', 1, 'SACK', 1, 'SACK', 40, 'KG', '2026-01-21 20:30:24', 'CRUSHED');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ice_machine`
--
ALTER TABLE `ice_machine`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ice_production`
--
ALTER TABLE `ice_production`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ice_product_type`
--
ALTER TABLE `ice_product_type`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ice_machine`
--
ALTER TABLE `ice_machine`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ice_production`
--
ALTER TABLE `ice_production`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ice_product_type`
--
ALTER TABLE `ice_product_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
