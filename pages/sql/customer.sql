-- phpMyAdmin SQL Dump
-- version 4.7.4
-- https://www.phpmyadmin.net/
--
-- Host: 192.168.0.226:3306
-- Generation Time: Jan 21, 2026 at 01:56 AM
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
-- Table structure for table `customer`
--

CREATE TABLE `customer` (
  `id` int(11) NOT NULL,
  `customer_code` varchar(100) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `middleName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `address` varchar(100) NOT NULL,
  `storeName` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `customer_series` int(11) NOT NULL,
  `customer_source` int(11) NOT NULL,
  `area` varchar(100) NOT NULL,
  `sales_channel` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `customer`
--

INSERT INTO `customer` (`id`, `customer_code`, `firstName`, `middleName`, `lastName`, `address`, `storeName`, `type`, `customer_series`, `customer_source`, `area`, `sales_channel`) VALUES
(1, '', 'ALITA', '', '', '', '', '', 1, 1, '', ''),
(2, '', 'ANDAK', '', '', '', '', '', 2, 1, '', ''),
(3, '', 'AURA', '', '', '', '', '', 3, 1, '', ''),
(4, '', 'BUKO HOUSE', '', '', '', '', '', 4, 1, '', ''),
(5, '', 'CARLO PAGASPAS', '', '', '', '', '', 5, 1, '', ''),
(6, '', 'CASH', '', '', '', '', '', 6, 1, '', ''),
(7, '', 'CECIL DE CASTRO', '', '', '', '', '', 7, 1, '', ''),
(8, '', 'CODO', '', '', '', '', '', 8, 1, '', ''),
(9, '', 'COFFEE BETS', '', '', '', '', '', 9, 1, '', ''),
(10, '', 'EDDIE ICE CREAM', '', '', '', '', '', 10, 1, '', ''),
(11, '', 'EDITH', '', '', '', '', '', 11, 1, '', ''),
(12, '', 'EDNA', '', '', '', '', '', 12, 1, '', ''),
(13, '', 'EGAY HUBILLA', '', '', '', '', '', 13, 1, '', ''),
(14, '', 'FRANCIS', '', '', '', '', '', 14, 1, '', ''),
(15, '', 'GLENN', '', '', '', '', '', 15, 1, '', ''),
(16, '', 'GODO', '', '', '', '', '', 16, 1, '', ''),
(17, '', 'HARVEYS CAFE', '', '', '', '', '', 17, 1, '', ''),
(18, '', 'LITO', '', '', '', '', '', 18, 1, '', ''),
(19, '', 'LUMBRES', '', '', '', '', '', 19, 1, '', ''),
(20, '', 'MAC MAC', '', '', '', '', '', 20, 1, '', ''),
(21, '', 'MALIJAN', '', '', '', '', '', 21, 1, '', ''),
(22, '', 'MANGOLICIOUS', '', '', '', '', '', 22, 1, '', ''),
(23, '', 'MARY ANN', '', '', '', '', '', 23, 1, '', ''),
(24, '', 'MICHELLE', '', '', '', '', '', 24, 1, '', ''),
(25, '', 'MONTE', '', '', '', '', '', 25, 1, '', ''),
(26, '', 'ONYOK', '', '', '', '', '', 26, 1, '', ''),
(27, '', 'RICHARD', '', '', '', '', '', 27, 1, '', ''),
(28, '', 'STORE SALES', '', '', '', '', '', 28, 1, '', ''),
(29, '', 'TOMMY', '', '', '', '', '', 29, 1, '', ''),
(30, '', 'TRICIAS BAR', '', '', '', '', '', 30, 1, '', ''),
(31, '', 'VERGARA', '', '', '', '', '', 31, 1, '', ''),
(32, '', 'VICTORY', '', '', '', '', '', 32, 1, '', ''),
(33, '', 'EVEREST TRADING OPC', '', '', '', '', '', 33, 1, '', ''),
(34, '', 'GIGI', '', '', '', '', '', 34, 1, '', ''),
(35, '', 'JAYSON', '', '', '', '', '', 35, 1, '', ''),
(36, '', 'JD CARLOS', '', '', '', '', '', 36, 1, '', ''),
(37, '', 'JHONNY', '', '', '', '', '', 37, 1, '', ''),
(38, '', 'JUDAY', '', '', '', '', '', 38, 1, '', ''),
(39, '', 'PRENCES', '', '', '', '', '', 39, 1, '', ''),
(40, '', 'FESTIVITY', '', '', 'TANAUAN', '', '', 1, 2, 'TANAUAN', ''),
(41, '', 'GENE DES PUES', '', '', 'SAN RAFAEL', '', '', 2, 2, 'SAN RAFAEL', ''),
(42, '', 'JOMAR MACALALAD', '', '', 'SAN ANTONIO', '', '', 3, 2, 'SAN ANTONIO', ''),
(43, '', 'HAZEL NARVCAN', '', '', 'TANAUAN', '', '', 4, 2, 'TANAUAN', ''),
(44, '', 'MARA', '', '', 'PUEBLO', '', '', 5, 2, 'PUEBLO', ''),
(45, '', 'NOEL DUKA', '', '', 'POBLACION 3', '', '', 6, 2, 'POBLACION 3', ''),
(46, '', 'JAI CUTARAN', '', '', 'POBLACION 1', '', '', 7, 2, 'POBLACION 1', ''),
(47, '', 'JUDE TABING', '', '', 'TANAUAN', '', '', 8, 2, 'TANAUAN', ''),
(48, '', 'MINERVA', '', '', 'SAN MIGUEL', '', '', 9, 2, 'SAN MIGUEL', ''),
(49, '', '23 STORE', '', '', 'SAN RAFAEL', '', '', 1, 4, 'SAN RAFAEL', ''),
(50, '', '29 ELEVEN', '', '', 'SAN RAFAEL', '', '', 2, 4, 'SAN RAFAEL', ''),
(51, '', 'ALVIN', '', '', 'MT CLAIRE', '', '', 3, 4, 'MT CLAIRE', ''),
(52, '', 'ANGIE', '', '', 'TANAUAN', '', '', 4, 4, 'TANAUAN', ''),
(53, '', 'BEER BOY', '', '', '', '', '', 5, 4, '', ''),
(54, '', 'BELLY CRAVINGS', '', '', '', '', '', 6, 4, '', ''),
(55, '', 'BETH', '', '', '', '', '', 7, 4, '', ''),
(56, '', 'BIG MAK', '', '', '', '', '', 8, 4, '', ''),
(57, '', 'BLACK TEA', '', '', '', '', '', 9, 4, '', ''),
(58, '', 'BREWKADA', '', '', 'SAN RAFAEL', '', '', 10, 4, 'SAN RAFAEL', ''),
(59, '', 'BUBOY PIZZA', '', '', '', '', '', 11, 4, '', ''),
(60, '', 'BUT FIRST COFFEE', '', '', '', '', '', 12, 4, '', ''),
(61, '', 'CLASSIC SAVORY LIPA', '', '', 'LIPA', '', '', 13, 4, 'LIPA', ''),
(62, '', 'CLASSIC SAVORY WALTERMART DARASA', '', '', '', '', '', 14, 4, '', ''),
(63, '', 'COFFEE POSTAL', '', '', '', '', '', 15, 4, '', ''),
(64, '', 'DAMPA', '', '', 'SAN ROQUE', '', '', 16, 4, 'SAN ROQUE', ''),
(65, '', 'DANNY', '', '', 'SAN RAFAEL', '', '', 17, 4, 'SAN RAFAEL', ''),
(66, '', 'DEZDAM', '', '', '', '', '', 18, 4, '', ''),
(67, '', 'E&E ICE CREAM', '', '', '', '', '', 19, 4, '', ''),
(68, '', 'EDOGAWA', '', '', '', '', '', 20, 4, '', ''),
(69, '', 'FCMJ', '', '', '', '', '', 21, 4, '', ''),
(70, '', 'FOUR A FOOD CORP', '', '', 'SM STO.TOMAS', '', '', 22, 4, 'SM STO.TOMAS', ''),
(71, '', 'GIANT TEA', '', '', 'BIGA', '', '', 23, 4, 'BIGA', ''),
(72, '', 'I KITCHEN INC', '', '', 'SAN ANTONIO ', '', '', 24, 4, 'SAN ANTONIO ', ''),
(73, '', 'IMIE', '', '', 'STA MARIA', '', '', 25, 4, 'STA MARIA', ''),
(74, '', 'ISDAAN', '', '', '', '', '', 26, 4, '', ''),
(75, '', 'JNK', '', '', 'BIGA', '', '', 27, 4, 'BIGA', ''),
(76, '', 'JEBL STORE', '', '', '', '', '', 28, 4, '', ''),
(77, '', 'JESSA', '', '', '', '', '', 29, 4, '', ''),
(78, '', 'JNK 2', '', '', '', '', '', 30, 4, '', ''),
(79, '', 'JNK 3', '', '', '', '', '', 31, 4, '', ''),
(80, '', 'JUBE TABINE', '', '', '', '', '', 32, 4, '', ''),
(81, '', 'KENNY ROGERS', '', '', 'SAN ANTONIO ', '', '', 33, 4, 'SAN ANTONIO ', ''),
(82, '', 'KERITOH', '', '', '', '', '', 34, 4, '', ''),
(83, '', 'KWATOGS', '', '', 'SAN VICENTE', '', '', 35, 4, 'SAN VICENTE', ''),
(84, '', 'L RESTO BAR', '', '', '', '', '', 36, 4, '', ''),
(85, '', 'LASPALMAS', '', '', '', '', '', 37, 4, '', ''),
(86, '', 'LEO STORE', '', '', '', '', '', 38, 4, '', ''),
(87, '', 'LORIE STORE', '', '', '', '', '', 39, 4, '', ''),
(88, '', 'LRESTOBAR', '', '', '', '', '', 40, 4, '', ''),
(89, '', 'MACAO', '', '', 'SAN ANTONIO ', '', '', 41, 4, 'SAN ANTONIO ', ''),
(90, '', 'MANGO ROYAL', '', '', '', '', '', 42, 4, '', ''),
(91, '', 'MANGRAE', '', '', '', '', '', 43, 4, '', ''),
(92, '', 'MARICH', '', '', '', '', '', 44, 4, '', ''),
(93, '', 'MARIS', '', '', '', '', '', 45, 4, '', ''),
(94, '', 'MARITES', '', '', '', '', '', 46, 4, '', ''),
(95, '', 'MARLYN STORE', '', '', '', '', '', 47, 4, '', ''),
(96, '', 'MARVELS', '', '', '', '', '', 48, 4, '', ''),
(97, '', 'MAX MANGO', '', '', 'BIGA', '', '', 49, 4, 'BIGA', ''),
(98, '', 'MEDALLA', '', '', '', '', '', 50, 4, '', ''),
(99, '', 'MEERA', '', '', '', '', '', 51, 4, '', ''),
(100, '', 'MR BOY', '', '', '', '', '', 52, 4, '', ''),
(101, '', 'MR TUBO', '', '', '', '', '', 53, 4, '', ''),
(102, '', 'MUSLIM', '', '', '', '', '', 54, 4, '', ''),
(103, '', 'NAS KAPE', '', '', 'SAN ANTONIO ', '', '', 55, 4, 'SAN ANTONIO ', ''),
(104, '', 'NDN HOTEL', '', '', '', '', '', 56, 4, '', ''),
(105, '', 'NEIGHBOURHOOD', '', '', 'SAN ANTONIO ', '', '', 57, 4, 'SAN ANTONIO ', ''),
(106, '', 'OCB', '', '', '', '', '', 58, 4, '', ''),
(107, '', 'PICK UP COFFEE ATC', '', '', '', '', '', 59, 4, '', ''),
(108, '', 'PICK UP COFFEE BIGA', '', '', '', '', '', 60, 4, '', ''),
(109, '', 'PICK UP COFFEE TANAUAN', '', '', '', '', '', 61, 4, '', ''),
(110, '', 'SABPPTECH SERVICE INC', '', '', '', '', '', 62, 4, '', ''),
(111, '', 'SHOBEES CORNER', '', '', '', '', '', 63, 4, '', ''),
(112, '', 'SIP N BITE', '', '', 'SAN ANTONIO ', '', '', 64, 4, 'SAN ANTONIO ', ''),
(113, '', 'SLA', '', '', '', '', '', 65, 4, '', ''),
(114, '', 'STAR BREAKER CORP ATC', '', '', '', '', '', 66, 4, '', ''),
(115, '', 'STAR BREAKER CORP BIGA', '', '', '', '', '', 67, 4, '', ''),
(116, '', 'STAR BREAKER CORP TANAUAN', '', '', '', '', '', 68, 4, '', ''),
(117, '', 'SUNRISE', '', '', '', '', '', 69, 4, '', ''),
(118, '', 'THEA', '', '', '', '', '', 70, 4, '', ''),
(119, '', 'VARIABLES', '', '', 'STB', '', '', 71, 4, 'STB', ''),
(120, '', 'VICTORINAS', '', '', '', '', '', 72, 4, '', ''),
(121, '', 'AURA 2', '', '', 'MT CLAIRE', '', '', 73, 4, 'MT CLAIRE', ''),
(122, '', 'AVANT', '', '', '', '', '', 74, 4, '', ''),
(123, '', 'BFC', '', '', 'SAN ANTONIO ', '', '', 75, 4, 'SAN ANTONIO ', ''),
(124, '', 'DIMPLE', '', '', '', '', '', 76, 4, '', ''),
(125, '', 'HINER MONTECILLO', '', '', '', '', '', 77, 4, '', ''),
(126, '', 'MANG RAE', '', '', '', '', '', 78, 4, '', ''),
(127, '', 'NOYMIE', '', '', '', '', '', 79, 4, '', ''),
(128, '', 'PATRICK MEERA', '', '', '', '', '', 80, 4, '', ''),
(129, '', 'PETER MEDALLA', '', '', '', '', '', 81, 4, '', ''),
(130, '', 'POSTAL COFFEE', '', '', '', '', '', 82, 4, '', ''),
(131, '', 'TESS MINI', '', '', '', '', '', 83, 4, '', ''),
(132, '', 'DT CHOWKING', '', '', '', '', '', 84, 4, '', ''),
(133, '', 'TBM OCTOPAD', '', '', '', '', '', 85, 4, '', ''),
(134, '', 'SM CHOWKING', '', '', '', '', '', 86, 4, '', ''),
(135, '', 'SAN JUAN', '', '', '', '', '', 1, 5, '', ''),
(136, '', 'SJ MC ALLEGREA', '', '', 'SAN JUAN', '', '', 1, 3, 'SAN JUAN', ''),
(137, '', 'SJ MC ANDAK', '', '', 'SAN JUAN', '', '', 2, 3, 'SAN JUAN', ''),
(138, '', 'SJ MC APOL', '', '', 'SAN JUAN', '', '', 3, 3, 'SAN JUAN', ''),
(139, '', 'SJ MC BERTO', '', '', 'SAN JUAN', '', '', 4, 3, 'SAN JUAN', ''),
(140, '', 'SJ MC CHERRY', '', '', 'SAN JUAN', '', '', 5, 3, 'SAN JUAN', ''),
(141, '', 'SJ MC CHITA', '', '', 'SAN JUAN', '', '', 6, 3, 'SAN JUAN', ''),
(142, '', 'SJ MC DOKITEA', '', '', 'SAN JUAN', '', '', 7, 3, 'SAN JUAN', ''),
(143, '', 'SJ MC DUO BREW', '', '', 'SAN JUAN', '', '', 8, 3, 'SAN JUAN', ''),
(144, '', 'SJ MC EDIN', '', '', 'SAN JUAN', '', '', 9, 3, 'SAN JUAN', ''),
(145, '', 'SJ MC EDITH', '', '', 'SAN JUAN', '', '', 10, 3, 'SAN JUAN', ''),
(146, '', 'SJ MC EDNA', '', '', 'SAN JUAN', '', '', 11, 3, 'SAN JUAN', ''),
(147, '', 'SJ MC EPOY', '', '', 'SAN JUAN', '', '', 12, 3, 'SAN JUAN', ''),
(148, '', 'SJ MC EROLLY', '', '', 'SAN JUAN', '', '', 13, 3, 'SAN JUAN', ''),
(149, '', 'SJ MC JOLAND', '', '', 'SAN JUAN', '', '', 14, 3, 'SAN JUAN', ''),
(150, '', 'SJ MC JONA', '', '', 'SAN JUAN', '', '', 15, 3, 'SAN JUAN', ''),
(151, '', 'SJ MC LIN LIN', '', '', 'SAN JUAN', '', '', 16, 3, 'SAN JUAN', ''),
(152, '', 'SJ MC MARCO', '', '', 'SAN JUAN', '', '', 17, 3, 'SAN JUAN', ''),
(153, '', 'SJ MC MARIO', '', '', 'SAN JUAN', '', '', 18, 3, 'SAN JUAN', ''),
(154, '', 'SJ MC NATO', '', '', 'SAN JUAN', '', '', 19, 3, 'SAN JUAN', ''),
(155, '', 'SJ MC PAPIT', '', '', 'SAN JUAN', '', '', 20, 3, 'SAN JUAN', ''),
(156, '', 'SJ MC POKETIE', '', '', 'SAN JUAN', '', '', 21, 3, 'SAN JUAN', ''),
(157, '', 'SJ MC ROLLY', '', '', 'SAN JUAN', '', '', 22, 3, 'SAN JUAN', ''),
(158, '', 'SJ MC SELECTA', '', '', 'SAN JUAN', '', '', 23, 3, 'SAN JUAN', ''),
(159, '', 'SJ MC TAWONG', '', '', 'SAN JUAN', '', '', 24, 3, 'SAN JUAN', ''),
(160, '', 'SJ MC WENG', '', '', 'SAN JUAN', '', '', 25, 3, 'SAN JUAN', ''),
(161, '', 'SJ NGC MARIO', '', '', 'SAN JUAN', '', '', 26, 3, 'SAN JUAN', ''),
(162, '', 'SJ WALK IN ALDRIN', '', '', 'SAN JUAN', '', '', 27, 3, 'SAN JUAN', ''),
(163, '', 'SJ WALK IN ALITA', '', '', 'SAN JUAN', '', '', 28, 3, 'SAN JUAN', ''),
(164, '', 'SJ WALK IN AMOR', '', '', 'SAN JUAN', '', '', 29, 3, 'SAN JUAN', ''),
(165, '', 'SJ WALK IN ARIEL', '', '', 'SAN JUAN', '', '', 30, 3, 'SAN JUAN', ''),
(166, '', 'SJ WALK IN ATE CHA', '', '', 'SAN JUAN', '', '', 31, 3, 'SAN JUAN', ''),
(167, '', 'SJ WALK IN BATANG', '', '', 'SAN JUAN', '', '', 32, 3, 'SAN JUAN', ''),
(168, '', 'SJ WALK IN BIG DADDY', '', '', 'SAN JUAN', '', '', 33, 3, 'SAN JUAN', ''),
(169, '', 'SJ WALK IN BING BING', '', '', 'SAN JUAN', '', '', 34, 3, 'SAN JUAN', ''),
(170, '', 'SJ WALK IN BOTCHI', '', '', 'SAN JUAN', '', '', 35, 3, 'SAN JUAN', ''),
(171, '', 'SJ WALK IN BOTCHOK', '', '', 'SAN JUAN', '', '', 36, 3, 'SAN JUAN', ''),
(172, '', 'SJ WALK IN BUBBY', '', '', 'SAN JUAN', '', '', 37, 3, 'SAN JUAN', ''),
(173, '', 'SJ WALK IN CAFÉ YUCA', '', '', 'SAN JUAN', '', '', 38, 3, 'SAN JUAN', ''),
(174, '', 'SJ WALK IN CASH', '', '', 'SAN JUAN', '', '', 39, 3, 'SAN JUAN', ''),
(175, '', 'SJ WALK IN CHERRY', '', '', 'SAN JUAN', '', '', 40, 3, 'SAN JUAN', ''),
(176, '', 'SJ WALK IN CHITA', '', '', 'SAN JUAN', '', '', 41, 3, 'SAN JUAN', ''),
(177, '', 'SJ WALK IN CHRISTIE', '', '', 'SAN JUAN', '', '', 42, 3, 'SAN JUAN', ''),
(178, '', 'SJ WALK IN DANIEL', '', '', 'SAN JUAN', '', '', 43, 3, 'SAN JUAN', ''),
(179, '', 'SJ WALK IN EDNA', '', '', 'SAN JUAN', '', '', 44, 3, 'SAN JUAN', ''),
(180, '', 'SJ WALK IN EDWARD', '', '', 'SAN JUAN', '', '', 45, 3, 'SAN JUAN', ''),
(181, '', 'SJ WALK IN EPOY', '', '', 'SAN JUAN', '', '', 46, 3, 'SAN JUAN', ''),
(182, '', 'SJ WALK IN GLEN', '', '', 'SAN JUAN', '', '', 47, 3, 'SAN JUAN', ''),
(183, '', 'SJ WALK IN GLENN', '', '', 'SAN JUAN', '', '', 48, 3, 'SAN JUAN', ''),
(184, '', 'SJ WALK IN JAY JAY', '', '', 'SAN JUAN', '', '', 49, 3, 'SAN JUAN', ''),
(185, '', 'SJ WALK IN JAY R', '', '', 'SAN JUAN', '', '', 50, 3, 'SAN JUAN', ''),
(186, '', 'SJ WALK IN JOCELYN', '', '', 'SAN JUAN', '', '', 51, 3, 'SAN JUAN', ''),
(187, '', 'SJ WALK IN LIN LIN', '', '', 'SAN JUAN', '', '', 52, 3, 'SAN JUAN', ''),
(188, '', 'SJ WALK IN LISA', '', '', 'SAN JUAN', '', '', 53, 3, 'SAN JUAN', ''),
(189, '', 'SJ WALK IN MAC', '', '', 'SAN JUAN', '', '', 54, 3, 'SAN JUAN', ''),
(190, '', 'SJ WALK IN MAC MAC', '', '', 'SAN JUAN', '', '', 55, 3, 'SAN JUAN', ''),
(191, '', 'SJ WALK IN MAR', '', '', 'SAN JUAN', '', '', 56, 3, 'SAN JUAN', ''),
(192, '', 'SJ WALK IN MARCO', '', '', 'SAN JUAN', '', '', 57, 3, 'SAN JUAN', ''),
(193, '', 'SJ WALK IN MARIO', '', '', 'SAN JUAN', '', '', 58, 3, 'SAN JUAN', ''),
(194, '', 'SJ WALK IN MARY ANN', '', '', 'SAN JUAN', '', '', 59, 3, 'SAN JUAN', ''),
(195, '', 'SJ WALK IN MERRY ANN', '', '', 'SAN JUAN', '', '', 60, 3, 'SAN JUAN', ''),
(196, '', 'SJ WALK IN MICHAEL', '', '', 'SAN JUAN', '', '', 61, 3, 'SAN JUAN', ''),
(197, '', 'SJ WALK IN NATO', '', '', 'SAN JUAN', '', '', 62, 3, 'SAN JUAN', ''),
(198, '', 'SJ WALK IN PAM', '', '', 'SAN JUAN', '', '', 63, 3, 'SAN JUAN', ''),
(199, '', 'SJ WALK IN PIDOY', '', '', 'SAN JUAN', '', '', 64, 3, 'SAN JUAN', ''),
(200, '', 'SJ WALK IN PIPI BOMBAY', '', '', 'SAN JUAN', '', '', 65, 3, 'SAN JUAN', ''),
(201, '', 'SJ WALK IN PUGAD BABOY', '', '', 'SAN JUAN', '', '', 66, 3, 'SAN JUAN', ''),
(202, '', 'SJ WALK IN REY', '', '', 'SAN JUAN', '', '', 67, 3, 'SAN JUAN', ''),
(203, '', 'SJ WALK IN RICHARD', '', '', 'SAN JUAN', '', '', 68, 3, 'SAN JUAN', ''),
(204, '', 'SJ WALK IN ROBERT', '', '', 'SAN JUAN', '', '', 69, 3, 'SAN JUAN', ''),
(205, '', 'SJ WALK IN RYAN', '', '', 'SAN JUAN', '', '', 70, 3, 'SAN JUAN', ''),
(206, '', 'SJ WALK IN SANCEBUCHE', '', '', 'SAN JUAN', '', '', 71, 3, 'SAN JUAN', ''),
(207, '', 'SJ WALK IN SANCHIBOTCHI', '', '', 'SAN JUAN', '', '', 72, 3, 'SAN JUAN', ''),
(208, '', 'SJ WALK IN SANHIBOTCHI', '', '', 'SAN JUAN', '', '', 73, 3, 'SAN JUAN', ''),
(209, '', 'SJ WALK IN SELECTA', '', '', 'SAN JUAN', '', '', 74, 3, 'SAN JUAN', ''),
(210, '', 'SJ WALK IN SHRISTIE', '', '', 'SAN JUAN', '', '', 75, 3, 'SAN JUAN', ''),
(211, '', 'SJ WALK IN TINANG', '', '', 'SAN JUAN', '', '', 76, 3, 'SAN JUAN', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
