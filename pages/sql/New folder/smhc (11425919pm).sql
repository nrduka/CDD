-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 04, 2025 at 02:13 PM
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
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_queue`
--

INSERT INTO `daily_queue` (`id`, `category`, `queue_number`, `unique_code`, `queue_date`, `created_at`) VALUES
(1, 'P', 1, 'YTC251104P01', '2025-11-04', '2025-11-04 13:08:35'),
(2, 'P', 2, '96K251104P02', '2025-11-04', '2025-11-04 19:07:17'),
(3, 'P', 3, 'RGP251104P03', '2025-11-04', '2025-11-04 20:19:07'),
(4, 'P', 4, '6UW251104P04', '2025-11-04', '2025-11-04 20:19:09');

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
(2, 'RAD', 'ULTRASOUND ROOM 1', 'Performs diagnostic imaging and radiology services.', '2025-10-22 14:13:12'),
(3, 'XRAY', 'X-RAY ROOM 1', 'Dedicated to X-ray imaging requests.', '2025-10-22 14:13:12'),
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
(2, 1, 1, 1, 'DONE', 2, '2025-11-04 13:26:58', 2, '2025-11-04 13:27:19', '2025-11-04 13:27:42'),
(3, 1, 1, 3, 'DONE', 2, '2025-11-04 13:27:42', 2, '2025-11-04 15:05:31', '2025-11-04 16:49:04'),
(4, 3, 2, 6, 'DONE', 2, '2025-11-04 20:19:19', 4, '2025-11-04 20:20:02', '2025-11-04 20:20:14'),
(5, 2, 1, 6, 'DONE', 2, '2025-11-04 20:55:10', 2, '2025-11-04 21:10:39', '2025-11-04 21:10:51'),
(6, 4, 3, 6, 'WAITING', 2, '2025-11-04 21:10:36', NULL, NULL, NULL),
(7, 2, 1, 1, 'CALLED', 2, '2025-11-04 21:10:51', 2, '2025-11-04 21:11:10', NULL),
(8, 2, 1, 2, 'CALLED', 2, '2025-11-04 21:10:51', 2, '2025-11-04 21:11:24', NULL),
(9, 2, 1, 3, 'CALLED', 2, '2025-11-04 21:10:51', 2, '2025-11-04 21:11:37', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_info`
--

CREATE TABLE `patient_info` (
  `id` int(11) NOT NULL,
  `retrieval_code` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL DEFAULT '',
  `dob` date NOT NULL,
  `sex` varchar(100) NOT NULL,
  `civil_status` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL DEFAULT '',
  `phone_no` varchar(100) NOT NULL,
  `address` varchar(255) NOT NULL,
  `permanent_address` varchar(255) NOT NULL DEFAULT '',
  `address_preference` varchar(20) NOT NULL DEFAULT 'current',
  `region` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `occupation` varchar(100) NOT NULL,
  `id_type` varchar(100) NOT NULL DEFAULT '',
  `id_number` varchar(100) NOT NULL DEFAULT '',
  `employer_name` varchar(150) NOT NULL DEFAULT '',
  `purpose_of_exam` varchar(150) NOT NULL DEFAULT '',
  `or_number` varchar(100) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
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
-- Table structure for table `queue_calls`
--

CREATE TABLE `queue_calls` (
  `id` int(10) UNSIGNED NOT NULL,
  `queue_id` int(10) UNSIGNED NOT NULL,
  `registration_user_id` int(10) UNSIGNED NOT NULL,
  `registration_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('ACTIVE','DONE','RETURNED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `called_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_calls`
--

INSERT INTO `queue_calls` (`id`, `queue_id`, `registration_user_id`, `registration_number`, `status`, `called_at`, `completed_at`) VALUES
(1, 1, 2, '1', 'DONE', '2025-11-04 13:26:41', '2025-11-04 16:49:04'),
(2, 1, 2, '1', 'ACTIVE', '2025-11-04 16:49:24', NULL),
(3, 2, 2, '1', 'DONE', '2025-11-04 20:19:15', '2025-11-04 20:20:14'),
(4, 3, 2, '1', 'ACTIVE', '2025-11-04 21:10:29', NULL);

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
(2, 3, 'registration_attendant2', '2', '2025-10-15 13:02:45');

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
-- Dumping data for table `registration_queue`
--

INSERT INTO `registration_queue` (`id`, `department_queue_id`, `queue_id`, `queue_call_id`, `department_id`, `registration_user_id`, `registration_number`, `status`, `routed_by`, `routed_at`, `called_at`, `completed_at`) VALUES
(1, 3, 1, 2, 3, 2, '1', 'CALLED', 2, '2025-11-04 16:49:04', '2025-11-04 16:49:17', NULL),
(2, 4, 2, 3, 6, 2, '1', 'WAITING', 4, '2025-11-04 20:20:14', NULL, NULL);

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
(1, 'admin', 'admin123', 'Admin', 'System', 'Administrator', '2025-10-15 13:02:45', ''),
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
-- Indexes for table `patient_info`
--
ALTER TABLE `patient_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `retrieval_code` (`retrieval_code`);

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `department_queue`
--
ALTER TABLE `department_queue`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `patient_info`
--
ALTER TABLE `patient_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `queue_calls`
--
ALTER TABLE `queue_calls`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `registration_attendant`
--
ALTER TABLE `registration_attendant`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  ADD CONSTRAINT `fk_department_queue_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE;

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
