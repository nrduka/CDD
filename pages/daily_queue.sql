-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 02:46 PM
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
-- Table structure for table `daily_queue`
--

CREATE TABLE `daily_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `category` varchar(20) NOT NULL,
  `queue_number` int(10) UNSIGNED NOT NULL,
  `unique_code` varchar(16) NOT NULL,
  `queue_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `daily_queue`
--

INSERT INTO `daily_queue` (`id`, `category`, `queue_number`, `unique_code`, `queue_date`, `created_at`) VALUES
(1, 'SENIORS', 1, '9A9QM7TW', '2025-10-15', '2025-10-15 19:38:02'),
(2, 'SENIORS', 2, 'PWPHRH2Q', '2025-10-15', '2025-10-15 19:40:15'),
(3, 'SENIORS', 3, 'ECCKTPEU', '2025-10-15', '2025-10-15 19:40:34'),
(4, 'SENIORS', 4, 'SL7SBYAF', '2025-10-15', '2025-10-15 19:44:49'),
(5, 'SENIORS', 5, 'QY2QUXZP', '2025-10-15', '2025-10-15 19:45:03'),
(6, 'OPD', 1, '7YPVCMUC', '2025-10-15', '2025-10-15 19:46:47'),
(7, 'SENIORS', 6, 'F5ZFFCZ3', '2025-10-15', '2025-10-15 19:48:08'),
(8, 'OPD_HMO', 1, '6SQLE6XS', '2025-10-15', '2025-10-15 20:04:24'),
(9, 'OPD_HMO', 2, 'RP687ALU', '2025-10-15', '2025-10-15 20:13:20'),
(10, 'OPD_HMO', 3, 'GVNWM4MJ', '2025-10-15', '2025-10-15 20:13:30'),
(11, 'OPD_HMO', 4, 'KHQ98DXY', '2025-10-15', '2025-10-15 20:13:38'),
(12, 'OPD_HMO', 5, '6RL6H886', '2025-10-15', '2025-10-15 20:14:29'),
(13, 'OPD', 2, '2SUDPCXE', '2025-10-15', '2025-10-15 20:14:32'),
(14, 'SENIORS', 7, 'VJYN5DGX', '2025-10-15', '2025-10-15 20:45:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `daily_queue`
--
ALTER TABLE `daily_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_date_cat_num` (`queue_date`,`category`,`queue_number`),
  ADD UNIQUE KEY `uq_unique_code` (`unique_code`),
  ADD KEY `idx_date_cat` (`queue_date`,`category`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `daily_queue`
--
ALTER TABLE `daily_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
