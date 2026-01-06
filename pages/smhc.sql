-- SMHC database schema rebuilt for queue management modules
-- Compatible with MySQL 5.6+ / MariaDB 10.1+

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = '+00:00';

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

DROP DATABASE IF EXISTS `smhc`;
CREATE DATABASE `smhc` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smhc`;

-- ---------------------------------------------------------------------------
-- Table: user
-- Stores all application users (admin, counter, staff)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(100) NOT NULL,
  `role` VARCHAR(30) NOT NULL DEFAULT 'Nonadmin',
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default users (passwords stored in clear text to match legacy login logic)
INSERT INTO `user` (`username`, `password`, `role`, `firstname`, `lastname`)
VALUES
  ('admin', 'admin123', 'Admin', 'System', 'Administrator'),
  ('counter_attendant1', 'admin123', 'COUNTER', 'Counter', 'Attendant 1');

-- ---------------------------------------------------------------------------
-- Table: counter_attendant
-- Associates counter attendants to their assigned counter numbers
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `counter_attendant`;
CREATE TABLE `counter_attendant` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `assigned_counter` VARCHAR(50) NOT NULL,
  `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_counter_user` (`user_id`),
  UNIQUE KEY `uq_counter_username` (`username`),
  CONSTRAINT `fk_counter_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `counter_attendant` (`user_id`, `username`, `assigned_counter`)
VALUES
  ((SELECT `id` FROM `user` WHERE `username` = 'counter_attendant1'), 'counter_attendant1', '1');

-- ---------------------------------------------------------------------------
-- Table: daily_queue
-- Stores generated queue numbers for patients (per-category, per-day)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `daily_queue`;
CREATE TABLE `daily_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category` VARCHAR(20) NOT NULL,
  `queue_number` INT UNSIGNED NOT NULL,
  `unique_code` VARCHAR(16) NOT NULL,
  `queue_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_date_cat_num` (`queue_date`, `category`, `queue_number`),
  UNIQUE KEY `uq_unique_code` (`unique_code`),
  KEY `idx_date_cat` (`queue_date`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional seed data (remove if not needed)
INSERT INTO `daily_queue` (`category`, `queue_number`, `unique_code`, `queue_date`)
VALUES
  ('SENIORS', 1, '9A9QM7TW', CURRENT_DATE),
  ('OPD', 1, '7YPVCMUC', CURRENT_DATE),
  ('OPD_HMO', 1, '6SQLE6XS', CURRENT_DATE);

-- ---------------------------------------------------------------------------
-- Table: queue_calls
-- Tracks customers currently served or called by counters
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `queue_calls`;
CREATE TABLE `queue_calls` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `queue_id` INT UNSIGNED NOT NULL,
  `counter_user_id` INT UNSIGNED NOT NULL,
  `counter_number` VARCHAR(50) NOT NULL,
  `status` ENUM('ACTIVE','DONE','RETURNED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  `called_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status` (`queue_id`, `status`),
  KEY `idx_counter_status` (`counter_user_id`, `status`),
  CONSTRAINT `fk_queue_calls_queue` FOREIGN KEY (`queue_id`) REFERENCES `daily_queue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_queue_calls_user` FOREIGN KEY (`counter_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
