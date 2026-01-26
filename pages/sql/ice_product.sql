-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 26, 2026 at 06:02 AM
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
-- Table structure for table `ice_product`
--

CREATE TABLE `ice_product` (
  `id` int(11) NOT NULL,
  `product_code` varchar(100) NOT NULL,
  `product_description` varchar(100) NOT NULL,
  `uom` varchar(100) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `product_type` varchar(100) NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `ice_product`
--

INSERT INTO `ice_product` (`id`, `product_code`, `product_description`, `uom`, `unit_price`, `date_created`, `product_type`, `status`) VALUES
(1, '001-PL-01', 'PLATE - 1 X 40KG', 'SACK', '115.00', '0000-00-00 00:00:00', 'PLATE', 0),
(2, '001-PL-02', 'PLATE - 1 X 35KG', 'SACK', '0.00', '0000-00-00 00:00:00', 'PLATE', 1),
(5, '001-PL-03', 'PLATE - 1 X 40KG', 'BOX', '0.00', '0000-00-00 00:00:00', 'PLATE', 1),
(6, '001-PL-04', 'PLATE - 1 X 35KG', 'BOX', '0.00', '0000-00-00 00:00:00', 'PLATE', 1),
(7, '001-PL-05', 'PLATE - LOOSE', 'KG', '60.00', '0000-00-00 00:00:00', 'PLATE', 0),
(8, '002-CU-01', 'CUBE - 1 X 40KG', 'SACK', '200.00', '0000-00-00 00:00:00', 'CUBE', 0),
(9, '002-CU-02', 'CUBE - 1 X 35KG', 'SACK', '0.00', '0000-00-00 00:00:00', 'CUBE', 1),
(10, '002-CU-03', 'CUBE - 1 X 40KG', 'BOX', '0.00', '0000-00-00 00:00:00', 'CUBE', 1),
(11, '002-CU-04', 'CUBE - 1 X 35KG', 'BOX', '0.00', '0000-00-00 00:00:00', 'CUBE', 1),
(12, '002-CU-05', 'CUBE - LOOSE', 'KG', '0.00', '0000-00-00 00:00:00', 'CUBE', 0),
(13, '002-TU-01', 'TUBE - 1 X 40KG', 'SACK', '150.00', '0000-00-00 00:00:00', 'TUBE', 0),
(14, '002-TU-02', 'TUBE - 1 X 35KG', 'SACK', '0.00', '0000-00-00 00:00:00', 'TUBE', 0),
(15, '002-TU-03', 'TUBE - 1 X 40KG', 'SACK', '135.00', '0000-00-00 00:00:00', 'TUBE', 0),
(16, '002-TU-04', 'TUBE - 1 X 35KG', 'BOX', '0.00', '0000-00-00 00:00:00', 'TUBE', 0),
(17, '002-TU-05', 'TUBE - LOOSE', 'KG', '80.00', '0000-00-00 00:00:00', 'TUBE', 0),
(18, '004-RE-01', 'REPACKED -1 X 2KG', 'KG', '20.00', '0000-00-00 00:00:00', 'REPACKED', 0),
(19, '005-CR-01', 'CRUSHED-1 X 1KG', 'KG', '150.00', '0000-00-00 00:00:00', 'CRUSHED', 0),
(20, '001-PL-06', 'PLATE - FREEBIE', 'KG', '0.00', '0000-00-00 00:00:00', 'PLATE', 0),
(21, '002-TU-06', 'TUBE - FREEBIE', 'KG', '0.00', '0000-00-00 00:00:00', 'TUBE', 0),
(22, '001-PL-07', 'PLATE - ADDITIONAL', 'KG', '0.00', '0000-00-00 00:00:00', 'PLATE', 0),
(23, '002-TU-07', 'TUBE - ADDITIONAL', 'KG', '0.00', '0000-00-00 00:00:00', 'TUBE', 0),
(24, '004-RE-02', 'REPACKED -1 X 2KG', 'KG', '15.00', '2026-01-14 11:38:48', 'REPACKED', 0),
(25, '004-RE-03', 'REPACKED -1 X 2KG', 'KG', '16.00', '2026-01-14 11:38:48', 'REPACKED', 0),
(26, '004-RE-04', 'REPACKED -1 X 2KG', 'KG', '12.00', '2026-01-14 11:38:48', 'REPACKED', 0),
(27, '005-CR-02', 'CRUSHED-1 X 40KG', 'SACK', '135.00', '2026-01-14 11:38:48', 'CRUSHED', 0),
(28, '005-CR-03', 'CRUSHED-1 X 20KG', 'SACK', '80.00', '2026-01-14 11:38:48', 'CRUSHED', 0),
(29, '002-TU-08', 'TUBE - 1 X 40KG', 'SACK', '200.00', '2026-01-14 11:38:48', 'TUBE', 0),
(30, '002-TU-09', 'TUBE - 1 X 40KG', 'SACK', '140.00', '2026-01-14 11:38:48', 'TUBE', 0),
(31, '002-TU-10', 'TUBE - 1 X 40KG', 'SACK', '110.00', '2026-01-14 11:38:48', 'TUBE', 0),
(32, '006-OTH-11', 'SACK', 'PC', '20.00', '2026-01-14 11:38:48', 'OTHERS', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ice_product`
--
ALTER TABLE `ice_product`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ice_product`
--
ALTER TABLE `ice_product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
