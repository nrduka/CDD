-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2025 at 03:45 AM
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
-- Table structure for table `registration_queue`
--

CREATE TABLE `registration_queue` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_queue_id` int(10) UNSIGNED NOT NULL,
  `queue_id` int(10) UNSIGNED NOT NULL,
  `queue_call_id` int(10) UNSIGNED DEFAULT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `registration_user_id` int(10) UNSIGNED NOT NULL,
  `registration_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('WAITING','CALLED','DONE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WAITING',
  `routed_by` int(10) UNSIGNED DEFAULT NULL,
  `routed_at` datetime NOT NULL,
  `called_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `registration_queue`
--
ALTER TABLE `registration_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_counter_status` (`registration_user_id`,`status`),
  ADD KEY `idx_queue_status` (`queue_id`,`status`),
  ADD KEY `idx_department_status` (`department_id`,`status`),
  ADD KEY `fk_counter_queue_department_queue` (`department_queue_id`),
  ADD KEY `fk_counter_queue_call` (`queue_call_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `registration_queue`
--
ALTER TABLE `registration_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
