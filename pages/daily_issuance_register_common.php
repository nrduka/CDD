<?php

function validateDailyIssuanceDateRange($startInput, $endInput)
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

function buildDailyIssuanceDateColumns($startDate, $endDate)
{
    $columns = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);

    foreach ($period as $date) {
        $key = $date->format('Y-m-d');
        $columns[$key] = [
            'date' => $key,
            'date_label' => $date->format('d-M-y'),
            'day_label' => strtoupper($date->format('l'))
        ];
    }

    return $columns;
}

function buildDailyIssuanceReportColumns($dateColumns)
{
    $columns = [];
    $weekNumber = 1;
    $weekDateKeys = [];
    $lastDateKey = '';
    if (!empty($dateColumns)) {
        $dateKeys = array_keys($dateColumns);
        $lastDateKey = (string)end($dateKeys);
    }

    foreach ($dateColumns as $dateKey => $dateMeta) {
        $columns[] = [
            'type' => 'date',
            'date_key' => $dateKey,
            'date_label' => isset($dateMeta['date_label']) ? (string)$dateMeta['date_label'] : $dateKey,
            'day_label' => isset($dateMeta['day_label']) ? (string)$dateMeta['day_label'] : ''
        ];
        $weekDateKeys[] = $dateKey;

        $dayLabel = isset($dateMeta['day_label']) ? strtoupper((string)$dateMeta['day_label']) : '';
        if ($dayLabel === 'SUNDAY' || (string)$dateKey === $lastDateKey) {
            $columns[] = [
                'type' => 'weekly_subtotal',
                'week_number' => $weekNumber,
                'date_keys' => $weekDateKeys,
                'date_label' => 'WEEK ' . $weekNumber,
                'day_label' => 'SUB-TOTAL'
            ];
            $weekNumber++;
            $weekDateKeys = [];
        }
    }

    $columns[] = [
        'type' => 'month_total',
        'date_label' => 'MONTH',
        'day_label' => 'TOTAL'
    ];

    return $columns;
}

function initializeDailyIssuanceDailyQty($dateColumns)
{
    $dailyQty = [];
    foreach ($dateColumns as $dateKey => $dateMeta) {
        $dailyQty[$dateKey] = 0.0;
    }

    return $dailyQty;
}

function addDailyIssuanceQtyToRow(&$row, $dateKey, $qty)
{
    if (!isset($row['daily_qty']) || !is_array($row['daily_qty'])) {
        $row['daily_qty'] = [];
    }
    if (!isset($row['daily_qty'][$dateKey])) {
        $row['daily_qty'][$dateKey] = 0.0;
    }

    $row['daily_qty'][$dateKey] += (float)$qty;
    if (!isset($row['subtotal'])) {
        $row['subtotal'] = 0.0;
    }
    $row['subtotal'] += (float)$qty;
}

function calculateDailyIssuanceColumnQty($row, $column)
{
    $type = isset($column['type']) ? (string)$column['type'] : '';
    if ($type === 'date') {
        $dateKey = isset($column['date_key']) ? (string)$column['date_key'] : '';
        return isset($row['daily_qty'][$dateKey]) ? (float)$row['daily_qty'][$dateKey] : 0.0;
    }

    if ($type === 'weekly_subtotal') {
        $subtotal = 0.0;
        $dateKeys = isset($column['date_keys']) && is_array($column['date_keys']) ? $column['date_keys'] : [];
        foreach ($dateKeys as $dateKey) {
            $subtotal += isset($row['daily_qty'][$dateKey]) ? (float)$row['daily_qty'][$dateKey] : 0.0;
        }
        return $subtotal;
    }

    if ($type === 'month_total') {
        return isset($row['subtotal']) ? (float)$row['subtotal'] : 0.0;
    }

    return 0.0;
}

function formatDailyIssuanceRangeLabel($startDate, $endDate)
{
    $startLabel = date('F d, Y', strtotime($startDate));
    $endLabel = date('F d, Y', strtotime($endDate));
    return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
}

function buildDailyIssuanceSourceChannelLabel($row)
{
    $channel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
    if ($channel !== '') {
        return $channel;
    }

    $customerSource = trim((string)(isset($row['customer_source']) ? $row['customer_source'] : ''));
    if ($customerSource !== '' && $customerSource !== '0') {
        return 'SOURCE ' . $customerSource;
    }

    return 'UNASSIGNED';
}

function resolveDailyIssuanceProductType($description, $productCode)
{
    $description = trim((string)$description);
    if ($description !== '') {
        $parts = explode('-', $description, 2);
        $type = strtoupper(trim($parts[0]));
        if ($type !== '') {
            return $type;
        }
    }

    $productCode = trim((string)$productCode);
    if ($productCode !== '') {
        $parts = explode('-', $productCode);
        if (isset($parts[1]) && trim((string)$parts[1]) !== '') {
            return strtoupper(trim((string)$parts[1]));
        }
        return strtoupper($productCode);
    }

    return 'UNCLASSIFIED';
}

function formatDailyIssuanceUnitPriceLabel($unitPrice)
{
    $unitPrice = (float)$unitPrice;
    if (abs($unitPrice - round($unitPrice)) < 0.00001) {
        return number_format($unitPrice, 0);
    }

    return number_format($unitPrice, 2);
}

function buildDailyIssuanceItemDescription($description, $salesUom, $unitPrice = null)
{
    $description = trim((string)$description);
    $salesUom = trim((string)$salesUom);
    $displayDescription = $description;

    if ($salesUom !== '' && stripos($displayDescription, '(' . $salesUom . ')') === false) {
        $displayDescription .= ' (' . $salesUom . ')';
    }

    if ($unitPrice !== null && is_numeric($unitPrice)) {
        $displayDescription .= ' @ ' . formatDailyIssuanceUnitPriceLabel($unitPrice) . ' PHP';
    }

    return $displayDescription;
}

function resolveDailyIssuanceMultiplier($row)
{
    $umuPer = isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0;
    if ($umuPer > 0) {
        return $umuPer;
    }

    $description = trim((string)(isset($row['master_product_description']) ? $row['master_product_description'] : ''));
    if ($description === '') {
        $description = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
    }
    if (preg_match('/(?:X|\b)(\d+(?:\.\d+)?)\s*KG\b/i', $description, $matches)) {
        return (float)$matches[1];
    }

    $umoPer = isset($row['umo_per']) ? (float)$row['umo_per'] : 0.0;
    if ($umoPer > 0) {
        return $umoPer;
    }

    return 1.0;
}

function initializeDailyIssuanceRow($sourceRow, $dateColumns)
{
    $productCode = trim((string)(isset($sourceRow['product_code']) ? $sourceRow['product_code'] : ''));
    $description = trim((string)(isset($sourceRow['master_product_description']) ? $sourceRow['master_product_description'] : ''));
    if ($description === '') {
        $description = trim((string)(isset($sourceRow['product_description']) ? $sourceRow['product_description'] : ''));
    }

    $productType = trim((string)(isset($sourceRow['product_type']) ? $sourceRow['product_type'] : ''));
    if ($productType === '') {
        $productType = resolveDailyIssuanceProductType($description, $productCode);
    }

    $salesUom = trim((string)(isset($sourceRow['uom']) ? $sourceRow['uom'] : ''));
    $issueUom = trim((string)(isset($sourceRow['umu']) ? $sourceRow['umu'] : ''));
    if ($issueUom === '') {
        $issueUom = $salesUom;
    }

    return [
        'product_type' => strtoupper($productType),
        'product_code' => $productCode,
        'item_description' => buildDailyIssuanceItemDescription($description, $salesUom, isset($sourceRow['unit_price']) ? (float)$sourceRow['unit_price'] : 0.0),
        'unit_price' => isset($sourceRow['unit_price']) ? (float)$sourceRow['unit_price'] : 0.0,
        'uom' => $issueUom,
        'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
        'subtotal' => 0.0
    ];
}

function fetchDailyIssuanceRegisterData($mysqli, $startDate, $endDate)
{
    $dateColumns = buildDailyIssuanceDateColumns($startDate, $endDate);
    $reportColumns = buildDailyIssuanceReportColumns($dateColumns);
    $sourceGroupsByKey = [];
    $productKeys = [];

    $salesSql = "SELECT DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) AS sale_date,
                        s.product_code,
                        s.product_description,
                        SUM(s.qty) AS sales_qty,
                        MAX(s.unit_price) AS sales_unit_price,
                        c.customer_source,
                        sc.customer_channel AS source_channel,
                        p.product_type,
                        p.product_description AS master_product_description,
                        p.uom,
                        p.umo_per,
                        p.umu,
                        p.umu_per,
                        p.unit_price
                 FROM sales s
                 LEFT JOIN customer_register c ON c.customer_code = s.customer_code
                 LEFT JOIN source_channel sc ON sc.customer_source = c.customer_source
                 LEFT JOIN ice_product p ON p.product_code = s.product_code
                 WHERE DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) BETWEEN ? AND ?
                   AND (s.void_status IS NULL OR s.void_status = 0)
                 GROUP BY sale_date,
                          c.customer_source,
                          sc.customer_channel,
                          s.product_code,
                          s.product_description,
                          p.product_type,
                          p.product_description,
                          p.uom,
                          p.umo_per,
                          p.umu,
                          p.umu_per,
                          p.unit_price
                 ORDER BY p.product_type, s.product_code, sale_date";
    $stmt = $mysqli->prepare($salesSql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($saleRow = $result->fetch_assoc()) {
                $dateKey = isset($saleRow['sale_date']) ? (string)$saleRow['sale_date'] : '';
                if (!isset($dateColumns[$dateKey])) {
                    continue;
                }

                $productKey = strtoupper(trim((string)(isset($saleRow['product_code']) ? $saleRow['product_code'] : '')));
                if ($productKey === '') {
                    $productKey = '__NO_CODE_' . count($productKeys);
                }
                $productKeys[$productKey] = true;

                $sourceChannel = buildDailyIssuanceSourceChannelLabel($saleRow);
                $sourceSort = isset($saleRow['customer_source']) && is_numeric($saleRow['customer_source']) ? (int)$saleRow['customer_source'] : 999999;
                $sourceKey = str_pad((string)$sourceSort, 6, '0', STR_PAD_LEFT) . '|' . strtoupper($sourceChannel);
                if (!isset($sourceGroupsByKey[$sourceKey])) {
                    $sourceGroupsByKey[$sourceKey] = [
                        'source_channel' => $sourceChannel,
                        'customer_source' => isset($saleRow['customer_source']) ? (string)$saleRow['customer_source'] : '',
                        'product_groups_by_key' => [],
                        'subtotal_row' => [
                            'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
                            'subtotal' => 0.0
                        ]
                    ];
                }

                if (!isset($saleRow['unit_price']) || (float)$saleRow['unit_price'] <= 0) {
                    $saleRow['unit_price'] = isset($saleRow['sales_unit_price']) ? (float)$saleRow['sales_unit_price'] : 0.0;
                }
                $itemRow = initializeDailyIssuanceRow($saleRow, $dateColumns);
                $productType = trim((string)$itemRow['product_type']);
                if ($productType === '') {
                    $productType = 'UNCLASSIFIED';
                }
                if (!isset($sourceGroupsByKey[$sourceKey]['product_groups_by_key'][$productType])) {
                    $sourceGroupsByKey[$sourceKey]['product_groups_by_key'][$productType] = [
                        'product_type' => $productType,
                        'rows_by_key' => [],
                        'subtotal_row' => [
                            'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
                            'subtotal' => 0.0
                        ]
                    ];
                }
                if (!isset($sourceGroupsByKey[$sourceKey]['product_groups_by_key'][$productType]['rows_by_key'][$productKey])) {
                    $sourceGroupsByKey[$sourceKey]['product_groups_by_key'][$productType]['rows_by_key'][$productKey] = $itemRow;
                }

                $qty = isset($saleRow['sales_qty']) ? (float)$saleRow['sales_qty'] : 0.0;
                $issuedQty = $qty * resolveDailyIssuanceMultiplier($saleRow);
                addDailyIssuanceQtyToRow(
                    $sourceGroupsByKey[$sourceKey]['product_groups_by_key'][$productType]['rows_by_key'][$productKey],
                    $dateKey,
                    $issuedQty
                );
                addDailyIssuanceQtyToRow(
                    $sourceGroupsByKey[$sourceKey]['product_groups_by_key'][$productType]['subtotal_row'],
                    $dateKey,
                    $issuedQty
                );
                addDailyIssuanceQtyToRow($sourceGroupsByKey[$sourceKey]['subtotal_row'], $dateKey, $issuedQty);
            }
            $result->free();
        }
        $stmt->close();
    }

    ksort($sourceGroupsByKey, SORT_NATURAL | SORT_FLAG_CASE);
    $sourceGroups = [];
    $othersSourceGroups = [];
    $iceTotalIssuedQty = 0.0;
    $othersTotalIssuedQty = 0.0;
    $iceTotalRow = [
        'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
        'subtotal' => 0.0
    ];
    $othersTotalRow = [
        'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
        'subtotal' => 0.0
    ];

    foreach ($sourceGroupsByKey as $sourceGroup) {
        $productGroupsByKey = $sourceGroup['product_groups_by_key'];
        ksort($productGroupsByKey, SORT_NATURAL | SORT_FLAG_CASE);
        $productGroups = [];
        $othersProductGroups = [];
        $sourceSubtotalRow = [
            'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
            'subtotal' => 0.0
        ];
        $othersSourceSubtotalRow = [
            'daily_qty' => initializeDailyIssuanceDailyQty($dateColumns),
            'subtotal' => 0.0
        ];

        foreach ($productGroupsByKey as $productGroup) {
            $rows = array_values($productGroup['rows_by_key']);
            usort($rows, function ($left, $right) {
                return strcasecmp((string)$left['product_code'], (string)$right['product_code']);
            });
            $normalizedProductType = strtoupper(trim((string)$productGroup['product_type']));
            $targetGroup = [
                'product_type' => $productGroup['product_type'],
                'rows' => $rows,
                'subtotal_row' => $productGroup['subtotal_row']
            ];

            if ($normalizedProductType === 'OTHERS') {
                $othersProductGroups[] = $targetGroup;
                foreach ($dateColumns as $dateKey => $dateMeta) {
                    $othersSourceSubtotalRow['daily_qty'][$dateKey] += isset($productGroup['subtotal_row']['daily_qty'][$dateKey])
                        ? (float)$productGroup['subtotal_row']['daily_qty'][$dateKey]
                        : 0.0;
                    $othersTotalRow['daily_qty'][$dateKey] += isset($productGroup['subtotal_row']['daily_qty'][$dateKey])
                        ? (float)$productGroup['subtotal_row']['daily_qty'][$dateKey]
                        : 0.0;
                }
                $othersSourceSubtotalRow['subtotal'] += (float)$productGroup['subtotal_row']['subtotal'];
                $othersTotalRow['subtotal'] += (float)$productGroup['subtotal_row']['subtotal'];
                $othersTotalIssuedQty += (float)$productGroup['subtotal_row']['subtotal'];
                continue;
            }

            $productGroups[] = $targetGroup;
            foreach ($dateColumns as $dateKey => $dateMeta) {
                $sourceSubtotalRow['daily_qty'][$dateKey] += isset($productGroup['subtotal_row']['daily_qty'][$dateKey])
                    ? (float)$productGroup['subtotal_row']['daily_qty'][$dateKey]
                    : 0.0;
                $iceTotalRow['daily_qty'][$dateKey] += isset($productGroup['subtotal_row']['daily_qty'][$dateKey])
                    ? (float)$productGroup['subtotal_row']['daily_qty'][$dateKey]
                    : 0.0;
            }
            $sourceSubtotalRow['subtotal'] += (float)$productGroup['subtotal_row']['subtotal'];
            $iceTotalRow['subtotal'] += (float)$productGroup['subtotal_row']['subtotal'];
            $iceTotalIssuedQty += (float)$productGroup['subtotal_row']['subtotal'];
        }

        if (!empty($productGroups)) {
            $sourceGroups[] = [
                'source_channel' => $sourceGroup['source_channel'],
                'customer_source' => $sourceGroup['customer_source'],
                'product_groups' => $productGroups,
                'subtotal_row' => $sourceSubtotalRow
            ];
        }

        if (!empty($othersProductGroups)) {
            $othersSourceGroups[] = [
                'source_channel' => $sourceGroup['source_channel'],
                'customer_source' => $sourceGroup['customer_source'],
                'product_groups' => $othersProductGroups,
                'subtotal_row' => $othersSourceSubtotalRow
            ];
        }
    }

    return [
        'date_columns' => $dateColumns,
        'report_columns' => $reportColumns,
        'source_groups' => $sourceGroups,
        'others_source_groups' => $othersSourceGroups,
        'groups' => isset($sourceGroups[0]['product_groups']) ? $sourceGroups[0]['product_groups'] : [],
        'total_row' => $iceTotalRow,
        'others_total_row' => $othersTotalRow,
        'product_count' => count($productKeys),
        'source_channel_count' => count($sourceGroups),
        'others_source_channel_count' => count($othersSourceGroups),
        'total_issued_qty' => $iceTotalIssuedQty + $othersTotalIssuedQty
    ];
}
