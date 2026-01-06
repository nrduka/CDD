-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2025 at 04:13 PM
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
-- Database: `smhc`
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
(1, 'SENIORS', 1, '9A9QM7TW', '2025-10-15', '2025-10-15 13:02:45'),
(2, 'OPD', 1, '7YPVCMUC', '2025-10-15', '2025-10-15 13:02:45'),
(3, 'OPD_HMO', 1, '6SQLE6XS', '2025-10-15', '2025-10-15 13:02:45'),
(4, 'SENIORS', 2, 'AQK6JFH2', '2025-10-15', '2025-10-15 21:02:54'),
(5, 'SENIORS', 3, 'LXZ4HXTC', '2025-10-15', '2025-10-15 21:37:09');

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
(1, 1, 2, '1', 'DONE', '2025-10-15 15:18:17', '2025-10-15 15:21:55'),
(2, 1, 2, '1', 'DONE', '2025-10-15 15:44:56', '2025-10-15 15:52:40'),
(3, 2, 2, '1', 'ACTIVE', '2025-10-15 16:04:36', NULL);

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
(2, 'counter_attendant1', 'admin123', 'COUNTER', 'Counter', 'Attendant 1', '2025-10-15 13:02:45');

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
-- Indexes for table `daily_queue`
--
ALTER TABLE `daily_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_date_cat_num` (`queue_date`,`category`,`queue_number`),
  ADD UNIQUE KEY `uq_unique_code` (`unique_code`),
  ADD KEY `idx_date_cat` (`queue_date`,`category`);

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
-- AUTO_INCREMENT for table `daily_queue`
--
ALTER TABLE `daily_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `queue_calls`
--
ALTER TABLE `queue_calls`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `counter_attendant`
--
ALTER TABLE `counter_attendant`
  ADD CONSTRAINT `fk_counter_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `queue_calls`
--
ALTER TABLE `queue_calls`
  ADD CONSTRAINT `fk_queue_calls_queue` FOREIGN KEY (`queue_id`) REFERENCES `daily_queue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_calls_user` FOREIGN KEY (`counter_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
