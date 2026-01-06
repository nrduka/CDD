-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2025 at 02:57 PM
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
-- Table structure for table `counter_attendant`
--

CREATE TABLE `counter_attendant` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_counter` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counter_attendant`
--

INSERT INTO `counter_attendant` (`id`, `user_id`, `username`, `assigned_counter`, `date_created`) VALUES
(1, 2, 'counter_attendant1', '1', '2025-10-15 13:02:45');

-- --------------------------------------------------------

--
-- Table structure for table `counter_queue`
--

CREATE TABLE `counter_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_queue_id` int(10) UNSIGNED NOT NULL,
  `queue_id` int(10) UNSIGNED NOT NULL,
  `queue_call_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `counter_user_id` int(10) UNSIGNED NOT NULL,
  `counter_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('WAITING','CALLED','DONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WAITING',
  `routed_by` int(10) UNSIGNED DEFAULT NULL,
  `routed_at` datetime NOT NULL,
  `called_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `counter_queue`
--

INSERT INTO `counter_queue` (`id`, `department_queue_id`, `queue_id`, `queue_call_id`, `department_id`, `counter_user_id`, `counter_number`, `status`, `routed_by`, `routed_at`, `called_at`, `completed_at`) VALUES
(1, 12, 8, 8, 1, 2, '1', 'CALLED', 2, '2025-10-27 14:55:13', '2025-10-27 14:55:24', NULL),
(2, 15, 9, 10, 5, 2, '1', 'DONE', 2, '2025-10-28 00:22:18', '2025-10-28 00:22:24', '2025-10-28 20:09:07');

-- --------------------------------------------------------

--
-- Table structure for table `daily_queue`
--

CREATE TABLE `daily_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `category` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_number` int(10) UNSIGNED NOT NULL,
  `unique_code` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_queue`
--

INSERT INTO `daily_queue` (`id`, `category`, `queue_number`, `unique_code`, `queue_date`, `created_at`) VALUES
(1, 'P', 1, 'GGW251028P01', '2025-10-28', '2025-10-28 20:55:41'),
(2, 'P', 2, 'RFN251028P02', '2025-10-28', '2025-10-28 21:04:44'),
(3, 'P', 3, 'ADH251028P03', '2025-10-28', '2025-10-28 21:43:27'),
(4, 'P', 4, 'A79251028P04', '2025-10-28', '2025-10-28 21:43:41'),
(5, 'P', 5, 'EU8251028P05', '2025-10-28', '2025-10-28 21:51:30'),
(6, 'P', 6, 'RWU251028P06', '2025-10-28', '2025-10-28 21:51:40'),
(7, 'P', 7, 'LST251028P07', '2025-10-28', '2025-10-28 21:54:44');

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dept_description` text COLLATE utf8mb4_unicode_ci,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`id`, `department_code`, `department`, `dept_description`, `date_created`) VALUES
(1, 'EXT', 'Extraction Department', 'Handles extraction procedures and preparation.', '2025-10-22 14:13:12'),
(2, 'RAD', 'Radiology Department', 'Performs diagnostic imaging and radiology services.', '2025-10-22 14:13:12'),
(3, 'XRAY', 'X-ray Department', 'Dedicated to X-ray imaging requests.', '2025-10-22 14:13:12'),
(4, 'CON1', 'CONSULTATION ROOM 1', 'Dedicated to CONSULTATION 1', '2025-10-22 14:13:12'),
(5, 'CON2', 'CONSULTATION ROOM 2', 'Dedicated to CONSULTATION 2', '2025-10-22 14:13:12'),
(6, 'OR1', 'OPERATING ROOM 1', 'Dedicated to OPERATING ROOM 1', '2025-10-22 14:13:12'),
(7, 'CASHIER 1', 'CASHIER 1', 'Dedicated to CASHIER 1', '2025-10-22 14:13:12');

-- --------------------------------------------------------

--
-- Table structure for table `department_queue`
--

CREATE TABLE `department_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `queue_call_id` int(10) UNSIGNED NOT NULL,
  `queue_id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `status` enum('WAITING','CALLED','DONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WAITING',
  `routed_by` int(10) UNSIGNED DEFAULT NULL,
  `routed_at` datetime NOT NULL,
  `called_by` int(10) UNSIGNED DEFAULT NULL,
  `called_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_queue`
--

INSERT INTO `department_queue` (`id`, `queue_call_id`, `queue_id`, `department_id`, `status`, `routed_by`, `routed_at`, `called_by`, `called_at`, `completed_at`) VALUES
(1, 11, 1, 4, 'CALLED', 2, '2025-10-28 21:22:11', 2, '2025-10-28 21:48:19', NULL),
(2, 12, 3, 5, 'CALLED', 2, '2025-10-28 21:53:38', 2, '2025-10-28 21:53:41', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `queue_calls`
--

CREATE TABLE `queue_calls` (
  `id` int(10) UNSIGNED NOT NULL,
  `queue_id` int(10) UNSIGNED NOT NULL,
  `counter_user_id` int(10) UNSIGNED NOT NULL,
  `counter_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('ACTIVE','DONE','RETURNED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `called_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_calls`
--

INSERT INTO `queue_calls` (`id`, `queue_id`, `counter_user_id`, `counter_number`, `status`, `called_at`, `completed_at`) VALUES
(1, 1, 2, '1', 'DONE', '2025-10-26 12:57:41', '2025-10-26 12:57:52'),
(2, 2, 2, '1', 'DONE', '2025-10-22 15:25:56', '2025-10-26 12:57:54'),
(3, 4, 2, '1', 'ACTIVE', '2025-10-28 21:54:00', NULL),
(4, 5, 2, '1', 'ACTIVE', '2025-10-27 13:38:38', NULL),
(5, 6, 2, '1', 'ACTIVE', '2025-10-27 13:39:11', NULL),
(6, 7, 2, '1', 'ACTIVE', '2025-10-27 15:01:14', NULL),
(7, 8, 2, '1', 'DONE', '2025-10-27 14:29:31', '2025-10-27 14:55:13'),
(8, 8, 2, '1', 'ACTIVE', '2025-10-27 14:55:24', NULL),
(9, 9, 2, '1', 'DONE', '2025-10-28 00:21:19', '2025-10-28 00:22:18'),
(10, 9, 2, '1', 'DONE', '2025-10-28 00:22:26', '2025-10-28 20:09:07'),
(11, 1, 2, '1', 'ACTIVE', '2025-10-28 21:21:45', NULL),
(12, 3, 2, '1', 'ACTIVE', '2025-10-28 21:52:22', NULL),
(13, 2, 2, '1', 'ACTIVE', '2025-10-28 21:22:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nonadmin',
  `firstname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lastname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `password`, `role`, `firstname`, `lastname`, `created_at`) VALUES
(1, 'admin', 'admin123', 'Admin', 'System', 'Administrator', '2025-10-15 13:02:45'),
(2, 'counter_attendant1', 'admin123', 'COUNTER', 'Counter', 'Attendant 1', '2025-10-15 13:02:45'),
(3, 'extraction', 'admin123', 'Extraction Department', 'Counter', 'Attendant 1', '2025-10-15 13:02:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `counter_attendant`
--
ALTER TABLE `counter_attendant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_counter_user` (`user_id`),
  ADD UNIQUE KEY `uq_counter_username` (`username`);

--
-- Indexes for table `counter_queue`
--
ALTER TABLE `counter_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_counter_status` (`counter_user_id`,`status`),
  ADD KEY `idx_queue_status` (`queue_id`,`status`),
  ADD KEY `idx_department_status` (`department_id`,`status`),
  ADD KEY `fk_counter_queue_department_queue` (`department_queue_id`),
  ADD KEY `fk_counter_queue_call` (`queue_call_id`);

--
-- Indexes for table `daily_queue`
--
ALTER TABLE `daily_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_date_cat_num` (`queue_date`,`category`,`queue_number`),
  ADD UNIQUE KEY `uq_unique_code` (`unique_code`),
  ADD KEY `idx_date_cat` (`queue_date`,`category`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_department_code` (`department_code`);

--
-- Indexes for table `department_queue`
--
ALTER TABLE `department_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_department_queue_call` (`queue_call_id`,`department_id`),
  ADD KEY `idx_department_queue_status` (`department_id`,`status`),
  ADD KEY `idx_department_queue_call` (`queue_call_id`);

--
-- Indexes for table `queue_calls`
--
ALTER TABLE `queue_calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_status` (`queue_id`,`status`),
  ADD KEY `idx_counter_status` (`counter_user_id`,`status`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `counter_attendant`
--
ALTER TABLE `counter_attendant`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `counter_queue`
--
ALTER TABLE `counter_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `daily_queue`
--
ALTER TABLE `daily_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `department_queue`
--
ALTER TABLE `department_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `queue_calls`
--
ALTER TABLE `queue_calls`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `counter_attendant`
--
ALTER TABLE `counter_attendant`
  ADD CONSTRAINT `fk_counter_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `counter_queue`
--
ALTER TABLE `counter_queue`
  ADD CONSTRAINT `fk_counter_queue_call` FOREIGN KEY (`queue_call_id`) REFERENCES `queue_calls` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_counter_queue_counter` FOREIGN KEY (`counter_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_counter_queue_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_counter_queue_department_queue` FOREIGN KEY (`department_queue_id`) REFERENCES `department_queue` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_counter_queue_queue` FOREIGN KEY (`queue_id`) REFERENCES `daily_queue` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `department_queue`
--
ALTER TABLE `department_queue`
  ADD CONSTRAINT `fk_department_queue_call` FOREIGN KEY (`queue_call_id`) REFERENCES `queue_calls` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_department_queue_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
