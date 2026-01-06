<?php

function ensureQueueTablesExist($mysqli)
{
    if (!tableExists($mysqli, 'daily_queue')) {
        return 'The table daily_queue is missing. Please run the SQL script to create it.';
    }

    if (!tableExists($mysqli, 'queue_calls')) {
        return 'The table queue_calls is missing. Please run the SQL script to create it.';
    }

    $departmentCheck = ensureDepartmentSchemas($mysqli);
    if ($departmentCheck !== null) {
        return $departmentCheck;
    }

    $registrationQueueCheck = ensureRegistrationQueueSchema($mysqli);
    if ($registrationQueueCheck !== null) {
        return $registrationQueueCheck;
    }

    return null;
}

function tableExists($mysqli, $table)
{
    if (!$mysqli) {
        return false;
    }

    $sql = 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ensureDepartmentSchemas($mysqli)
{
    if (!$mysqli) {
        return 'Unable to establish connection for department queue verification.';
    }

    if (!tableExists($mysqli, 'department')) {
        $createDepartmentSql = <<<SQL
CREATE TABLE `department` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_code` VARCHAR(50) NOT NULL,
    `department` VARCHAR(150) NOT NULL,
    `dept_description` TEXT DEFAULT NULL,
    `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_department_code` (`department_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        if (!$mysqli->query($createDepartmentSql)) {
            return 'Unable to create department table. Error: ' . $mysqli->error;
        }

        $sampleDepartments = [
            ['EXT', 'Extraction Department', 'Handles extraction procedures and preparation.'],
            ['RAD', 'Radiology Department', 'Performs diagnostic imaging and radiology services.'],
            ['XRAY', 'X-ray Department', 'Dedicated to X-ray imaging requests.']
        ];

        $insertSql = "INSERT INTO department (department_code, department, dept_description) VALUES (?, ?, ?)";
        $stmt = $mysqli->prepare($insertSql);
        if ($stmt) {
            foreach ($sampleDepartments as $departmentRow) {
                $stmt->bind_param('sss', $departmentRow[0], $departmentRow[1], $departmentRow[2]);
                $stmt->execute();
            }
            $stmt->close();
        }
    }

    if (!tableExists($mysqli, 'department_queue')) {
        $sql = <<<SQL
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
    CONSTRAINT `fk_department_queue_call` FOREIGN KEY (`queue_call_id`) REFERENCES `queue_calls` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_department_queue_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_department_queue_source` FOREIGN KEY (`source_department_queue_id`) REFERENCES `department_queue` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        if (!$mysqli->query($sql)) {
            return 'Unable to create department_queue table. Error: ' . $mysqli->error;
        }
        return null;
    }

    return upgradeDepartmentQueueSchema($mysqli);
}

function upgradeDepartmentQueueSchema($mysqli)
{
    $columns = describeTableColumns($mysqli, 'department_queue');
    if ($columns === null) {
        return 'Unable to inspect department_queue table.';
    }

    if (!isset($columns['is_history'])) {
        if (!$mysqli->query("ALTER TABLE `department_queue` ADD COLUMN `is_history` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`")) {
            return 'Unable to add history column to department_queue. Error: ' . $mysqli->error;
        }
    }

    if (!isset($columns['log_action'])) {
        if (!$mysqli->query("ALTER TABLE `department_queue` ADD COLUMN `log_action` VARCHAR(50) DEFAULT NULL AFTER `is_history`")) {
            return 'Unable to add action column to department_queue. Error: ' . $mysqli->error;
        }
    }

    if (!isset($columns['source_department_queue_id'])) {
        if (!$mysqli->query("ALTER TABLE `department_queue` ADD COLUMN `source_department_queue_id` INT UNSIGNED DEFAULT NULL AFTER `log_action`")) {
            return 'Unable to add source reference column to department_queue. Error: ' . $mysqli->error;
        }
    }

    if (tableIndexExists($mysqli, 'department_queue', 'uq_department_queue_call')) {
        if (!$mysqli->query("ALTER TABLE `department_queue` DROP INDEX `uq_department_queue_call`")) {
            return 'Unable to remove unique constraint from department_queue. Error: ' . $mysqli->error;
        }
    }

    if (!tableIndexExists($mysqli, 'department_queue', 'idx_department_queue_status')) {
        if (!$mysqli->query("ALTER TABLE `department_queue` ADD KEY `idx_department_queue_status` (`department_id`, `status`, `is_history`)")) {
            return 'Unable to add status index to department_queue. Error: ' . $mysqli->error;
        }
    }

    if (!tableIndexExists($mysqli, 'department_queue', 'idx_department_queue_call')) {
        if (!$mysqli->query("ALTER TABLE `department_queue` ADD KEY `idx_department_queue_call` (`queue_call_id`, `is_history`)")) {
            return 'Unable to add call index to department_queue. Error: ' . $mysqli->error;
        }
    }

    if (!tableIndexExists($mysqli, 'department_queue', 'idx_department_queue_source')) {
        if (!$mysqli->query("ALTER TABLE `department_queue` ADD KEY `idx_department_queue_source` (`source_department_queue_id`)")) {
            return 'Unable to add history index to department_queue. Error: ' . $mysqli->error;
        }
    }

    if (!tableHasForeignKey($mysqli, 'department_queue', 'fk_department_queue_source')) {
        $fkSql = "ALTER TABLE `department_queue` ADD CONSTRAINT `fk_department_queue_source`
                  FOREIGN KEY (`source_department_queue_id`) REFERENCES `department_queue` (`id`) ON DELETE CASCADE";
        if (!$mysqli->query($fkSql)) {
            return 'Unable to add history foreign key to department_queue. Error: ' . $mysqli->error;
        }
    }

    return null;
}

function describeTableColumns($mysqli, $table)
{
    if (!$mysqli) {
        return null;
    }

    $columns = [];
    if ($result = $mysqli->query("SHOW COLUMNS FROM `{$table}`")) {
        while ($row = $result->fetch_assoc()) {
            $columns[strtolower($row['Field'])] = $row;
        }
        $result->free();
    }
    return $columns;
}

function tableIndexExists($mysqli, $table, $indexName)
{
    if (!$mysqli) {
        return false;
    }

    $sql = "SELECT 1 FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = ? 
              AND INDEX_NAME = ? 
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $indexName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function tableHasForeignKey($mysqli, $table, $constraintName)
{
    if (!$mysqli) {
        return false;
    }

    $sql = "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $constraintName);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function ensureRegistrationQueueSchema($mysqli)
{
    if (!$mysqli) {
        return 'Unable to verify registration routing table.';
    }

    if (tableExists($mysqli, 'registration_queue')) {
        return null;
    }

    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `registration_queue` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_queue_id` INT UNSIGNED NOT NULL,
    `queue_id` INT UNSIGNED NOT NULL,
    `queue_call_id` INT UNSIGNED DEFAULT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `registration_user_id` INT UNSIGNED NOT NULL,
    `registration_number` VARCHAR(50) NOT NULL,
    `status` ENUM('WAITING', 'CALLED', 'DONE') NOT NULL DEFAULT 'WAITING',
    `routed_by` INT UNSIGNED DEFAULT NULL,
    `routed_at` DATETIME NOT NULL,
    `called_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_registration_status` (`registration_user_id`, `status`),
    KEY `idx_registration_queue_status` (`queue_id`, `status`),
    KEY `idx_registration_department_status` (`department_id`, `status`),
    KEY `idx_registration_department_queue` (`department_queue_id`),
    CONSTRAINT `fk_registration_queue_department_queue` FOREIGN KEY (`department_queue_id`) REFERENCES `department_queue` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_registration_queue_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_registration_queue_user` FOREIGN KEY (`registration_user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_registration_queue_queue` FOREIGN KEY (`queue_id`) REFERENCES `daily_queue` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_registration_queue_call` FOREIGN KEY (`queue_call_id`) REFERENCES `queue_calls` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    if (!$mysqli->query($sql)) {
        return 'Unable to create registration routing table. Error: ' . $mysqli->error;
    }

    return null;
}
