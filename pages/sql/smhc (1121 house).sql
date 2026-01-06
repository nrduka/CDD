-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 20, 2025 at 05:12 PM
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
(1, 2, 'counter_attendant1', '1', '2025-10-15 13:02:45'),
(2, 3, 'counter_attendant2', '2', '2025-10-15 13:02:45');

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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `retrieval_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_status` int(11) NOT NULL,
  `date_paid` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_queue`
--

INSERT INTO `daily_queue` (`id`, `category`, `queue_number`, `unique_code`, `queue_date`, `created_at`, `retrieval_code`, `payment_status`, `date_paid`) VALUES
(1, 'P', 1, 'YTC251104P01', '2025-11-04', '2025-11-04 13:08:35', '', 0, '2025-11-21 00:05:47'),
(2, 'P', 2, '96K251104P02', '2025-11-04', '2025-11-04 19:07:17', '', 0, '2025-11-21 00:05:47'),
(3, 'P', 3, 'RGP251104P03', '2025-11-04', '2025-11-04 20:19:07', '', 0, '2025-11-21 00:05:47'),
(4, 'P', 4, '6UW251104P04', '2025-11-04', '2025-11-04 20:19:09', '', 0, '2025-11-21 00:05:47'),
(5, 'P', 1, 'KVE251113P01', '2025-11-13', '2025-11-13 19:56:09', '31DG2FNHM4M', 0, '2025-11-21 00:05:47'),
(6, 'P', 2, '6LP251113P02', '2025-11-13', '2025-11-13 20:01:34', '', 0, '2025-11-21 00:05:47'),
(7, 'P', 3, 'FWJ251113P03', '2025-11-13', '2025-11-13 21:38:50', '31DG2FNHM4M', 0, '2025-11-21 00:05:47'),
(8, 'P', 4, '5Z5251113P04', '2025-11-13', '2025-11-13 22:02:57', '31DG6QVZI4Y', 0, '2025-11-21 00:05:47'),
(9, 'P', 5, 'NAP251113P05', '2025-11-13', '2025-11-13 22:08:29', '', 0, '2025-11-21 00:05:47'),
(10, 'P', 6, 'MKB251113P06', '2025-11-13', '2025-11-13 22:45:50', '31DG6QVZI4Y', 0, '2025-11-21 00:05:47'),
(11, 'P', 7, 'PCR251113P07', '2025-11-13', '2025-11-13 23:16:43', '', 0, '2025-11-21 00:05:47'),
(12, 'R', 1, 'VVX251113R01', '2025-11-13', '2025-11-13 23:16:46', '', 0, '2025-11-21 00:05:47'),
(13, 'P', 1, 'P46251120P01', '2025-11-20', '2025-11-20 21:07:14', '31DG6QVZI4Y', 0, '2025-11-21 00:05:47'),
(14, 'P', 2, 'NDJ251120P02', '2025-11-20', '2025-11-20 21:42:17', '31DG6QVZI4Y', 0, '2025-11-21 00:05:47'),
(15, 'P', 3, 'PRF251120P03', '2025-11-20', '2025-11-20 22:10:03', '31DG50G6KZD', 0, '2025-11-21 00:05:47'),
(16, 'P', 4, 'PPV251120P04', '2025-11-20', '2025-11-20 23:11:09', '31DFZ3TXT4R', 0, '2025-11-21 00:05:47'),
(17, 'P', 5, 'WSV251120P05', '2025-11-20', '2025-11-20 23:16:20', '31DIACRYZFW', 0, '2025-11-21 00:05:47'),
(18, 'P', 1, '9JE251121P01', '2025-11-21', '2025-11-21 00:02:20', '31DG6QVZI4Y', 1, '2025-11-21 00:05:47'),
(19, 'P', 2, '9QZ251121P02', '2025-11-21', '2025-11-21 00:07:20', '31DG50G6KZD', 1, '2025-11-21 00:08:51');

-- --------------------------------------------------------

--
-- Table structure for table `daily_sales`
--

CREATE TABLE `daily_sales` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) NOT NULL,
  `retrieval_code` varchar(100) NOT NULL,
  `item_code` varchar(100) NOT NULL,
  `lineitem` int(11) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `amount` int(11) NOT NULL,
  `payment_status` int(11) NOT NULL,
  `date_paid` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `daily_sales`
--

INSERT INTO `daily_sales` (`id`, `queue_id`, `retrieval_code`, `item_code`, `lineitem`, `date_created`, `amount`, `payment_status`, `date_paid`) VALUES
(1, 13, '31DG6QVZI4Y', '71', 1, '2025-11-20 21:41:55', 650, 0, '2025-11-21 00:05:29'),
(2, 14, '31DG6QVZI4Y', '14', 1, '2025-11-20 22:09:15', 1800, 0, '2025-11-21 00:05:29'),
(3, 14, '31DG6QVZI4Y', '10', 2, '2025-11-20 22:09:15', 1000, 0, '2025-11-21 00:05:29'),
(4, 15, '31DG50G6KZD', '1', 1, '2025-11-20 22:57:45', 250, 0, '2025-11-21 00:05:29'),
(5, 15, '31DG50G6KZD', '100', 2, '2025-11-20 22:57:45', 600, 0, '2025-11-21 00:05:29'),
(6, 16, '31DFZ3TXT4R', '10', 1, '2025-11-20 23:12:07', 1000, 0, '2025-11-21 00:05:29'),
(7, 16, '31DFZ3TXT4R', '100', 2, '2025-11-20 23:12:07', 600, 0, '2025-11-21 00:05:29'),
(8, 16, '31DFZ3TXT4R', '72', 3, '2025-11-20 23:12:07', 3000, 0, '2025-11-21 00:05:29'),
(9, 17, '31DIACRYZFW', '1', 1, '2025-11-20 23:17:09', 250, 0, '2025-11-21 00:05:29'),
(10, 17, '31DIACRYZFW', '119', 2, '2025-11-20 23:17:09', 695, 0, '2025-11-21 00:05:29'),
(11, 17, '31DIACRYZFW', '2', 3, '2025-11-20 23:17:09', 250, 0, '2025-11-21 00:05:29'),
(12, 18, '31DG6QVZI4Y', '1', 1, '2025-11-21 00:02:46', 250, 1, '2025-11-21 00:05:29'),
(13, 18, '31DG6QVZI4Y', '102', 2, '2025-11-21 00:02:46', 245, 1, '2025-11-21 00:05:29'),
(14, 18, '31DG6QVZI4Y', '11', 3, '2025-11-21 00:02:46', 1200, 1, '2025-11-21 00:05:29'),
(15, 19, '31DG50G6KZD', '1', 1, '2025-11-21 00:07:49', 250, 1, '2025-11-21 00:08:51'),
(16, 19, '31DG50G6KZD', '100', 2, '2025-11-21 00:07:49', 600, 1, '2025-11-21 00:08:51'),
(17, 19, '31DG50G6KZD', '111', 3, '2025-11-21 00:07:49', 595, 1, '2025-11-21 00:08:51');

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
(1, 'LABORATORY', 'LABORATORY', 'Handles extraction procedures and LABORATORY.', '2025-10-22 14:13:12'),
(2, 'RAD', 'ULTRASOUND ROOM', 'Performs diagnostic imaging and radiology services.', '2025-10-22 14:13:12'),
(3, 'X-RAY', 'X-RAY', 'Dedicated to X-ray imaging requests.', '2025-10-22 14:13:12'),
(4, 'CONSULTATION 1', 'CONSULTATION ROOM 1', 'Dedicated to CONSULTATION', '2025-10-22 14:13:12'),
(5, 'CONSULTATION 2', 'CONSULTATION ROOM 2', 'Dedicated to CONSULTATION 2', '2025-10-22 14:13:12'),
(6, 'CASHIER', 'CASHIER', 'CASHIER', '2025-10-22 14:13:12');

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
  `is_history` tinyint(1) NOT NULL DEFAULT '0',
  `log_action` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_department_queue_id` int(10) UNSIGNED DEFAULT NULL,
  `routed_by` int(10) UNSIGNED DEFAULT NULL,
  `routed_at` datetime NOT NULL,
  `called_by` int(10) UNSIGNED DEFAULT NULL,
  `called_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `retrieval_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_queue`
--

INSERT INTO `department_queue` (`id`, `queue_call_id`, `queue_id`, `department_id`, `status`, `is_history`, `log_action`, `source_department_queue_id`, `routed_by`, `routed_at`, `called_by`, `called_at`, `completed_at`, `retrieval_code`) VALUES
(2, 1, 1, 1, 'DONE', 0, NULL, NULL, 2, '2025-11-04 13:26:58', 2, '2025-11-04 13:27:19', '2025-11-04 13:27:42', ''),
(3, 1, 1, 3, 'DONE', 0, NULL, NULL, 2, '2025-11-04 13:27:42', 2, '2025-11-04 15:05:31', '2025-11-04 16:49:04', ''),
(4, 3, 2, 6, 'DONE', 0, NULL, NULL, 2, '2025-11-04 20:19:19', 4, '2025-11-04 20:20:02', '2025-11-04 20:20:14', ''),
(5, 2, 1, 6, 'DONE', 0, NULL, NULL, 2, '2025-11-04 20:55:10', 2, '2025-11-04 21:10:39', '2025-11-04 21:10:51', ''),
(6, 4, 3, 6, 'WAITING', 0, NULL, NULL, 2, '2025-11-04 21:10:36', NULL, NULL, NULL, ''),
(7, 2, 1, 1, 'CALLED', 0, NULL, NULL, 2, '2025-11-04 21:10:51', 2, '2025-11-04 21:11:10', NULL, ''),
(8, 2, 1, 2, 'CALLED', 0, NULL, NULL, 2, '2025-11-04 21:10:51', 2, '2025-11-04 21:11:24', NULL, ''),
(9, 2, 1, 3, 'CALLED', 0, NULL, NULL, 2, '2025-11-04 21:10:51', 2, '2025-11-04 21:11:37', NULL, ''),
(10, 6, 6, 6, 'DONE', 0, NULL, NULL, 2, '2025-11-13 20:27:05', 2, '2025-11-13 20:55:47', '2025-11-13 21:00:08', ''),
(11, 6, 6, 1, 'DONE', 0, NULL, NULL, 2, '2025-11-13 21:00:08', 2, '2025-11-13 21:00:11', '2025-11-13 21:00:34', ''),
(12, 6, 6, 2, 'DONE', 0, NULL, NULL, 2, '2025-11-13 21:00:08', 5, '2025-11-13 21:36:12', '2025-11-13 21:36:29', ''),
(13, 6, 6, 3, 'CALLED', 0, NULL, NULL, 2, '2025-11-13 21:00:08', 5, '2025-11-13 21:37:46', NULL, ''),
(14, 6, 6, 2, 'CALLED', 1, 'CALL', 12, 2, '2025-11-13 21:00:08', 5, '2025-11-13 21:35:54', NULL, ''),
(15, 6, 6, 2, 'CALLED', 1, 'CALL', 12, 2, '2025-11-13 21:00:08', 5, '2025-11-13 21:36:12', NULL, ''),
(16, 6, 6, 2, 'DONE', 1, 'COMPLETE_FINISH', 12, 2, '2025-11-13 21:00:08', 5, '2025-11-13 21:36:12', '2025-11-13 21:36:29', ''),
(17, 6, 6, 3, 'CALLED', 1, 'CALL', 13, 2, '2025-11-13 21:00:08', 5, '2025-11-13 21:37:46', NULL, ''),
(18, 5, 5, 6, 'DONE', 0, NULL, NULL, 2, '2025-11-13 21:39:06', 4, '2025-11-13 22:46:57', '2025-11-13 22:47:15', ''),
(19, 5, 5, 6, 'CALLED', 1, 'CALL', 18, 2, '2025-11-13 21:39:06', 2, '2025-11-13 21:39:37', NULL, ''),
(20, 7, 7, 6, 'WAITING', 0, NULL, NULL, 2, '2025-11-13 22:05:41', NULL, NULL, NULL, ''),
(21, 5, 5, 6, 'CALLED', 1, 'CALL', 18, 2, '2025-11-13 21:39:06', 4, '2025-11-13 22:06:20', NULL, ''),
(22, 5, 5, 6, 'CALLED', 1, 'CALL', 18, 2, '2025-11-13 21:39:06', 4, '2025-11-13 22:07:52', NULL, ''),
(23, 9, 9, 6, 'WAITING', 0, NULL, NULL, 1, '2025-11-13 22:09:20', NULL, NULL, NULL, ''),
(24, 8, 8, 6, 'WAITING', 0, NULL, NULL, 2, '2025-11-13 22:10:01', NULL, NULL, NULL, ''),
(25, 10, 10, 6, 'WAITING', 0, NULL, NULL, 2, '2025-11-13 22:46:35', NULL, NULL, NULL, ''),
(26, 5, 5, 6, 'CALLED', 1, 'CALL', 18, 2, '2025-11-13 21:39:06', 4, '2025-11-13 22:46:57', NULL, ''),
(27, 5, 5, 6, 'DONE', 1, 'COMPLETE_ROUTE', 18, 2, '2025-11-13 21:39:06', 4, '2025-11-13 22:46:57', '2025-11-13 22:47:15', ''),
(28, 5, 5, 1, 'WAITING', 0, NULL, NULL, 4, '2025-11-13 22:47:15', NULL, NULL, NULL, ''),
(29, 5, 5, 2, 'WAITING', 0, NULL, NULL, 4, '2025-11-13 22:47:15', NULL, NULL, NULL, ''),
(30, 5, 5, 3, 'WAITING', 0, NULL, NULL, 4, '2025-11-13 22:47:15', NULL, NULL, NULL, ''),
(31, 11, 13, 6, 'CALLED', 0, NULL, NULL, 1, '2025-11-20 21:41:55', 1, '2025-11-20 23:35:40', NULL, ''),
(32, 12, 14, 1, 'CALLED', 0, NULL, NULL, 1, '2025-11-20 22:09:15', 1, '2025-11-20 23:35:55', NULL, ''),
(33, 12, 14, 6, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 22:09:15', NULL, NULL, NULL, ''),
(34, 13, 15, 1, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 22:57:45', NULL, NULL, NULL, ''),
(35, 13, 15, 2, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 22:57:45', NULL, NULL, NULL, ''),
(36, 13, 15, 6, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 22:57:45', NULL, NULL, NULL, ''),
(37, 14, 16, 1, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:12:07', NULL, NULL, NULL, ''),
(38, 14, 16, 3, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:12:07', NULL, NULL, NULL, ''),
(39, 14, 16, 2, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:12:07', NULL, NULL, NULL, ''),
(40, 14, 16, 6, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:12:07', NULL, NULL, NULL, ''),
(41, 15, 17, 1, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:17:09', NULL, NULL, NULL, ''),
(42, 15, 17, 3, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:17:09', NULL, NULL, NULL, ''),
(43, 15, 17, 6, 'WAITING', 0, NULL, NULL, 1, '2025-11-20 23:17:09', NULL, NULL, NULL, ''),
(44, 11, 13, 6, 'CALLED', 1, 'CALL', 31, 1, '2025-11-20 21:41:55', 1, '2025-11-20 23:19:11', NULL, ''),
(45, 11, 13, 6, 'CALLED', 1, 'CALL', 31, 1, '2025-11-20 21:41:55', 1, '2025-11-20 23:35:40', NULL, ''),
(46, 12, 14, 1, 'CALLED', 1, 'CALL', 32, 1, '2025-11-20 22:09:15', 1, '2025-11-20 23:35:55', NULL, ''),
(47, 16, 18, 1, 'CALLED', 0, NULL, NULL, 1, '2025-11-21 00:02:46', 1, '2025-11-21 00:03:10', NULL, ''),
(48, 16, 18, 3, 'CALLED', 0, NULL, NULL, 1, '2025-11-21 00:02:46', 1, '2025-11-21 00:08:55', NULL, ''),
(49, 16, 18, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-21 00:02:46', NULL, '2025-11-21 00:03:07', '2025-11-21 00:03:07', ''),
(50, 16, 18, 6, 'DONE', 1, 'PAYMENT', 49, 1, '2025-11-21 00:02:46', NULL, '2025-11-21 00:03:07', '2025-11-21 00:03:07', ''),
(51, 16, 18, 1, 'CALLED', 1, 'CALL', 47, 1, '2025-11-21 00:02:46', 1, '2025-11-21 00:03:10', NULL, ''),
(52, 17, 19, 1, 'WAITING', 0, NULL, NULL, 1, '2025-11-21 00:07:49', NULL, NULL, NULL, ''),
(53, 17, 19, 3, 'CALLED', 0, NULL, NULL, 1, '2025-11-21 00:07:49', 1, '2025-11-21 00:08:58', NULL, ''),
(54, 17, 19, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-21 00:07:49', 1, '2025-11-21 00:08:17', '2025-11-21 00:08:51', ''),
(55, 17, 19, 6, 'CALLED', 1, 'CALL', 54, 1, '2025-11-21 00:07:49', 1, '2025-11-21 00:08:17', NULL, ''),
(56, 17, 19, 6, 'DONE', 1, 'PAYMENT', 54, 1, '2025-11-21 00:07:49', 1, '2025-11-21 00:08:17', '2025-11-21 00:08:51', ''),
(57, 16, 18, 3, 'CALLED', 1, 'CALL', 48, 1, '2025-11-21 00:02:46', 1, '2025-11-21 00:08:55', NULL, ''),
(58, 17, 19, 3, 'CALLED', 1, 'CALL', 53, 1, '2025-11-21 00:07:49', 1, '2025-11-21 00:08:58', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `patient_info`
--

CREATE TABLE `patient_info` (
  `id` int(11) NOT NULL,
  `retrieval_code` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `dob` date NOT NULL,
  `sex` varchar(100) NOT NULL,
  `phone_no` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `region` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `occupation` varchar(100) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `patient_info`
--

INSERT INTO `patient_info` (`id`, `retrieval_code`, `firstname`, `middlename`, `dob`, `sex`, `phone_no`, `address`, `region`, `province`, `city`, `barangay`, `occupation`, `date_created`) VALUES
(1, '31DIACRYZFW', 'ELISA', '', '1947-09-23', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 14:41:05'),
(3, '31DIF8SP19G', 'JHENELLA KHYRA', '', '1999-01-12', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(4, '31DIEGD9KO8', 'REX', '', '1973-11-18', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(5, '31DIDDBN1JL', 'RHICA MAY', '', '2002-03-31', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(6, '31DICZJ7XFL', 'MIA JOY', '', '1995-10-09', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(8, 'AXR99ZKYL3I', 'ANDRES', '', '1960-07-12', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(9, 'AXR811AQT8C', 'MARIVIC', '', '1987-12-10', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(10, 'AXR7KL5KHLZ', 'ROSALIE', '', '1974-03-06', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(11, 'AXR5ELG6NV7', 'MEDELINE', '', '1968-10-23', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(12, 'AXR42JZ9ONW', 'RUBEN', '', '1967-01-10', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(13, 'AXR3SP03CR8', 'WILREDO', '', '1968-08-24', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(14, 'AXR2MS157T2', 'ELIZABETH', '', '1963-12-05', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(15, 'AXR1QLFNN8I', 'FLORENCIO', '', '1949-10-27', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(16, '31DG9YCFU3N', 'RODOLFO', '', '1961-11-01', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(17, '31DG88EKNTL', 'JOYLY', '', '1999-10-23', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(18, '31DG7XYMOU3', 'ROSE ANN', '', '2001-06-09', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(19, '31DG6QVZI4Y', 'MELISA JOY', '', '1999-11-19', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(20, '31DG50G6KZD', 'ASHLEY', '', '2007-04-17', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(21, '31DG44KEZ8U', 'RANDY', '', '1995-02-13', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(22, '31DG3HQEA4D', 'EMMANUEL', '', '1963-12-05', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(23, '31DG2FNHM4M', 'MERIAM', '', '1981-04-22', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(24, '31DG1TM8H54', 'SHAINA', '', '2006-10-06', 'FEMALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(25, '31DG049SIK7', 'CHARLES CHAN', '', '2015-02-06', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(26, '31DFZ3TXT4R', 'JULIO', '', '1968-07-15', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05'),
(27, '31DFY3SYD47', 'JULZ BIEN', '', '2015-08-09', 'MALE', '', '', '', '', '', '', '', '2025-10-30 15:02:05');

-- --------------------------------------------------------

--
-- Table structure for table `product_register`
--

CREATE TABLE `product_register` (
  `id` int(11) NOT NULL,
  `item_code` varchar(100) NOT NULL,
  `department_code` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `item_description` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `product_register`
--

INSERT INTO `product_register` (`id`, `item_code`, `department_code`, `department`, `item_description`, `amount`, `date_created`) VALUES
(1, '1', 'LAB', 'LABORATORY', 'FBS/RBS/2HPPBS', '250.00', '2025-11-20 22:07:39'),
(2, '2', 'LAB', 'LABORATORY', 'BUN', '250.00', '2025-11-20 22:07:39'),
(3, '3', 'LAB', 'LABORATORY', 'CREATININE ', '250.00', '2025-11-20 22:07:39'),
(4, '4', 'LAB', 'LABORATORY', 'BUA (URIC ACID)', '300.00', '2025-11-20 22:07:39'),
(5, '5', 'LAB', 'LABORATORY', 'CHOLESTEROL', '250.00', '2025-11-20 22:07:39'),
(6, '6', 'LAB', 'LABORATORY', 'TRIGLYCERIDE', '300.00', '2025-11-20 22:07:39'),
(7, '7', 'LAB', 'LABORATORY', 'LIPID PROFILE ', '800.00', '2025-11-20 22:07:39'),
(8, '8', 'LAB', 'LABORATORY', 'HBA1C', '700.00', '2025-11-20 22:07:39'),
(9, '9', 'LAB', 'LABORATORY', 'OGCT 75G', '800.00', '2025-11-20 22:07:39'),
(10, '12', 'LAB', 'LABORATORY', 'CHEM 9: FBS BUA BUN CREA LIPID PROFILE SGPT', '1400.00', '2025-11-20 22:07:39'),
(11, '13', 'LAB', 'LABORATORY', 'CHEM 10: FBS BUA BUN CREA LIPID PROFILE SGOT SGPT', '1500.00', '2025-11-20 22:07:39'),
(12, '16', 'LAB', 'LABORATORY', 'SGOT (AST)', '250.00', '2025-11-20 22:07:39'),
(13, '17', 'LAB', 'LABORATORY', 'ALT AST', '350.00', '2025-11-20 22:07:39'),
(14, '18', 'LAB', 'LABORATORY', 'CREA BUN', '450.00', '2025-11-20 22:07:39'),
(15, '19', 'LAB', 'LABORATORY', 'URINALYSIS', '100.00', '2025-11-20 22:07:39'),
(16, '20', 'LAB', 'LABORATORY', 'MICRAL TEST', '300.00', '2025-11-20 22:07:39'),
(17, '21', 'LAB', 'LABORATORY', 'PT: URINE', '150.00', '2025-11-20 22:07:39'),
(18, '22', 'LAB', 'LABORATORY', 'FECALYSIS', '100.00', '2025-11-20 22:07:39'),
(19, '23', 'LAB', 'LABORATORY', 'OCCULT BLOOD', '450.00', '2025-11-20 22:07:39'),
(20, '24', 'LAB', 'LABORATORY', 'CBC w/ platelet', '295.00', '2025-11-20 22:07:39'),
(21, '25', 'LAB', 'LABORATORY', 'CT/BT', '150.00', '2025-11-20 22:07:39'),
(22, '26', 'LAB', 'LABORATORY', 'ESR', '150.00', '2025-11-20 22:07:39'),
(23, '27', 'LAB', 'LABORATORY', 'BLOOD TYPING', '200.00', '2025-11-20 22:07:39'),
(24, '28', 'LAB', 'LABORATORY', 'PERIPHERAL SMEAR BLOOD TEST', '550.00', '2025-11-20 22:07:39'),
(25, '29', 'LAB', 'LABORATORY', 'SODIUM (Na)', '300.00', '2025-11-20 22:07:39'),
(26, '30', 'LAB', 'LABORATORY', 'POTASSIUM (K)', '300.00', '2025-11-20 22:07:39'),
(27, '31', 'LAB', 'LABORATORY', 'CHLORIDE (Cl)', '300.00', '2025-11-20 22:07:39'),
(28, '32', 'LAB', 'LABORATORY', 'TOTAL CALCIUM (Ca)', '300.00', '2025-11-20 22:07:39'),
(29, '33', 'LAB', 'LABORATORY', 'IONIZED CALCIUM', '300.00', '2025-11-20 22:07:39'),
(30, '34', 'LAB', 'LABORATORY', 'SODIUM (Na)  POTASSIUM (K)', '550.00', '2025-11-20 22:07:39'),
(31, '35', 'LAB', 'LABORATORY', 'SODIUM (Na)  POTASSIUM (K) CHLORIDE (Cl)', '850.00', '2025-11-20 22:07:39'),
(32, '36', 'LAB', 'LABORATORY', 'SODIUM (Na)  POTASSIUM (K) CHLORIDE (Cl) I.CAL', '950.00', '2025-11-20 22:07:39'),
(33, '37', 'LAB', 'LABORATORY', 'HBsAg(Screening)', '200.00', '2025-11-20 22:07:39'),
(34, '38', 'LAB', 'LABORATORY', 'Anti HAV IgM (Hepa A)', '500.00', '2025-11-20 22:07:39'),
(35, '39', 'LAB', 'LABORATORY', 'TSH  ', '800.00', '2025-11-20 22:07:39'),
(36, '40', 'LAB', 'LABORATORY', 'FT3', '800.00', '2025-11-20 22:07:39'),
(37, '41', 'LAB', 'LABORATORY', 'FT4', '800.00', '2025-11-20 22:07:39'),
(38, '42', 'LAB', 'LABORATORY', 'VDRL/RPR', '250.00', '2025-11-20 22:07:39'),
(39, '43', 'LAB', 'LABORATORY', 'DENGUE DUO', '2500.00', '2025-11-20 22:07:39'),
(40, '44', 'LAB', 'LABORATORY', 'DENGUE NS1', '1800.00', '2025-11-20 22:07:39'),
(41, '45', 'LAB', 'LABORATORY', 'HIV TESTING', '500.00', '2025-11-20 22:07:39'),
(42, '46', 'LAB', 'LABORATORY', 'PSA', '1000.00', '2025-11-20 22:07:39'),
(43, '47', 'RAD', 'ULTRASOUND', 'BREAST DOUBLE', '1260.00', '2025-11-20 22:07:39'),
(44, '48', 'RAD', 'ULTRASOUND', 'BREAST SINGLE', '1105.00', '2025-11-20 22:07:39'),
(45, '49', 'RAD', 'ULTRASOUND', 'BIOPSY UTZ GUIDED', '1810.00', '2025-11-20 22:07:39'),
(46, '50', 'RAD', 'ULTRASOUND', 'BPS', '1110.00', '2025-11-20 22:07:39'),
(47, '51', 'RAD', 'ULTRASOUND', 'BPS (TWIN)', '2110.00', '2025-11-20 22:07:39'),
(48, '52', 'RAD', 'ULTRASOUND', 'CHEST/THORAX (BILATERAL)', '1200.00', '2025-11-20 22:07:39'),
(49, '53', 'RAD', 'ULTRASOUND', 'DOPPLER', '1700.00', '2025-11-20 22:07:39'),
(50, '1', 'LAB', 'LABORATORY', 'FBS/RBS/2HPPBS', '250.00', '2025-11-20 22:08:01'),
(51, '2', 'LAB', 'LABORATORY', 'BUN', '250.00', '2025-11-20 22:08:01'),
(52, '3', 'LAB', 'LABORATORY', 'CREATININE ', '250.00', '2025-11-20 22:08:01'),
(53, '4', 'LAB', 'LABORATORY', 'BUA (URIC ACID)', '300.00', '2025-11-20 22:08:01'),
(54, '5', 'LAB', 'LABORATORY', 'CHOLESTEROL', '250.00', '2025-11-20 22:08:01'),
(55, '6', 'LAB', 'LABORATORY', 'TRIGLYCERIDE', '300.00', '2025-11-20 22:08:01'),
(56, '7', 'LAB', 'LABORATORY', 'LIPID PROFILE ', '800.00', '2025-11-20 22:08:01'),
(57, '8', 'LAB', 'LABORATORY', 'HBA1C', '700.00', '2025-11-20 22:08:01'),
(58, '9', 'LAB', 'LABORATORY', 'OGCT 75G', '800.00', '2025-11-20 22:08:01'),
(59, '10', 'LAB', 'LABORATORY', 'CHEM 6: FBS, BUA, BUN, CREA, TOTAL CHOLESTEROL, \r\nTRIGLYCERIDES', '1000.00', '2025-11-20 22:08:01'),
(60, '11', 'LAB', 'LABORATORY', 'CHEM 8: FBS BUA CREA BUN LIPID PROFILE', '1200.00', '2025-11-20 22:08:01'),
(61, '12', 'LAB', 'LABORATORY', 'CHEM 9: FBS BUA BUN CREA LIPID PROFILE SGPT', '1400.00', '2025-11-20 22:08:01'),
(62, '13', 'LAB', 'LABORATORY', 'CHEM 10: FBS BUA BUN CREA LIPID PROFILE SGOT SGPT', '1500.00', '2025-11-20 22:08:01'),
(63, '14', 'LAB', 'LABORATORY', 'CHEM12: FBS BUA BUN CREA LIPID PROFILE SGOT SGPT\r\nNA K', '1800.00', '2025-11-20 22:08:01'),
(64, '15', 'LAB', 'LABORATORY', 'SGPT (ALT)', '250.00', '2025-11-20 22:08:01'),
(65, '16', 'LAB', 'LABORATORY', 'SGOT (AST)', '250.00', '2025-11-20 22:08:01'),
(66, '17', 'LAB', 'LABORATORY', 'ALT AST', '350.00', '2025-11-20 22:08:01'),
(67, '18', 'LAB', 'LABORATORY', 'CREA BUN', '450.00', '2025-11-20 22:08:01'),
(68, '19', 'LAB', 'LABORATORY', 'URINALYSIS', '100.00', '2025-11-20 22:08:01'),
(69, '20', 'LAB', 'LABORATORY', 'MICRAL TEST', '300.00', '2025-11-20 22:08:01'),
(70, '21', 'LAB', 'LABORATORY', 'PT: URINE', '150.00', '2025-11-20 22:08:01'),
(71, '22', 'LAB', 'LABORATORY', 'FECALYSIS', '100.00', '2025-11-20 22:08:01'),
(72, '23', 'LAB', 'LABORATORY', 'OCCULT BLOOD', '450.00', '2025-11-20 22:08:01'),
(73, '24', 'LAB', 'LABORATORY', 'CBC w/ platelet', '295.00', '2025-11-20 22:08:01'),
(74, '25', 'LAB', 'LABORATORY', 'CT/BT', '150.00', '2025-11-20 22:08:01'),
(75, '26', 'LAB', 'LABORATORY', 'ESR', '150.00', '2025-11-20 22:08:01'),
(76, '27', 'LAB', 'LABORATORY', 'BLOOD TYPING', '200.00', '2025-11-20 22:08:01'),
(77, '28', 'LAB', 'LABORATORY', 'PERIPHERAL SMEAR BLOOD TEST', '550.00', '2025-11-20 22:08:01'),
(78, '29', 'LAB', 'LABORATORY', 'SODIUM (Na)', '300.00', '2025-11-20 22:08:01'),
(79, '30', 'LAB', 'LABORATORY', 'POTASSIUM (K)', '300.00', '2025-11-20 22:08:01'),
(80, '31', 'LAB', 'LABORATORY', 'CHLORIDE (Cl)', '300.00', '2025-11-20 22:08:01'),
(81, '32', 'LAB', 'LABORATORY', 'TOTAL CALCIUM (Ca)', '300.00', '2025-11-20 22:08:01'),
(82, '33', 'LAB', 'LABORATORY', 'IONIZED CALCIUM', '300.00', '2025-11-20 22:08:01'),
(83, '34', 'LAB', 'LABORATORY', 'SODIUM (Na)  POTASSIUM (K)', '550.00', '2025-11-20 22:08:01'),
(84, '35', 'LAB', 'LABORATORY', 'SODIUM (Na)  POTASSIUM (K) CHLORIDE (Cl)', '850.00', '2025-11-20 22:08:01'),
(85, '36', 'LAB', 'LABORATORY', 'SODIUM (Na)  POTASSIUM (K) CHLORIDE (Cl) I.CAL', '950.00', '2025-11-20 22:08:01'),
(86, '37', 'LAB', 'LABORATORY', 'HBsAg(Screening)', '200.00', '2025-11-20 22:08:01'),
(87, '38', 'LAB', 'LABORATORY', 'Anti HAV IgM (Hepa A)', '500.00', '2025-11-20 22:08:01'),
(88, '39', 'LAB', 'LABORATORY', 'TSH  ', '800.00', '2025-11-20 22:08:01'),
(89, '40', 'LAB', 'LABORATORY', 'FT3', '800.00', '2025-11-20 22:08:01'),
(90, '41', 'LAB', 'LABORATORY', 'FT4', '800.00', '2025-11-20 22:08:01'),
(91, '42', 'LAB', 'LABORATORY', 'VDRL/RPR', '250.00', '2025-11-20 22:08:01'),
(92, '43', 'LAB', 'LABORATORY', 'DENGUE DUO', '2500.00', '2025-11-20 22:08:01'),
(93, '44', 'LAB', 'LABORATORY', 'DENGUE NS1', '1800.00', '2025-11-20 22:08:01'),
(94, '45', 'LAB', 'LABORATORY', 'HIV TESTING', '500.00', '2025-11-20 22:08:01'),
(95, '46', 'LAB', 'LABORATORY', 'PSA', '1000.00', '2025-11-20 22:08:01'),
(96, '47', 'RAD', 'ULTRASOUND', 'BREAST DOUBLE', '1260.00', '2025-11-20 22:08:01'),
(97, '48', 'RAD', 'ULTRASOUND', 'BREAST SINGLE', '1105.00', '2025-11-20 22:08:01'),
(98, '49', 'RAD', 'ULTRASOUND', 'BIOPSY UTZ GUIDED', '1810.00', '2025-11-20 22:08:01'),
(99, '50', 'RAD', 'ULTRASOUND', 'BPS', '1110.00', '2025-11-20 22:08:01'),
(100, '51', 'RAD', 'ULTRASOUND', 'BPS (TWIN)', '2110.00', '2025-11-20 22:08:01'),
(101, '52', 'RAD', 'ULTRASOUND', 'CHEST/THORAX (BILATERAL)', '1200.00', '2025-11-20 22:08:01'),
(102, '53', 'RAD', 'ULTRASOUND', 'DOPPLER', '1700.00', '2025-11-20 22:08:01'),
(103, '54', 'RAD', 'ULTRASOUND', 'DOPPLER PREGNANT', '2800.00', '2025-11-20 22:08:01'),
(104, '55', 'RAD', 'ULTRASOUND', 'GALLBADDER ONLY', '700.00', '2025-11-20 22:08:01'),
(105, '56', 'RAD', 'ULTRASOUND', 'HEPATO-BILIARY TREE (HBT)', '900.00', '2025-11-20 22:08:01'),
(106, '57', 'RAD', 'ULTRASOUND', 'HEPATO-BILIARY TREE-PANCREASE (HBTP)', '1200.00', '2025-11-20 22:08:01'),
(107, '58', 'RAD', 'ULTRASOUND', 'HEMITHORAX', '960.00', '2025-11-20 22:08:01'),
(108, '59', 'RAD', 'ULTRASOUND', 'KIDNEY-URETER-BLADDER (KUB)', '1005.00', '2025-11-20 22:08:01'),
(109, '60', 'RAD', 'ULTRASOUND', 'KIDNEY-URETER-BLADDER=PROSTATE (KUB+P)', '1205.00', '2025-11-20 22:08:01'),
(110, '61', 'RAD', 'ULTRASOUND', 'LOWER ABDOMEN', '1350.00', '2025-11-20 22:08:01'),
(111, '62', 'RAD', 'ULTRASOUND', 'UPPER ABDOMEN', '1350.00', '2025-11-20 22:08:01'),
(112, '63', 'RAD', 'ULTRASOUND', 'PELVIC', '610.00', '2025-11-20 22:08:01'),
(113, '64', 'RAD', 'ULTRASOUND', 'SCROTAL/INGUINAL ', '1800.00', '2025-11-20 22:08:01'),
(114, '65', 'RAD', 'ULTRASOUND', 'SINGLE ORGAN', '650.00', '2025-11-20 22:08:01'),
(115, '66', 'RAD', 'ULTRASOUND', 'WHOLE ABDOMEN', '1500.00', '2025-11-20 22:08:01'),
(116, '67', 'RAD', 'ULTRASOUND', 'THYROID', '900.00', '2025-11-20 22:08:01'),
(117, '68', 'RAD', 'ULTRASOUND', 'NECK', '1745.00', '2025-11-20 22:08:01'),
(118, '69', 'RAD', 'ULTRASOUND', 'NECK/ THYROID WITH TIRADS', '1505.00', '2025-11-20 22:08:01'),
(119, '70', 'RAD', 'ULTRASOUND', 'TRANSVAGINAL/TRANSRECTAL', '1055.00', '2025-11-20 22:08:01'),
(120, '71', 'RAD', 'ULTRASOUND', 'SOFT TISSUE', '650.00', '2025-11-20 22:08:01'),
(121, '72', 'RAD', 'ULTRASOUND', 'CONGENITAL ANOMALY', '3000.00', '2025-11-20 22:08:01'),
(122, '73', 'RAD', 'X-RAY', 'ABDOMEN AP ', '595.00', '2025-11-20 22:08:01'),
(123, '74', 'RAD', 'X-RAY', 'ABDOMEN SUPINE/UPRIGHT', '745.00', '2025-11-20 22:08:01'),
(124, '75', 'RAD', 'X-RAY', 'ANKLE AP/MORTISE', '245.00', '2025-11-20 22:08:01'),
(125, '76', 'RAD', 'X-RAY', 'ANKLE APL', '370.00', '2025-11-20 22:08:01'),
(126, '77', 'RAD', 'X-RAY', 'ANKLE BILATERAL', '600.00', '2025-11-20 22:08:01'),
(127, '78', 'RAD', 'X-RAY', 'ARM AP', '245.00', '2025-11-20 22:08:01'),
(128, '79', 'RAD', 'X-RAY', 'ARM APL', '370.00', '2025-11-20 22:08:01'),
(129, '80', 'RAD', 'X-RAY', 'ARM BILATERAL', '600.00', '2025-11-20 22:08:01'),
(130, '81', 'RAD', 'X-RAY', 'BARIUM SWALLOW-ESOPHOGRAM', '0.00', '2025-11-20 22:08:01'),
(131, '82', 'RAD', 'X-RAY', 'CALCANEAL VIEW', '0.00', '2025-11-20 22:08:01'),
(132, '83', 'RAD', 'X-RAY', 'CERVICAL  SPINE AP', '400.00', '2025-11-20 22:08:01'),
(133, '84', 'RAD', 'X-RAY', 'CERVICAL SPINE APL', '650.00', '2025-11-20 22:08:01'),
(134, '85', 'RAD', 'X-RAY', 'CHEST PA', '300.00', '2025-11-20 22:08:01'),
(135, '86', 'RAD', 'X-RAY', 'APICO/SPOTVIEW', '295.00', '2025-11-20 22:08:01'),
(136, '87', 'RAD', 'X-RAY', 'CHEST APL', '580.00', '2025-11-20 22:08:01'),
(137, '88', 'RAD', 'X-RAY', 'CHEST LATERAL', '300.00', '2025-11-20 22:08:01'),
(138, '89', 'RAD', 'X-RAY', 'CHEST PAL', '580.00', '2025-11-20 22:08:01'),
(139, '90', 'RAD', 'X-RAY', 'CLAVICLE AP', '245.00', '2025-11-20 22:08:01'),
(140, '91', 'RAD', 'X-RAY', 'ELBOW APL', '370.00', '2025-11-20 22:08:01'),
(141, '92', 'RAD', 'X-RAY', 'ELBOW APL BILATERAL', '600.00', '2025-11-20 22:08:01'),
(142, '93', 'RAD', 'X-RAY', 'FOOT AP', '245.00', '2025-11-20 22:08:01'),
(143, '94', 'RAD', 'X-RAY', 'FOOT AP/L', '370.00', '2025-11-20 22:08:01'),
(144, '95', 'RAD', 'X-RAY', 'FOOT APL/O', '500.00', '2025-11-20 22:08:01'),
(145, '96', 'RAD', 'X-RAY', 'FOOT APO', '370.00', '2025-11-20 22:08:01'),
(146, '97', 'RAD', 'X-RAY', 'FOOT APOL', '500.00', '2025-11-20 22:08:01'),
(147, '98', 'RAD', 'X-RAY', 'FOOT BILATERAL', '600.00', '2025-11-20 22:08:01'),
(148, '99', 'RAD', 'X-RAY', 'FOREARM APL', '370.00', '2025-11-20 22:08:01'),
(149, '100', 'RAD', 'X-RAY', 'FOREARM APL BILATERAL', '600.00', '2025-11-20 22:08:01'),
(150, '101', 'RAD', 'X-RAY', 'FROGLEG AP', '370.00', '2025-11-20 22:08:01'),
(151, '102', 'RAD', 'X-RAY', 'HAND AP', '245.00', '2025-11-20 22:08:01'),
(152, '103', 'RAD', 'X-RAY', 'HAND AP/L', '370.00', '2025-11-20 22:08:01'),
(153, '104', 'RAD', 'X-RAY', 'HAND APL OBLIQUE', '500.00', '2025-11-20 22:08:01'),
(154, '105', 'RAD', 'X-RAY', 'HAND APO', '370.00', '2025-11-20 22:08:01'),
(155, '106', 'RAD', 'X-RAY', 'HAND APO BILATERAL', '600.00', '2025-11-20 22:08:01'),
(156, '107', 'RAD', 'X-RAY', 'KNEE AP', '245.00', '2025-11-20 22:08:01'),
(157, '108', 'RAD', 'X-RAY', 'KNEE APL', '370.00', '2025-11-20 22:08:01'),
(158, '109', 'RAD', 'X-RAY', 'KNEE BILATERAL', '600.00', '2025-11-20 22:08:01'),
(159, '110', 'RAD', 'X-RAY', 'KNEE MERCHANT-SUNRISE VIEW', '245.00', '2025-11-20 22:08:01'),
(160, '111', 'RAD', 'X-RAY', 'KUB', '595.00', '2025-11-20 22:08:01'),
(161, '112', 'RAD', 'X-RAY', 'LEG AP', '245.00', '2025-11-20 22:08:01'),
(162, '113', 'RAD', 'X-RAY', 'LEG APL', '370.00', '2025-11-20 22:08:01'),
(163, '114', 'RAD', 'X-RAY', 'LEG BILATERAL', '600.00', '2025-11-20 22:08:01'),
(164, '115', 'RAD', 'X-RAY', 'LUMBAR SPINE AP', '450.00', '2025-11-20 22:08:01'),
(165, '116', 'RAD', 'X-RAY', 'LUMBAR SPINE APL', '650.00', '2025-11-20 22:08:01'),
(166, '117', 'RAD', 'X-RAY', 'LUMBO-SACRAL AP', '450.00', '2025-11-20 22:08:01'),
(167, '118', 'RAD', 'X-RAY', 'LUMBO-SACRAL APL', '650.00', '2025-11-20 22:08:01'),
(168, '119', 'RAD', 'X-RAY', 'MANDIBLE SERIES', '695.00', '2025-11-20 22:08:01'),
(169, '120', 'RAD', 'X-RAY', 'MASTOID SERIES', '695.00', '2025-11-20 22:08:01'),
(170, '121', 'RAD', 'X-RAY', 'NASAL BONE', '370.00', '2025-11-20 22:08:01'),
(171, '122', 'RAD', 'X-RAY', 'PARANASAL SINUSES', '695.00', '2025-11-20 22:08:01'),
(172, '123', 'RAD', 'X-RAY', 'PELVIS AP', '450.00', '2025-11-20 22:08:01'),
(173, '124', 'RAD', 'X-RAY', 'SCOLIOTIC SERIES', '2069.00', '2025-11-20 22:08:01'),
(174, '125', 'RAD', 'X-RAY', 'SHOULDER AP', '245.00', '2025-11-20 22:08:01'),
(175, '126', 'RAD', 'X-RAY', 'SHOULDER APL', '370.00', '2025-11-20 22:08:01'),
(176, '127', 'RAD', 'X-RAY', 'SHOULDER APY', '370.00', '2025-11-20 22:08:01'),
(177, '128', 'RAD', 'X-RAY', 'SHOULDER BILATERAL', '600.00', '2025-11-20 22:08:01'),
(178, '129', 'RAD', 'X-RAY', 'SKULL APL', '695.00', '2025-11-20 22:08:01'),
(179, '130', 'RAD', 'X-RAY', 'THIGH-FEMUR  BILATERAL', '600.00', '2025-11-20 22:08:01'),
(180, '131', 'RAD', 'X-RAY', 'THIGH-FEMUR AP', '245.00', '2025-11-20 22:08:01'),
(181, '132', 'RAD', 'X-RAY', 'THIGH-FEMUR APL', '370.00', '2025-11-20 22:08:01'),
(182, '133', 'RAD', 'X-RAY', 'THORACIC CAGE AP', '450.00', '2025-11-20 22:08:01'),
(183, '134', 'RAD', 'X-RAY', 'THORACIC SPINE AP', '450.00', '2025-11-20 22:08:01'),
(184, '135', 'RAD', 'X-RAY', 'THORACIC SPINE APL', '600.00', '2025-11-20 22:08:01'),
(185, '136', 'RAD', 'X-RAY', 'THORACO-LUMBAR APL', '795.00', '2025-11-20 22:08:01'),
(186, '137', 'RAD', 'X-RAY', 'ORBITAL SERIES', '695.00', '2025-11-20 22:08:01'),
(187, '138', 'RAD', 'X-RAY', 'WATERS VIEW ONLY', '495.00', '2025-11-20 22:08:01'),
(188, '139', 'RAD', 'X-RAY', 'WRIST APL', '370.00', '2025-11-20 22:08:01'),
(189, '140', 'RAD', 'X-RAY', 'WRIST APL BILATERAL', '600.00', '2025-11-20 22:08:01');

-- --------------------------------------------------------

--
-- Table structure for table `queue_calls`
--

CREATE TABLE `queue_calls` (
  `id` int(10) UNSIGNED NOT NULL,
  `queue_id` int(10) UNSIGNED NOT NULL,
  `registration_user_id` int(10) UNSIGNED NOT NULL,
  `registration_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('ACTIVE','DONE','RETURNED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `called_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `retrieval_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_calls`
--

INSERT INTO `queue_calls` (`id`, `queue_id`, `registration_user_id`, `registration_number`, `status`, `called_at`, `completed_at`, `retrieval_code`) VALUES
(1, 1, 2, '1', 'DONE', '2025-11-04 13:26:41', '2025-11-04 16:49:04', ''),
(2, 1, 2, '1', 'ACTIVE', '2025-11-13 22:12:45', NULL, ''),
(3, 2, 2, '1', 'DONE', '2025-11-04 20:19:15', '2025-11-04 20:20:14', ''),
(4, 3, 2, '1', 'ACTIVE', '2025-11-13 22:12:46', NULL, ''),
(5, 5, 2, '1', 'ACTIVE', '2025-11-13 22:12:48', NULL, ''),
(6, 6, 2, '1', 'ACTIVE', '2025-11-13 20:27:00', NULL, ''),
(7, 7, 2, '1', 'ACTIVE', '2025-11-13 22:03:01', NULL, ''),
(8, 8, 2, '1', 'ACTIVE', '2025-11-13 22:05:22', NULL, ''),
(9, 9, 1, '3', 'ACTIVE', '2025-11-13 22:08:33', NULL, ''),
(10, 10, 2, '1', 'ACTIVE', '2025-11-13 22:46:13', NULL, ''),
(11, 13, 1, '3', 'ACTIVE', '2025-11-20 21:07:18', NULL, ''),
(12, 14, 1, '3', 'ACTIVE', '2025-11-20 21:42:20', NULL, ''),
(13, 15, 1, '3', 'ACTIVE', '2025-11-20 22:10:06', NULL, ''),
(14, 16, 1, '3', 'ACTIVE', '2025-11-20 23:11:12', NULL, ''),
(15, 17, 1, '3', 'ACTIVE', '2025-11-20 23:16:23', NULL, ''),
(16, 18, 1, '3', 'ACTIVE', '2025-11-21 00:02:23', NULL, ''),
(17, 19, 1, '3', 'ACTIVE', '2025-11-21 00:07:23', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `registration_attendant`
--

CREATE TABLE `registration_attendant` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_registration` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_attendant`
--

INSERT INTO `registration_attendant` (`id`, `user_id`, `username`, `assigned_registration`, `date_created`) VALUES
(1, 2, 'registration_attendant1', '1', '2025-10-15 13:02:45'),
(2, 3, 'registration_attendant2', '2', '2025-10-15 13:02:45'),
(3, 1, 'Admin', '3', '2025-10-15 13:02:45');

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
  `completed_at` datetime DEFAULT NULL,
  `retrieval_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registration_queue`
--

INSERT INTO `registration_queue` (`id`, `department_queue_id`, `queue_id`, `queue_call_id`, `department_id`, `registration_user_id`, `registration_number`, `status`, `routed_by`, `routed_at`, `called_at`, `completed_at`, `retrieval_code`) VALUES
(1, 3, 1, 2, 3, 2, '1', 'CALLED', 2, '2025-11-04 16:49:04', '2025-11-04 16:49:17', NULL, ''),
(2, 4, 2, 3, 6, 2, '1', 'WAITING', 4, '2025-11-04 20:20:14', NULL, NULL, '');

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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `password`, `role`, `firstname`, `lastname`, `created_at`, `department`) VALUES
(1, 'admin', 'admin123', 'ADMIN', 'System', 'Administrator', '2025-10-15 13:02:45', ''),
(2, 'registration_attendant1', 'admin123', 'REGISTRATION', 'REGISTRATION', 'Attendant 1', '2025-10-15 13:02:45', ''),
(3, 'counter_attendant2', 'admin123', 'REGISTRATION', 'REGISTRATION', 'Attendant 2', '2025-10-15 13:02:45', ''),
(4, 'cashier1', '1234', 'CASHIER', 'CASHIER', '', '2025-10-15 13:02:45', 'CASHIER'),
(5, 'xray', '1234', 'xray', 'XRAY', '', '2025-10-15 13:02:45', 'X-RAY ROOM 1,ULTRASOUND ROOM 1');

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
-- Indexes for table `daily_sales`
--
ALTER TABLE `daily_sales`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `idx_department_queue_status` (`department_id`,`status`),
  ADD KEY `idx_department_queue_call` (`queue_call_id`),
  ADD KEY `idx_department_queue_source` (`source_department_queue_id`);

--
-- Indexes for table `patient_info`
--
ALTER TABLE `patient_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `retrieval_code` (`retrieval_code`);

--
-- Indexes for table `product_register`
--
ALTER TABLE `product_register`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `queue_calls`
--
ALTER TABLE `queue_calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_status` (`queue_id`,`status`),
  ADD KEY `idx_counter_status` (`registration_user_id`,`status`);

--
-- Indexes for table `registration_attendant`
--
ALTER TABLE `registration_attendant`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_counter_user` (`user_id`),
  ADD UNIQUE KEY `uq_counter_username` (`username`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `counter_queue`
--
ALTER TABLE `counter_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_queue`
--
ALTER TABLE `daily_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `daily_sales`
--
ALTER TABLE `daily_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department_queue`
--
ALTER TABLE `department_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `patient_info`
--
ALTER TABLE `patient_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `product_register`
--
ALTER TABLE `product_register`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `queue_calls`
--
ALTER TABLE `queue_calls`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `registration_attendant`
--
ALTER TABLE `registration_attendant`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `registration_queue`
--
ALTER TABLE `registration_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  ADD CONSTRAINT `fk_department_queue_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_department_queue_source` FOREIGN KEY (`source_department_queue_id`) REFERENCES `department_queue` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `queue_calls`
--
ALTER TABLE `queue_calls`
  ADD CONSTRAINT `fk_queue_calls_queue` FOREIGN KEY (`queue_id`) REFERENCES `daily_queue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_calls_user` FOREIGN KEY (`registration_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
