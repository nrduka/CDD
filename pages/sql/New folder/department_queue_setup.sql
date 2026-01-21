-- ---------------------------------------------------------------------------
-- Department Queue Support Schema
-- ---------------------------------------------------------------------------
-- This script creates the department catalog table (if it does not yet
-- exist) and seeds three sample departments.  It also provisions the
-- department_queue table used to route patients from the counter to a
-- department attendant.
--
-- Execute this file manually against your SMHC database:
--   mysql -u <user> -p <database> < department_queue_setup.sql
--
-- Adjust table/column definitions as needed for your environment.
-- ---------------------------------------------------------------------------

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1. Department master list
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `department` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_code` VARCHAR(50) NOT NULL,
    `department` VARCHAR(150) NOT NULL,
    `dept_description` TEXT DEFAULT NULL,
    `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_department_code` (`department_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sample departments (safe for repeated runs)
INSERT INTO `department` (`department_code`, `department`, `dept_description`)
VALUES
    ('EXT',  'Extraction Department',  'Handles extraction procedures and patient preparation.'),
    ('RAD',  'Radiology Department',   'Performs diagnostic imaging and radiology services.'),
    ('XRAY', 'X-ray Department',       'Dedicated to X-ray imaging requests.')
ON DUPLICATE KEY UPDATE
    `department`      = VALUES(`department`),
    `dept_description`= VALUES(`dept_description`);

-- ---------------------------------------------------------------------------
-- 2. Department queue routing table
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `department_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue_call_id` INT UNSIGNED NOT NULL,
    `queue_id` INT UNSIGNED NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `status` ENUM('WAITING', 'CALLED', 'DONE') NOT NULL DEFAULT 'WAITING',
    `is_history` TINYINT(1) NOT NULL DEFAULT 0,
    `log_action` VARCHAR(50) DEFAULT NULL,
    `source_department_queue_id` INT UNSIGNED DEFAULT NULL,
    `routed_by` INT UNSIGNED DEFAULT NULL,
    `routed_at` DATETIME NOT NULL,
    `called_by` INT UNSIGNED DEFAULT NULL,
    `called_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_department_queue_status` (`department_id`, `status`, `is_history`),
    KEY `idx_department_queue_call` (`queue_call_id`, `is_history`),
    KEY `idx_department_queue_source` (`source_department_queue_id`),
    CONSTRAINT `fk_department_queue_call`
        FOREIGN KEY (`queue_call_id`) REFERENCES `queue_calls` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_department_queue_department`
        FOREIGN KEY (`department_id`) REFERENCES `department` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_department_queue_source`
        FOREIGN KEY (`source_department_queue_id`) REFERENCES `department_queue` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- ---------------------------------------------------------------------------
-- End of department_queue_setup.sql
-- ---------------------------------------------------------------------------
