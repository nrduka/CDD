-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 05, 2025 at 07:26 AM
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

--
-- Indexes for dumped tables
--

--
-- Indexes for table `patient_info`
--
ALTER TABLE `patient_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `retrieval_code` (`retrieval_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `patient_info`
--
ALTER TABLE `patient_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
