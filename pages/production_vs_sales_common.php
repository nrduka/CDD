<?php

function prodSalesNormalizeClass($value)
{
    $value = strtoupper(trim((string)$value));
    $allowed = ['PLATE', 'TUBE', 'CUBE'];

    return in_array($value, $allowed, true) ? $value : 'PLATE';
}

function prodSalesClassOptions()
{
    return [
        'PLATE' => 'Plate',
        'TUBE' => 'Tube',
        'CUBE' => 'Cube'
    ];
}

function prodSalesResolveDateRange($startInput, $endInput)
{
    $today = date('Y-m-d');
    $startDate = trim((string)$startInput) !== '' ? trim((string)$startInput) : $today;
    $endDate = trim((string)$endInput) !== '' ? trim((string)$endInput) : $today;

    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$startObject || $startObject->format('Y-m-d') !== $startDate) {
        $startDate = $today;
        $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    }

    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$endObject || $endObject->format('Y-m-d') !== $endDate) {
        $endDate = $today;
        $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    }

    if ($startDate > $endDate) {
        $temp = $startDate;
        $startDate = $endDate;
        $endDate = $temp;
        $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
        $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    }

    return [$startDate, $endDate, $startObject, $endObject];
}

function prodSalesFormatHeading($startDate, $endDate)
{
    if ($startDate === $endDate) {
        return strtoupper(date('F d, Y', strtotime($startDate)));
    }

    return strtoupper(date('F d, Y', strtotime($startDate)) . ' TO ' . date('F d, Y', strtotime($endDate)));
}

function prodSalesResolvePreviousDate($dateValue)
{
    $dateObject = DateTime::createFromFormat('Y-m-d', (string)$dateValue);
    if (!$dateObject) {
        return '';
    }

    $dateObject->modify('-1 day');

    return $dateObject->format('Y-m-d');
}

function prodSalesFormatShortDateLabel($dateValue)
{
    $dateObject = DateTime::createFromFormat('Y-m-d', (string)$dateValue);
    if (!$dateObject) {
        return '';
    }

    return $dateObject->format('d-M-y');
}

function prodSalesResolveDateTimeLabel($datetimeValue, $dateValue = '', $timeValue = '')
{
    $datetimeValue = trim((string)$datetimeValue);
    if ($datetimeValue !== '' && $datetimeValue !== '0000-00-00 00:00:00') {
        $datetimeObject = DateTime::createFromFormat('Y-m-d H:i:s', $datetimeValue);
        if ($datetimeObject instanceof DateTime) {
            return $datetimeObject->format('Y-m-d H:i:s');
        }

        return $datetimeValue;
    }

    $dateValue = trim((string)$dateValue);
    $timeValue = trim((string)$timeValue);
    if ($dateValue !== '' && $timeValue !== '' && $timeValue !== '00:00:00') {
        return $dateValue . ' ' . $timeValue;
    }

    return $dateValue;
}

function prodSalesCalculateHours($startedValue, $endedValue)
{
    $startedValue = trim((string)$startedValue);
    $endedValue = trim((string)$endedValue);
    if ($startedValue === '' || $endedValue === '') {
        return 0.0;
    }

    $startedAt = strtotime($startedValue);
    $endedAt = strtotime($endedValue);
    if ($startedAt === false || $endedAt === false || $endedAt < $startedAt) {
        return 0.0;
    }

    return ($endedAt - $startedAt) / 3600;
}

function prodSalesFormatMachineLabel($machineDescription)
{
    $label = strtoupper(trim((string)$machineDescription));
    $label = preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
    $label = preg_replace('/\s+MACHINE\b/', '', $label);
    $label = preg_replace('/\s+/', ' ', $label);

    return trim($label);
}

function prodSalesFetchProductionRows($mysqli, $startDate, $endDate, $prodSalesClass)
{
    $rows = [];
    $sql = "SELECT ip.id,
                   ip.dpr_code,
                   ip.production_code,
                   ip.date_production,
                   ip.time_dropped,
                   ip.datetime_started,
                   ip.datetime_ended,
                   ip.machine,
                   ip.finished_ice_qty,
                   ip.loose_ice_qty,
                   ip.remarks,
                   im.machine_description,
                   im.product_type AS machine_product_type
            FROM ice_production ip
            LEFT JOIN ice_machine im ON im.id = ip.machine
            WHERE ip.date_production BETWEEN ? AND ?
              AND UPPER(TRIM(COALESCE(im.product_type, ip.product_type, ''))) = ?
            ORDER BY COALESCE(ip.datetime_started, ip.date_production),
                     COALESCE(ip.time_dropped, '00:00:00'),
                     im.machine_description,
                     ip.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $startDate, $endDate, $prodSalesClass);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $machineId = isset($row['machine']) ? (string)$row['machine'] : '';
                $machineDescription = trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
                if ($machineDescription === '') {
                    $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
                }

                $dprCode = trim((string)(isset($row['dpr_code']) ? $row['dpr_code'] : ''));
                if ($dprCode === '') {
                    $dprCode = trim((string)(isset($row['production_code']) ? $row['production_code'] : ''));
                }

                $startedAt = prodSalesResolveDateTimeLabel(
                    isset($row['datetime_started']) ? $row['datetime_started'] : '',
                    isset($row['date_production']) ? $row['date_production'] : '',
                    isset($row['time_dropped']) ? $row['time_dropped'] : ''
                );
                $endedAt = prodSalesResolveDateTimeLabel(isset($row['datetime_ended']) ? $row['datetime_ended'] : '');
                $finishedQty = isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
                $looseQty = isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;

                $rows[] = [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'date_production' => isset($row['date_production']) ? (string)$row['date_production'] : '',
                    'datetime_started' => $startedAt,
                    'datetime_ended' => $endedAt,
                    'dpr_code' => $dprCode,
                    'machine_id' => $machineId,
                    'machine_description' => $machineDescription,
                    'product_type' => $prodSalesClass,
                    'hours' => prodSalesCalculateHours($startedAt, $endedAt),
                    'finished_ice_qty' => $finishedQty,
                    'loose_ice_qty' => $looseQty,
                    'production_qty' => $finishedQty + $looseQty,
                    'remarks' => isset($row['remarks']) ? (string)$row['remarks'] : ''
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function prodSalesFetchMachineColumns($mysqli, $rows, $prodSalesClass)
{
    $columns = [];
    $seen = [];
    $appendColumn = function ($machineId, $machineDescription) use (&$columns, &$seen, $prodSalesClass) {
        $machineId = trim((string)$machineId);
        $machineDescription = trim((string)$machineDescription);
        if ($machineDescription === '') {
            $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
        }

        $key = $machineId . '|' . $machineDescription;
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $columns[] = [
            'key' => $key,
            'machine_id' => $machineId,
            'product_type' => $prodSalesClass,
            'machine_description' => $machineDescription,
            'machine_label' => prodSalesFormatMachineLabel($machineDescription)
        ];
    };

    if ($mysqli) {
        $sql = "SELECT id, machine_description
                FROM ice_machine
                WHERE UPPER(TRIM(product_type)) = ?
                ORDER BY machine_description";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $prodSalesClass);
            if ($stmt->execute() && ($result = $stmt->get_result())) {
                while ($row = $result->fetch_assoc()) {
                    $appendColumn(
                        isset($row['id']) ? $row['id'] : '',
                        isset($row['machine_description']) ? $row['machine_description'] : ''
                    );
                }
                $result->free();
            }
            $stmt->close();
        }
    }

    foreach ($rows as $row) {
        $appendColumn(
            isset($row['machine_id']) ? $row['machine_id'] : '',
            isset($row['machine_description']) ? $row['machine_description'] : ''
        );
    }

    usort($columns, function ($left, $right) {
        return strcasecmp((string)$left['machine_label'], (string)$right['machine_label']);
    });

    return $columns;
}

function prodSalesBuildProductionMatrix($rows, $startDate, $endDate, $machineColumns)
{
    $machineColumnMap = [];
    foreach ($machineColumns as $column) {
        $key = isset($column['key']) ? (string)$column['key'] : '';
        if ($key !== '') {
            $machineColumnMap[$key] = true;
        }
    }

    $matrixRows = [];
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($startObject && $endObject) {
        $currentObject = clone $startObject;
        while ($currentObject <= $endObject) {
            $dateKey = $currentObject->format('Y-m-d');
            $matrixRows[$dateKey] = [
                'date' => $dateKey,
                'date_label' => $currentObject->format('d-M-y'),
                'machines' => []
            ];
            foreach ($machineColumns as $column) {
                $matrixRows[$dateKey]['machines'][$column['key']] = [
                    'hours' => 0.0,
                    'qty' => 0.0
                ];
            }
            $currentObject->modify('+1 day');
        }
    }

    foreach ($rows as $row) {
        $dateKey = isset($row['date_production']) ? (string)$row['date_production'] : '';
        if (!isset($matrixRows[$dateKey])) {
            continue;
        }

        $machineKey = trim((string)(isset($row['machine_id']) ? $row['machine_id'] : '')) . '|' . trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
        if ($machineKey === '|' || !isset($machineColumnMap[$machineKey])) {
            continue;
        }

        $matrixRows[$dateKey]['machines'][$machineKey]['hours'] += isset($row['hours']) ? (float)$row['hours'] : 0.0;
        $matrixRows[$dateKey]['machines'][$machineKey]['qty'] += isset($row['production_qty']) ? (float)$row['production_qty'] : 0.0;
    }

    return array_values($matrixRows);
}

function prodSalesFormatMatrixNumber($value)
{
    $value = (float)$value;
    if (abs($value) < 0.005) {
        return '';
    }

    return number_format($value, 2);
}

function prodSalesFormatSalesMatrixNumber($value, $showZero = false)
{
    $value = (float)$value;
    if (!$showZero && abs($value) < 0.005) {
        return '';
    }

    return number_format($value, 2);
}

function prodSalesTableHasColumn($mysqli, $tableName, $columnName)
{
    if (!$mysqli) {
        return false;
    }

    $sql = "SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $tableName, $columnName);
    $hasColumn = false;
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        $row = $result->fetch_assoc();
        $hasColumn = isset($row['total']) && (int)$row['total'] > 0;
        $result->free();
    }
    $stmt->close();

    return $hasColumn;
}

function prodSalesEnsureBeginningInventoryTable($mysqli)
{
    if (!$mysqli) {
        return false;
    }

    $sql = "CREATE TABLE IF NOT EXISTS ice_beginning_inventory (
                id INT NOT NULL AUTO_INCREMENT,
                inventory_date DATE NOT NULL,
                prod_sales_class VARCHAR(20) NOT NULL,
                qty_kg DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                remarks VARCHAR(255) DEFAULT NULL,
                date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_updated DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_ice_beginning_inventory_class_date (prod_sales_class, inventory_date),
                KEY idx_ice_beginning_inventory_lookup (prod_sales_class, inventory_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$mysqli->query($sql)) {
        return false;
    }

    $class = 'PLATE';
    $inventoryDate = '2026-05-31';
    $qtyKg = 10280.00;
    $remarks = 'Beginning storage quantity provided for production vs sales analysis.';
    $insertSql = "INSERT INTO ice_beginning_inventory (inventory_date, prod_sales_class, qty_kg, remarks)
                  VALUES (?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)";
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssds', $inventoryDate, $class, $qtyKg, $remarks);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function prodSalesFetchBeginningInventory($mysqli, $prodSalesClass, $startDate)
{
    $empty = [
        'inventory_date' => '',
        'qty_kg' => 0.0,
        'remarks' => ''
    ];
    if (!$mysqli) {
        return $empty;
    }

    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$startObject) {
        return $empty;
    }
    $lookupObject = clone $startObject;
    $lookupObject->modify('-1 day');
    $lookupDate = $lookupObject->format('Y-m-d');

    $sql = "SELECT inventory_date, qty_kg, remarks
            FROM ice_beginning_inventory
            WHERE UPPER(TRIM(prod_sales_class)) = ?
              AND inventory_date <= ?
            ORDER BY inventory_date DESC, id DESC
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $empty;
    }

    $class = prodSalesNormalizeClass($prodSalesClass);
    $stmt->bind_param('ss', $class, $lookupDate);
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();
        if ($row) {
            return [
                'inventory_date' => isset($row['inventory_date']) ? (string)$row['inventory_date'] : '',
                'qty_kg' => isset($row['qty_kg']) ? (float)$row['qty_kg'] : 0.0,
                'remarks' => isset($row['remarks']) ? (string)$row['remarks'] : ''
            ];
        }

        return $empty;
    }
    $stmt->close();

    return $empty;
}

function prodSalesResolveSalesProductTypes($prodSalesClass)
{
    $prodSalesClass = prodSalesNormalizeClass($prodSalesClass);
    $map = [
        'PLATE' => ['PLATE', 'CRUSHED'],
        'TUBE' => ['REPACKED', 'TUBE'],
        'CUBE' => ['CUBE']
    ];

    return isset($map[$prodSalesClass]) ? $map[$prodSalesClass] : [$prodSalesClass];
}

function prodSalesResolveSalesProductType($productDescription, $productCode)
{
    $description = trim((string)$productDescription);
    if ($description !== '') {
        $parts = explode('-', $description, 2);
        $groupLabel = strtoupper(trim($parts[0]));
        if ($groupLabel !== '') {
            return $groupLabel;
        }
    }

    $code = trim((string)$productCode);
    if ($code !== '') {
        $parts = explode('-', $code);
        if (isset($parts[1]) && trim((string)$parts[1]) !== '') {
            return strtoupper(trim((string)$parts[1]));
        }

        return strtoupper($code);
    }

    return 'UNCLASSIFIED';
}

function prodSalesNormalizeSalesProductType($productType, $productDescription = '', $productCode = '')
{
    $productType = strtoupper(trim((string)$productType));
    if ($productType !== '') {
        return $productType;
    }

    return prodSalesResolveSalesProductType($productDescription, $productCode);
}

function prodSalesIsSalesProductTypeAllowed($productType, $prodSalesClass)
{
    return in_array(
        prodSalesNormalizeSalesProductType($productType),
        prodSalesResolveSalesProductTypes($prodSalesClass),
        true
    );
}

function prodSalesParseKgMultiplierFromDescription($description)
{
    $description = strtoupper(trim((string)$description));
    if ($description === '') {
        return 1.0;
    }

    if (preg_match('/\b([0-9]+(?:\.[0-9]+)?)\s*X\s*([0-9]+(?:\.[0-9]+)?)\s*KG\b/', $description, $matches)) {
        return (float)$matches[1] * (float)$matches[2];
    }

    if (preg_match('/\b([0-9]+(?:\.[0-9]+)?)\s*KG\b/', $description, $matches)) {
        return (float)$matches[1];
    }

    return 1.0;
}

function prodSalesResolveKgMultiplier($row)
{
    $umuPer = isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0;
    if ($umuPer > 0) {
        return $umuPer;
    }

    $uom = strtoupper(trim((string)(isset($row['uom']) ? $row['uom'] : '')));
    if ($uom === 'KG' || $uom === 'KGS') {
        return 1.0;
    }

    return prodSalesParseKgMultiplierFromDescription(isset($row['product_description']) ? $row['product_description'] : '');
}

function prodSalesResolveSalesDateTime($primaryValue, $fallbackValue)
{
    $primaryValue = trim((string)$primaryValue);
    $fallbackValue = trim((string)$fallbackValue);
    $dateTime = null;

    if ($primaryValue !== '' && $primaryValue !== '0000-00-00 00:00:00') {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $primaryValue);
    }
    if (!$dateTime && $fallbackValue !== '' && $fallbackValue !== '0000-00-00 00:00:00') {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $fallbackValue);
    }

    return $dateTime ?: null;
}

function prodSalesBuildSalesProductMeta($row)
{
    $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
    $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
    $unitPrice = isset($row['unit_price']) ? (float)$row['unit_price'] : 0.0;
    $productType = prodSalesNormalizeSalesProductType(
        isset($row['product_type']) ? $row['product_type'] : '',
        $productDescription,
        $productCode
    );
    $uom = trim((string)(isset($row['umu']) ? $row['umu'] : ''));
    if ($uom === '') {
        $uom = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
    }
    if ($uom === '') {
        $uom = 'KG';
    }

    return [
        'key' => strtoupper($productCode),
        'product_type' => $productType,
        'product_code' => $productCode,
        'product_description' => $productDescription,
        'unit_price' => $unitPrice,
        'uom' => strtoupper($uom),
        'kg_multiplier' => prodSalesResolveKgMultiplier($row)
    ];
}

function prodSalesFetchSalesRows($mysqli, $startDate, $endDate, $prodSalesClass)
{
    $rows = [];
    if (!$mysqli) {
        return $rows;
    }

    $hasUmu = prodSalesTableHasColumn($mysqli, 'ice_product', 'umu');
    $hasUmuPer = prodSalesTableHasColumn($mysqli, 'ice_product', 'umu_per');
    $umuSelect = $hasUmu ? 'p.umu' : "''";
    $umuPerSelect = $hasUmuPer ? 'p.umu_per' : '0';

    $sql = "SELECT s.order_no,
                   s.qty,
                   s.product_code,
                   s.product_description,
                   s.unit_price AS sales_unit_price,
                   s.date_created,
                   s.date_time_ordered,
                   p.product_type AS master_product_type,
                   p.product_description AS master_product_description,
                   p.unit_price AS master_unit_price,
                   p.uom,
                   {$umuSelect} AS umu,
                   {$umuPerSelect} AS umu_per
            FROM sales s
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            WHERE DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) BETWEEN ? AND ?
              AND (s.void_status IS NULL OR s.void_status = 0)
            ORDER BY COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created),
                     s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('ss', $startDate, $endDate);
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        while ($row = $result->fetch_assoc()) {
            $orderedAt = prodSalesResolveSalesDateTime(
                isset($row['date_time_ordered']) ? $row['date_time_ordered'] : '',
                isset($row['date_created']) ? $row['date_created'] : ''
            );
            $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
            if ($productDescription === '') {
                $productDescription = trim((string)(isset($row['master_product_description']) ? $row['master_product_description'] : ''));
            }
            $productType = prodSalesNormalizeSalesProductType(
                isset($row['master_product_type']) ? $row['master_product_type'] : '',
                $productDescription,
                isset($row['product_code']) ? $row['product_code'] : ''
            );
            if (!prodSalesIsSalesProductTypeAllowed($productType, $prodSalesClass)) {
                continue;
            }

            $sourceRow = [
                'product_description' => $productDescription,
                'uom' => isset($row['uom']) ? $row['uom'] : '',
                'umu' => isset($row['umu']) ? $row['umu'] : '',
                'umu_per' => isset($row['umu_per']) ? $row['umu_per'] : 0
            ];
            $kgMultiplier = prodSalesResolveKgMultiplier($sourceRow);

            $rows[] = [
                'date' => $orderedAt ? $orderedAt->format('Y-m-d') : '',
                'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                'product_type' => $productType,
                'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                'product_description' => $productDescription,
                'unit_price' => isset($row['master_unit_price']) && (float)$row['master_unit_price'] > 0
                    ? (float)$row['master_unit_price']
                    : (isset($row['sales_unit_price']) ? (float)$row['sales_unit_price'] : 0.0),
                'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : '')),
                'umu' => trim((string)(isset($row['umu']) ? $row['umu'] : '')),
                'umu_per' => $kgMultiplier,
                'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                'kg_qty' => (isset($row['qty']) ? (float)$row['qty'] : 0.0) * $kgMultiplier
            ];
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

function prodSalesFetchSalesProductColumns($mysqli, $prodSalesClass, $salesRows)
{
    $columnsByKey = [];
    $soldProductKeys = [];
    foreach ($salesRows as $row) {
        $productKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        $soldQty = isset($row['kg_qty']) ? (float)$row['kg_qty'] : (isset($row['qty']) ? (float)$row['qty'] : 0.0);
        if ($productKey !== '' && abs($soldQty) >= 0.005) {
            $soldProductKeys[$productKey] = true;
        }
    }

    $appendColumn = function ($row) use (&$columnsByKey, $soldProductKeys, $prodSalesClass) {
        $meta = prodSalesBuildSalesProductMeta($row);
        if (
            $meta['key'] === ''
            || !isset($soldProductKeys[$meta['key']])
            || !prodSalesIsSalesProductTypeAllowed($meta['product_type'], $prodSalesClass)
        ) {
            return;
        }

        if (!isset($columnsByKey[$meta['key']])) {
            $columnsByKey[$meta['key']] = $meta;
            return;
        }

        foreach (['product_description', 'uom'] as $field) {
            if ($columnsByKey[$meta['key']][$field] === '' && $meta[$field] !== '') {
                $columnsByKey[$meta['key']][$field] = $meta[$field];
            }
        }
        if ((float)$columnsByKey[$meta['key']]['unit_price'] <= 0 && (float)$meta['unit_price'] > 0) {
            $columnsByKey[$meta['key']]['unit_price'] = (float)$meta['unit_price'];
        }
    };

    if ($mysqli) {
        $hasUmu = prodSalesTableHasColumn($mysqli, 'ice_product', 'umu');
        $hasUmuPer = prodSalesTableHasColumn($mysqli, 'ice_product', 'umu_per');
        $hasStatus = prodSalesTableHasColumn($mysqli, 'ice_product', 'status');
        $umuSelect = $hasUmu ? 'umu' : "''";
        $umuPerSelect = $hasUmuPer ? 'umu_per' : '0';
        $statusWhere = $hasStatus ? 'WHERE (status IS NULL OR status != 1)' : '';
        $sql = "SELECT product_code,
                       product_description,
                       product_type,
                       unit_price,
                       uom,
                       {$umuSelect} AS umu,
                       {$umuPerSelect} AS umu_per
                FROM ice_product
                {$statusWhere}
                ORDER BY product_type, product_code, product_description";
        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $appendColumn($row);
            }
            $result->free();
        }
    }

    foreach ($salesRows as $row) {
        $appendColumn($row);
    }

    $columns = array_values($columnsByKey);
    usort($columns, function ($left, $right) {
        $leftType = strtoupper(trim((string)$left['product_type']));
        $rightType = strtoupper(trim((string)$right['product_type']));
        $typeOrder = ['REPACKED' => 10, 'TUBE' => 20, 'CRUSHED' => 10, 'CUBE' => 20, 'PLATE' => 10];
        $leftOrder = isset($typeOrder[$leftType]) ? $typeOrder[$leftType] : 999;
        $rightOrder = isset($typeOrder[$rightType]) ? $typeOrder[$rightType] : 999;
        if ($leftOrder !== $rightOrder) {
            return $leftOrder < $rightOrder ? -1 : 1;
        }
        if ($leftType !== $rightType) {
            return strcmp($leftType, $rightType);
        }

        return strcasecmp((string)$left['product_code'], (string)$right['product_code']);
    });

    return $columns;
}

function prodSalesBuildSalesProductGroups($salesProductColumns)
{
    $groups = [];
    foreach ($salesProductColumns as $column) {
        $productType = trim((string)(isset($column['product_type']) ? $column['product_type'] : ''));
        if ($productType === '') {
            $productType = 'UNCLASSIFIED';
        }
        $productTypeKey = strtoupper($productType);
        if (empty($groups) || $groups[count($groups) - 1]['key'] !== $productTypeKey) {
            $groups[] = [
                'key' => $productTypeKey,
                'label' => $productType,
                'products' => []
            ];
        }
        $groups[count($groups) - 1]['products'][] = $column;
    }

    return $groups;
}

function prodSalesBuildSalesMatrix($salesRows, $startDate, $endDate, $salesProductColumns)
{
    $matrixRows = [];
    $productKeys = [];
    foreach ($salesProductColumns as $column) {
        $productKeys[$column['key']] = true;
    }

    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($startObject && $endObject) {
        $currentObject = clone $startObject;
        while ($currentObject <= $endObject) {
            $dateKey = $currentObject->format('Y-m-d');
            $matrixRows[$dateKey] = [
                'date' => $dateKey,
                'date_label' => $currentObject->format('d-M-y'),
                'products' => []
            ];
            foreach ($salesProductColumns as $column) {
                $matrixRows[$dateKey]['products'][$column['key']] = 0.0;
            }
            $currentObject->modify('+1 day');
        }
    }

    foreach ($salesRows as $row) {
        $dateKey = trim((string)(isset($row['date']) ? $row['date'] : ''));
        $productKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($dateKey === '' || $productKey === '' || !isset($matrixRows[$dateKey]) || !isset($productKeys[$productKey])) {
            continue;
        }

        $matrixRows[$dateKey]['products'][$productKey] += isset($row['kg_qty']) ? (float)$row['kg_qty'] : 0.0;
    }

    return array_values($matrixRows);
}

function prodSalesCalculateSalesTotalQty($salesRows)
{
    $total = 0.0;
    foreach ($salesRows as $row) {
        $total += isset($row['kg_qty']) ? (float)$row['kg_qty'] : 0.0;
    }

    return $total;
}

function prodSalesCalculateProductionTotalQty($productionRows)
{
    $total = 0.0;
    foreach ($productionRows as $row) {
        $total += isset($row['production_qty']) ? (float)$row['production_qty'] : 0.0;
    }

    return $total;
}

function prodSalesCalculateOpeningInventory($mysqli, $prodSalesClass, $startDate)
{
    $beginning = prodSalesFetchBeginningInventory($mysqli, $prodSalesClass, $startDate);
    $opening = [
        'inventory_date' => $beginning['inventory_date'],
        'source_qty_kg' => isset($beginning['qty_kg']) ? (float)$beginning['qty_kg'] : 0.0,
        'qty_kg' => isset($beginning['qty_kg']) ? (float)$beginning['qty_kg'] : 0.0,
        'movement_start_date' => '',
        'movement_end_date' => '',
        'movement_production_qty' => 0.0,
        'movement_sales_qty' => 0.0,
        'remarks' => isset($beginning['remarks']) ? (string)$beginning['remarks'] : ''
    ];

    if ($beginning['inventory_date'] === '') {
        return $opening;
    }

    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $beginningObject = DateTime::createFromFormat('Y-m-d', $beginning['inventory_date']);
    if (!$startObject || !$beginningObject) {
        return $opening;
    }

    $movementStartObject = clone $beginningObject;
    $movementStartObject->modify('+1 day');
    $movementEndObject = clone $startObject;
    $movementEndObject->modify('-1 day');
    if ($movementStartObject > $movementEndObject) {
        return $opening;
    }

    $movementStartDate = $movementStartObject->format('Y-m-d');
    $movementEndDate = $movementEndObject->format('Y-m-d');
    $movementProductionQty = prodSalesCalculateProductionTotalQty(
        prodSalesFetchProductionRows($mysqli, $movementStartDate, $movementEndDate, $prodSalesClass)
    );
    $movementSalesQty = prodSalesCalculateSalesTotalQty(
        prodSalesFetchSalesRows($mysqli, $movementStartDate, $movementEndDate, $prodSalesClass)
    );

    $opening['movement_start_date'] = $movementStartDate;
    $opening['movement_end_date'] = $movementEndDate;
    $opening['movement_production_qty'] = $movementProductionQty;
    $opening['movement_sales_qty'] = $movementSalesQty;
    $opening['qty_kg'] = $opening['source_qty_kg'] + $movementProductionQty - $movementSalesQty;

    return $opening;
}

function prodSalesFormatSalesProductHeader($salesProduct)
{
    $description = trim((string)(isset($salesProduct['product_description']) ? $salesProduct['product_description'] : ''));
    $unitLabel = trim((string)(isset($salesProduct['uom']) ? $salesProduct['uom'] : 'KG'));
    if ($unitLabel === '') {
        $unitLabel = 'KG';
    }

    $label = $description . "\n(" . strtoupper($unitLabel) . ')';
    $unitPrice = isset($salesProduct['unit_price']) ? (float)$salesProduct['unit_price'] : 0.0;
    if ($unitPrice > 0) {
        $priceLabel = number_format($unitPrice, 2, '.', '');
        $priceLabel = rtrim(rtrim($priceLabel, '0'), '.');
        $label .= ' | ' . $priceLabel . ' php';
    }

    return $label;
}
