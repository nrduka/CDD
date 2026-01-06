-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2025 at 03:27 PM
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
(1, 'P', 1, 'QBY251124P01', '2025-11-24', '2025-11-24 20:52:28', '31KGZE98R4O', 1, '2025-11-24 20:53:31'),
(2, 'P', 2, 'Z48251124P02', '2025-11-24', '2025-11-24 20:53:00', '31KH5IOLOHG', 1, '2025-11-24 20:53:41'),
(3, 'P', 3, 'L6V251124P03', '2025-11-24', '2025-11-24 20:59:59', '31KHDWMGSA0', 1, '2025-11-24 21:27:24'),
(4, 'P', 4, 'DXQ251124P04', '2025-11-24', '2025-11-24 21:27:54', '31KHE45JSZX', 1, '2025-11-24 21:28:26'),
(5, 'P', 5, 'QVX251124P05', '2025-11-24', '2025-11-24 21:44:58', '31KH1PHVK8S', 1, '2025-11-24 21:45:27'),
(6, 'P', 6, '5VN251124P06', '2025-11-24', '2025-11-24 21:50:08', '31KGXD3SN68', 1, '2025-11-24 21:50:33'),
(7, 'P', 7, 'FX8251124P07', '2025-11-24', '2025-11-24 21:57:32', '31KH5IOLOHG', 1, '2025-11-24 21:57:47'),
(8, 'P', 8, 'FLC251124P08', '2025-11-24', '2025-11-24 22:07:08', '31KHJM5VN79', 1, '2025-11-24 22:08:20'),
(9, 'R', 1, 'KCF251124R01', '2025-11-24', '2025-11-24 22:09:27', '31KGZE98R4O', 1, '2025-11-24 22:20:28'),
(10, 'P', 9, 'SH4251124P09', '2025-11-24', '2025-11-24 22:20:44', '31KGZE98R4O', 1, '2025-11-24 22:21:32'),
(11, 'P', 10, 'S4G251124P10', '2025-11-24', '2025-11-24 22:22:04', '31KHIGVAVDF', 1, '2025-11-24 22:25:13'),
(12, 'P', 11, 'J39251124P11', '2025-11-24', '2025-11-24 22:22:12', '', 0, '2025-11-24 22:22:12');

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
(1, 1, '31KGZE98R4O', '1', 1, '2025-11-24 20:52:42', 250, 1, '2025-11-24 20:53:31'),
(2, 1, '31KGZE98R4O', '4', 2, '2025-11-24 20:52:42', 300, 1, '2025-11-24 20:53:31'),
(3, 2, '31KH5IOLOHG', '10', 1, '2025-11-24 20:53:16', 1000, 1, '2025-11-24 20:53:41'),
(4, 3, '31KHDWMGSA0', '2', 1, '2025-11-24 21:00:17', 250, 1, '2025-11-24 21:27:24'),
(5, 3, '31KHDWMGSA0', '139', 2, '2025-11-24 21:00:17', 370, 1, '2025-11-24 21:27:24'),
(6, 4, '31KHE45JSZX', '2', 1, '2025-11-24 21:28:21', 250, 1, '2025-11-24 21:28:26'),
(7, 4, '31KHE45JSZX', '139', 2, '2025-11-24 21:28:21', 370, 1, '2025-11-24 21:28:26'),
(8, 5, '31KH1PHVK8S', '1', 1, '2025-11-24 21:45:22', 250, 1, '2025-11-24 21:45:27'),
(9, 5, '31KH1PHVK8S', '139', 2, '2025-11-24 21:45:22', 370, 1, '2025-11-24 21:45:27'),
(10, 6, '31KGXD3SN68', '1', 1, '2025-11-24 21:50:29', 250, 1, '2025-11-24 21:50:33'),
(11, 6, '31KGXD3SN68', '140', 2, '2025-11-24 21:50:29', 600, 1, '2025-11-24 21:50:33'),
(12, 7, '31KH5IOLOHG', '2', 1, '2025-11-24 21:57:44', 250, 1, '2025-11-24 21:57:47'),
(13, 8, '31KHJM5VN79', '1', 1, '2025-11-24 22:07:38', 250, 1, '2025-11-24 22:08:20'),
(14, 8, '31KHJM5VN79', '96', 2, '2025-11-24 22:07:38', 370, 1, '2025-11-24 22:08:20'),
(15, 9, '31KGZE98R4O', '3', 1, '2025-11-24 22:09:47', 250, 1, '2025-11-24 22:20:28'),
(16, 10, '31KGZE98R4O', '2', 1, '2025-11-24 22:21:17', 250, 1, '2025-11-24 22:21:32'),
(17, 10, '31KGZE98R4O', '94', 2, '2025-11-24 22:21:17', 370, 1, '2025-11-24 22:21:32'),
(18, 11, '31KHIGVAVDF', '2', 1, '2025-11-24 22:24:19', 250, 1, '2025-11-24 22:25:13'),
(19, 11, '31KHIGVAVDF', '86', 2, '2025-11-24 22:24:19', 295, 1, '2025-11-24 22:25:13'),
(20, 11, '31KHIGVAVDF', '140', 3, '2025-11-24 22:24:19', 600, 1, '2025-11-24 22:25:13');

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
(1, 1, 1, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 20:52:42', 1, '2025-11-24 20:53:46', '2025-11-24 20:53:49', ''),
(2, 1, 1, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 20:52:42', 1, '2025-11-24 20:53:23', '2025-11-24 20:53:31', ''),
(3, 2, 2, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 20:53:16', 1, '2025-11-24 20:54:06', '2025-11-24 20:54:13', ''),
(4, 2, 2, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 20:53:16', 1, '2025-11-24 20:53:38', '2025-11-24 20:53:41', ''),
(5, 1, 1, 6, 'CALLED', 1, 'CALL', 2, 1, '2025-11-24 20:52:42', 1, '2025-11-24 20:53:23', NULL, ''),
(6, 1, 1, 6, 'DONE', 1, 'PAYMENT', 2, 1, '2025-11-24 20:52:42', 1, '2025-11-24 20:53:23', '2025-11-24 20:53:31', ''),
(7, 2, 2, 6, 'CALLED', 1, 'CALL', 4, 1, '2025-11-24 20:53:16', 1, '2025-11-24 20:53:38', NULL, ''),
(8, 2, 2, 6, 'DONE', 1, 'PAYMENT', 4, 1, '2025-11-24 20:53:16', 1, '2025-11-24 20:53:38', '2025-11-24 20:53:41', ''),
(9, 1, 1, 1, 'CALLED', 1, 'CALL', 1, 1, '2025-11-24 20:52:42', 1, '2025-11-24 20:53:46', NULL, ''),
(10, 1, 1, 1, 'DONE', 1, 'COMPLETE_FINISH', 1, 1, '2025-11-24 20:52:42', 1, '2025-11-24 20:53:46', '2025-11-24 20:53:49', ''),
(11, 2, 2, 1, 'CALLED', 1, 'CALL', 3, 1, '2025-11-24 20:53:16', 1, '2025-11-24 20:54:06', NULL, ''),
(12, 2, 2, 1, 'DONE', 1, 'COMPLETE_FINISH', 3, 1, '2025-11-24 20:53:16', 1, '2025-11-24 20:54:06', '2025-11-24 20:54:13', ''),
(13, 3, 3, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:27', '2025-11-24 21:27:30', ''),
(14, 3, 3, 3, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:32', '2025-11-24 21:27:35', ''),
(15, 3, 3, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:19', '2025-11-24 21:27:24', ''),
(16, 3, 3, 6, 'CALLED', 1, 'CALL', 15, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:00:34', NULL, ''),
(17, 3, 3, 6, 'CALLED', 1, 'REANNOUNCE', 15, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:19', NULL, ''),
(18, 3, 3, 6, 'DONE', 1, 'PAYMENT', 15, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:19', '2025-11-24 21:27:24', ''),
(19, 3, 3, 1, 'CALLED', 1, 'CALL', 13, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:27', NULL, ''),
(20, 3, 3, 1, 'DONE', 1, 'COMPLETE_FINISH', 13, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:27', '2025-11-24 21:27:30', ''),
(21, 3, 3, 3, 'CALLED', 1, 'CALL', 14, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:32', NULL, ''),
(22, 3, 3, 3, 'DONE', 1, 'COMPLETE_FINISH', 14, 1, '2025-11-24 21:00:17', 1, '2025-11-24 21:27:32', '2025-11-24 21:27:35', ''),
(23, 4, 4, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:29', '2025-11-24 21:28:32', ''),
(24, 4, 4, 3, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:34', '2025-11-24 21:28:35', ''),
(25, 4, 4, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:25', '2025-11-24 21:28:26', ''),
(26, 4, 4, 6, 'CALLED', 1, 'CALL', 25, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:25', NULL, ''),
(27, 4, 4, 6, 'DONE', 1, 'PAYMENT', 25, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:25', '2025-11-24 21:28:26', ''),
(28, 4, 4, 1, 'CALLED', 1, 'CALL', 23, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:29', NULL, ''),
(29, 4, 4, 1, 'DONE', 1, 'COMPLETE_FINISH', 23, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:29', '2025-11-24 21:28:32', ''),
(30, 4, 4, 3, 'CALLED', 1, 'CALL', 24, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:34', NULL, ''),
(31, 4, 4, 3, 'DONE', 1, 'COMPLETE_FINISH', 24, 1, '2025-11-24 21:28:21', 1, '2025-11-24 21:28:34', '2025-11-24 21:28:35', ''),
(32, 5, 5, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:31', '2025-11-24 21:45:33', ''),
(33, 5, 5, 3, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:34', '2025-11-24 21:45:36', ''),
(34, 5, 5, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:24', '2025-11-24 21:45:27', ''),
(35, 5, 5, 6, 'CALLED', 1, 'CALL', 34, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:24', NULL, ''),
(36, 5, 5, 6, 'DONE', 1, 'PAYMENT', 34, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:24', '2025-11-24 21:45:27', ''),
(37, 5, 5, 1, 'CALLED', 1, 'CALL', 32, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:31', NULL, ''),
(38, 5, 5, 1, 'DONE', 1, 'COMPLETE_FINISH', 32, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:31', '2025-11-24 21:45:33', ''),
(39, 5, 5, 3, 'CALLED', 1, 'CALL', 33, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:34', NULL, ''),
(40, 5, 5, 3, 'DONE', 1, 'COMPLETE_FINISH', 33, 1, '2025-11-24 21:45:22', 1, '2025-11-24 21:45:34', '2025-11-24 21:45:36', ''),
(41, 6, 6, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:50:29', 1, '2025-11-24 21:50:35', '2025-11-24 21:50:36', ''),
(42, 6, 6, 3, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:50:29', 1, '2025-11-24 21:50:37', '2025-11-24 21:50:39', ''),
(43, 6, 6, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:50:29', NULL, '2025-11-24 21:50:33', '2025-11-24 21:50:33', ''),
(44, 6, 6, 6, 'DONE', 1, 'PAYMENT', 43, 1, '2025-11-24 21:50:29', NULL, '2025-11-24 21:50:33', '2025-11-24 21:50:33', ''),
(45, 6, 6, 1, 'CALLED', 1, 'CALL', 41, 1, '2025-11-24 21:50:29', 1, '2025-11-24 21:50:35', NULL, ''),
(46, 6, 6, 1, 'DONE', 1, 'COMPLETE_FINISH', 41, 1, '2025-11-24 21:50:29', 1, '2025-11-24 21:50:35', '2025-11-24 21:50:36', ''),
(47, 6, 6, 3, 'CALLED', 1, 'CALL', 42, 1, '2025-11-24 21:50:29', 1, '2025-11-24 21:50:37', NULL, ''),
(48, 6, 6, 3, 'DONE', 1, 'COMPLETE_FINISH', 42, 1, '2025-11-24 21:50:29', 1, '2025-11-24 21:50:37', '2025-11-24 21:50:39', ''),
(49, 7, 7, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:57:44', 1, '2025-11-24 21:57:48', '2025-11-24 21:57:49', ''),
(50, 7, 7, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 21:57:44', NULL, '2025-11-24 21:57:47', '2025-11-24 21:57:47', ''),
(51, 7, 7, 6, 'DONE', 1, 'PAYMENT', 50, 1, '2025-11-24 21:57:44', NULL, '2025-11-24 21:57:47', '2025-11-24 21:57:47', ''),
(52, 7, 7, 1, 'CALLED', 1, 'CALL', 49, 1, '2025-11-24 21:57:44', 1, '2025-11-24 21:57:48', NULL, ''),
(53, 7, 7, 1, 'DONE', 1, 'COMPLETE_FINISH', 49, 1, '2025-11-24 21:57:44', 1, '2025-11-24 21:57:48', '2025-11-24 21:57:49', ''),
(54, 8, 8, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:08:22', '2025-11-24 22:08:34', ''),
(55, 8, 8, 3, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:08:38', '2025-11-24 22:08:47', ''),
(56, 8, 8, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:07:46', '2025-11-24 22:08:20', ''),
(57, 8, 8, 6, 'CALLED', 1, 'CALL', 56, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:07:46', NULL, ''),
(58, 8, 8, 6, 'DONE', 1, 'PAYMENT', 56, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:07:46', '2025-11-24 22:08:20', ''),
(59, 8, 8, 1, 'CALLED', 1, 'CALL', 54, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:08:22', NULL, ''),
(60, 8, 8, 1, 'DONE', 1, 'COMPLETE_FINISH', 54, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:08:22', '2025-11-24 22:08:34', ''),
(61, 8, 8, 3, 'CALLED', 1, 'CALL', 55, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:08:38', NULL, ''),
(62, 8, 8, 3, 'DONE', 1, 'COMPLETE_FINISH', 55, 1, '2025-11-24 22:07:38', 1, '2025-11-24 22:08:38', '2025-11-24 22:08:47', ''),
(63, 9, 9, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:20:29', '2025-11-24 22:20:39', ''),
(64, 9, 9, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:20:08', '2025-11-24 22:20:28', ''),
(65, 9, 9, 6, 'CALLED', 1, 'CALL', 64, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:09:48', NULL, ''),
(66, 9, 9, 6, 'CALLED', 1, 'REANNOUNCE', 64, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:20:08', NULL, ''),
(67, 9, 9, 6, 'DONE', 1, 'PAYMENT', 64, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:20:08', '2025-11-24 22:20:28', ''),
(68, 9, 9, 1, 'CALLED', 1, 'CALL', 63, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:20:29', NULL, ''),
(69, 9, 9, 1, 'DONE', 1, 'COMPLETE_FINISH', 63, 1, '2025-11-24 22:09:47', 1, '2025-11-24 22:20:29', '2025-11-24 22:20:39', ''),
(70, 10, 10, 1, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:37', '2025-11-24 22:21:46', ''),
(71, 10, 10, 3, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:47', '2025-11-24 22:21:58', ''),
(72, 10, 10, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:21', '2025-11-24 22:21:32', ''),
(73, 10, 10, 6, 'CALLED', 1, 'CALL', 72, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:21', NULL, ''),
(74, 10, 10, 6, 'DONE', 1, 'PAYMENT', 72, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:21', '2025-11-24 22:21:32', ''),
(75, 10, 10, 1, 'CALLED', 1, 'CALL', 70, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:34', NULL, ''),
(76, 10, 10, 1, 'CALLED', 1, 'REANNOUNCE', 70, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:37', NULL, ''),
(77, 10, 10, 1, 'DONE', 1, 'COMPLETE_FINISH', 70, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:37', '2025-11-24 22:21:46', ''),
(78, 10, 10, 3, 'CALLED', 1, 'CALL', 71, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:47', NULL, ''),
(79, 10, 10, 3, 'DONE', 1, 'COMPLETE_FINISH', 71, 1, '2025-11-24 22:21:17', 1, '2025-11-24 22:21:47', '2025-11-24 22:21:58', ''),
(80, 11, 11, 1, 'WAITING', 0, NULL, NULL, 1, '2025-11-24 22:24:19', NULL, NULL, NULL, ''),
(81, 11, 11, 3, 'WAITING', 0, NULL, NULL, 1, '2025-11-24 22:24:19', NULL, NULL, NULL, ''),
(82, 11, 11, 6, 'DONE', 0, NULL, NULL, 1, '2025-11-24 22:24:19', 1, '2025-11-24 22:25:00', '2025-11-24 22:25:13', ''),
(83, 11, 11, 6, 'CALLED', 1, 'CALL', 82, 1, '2025-11-24 22:24:19', 1, '2025-11-24 22:24:56', NULL, ''),
(84, 11, 11, 6, 'CALLED', 1, 'REANNOUNCE', 82, 1, '2025-11-24 22:24:19', 1, '2025-11-24 22:25:00', NULL, ''),
(85, 11, 11, 6, 'DONE', 1, 'PAYMENT', 82, 1, '2025-11-24 22:24:19', 1, '2025-11-24 22:25:00', '2025-11-24 22:25:13', '');

-- --------------------------------------------------------

--
-- Table structure for table `patient_info`
--

CREATE TABLE `patient_info` (
  `id` int(11) NOT NULL,
  `retrieval_code` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
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

INSERT INTO `patient_info` (`id`, `retrieval_code`, `firstname`, `middlename`, `lastname`, `dob`, `sex`, `phone_no`, `address`, `region`, `province`, `city`, `barangay`, `occupation`, `date_created`) VALUES
(28, '31KHLNGLCXE', 'PATRICK JULIUS', '', 'PARRA', '1992-03-23', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(29, '31KHK41X99W', 'REN ISSER', '', 'PARRA', '2002-01-03', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(30, '31KHJM5VN79', 'ANGEL FAYE', '', 'PARRA', '2000-02-10', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(31, '31KHIGVAVDF', 'MARK ANTHONY', '', 'PARIENTE', '1983-02-19', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(32, '31KHHMF9DVS', 'JOSEPH', '', 'PAREDES', '1988-10-02', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(33, '31KHG2T4RR4', 'ROLANDO', '', 'PANGILINAN', '1983-12-09', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(34, '31KHF1H3XIP', 'NEIL', '', 'PANGANIBAN', '1978-04-07', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(35, '31KHE45JSZX', 'ARNEL', '', 'PANGANIBAN', '1976-11-29', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(36, '31KHDWMGSA0', 'NOLILON', '', 'PANGANIBAN', '1968-10-22', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(37, '31KHCNKRXQJ', 'SHEILA', '', 'PANAPANAAN', '1982-09-04', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(38, '31KHBTNB3VS', 'GILBERT', '', 'PALMERO', '1979-10-25', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(39, '31KHAXEHYQ8', 'LUIS', '', 'PALICPIC', '1977-10-09', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(40, '31KH9ZRT4A2', 'BEN', '', 'PALICPIC', '1968-04-12', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(41, '31KH8298W0G', 'OPHELIA', '', 'PAHILAGA', '1971-02-16', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(42, '31KH7RKYN34', 'MARIA CIELO', '', 'PABALAT', '1965-05-21', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(43, '31KH684OVJ4', 'HOWARD', '', 'OSEÑA', '1980-02-16', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(44, '31KH5IOLOHG', 'MARK IAN', '', 'MASA', '1987-06-24', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(45, '31KH4EGGU09', 'MARK', '', 'ORTIZ', '1984-11-01', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(46, '31KH3NJXDES', 'JOHN ROEN', '', 'ORTIZ', '1987-09-11', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(47, '31KH2WTR58C', 'AIDA', '', 'CACAO', '1961-10-10', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(48, '31KH1PHVK8S', 'ADAM', '', 'SANGAHIN', '1982-05-02', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(49, '31KH0D91LY7', 'LUDIVINA', '', 'OROZO', '1988-02-12', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(50, '31KGZE98R4O', 'CONNIE', '', 'ABELILLA', '1975-12-08', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(51, '31KGYJK0FVO', 'CHERYL', '', 'ORENSE', '1994-01-13', 'FEMALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24'),
(52, '31KGXD3SN68', 'ERVIN JAY', '', 'ORENDAIN', '1992-10-23', 'MALE', '', '', '', '', '', '', '', '2025-11-24 20:22:24');

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
(1, 1, 1, '3', 'DONE', '2025-11-24 20:52:31', '2025-11-24 21:54:05', ''),
(2, 2, 1, '3', 'DONE', '2025-11-24 20:53:03', '2025-11-24 21:54:04', ''),
(3, 3, 1, '3', 'DONE', '2025-11-24 21:00:02', '2025-11-24 21:54:03', ''),
(4, 4, 1, '3', 'DONE', '2025-11-24 21:27:58', '2025-11-24 21:54:00', ''),
(5, 5, 1, '3', 'DONE', '2025-11-24 21:45:01', '2025-11-24 21:53:58', ''),
(6, 6, 1, '3', 'DONE', '2025-11-24 21:50:11', '2025-11-24 21:53:54', ''),
(7, 7, 1, '3', 'ACTIVE', '2025-11-24 21:57:34', NULL, ''),
(8, 8, 1, '3', 'ACTIVE', '2025-11-24 22:07:12', NULL, ''),
(9, 9, 1, '3', 'ACTIVE', '2025-11-24 22:09:34', NULL, ''),
(10, 10, 1, '3', 'ACTIVE', '2025-11-24 22:20:48', NULL, ''),
(11, 11, 1, '3', 'ACTIVE', '2025-11-24 22:22:10', NULL, ''),
(12, 12, 1, '3', 'ACTIVE', '2025-11-24 22:22:22', NULL, '');

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `daily_sales`
--
ALTER TABLE `daily_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department_queue`
--
ALTER TABLE `department_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `patient_info`
--
ALTER TABLE `patient_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `product_register`
--
ALTER TABLE `product_register`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `queue_calls`
--
ALTER TABLE `queue_calls`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `registration_attendant`
--
ALTER TABLE `registration_attendant`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `registration_queue`
--
ALTER TABLE `registration_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

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
