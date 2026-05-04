<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'CASHIER', 'REGISTRATION', 'COUNTER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && $assignedDepartments === '') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

require_once __DIR__ . '/db2.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

if (
    !isset($mysqli)
    || !($mysqli instanceof mysqli)
    || (function_exists('mysqli_ping') && !@mysqli_ping($mysqli))
) {
    $fallbackHost = isset($databaseHost) ? (string)$databaseHost : 'localhost';
    $fallbackName = isset($databaseName) ? (string)$databaseName : 'cdd';
    $fallbackUser = isset($databaseUsername) ? (string)$databaseUsername : 'root';
    $fallbackPass = isset($databasePassword) ? (string)$databasePassword : '';
    $fallbackMysqli = @mysqli_connect($fallbackHost, $fallbackUser, $fallbackPass, $fallbackName);
    if ($fallbackMysqli instanceof mysqli) {
        $mysqli = $fallbackMysqli;
    }
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    $connectError = trim((string)mysqli_connect_error());
    echo $connectError !== '' ? 'Database connection unavailable. ' . $connectError : 'Database connection unavailable.';
    exit;
}

function sourceChannelColumnLetter($columnIndex)
{
    return Coordinate::stringFromColumnIndex((int)$columnIndex);
}

function sourceChannelResolveSalesDateTime($primaryValue, $fallbackValue)
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

function sourceChannelResolveProductTypeGroup($productDescription, $productCode)
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

function sourceChannelBuildLabel($row)
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

function sourceChannelFetchRows($mysqli, $startDate, $endDate)
{
    $rows = [];
    $sql = "SELECT s.order_no,
                   s.customer_code,
                   s.qty,
                   s.product_code,
                   s.product_description,
                   s.unit_price,
                   s.amount,
                   s.date_created,
                   s.date_time_ordered,
                   s.amt_received,
                   s.amt_change,
                   p.product_type AS master_product_type,
                   p.product_description AS master_product_description,
                   p.uom,
                   p.umu,
                   p.umu_per,
                   c.customer_source,
                   sc.customer_channel AS source_channel
             FROM sales s
             LEFT JOIN ice_product p ON p.product_code = s.product_code
             LEFT JOIN customer_register c ON c.customer_code = s.customer_code
             LEFT JOIN source_channel sc ON sc.customer_source = c.customer_source
             WHERE DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) BETWEEN ? AND ?
              AND (s.void_status IS NULL OR s.void_status = 0)
             ORDER BY c.customer_source,
                      COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created),
                      s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

    $stmt->bind_param('ss', $startDate, $endDate);
    if ($stmt->execute() && ($result = $stmt->get_result())) {
        while ($row = $result->fetch_assoc()) {
            $orderedAt = sourceChannelResolveSalesDateTime(
                isset($row['date_time_ordered']) ? $row['date_time_ordered'] : '',
                isset($row['date_created']) ? $row['date_created'] : ''
            );
            $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
            if ($productDescription === '') {
                $productDescription = trim((string)(isset($row['master_product_description']) ? $row['master_product_description'] : ''));
            }
            $productType = trim((string)(isset($row['master_product_type']) ? $row['master_product_type'] : ''));
            if ($productType === '') {
                $productType = sourceChannelResolveProductTypeGroup(
                    $productDescription,
                    isset($row['product_code']) ? $row['product_code'] : ''
                );
            }

            $rows[] = [
                'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                'product_description' => $productDescription,
                'product_type' => $productType,
                'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : '')),
                'umu' => trim((string)(isset($row['umu']) ? $row['umu'] : '')),
                'umu_per' => isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0,
                'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                'customer_source' => isset($row['customer_source']) ? (string)$row['customer_source'] : '',
                'source_channel' => sourceChannelBuildLabel($row),
                'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0.0,
                'amount' => isset($row['amount']) ? (float)$row['amount'] : 0.0,
                'amt_received' => isset($row['amt_received']) ? (float)$row['amt_received'] : 0.0,
                'amt_change' => isset($row['amt_change']) ? (float)$row['amt_change'] : 0.0,
                'date' => $orderedAt ? $orderedAt->format('Y-m-d') : ''
            ];
        }
        $result->free();
    }
    $stmt->close();

    return $rows;
}

function sourceChannelEnrichPayments($rows)
{
    $orderPayments = [];
    foreach ($rows as $index => $row) {
        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $amtReceived = isset($row['amt_received']) ? (float)$row['amt_received'] : 0.0;
        $amtChange = isset($row['amt_change']) ? (float)$row['amt_change'] : 0.0;

        if (!isset($orderPayments[$orderKey])) {
            $orderPayments[$orderKey] = [
                'first_index' => $index,
                'order_amount' => 0.0,
                'amt_received' => $amtReceived,
                'amt_change' => $amtChange,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        $orderPayments[$orderKey]['order_amount'] += $amount;
        if ($amtReceived > $orderPayments[$orderKey]['amt_received']) {
            $orderPayments[$orderKey]['amt_received'] = $amtReceived;
        }
        if ($amtChange > $orderPayments[$orderKey]['amt_change']) {
            $orderPayments[$orderKey]['amt_change'] = $amtChange;
        }
    }

    foreach ($orderPayments as &$payment) {
        $cash = $payment['amt_received'] - $payment['amt_change'];
        $payment['cash'] = $cash > 0 ? $cash : 0.0;
        $payment['on_account'] = $payment['amt_received'] <= 0 ? $payment['order_amount'] : 0.0;
    }
    unset($payment);

    foreach ($rows as $index => &$row) {
        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $payment = $orderPayments[$orderKey];
        $lineAmount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $orderAmount = (float)$payment['order_amount'];
        $allocationRatio = $orderAmount > 0 ? ($lineAmount / $orderAmount) : ($payment['first_index'] === $index ? 1.0 : 0.0);

        $row['cash_once'] = $payment['first_index'] === $index ? (float)$payment['cash'] : 0.0;
        $row['on_account_once'] = $payment['first_index'] === $index ? (float)$payment['on_account'] : 0.0;
        $row['cash_allocated'] = (float)$payment['cash'] * $allocationRatio;
        $row['on_account_allocated'] = (float)$payment['on_account'] * $allocationRatio;
    }
    unset($row);

    return $rows;
}

function sourceChannelConvertRowsToUmu($rows)
{
    $convertedRows = [];

    foreach ($rows as $row) {
        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $umuPer = isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0;
        if ($umuPer <= 0) {
            $umuPer = 1.0;
        }

        $umuUnit = trim((string)(isset($row['umu']) ? $row['umu'] : ''));
        if ($umuUnit === '') {
            $umuUnit = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        }

        $row['qty'] = $qty * $umuPer;
        $row['uom'] = $umuUnit;
        $convertedRows[] = $row;
    }

    return $convertedRows;
}

function sourceChannelMapRowsToMetric($rows, $metricKey)
{
    $mappedRows = [];
    foreach ($rows as $row) {
        $row['qty'] = isset($row[$metricKey]) ? (float)$row[$metricKey] : 0.0;
        $mappedRows[] = $row;
    }

    return $mappedRows;
}

function sourceChannelResolvePivotSection($row)
{
    $productCode = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
    if ($productCode === '') {
        return ['section' => '', 'product_key' => ''];
    }

    $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
    if ($productType === '') {
        $productType = sourceChannelResolveProductTypeGroup(
            isset($row['product_description']) ? $row['product_description'] : '',
            isset($row['product_code']) ? $row['product_code'] : ''
        );
    }

    return [
        'section' => strtoupper($productType) === 'OTHERS' ? 'others' : 'ice',
        'product_key' => $productCode
    ];
}

function sourceChannelBuildPivotColumns($rows, $section)
{
    $columns = [];

    foreach ($rows as $row) {
        $pivotMeta = sourceChannelResolvePivotSection($row);
        if ($pivotMeta['section'] !== $section || $pivotMeta['product_key'] === '') {
            continue;
        }

        $productKey = $pivotMeta['product_key'];
        $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
        $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
        $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
        $uom = trim((string)(isset($row['uom']) ? $row['uom'] : ''));

        if (!isset($columns[$productKey])) {
            $columns[$productKey] = [
                'product_key' => $productKey,
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'product_type' => $productType,
                'uom' => $uom
            ];
        }

        if ($columns[$productKey]['product_description'] === '' && $productDescription !== '') {
            $columns[$productKey]['product_description'] = $productDescription;
        }
        if ($columns[$productKey]['product_type'] === '' && $productType !== '') {
            $columns[$productKey]['product_type'] = $productType;
        }
        if ($columns[$productKey]['uom'] === '' && $uom !== '') {
            $columns[$productKey]['uom'] = $uom;
        }
    }

    $pivotRows = array_values($columns);
    usort($pivotRows, function ($left, $right) use ($section) {
        if ($section === 'ice') {
            $leftType = strtoupper(trim((string)$left['product_type']));
            $rightType = strtoupper(trim((string)$right['product_type']));
            if ($leftType !== $rightType) {
                return strcmp($leftType, $rightType);
            }
        }

        return strcmp(
            strtoupper(trim((string)$left['product_code'])),
            strtoupper(trim((string)$right['product_code']))
        );
    });

    return $pivotRows;
}

function sourceChannelMergePivotColumnsForUmu($basePivotRows, $umuPivotRows)
{
    $umuByCode = [];
    foreach ($umuPivotRows as $row) {
        $codeKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($codeKey !== '') {
            $umuByCode[$codeKey] = $row;
        }
    }

    $merged = [];
    foreach ($basePivotRows as $row) {
        $codeKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($codeKey !== '' && isset($umuByCode[$codeKey])) {
            $umuUnit = trim((string)(isset($umuByCode[$codeKey]['uom']) ? $umuByCode[$codeKey]['uom'] : ''));
            if ($umuUnit !== '') {
                $row['uom'] = $umuUnit;
            }
        }
        $merged[] = $row;
    }

    return $merged;
}

function sourceChannelBuildSummaryRows($rows, $transactionMetricKey = null)
{
    $sourceRows = [];

    foreach ($rows as $index => $row) {
        $sourceChannel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
        if ($sourceChannel === '') {
            $sourceChannel = 'UNASSIGNED';
        }
        $sourceSort = isset($row['customer_source']) && is_numeric($row['customer_source']) ? (int)$row['customer_source'] : 999999;
        $sourceKey = $sourceSort . '|' . strtoupper($sourceChannel);

        if (!isset($sourceRows[$sourceKey])) {
            $sourceRows[$sourceKey] = [
                'source_channel' => $sourceChannel,
                'source_sort' => $sourceSort,
                'transaction_orders' => [],
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0,
                'products' => ['ice' => [], 'others' => []]
            ];
        }

        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $shouldCountTransaction = true;
        if ($transactionMetricKey !== null) {
            $shouldCountTransaction = isset($row[$transactionMetricKey]) && (float)$row[$transactionMetricKey] > 0.0;
        }
        if ($shouldCountTransaction) {
            $sourceRows[$sourceKey]['transaction_orders'][$orderKey] = true;
        }
        $sourceRows[$sourceKey]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $sourceRows[$sourceKey]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $sourceRows[$sourceKey]['cash'] += isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0;
        $sourceRows[$sourceKey]['on_account'] += isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0;

        $pivotMeta = sourceChannelResolvePivotSection($row);
        $section = $pivotMeta['section'];
        $productKey = $pivotMeta['product_key'];
        if ($section !== '' && $productKey !== '') {
            if (!isset($sourceRows[$sourceKey]['products'][$section][$productKey])) {
                $sourceRows[$sourceKey]['products'][$section][$productKey] = 0.0;
            }
            $sourceRows[$sourceKey]['products'][$section][$productKey] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        }
    }

    foreach ($sourceRows as &$sourceRow) {
        $sourceRow['transaction_count'] = count($sourceRow['transaction_orders']);
        unset($sourceRow['transaction_orders']);
    }
    unset($sourceRow);

    $sourceRows = array_values($sourceRows);
    usort($sourceRows, function ($left, $right) {
        $leftSourceSort = isset($left['source_sort']) ? (int)$left['source_sort'] : 999999;
        $rightSourceSort = isset($right['source_sort']) ? (int)$right['source_sort'] : 999999;
        if ($leftSourceSort !== $rightSourceSort) {
            return $leftSourceSort < $rightSourceSort ? -1 : 1;
        }

        return strcasecmp((string)$left['source_channel'], (string)$right['source_channel']);
    });

    return $sourceRows;
}

function sourceChannelWriteSummarySheet(
    $sheet,
    $sheetTitle,
    $rangeLabel,
    $generatedAt,
    $sourceRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle,
    $metricHeader,
    $showAmountColumns = false
) {
    $sheet->setCellValue('A1', $sheetTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
    $sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

    $sectionRow = 6;
    $productTypeRow = 7;
    $headerRow = 8;
    $dataStartRow = 9;

    $baseHeaders = ['NO.', 'SOURCE CHANNEL', 'TRANSACTION COUNT', $metricHeader];
    if ($showAmountColumns) {
        $baseHeaders[] = 'CASH';
        $baseHeaders[] = 'ON ACCOUNT';
    }

    $baseLastColumnIndex = count($baseHeaders);
    $baseLastColumnLetter = sourceChannelColumnLetter($baseLastColumnIndex);
    $sheet->fromArray($baseHeaders, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:{$baseLastColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:{$baseLastColumnLetter}{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(28);
    for ($i = 3; $i <= $baseLastColumnIndex; $i++) {
        $sheet->getColumnDimension(sourceChannelColumnLetter($i))->setAutoSize(false)->setWidth(16);
    }

    $currentColumnIndex = $baseLastColumnIndex + 1;
    $productColumnsBySection = ['ice' => [], 'others' => []];

    $renderPivotSection = function ($sectionKey, $sectionLabel, $pivotRows) use (
        $sheet,
        $sectionRow,
        $productTypeRow,
        $headerRow,
        $headerStyle,
        &$currentColumnIndex,
        &$productColumnsBySection
    ) {
        if (empty($pivotRows)) {
            return;
        }

        $startColumnIndex = $currentColumnIndex;
        $typeGroups = [];

        foreach ($pivotRows as $pivotData) {
            $columnLetter = sourceChannelColumnLetter($currentColumnIndex);
            $productCode = trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : ''));
            $productDescription = trim((string)(isset($pivotData['product_description']) ? $pivotData['product_description'] : ''));
            $productType = trim((string)(isset($pivotData['product_type']) ? $pivotData['product_type'] : ''));
            if ($productType === '') {
                $productType = 'UNCLASSIFIED';
            }
            $uom = trim((string)(isset($pivotData['uom']) ? $pivotData['uom'] : ''));
            $label = $productCode !== '' ? $productCode : ($productDescription !== '' ? $productDescription : 'PRODUCT');
            if ($uom !== '') {
                $label .= "\n(" . $uom . ')';
            }

            $sheet->setCellValueExplicit($columnLetter . $headerRow, $label, DataType::TYPE_STRING);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(false)->setWidth(14);

            $productKey = strtoupper($productCode);
            if ($productKey !== '') {
                $productColumnsBySection[$sectionKey][$productKey] = [
                    'column' => $columnLetter,
                    'product_code' => $productCode
                ];
            }

            if ($sectionKey === 'ice') {
                $productTypeKey = strtoupper($productType);
                if (empty($typeGroups) || $typeGroups[count($typeGroups) - 1]['key'] !== $productTypeKey) {
                    $typeGroups[] = [
                        'key' => $productTypeKey,
                        'label' => $productType,
                        'start_column_index' => $currentColumnIndex,
                        'end_column_index' => $currentColumnIndex
                    ];
                } else {
                    $typeGroups[count($typeGroups) - 1]['end_column_index'] = $currentColumnIndex;
                }
            }

            $currentColumnIndex++;
        }

        $endColumnIndex = $currentColumnIndex - 1;
        if ($endColumnIndex < $startColumnIndex) {
            return;
        }

        $startColumnLetter = sourceChannelColumnLetter($startColumnIndex);
        $endColumnLetter = sourceChannelColumnLetter($endColumnIndex);
        if ($startColumnLetter !== $endColumnLetter) {
            $sheet->mergeCells("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}");
        }
        $sheet->setCellValue("{$startColumnLetter}{$sectionRow}", $sectionLabel);
        $sheet->getStyle("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        if ($sectionKey === 'ice') {
            foreach ($typeGroups as $typeGroup) {
                $typeStartColumnLetter = sourceChannelColumnLetter($typeGroup['start_column_index']);
                $typeEndColumnLetter = sourceChannelColumnLetter($typeGroup['end_column_index']);
                if ($typeStartColumnLetter !== $typeEndColumnLetter) {
                    $sheet->mergeCells("{$typeStartColumnLetter}{$productTypeRow}:{$typeEndColumnLetter}{$productTypeRow}");
                }
                $sheet->setCellValue("{$typeStartColumnLetter}{$productTypeRow}", $typeGroup['label']);
                $sheet->getStyle("{$typeStartColumnLetter}{$productTypeRow}:{$typeEndColumnLetter}{$productTypeRow}")->applyFromArray($headerStyle);
                $sheet->getStyle("{$typeStartColumnLetter}{$productTypeRow}:{$typeEndColumnLetter}{$productTypeRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
            }
        } else {
            if ($startColumnLetter !== $endColumnLetter) {
                $sheet->mergeCells("{$startColumnLetter}{$productTypeRow}:{$endColumnLetter}{$productTypeRow}");
            }
            $sheet->setCellValue("{$startColumnLetter}{$productTypeRow}", 'OTHERS');
            $sheet->getStyle("{$startColumnLetter}{$productTypeRow}:{$endColumnLetter}{$productTypeRow}")->applyFromArray($headerStyle);
            $sheet->getStyle("{$startColumnLetter}{$productTypeRow}:{$endColumnLetter}{$productTypeRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        $sheet->getStyle("{$startColumnLetter}{$headerRow}:{$endColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$startColumnLetter}{$headerRow}:{$endColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    };

    $renderPivotSection('ice', 'ICE PRODUCTS', $icePivotRows);
    $renderPivotSection('others', 'OTHERS PRODUCTS', $otherPivotRows);

    $lastColumnIndex = max($baseLastColumnIndex, $currentColumnIndex - 1);
    $lastColumnLetter = sourceChannelColumnLetter($lastColumnIndex);

    $currentRow = $dataStartRow;
    $lineNumber = 1;
    $grandTotals = [
        'transactions' => 0,
        'qty' => 0.0,
        'cash' => 0.0,
        'on_account' => 0.0,
        'products' => ['ice' => [], 'others' => []]
    ];
    foreach ($productColumnsBySection as $sectionKey => $columns) {
        foreach ($columns as $productKey => $meta) {
            $grandTotals['products'][$sectionKey][$productKey] = 0.0;
        }
    }

    if (empty($sourceRows)) {
        $sheet->mergeCells("A{$currentRow}:{$lastColumnLetter}{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'No sales were recorded for the selected date range.');
        $sheet->getStyle("A{$currentRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $currentRow++;
    } else {
        foreach ($sourceRows as $sourceRow) {
            $sheet->setCellValue("A{$currentRow}", $lineNumber++);
            $sheet->setCellValue("B{$currentRow}", (string)$sourceRow['source_channel']);
            $sheet->setCellValue("C{$currentRow}", isset($sourceRow['transaction_count']) ? (int)$sourceRow['transaction_count'] : 0);
            $sheet->setCellValue("D{$currentRow}", isset($sourceRow['qty']) ? (float)$sourceRow['qty'] : 0.0);
            if ($showAmountColumns) {
                $sheet->setCellValue("E{$currentRow}", isset($sourceRow['cash']) ? (float)$sourceRow['cash'] : 0.0);
                $sheet->setCellValue("F{$currentRow}", isset($sourceRow['on_account']) ? (float)$sourceRow['on_account'] : 0.0);
            }

            $grandTotals['transactions'] += isset($sourceRow['transaction_count']) ? (int)$sourceRow['transaction_count'] : 0;
            $grandTotals['qty'] += isset($sourceRow['qty']) ? (float)$sourceRow['qty'] : 0.0;
            $grandTotals['cash'] += isset($sourceRow['cash']) ? (float)$sourceRow['cash'] : 0.0;
            $grandTotals['on_account'] += isset($sourceRow['on_account']) ? (float)$sourceRow['on_account'] : 0.0;

            foreach (['ice', 'others'] as $sectionKey) {
                foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
                    $value = 0.0;
                    if (isset($sourceRow['products'][$sectionKey]) && isset($sourceRow['products'][$sectionKey][$productKey])) {
                        $value = (float)$sourceRow['products'][$sectionKey][$productKey];
                    }
                    if ($value != 0.0) {
                        $sheet->setCellValue($columnMeta['column'] . $currentRow, $value);
                    }
                    $grandTotals['products'][$sectionKey][$productKey] += $value;
                }
            }

            $currentRow++;
        }
    }

    $totalRow = $currentRow;
    $sheet->setCellValue("B{$totalRow}", 'GRAND TOTAL');
    $sheet->setCellValue("C{$totalRow}", $grandTotals['transactions']);
    $sheet->setCellValue("D{$totalRow}", $grandTotals['qty']);
    if ($showAmountColumns) {
        $sheet->setCellValue("E{$totalRow}", $grandTotals['cash']);
        $sheet->setCellValue("F{$totalRow}", $grandTotals['on_account']);
    }

    foreach (['ice', 'others'] as $sectionKey) {
        foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
            $sheet->setCellValue(
                $columnMeta['column'] . $totalRow,
                isset($grandTotals['products'][$sectionKey][$productKey]) ? (float)$grandTotals['products'][$sectionKey][$productKey] : 0.0
            );
        }
    }

    $sheet->getStyle("A{$totalRow}:{$lastColumnLetter}{$totalRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$totalRow}:{$lastColumnLetter}{$totalRow}")->getFont()->setBold(true);

    if ($totalRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$totalRow}")->applyFromArray($borderStyle);
    }
    if ($lastColumnIndex > $baseLastColumnIndex) {
        $firstProductColumnLetter = sourceChannelColumnLetter($baseLastColumnIndex + 1);
        $sheet->getStyle("{$firstProductColumnLetter}{$sectionRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray($borderStyle);
    }

    $sheet->getStyle("A{$headerRow}:A{$totalRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("B{$headerRow}:B{$totalRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    if ($totalRow >= $dataStartRow) {
        $sheet->getStyle("C{$dataStartRow}:C{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("D{$dataStartRow}:{$lastColumnLetter}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("C{$dataStartRow}:{$lastColumnLetter}{$totalRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    $sheet->freezePane('A9');
}

$today = date('Y-m-d');
$startInput = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$endInput = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
$startDate = $startInput !== '' ? $startInput : $today;
$endDate = $endInput !== '' ? $endInput : $today;

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

$isAmountReport = isset($sourceChannelExportIsAmount) && $sourceChannelExportIsAmount;
$generatedAt = date('Y-m-d H:i:s');
$rangeLabel = date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));
$registerRows = sourceChannelEnrichPayments(sourceChannelFetchRows($mysqli, $startDate, $endDate));

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E5E7EB']
    ]
];

$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($isAmountReport) {
    $icePivotRows = sourceChannelBuildPivotColumns($registerRows, 'ice');
    $otherPivotRows = sourceChannelBuildPivotColumns($registerRows, 'others');

    $sheet->setTitle('Source Channel Amount Summary');
    sourceChannelWriteSummarySheet(
        $sheet,
        'SOURCE CHANNEL AMOUNT SUMMARY',
        $rangeLabel,
        $generatedAt,
        sourceChannelBuildSummaryRows(sourceChannelMapRowsToMetric($registerRows, 'amount')),
        $icePivotRows,
        $otherPivotRows,
        $headerStyle,
        $borderStyle,
        'TOTAL AMOUNT',
        true
    );

    $cashSheet = $spreadsheet->createSheet();
    $cashSheet->setTitle('Cash Summary');
    sourceChannelWriteSummarySheet(
        $cashSheet,
        'SOURCE CHANNEL CASH SUMMARY',
        $rangeLabel,
        $generatedAt,
        sourceChannelBuildSummaryRows(sourceChannelMapRowsToMetric($registerRows, 'cash_allocated'), 'cash_once'),
        $icePivotRows,
        $otherPivotRows,
        $headerStyle,
        $borderStyle,
        'CASH TOTAL'
    );

    $onAccountSheet = $spreadsheet->createSheet();
    $onAccountSheet->setTitle('On Account Summary');
    sourceChannelWriteSummarySheet(
        $onAccountSheet,
        'SOURCE CHANNEL ON ACCOUNT SUMMARY',
        $rangeLabel,
        $generatedAt,
        sourceChannelBuildSummaryRows(sourceChannelMapRowsToMetric($registerRows, 'on_account_allocated'), 'on_account_once'),
        $icePivotRows,
        $otherPivotRows,
        $headerStyle,
        $borderStyle,
        'ON ACCOUNT TOTAL'
    );
} else {
    $icePivotRows = sourceChannelBuildPivotColumns($registerRows, 'ice');
    $otherPivotRows = sourceChannelBuildPivotColumns($registerRows, 'others');

    $sheet->setTitle('Source Channel Summary');
    sourceChannelWriteSummarySheet(
        $sheet,
        'SOURCE CHANNEL QUANTITY SUMMARY',
        $rangeLabel,
        $generatedAt,
        sourceChannelBuildSummaryRows($registerRows),
        $icePivotRows,
        $otherPivotRows,
        $headerStyle,
        $borderStyle,
        'TOTAL QTY'
    );

    $registerRowsUmu = sourceChannelConvertRowsToUmu($registerRows);
    $icePivotRowsUmu = sourceChannelMergePivotColumnsForUmu($icePivotRows, sourceChannelBuildPivotColumns($registerRowsUmu, 'ice'));
    $otherPivotRowsUmu = sourceChannelMergePivotColumnsForUmu($otherPivotRows, sourceChannelBuildPivotColumns($registerRowsUmu, 'others'));

    $summaryUmuSheet = $spreadsheet->createSheet();
    $summaryUmuSheet->setTitle('Source Channel Summary BY KG');
    sourceChannelWriteSummarySheet(
        $summaryUmuSheet,
        'SOURCE CHANNEL QUANTITY SUMMARY (BY KG)',
        $rangeLabel,
        $generatedAt,
        sourceChannelBuildSummaryRows($registerRowsUmu),
        $icePivotRowsUmu,
        $otherPivotRowsUmu,
        $headerStyle,
        $borderStyle,
        'TOTAL QTY (BY KG)'
    );
    $summaryUmuSheet->getColumnDimension('D')->setVisible(false);
}

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filenamePrefix = $isAmountReport ? 'todate_daily_sales_amount_summary' : 'todate_daily_sales_summary';
$filename = $filenamePrefix . '_' . $startDate . '_to_' . $endDate . '_by_source_channel.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
