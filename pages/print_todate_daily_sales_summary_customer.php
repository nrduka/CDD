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

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    $connectError = trim((string)mysqli_connect_error());
    echo $connectError !== '' ? 'Database connection unavailable. ' . $connectError : 'Database connection unavailable.';
    exit;
}

function resolveSalesDateTime($primaryValue, $fallbackValue)
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

function buildCustomerDisplayName($row)
{
    $storeName = trim((string)(isset($row['storeName']) ? $row['storeName'] : ''));
    if ($storeName !== '') {
        return $storeName;
    }

    $firstName = trim((string)(isset($row['firstName']) ? $row['firstName'] : ''));
    $middleName = trim((string)(isset($row['middleName']) ? $row['middleName'] : ''));
    $lastName = trim((string)(isset($row['lastName']) ? $row['lastName'] : ''));
    $nameParts = [];

    if ($firstName !== '') {
        $nameParts[] = $firstName;
    }
    if ($middleName !== '') {
        $nameParts[] = $middleName;
    }
    if ($lastName !== '') {
        $nameParts[] = $lastName;
    }

    $displayName = trim(implode(' ', $nameParts));
    if ($displayName !== '') {
        return $displayName;
    }

    return trim((string)(isset($row['customer_code']) ? $row['customer_code'] : ''));
}

function buildSourceChannelLabel($row)
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

function resolveProductTypeGroup($productDescription, $productCode)
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

function columnIndexToLetter($columnIndex)
{
    $index = (int)$columnIndex;
    if ($index <= 0) {
        return 'A';
    }

    $letter = '';
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $letter = chr(65 + $remainder) . $letter;
        $index = (int)(($index - $remainder - 1) / 26);
    }

    return $letter;
}

function formatRangeLabel($startDate, $endDate)
{
    $startLabel = date('F d, Y', strtotime($startDate));
    $endLabel = date('F d, Y', strtotime($endDate));

    return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
}

function fetchToDateRegisterRows($mysqli, $startDate, $endDate)
{
    $rows = [];
    $sql = "SELECT s.order_no,
                   s.customer_code,
                   s.qty,
                   s.product_code,
                   s.product_description,
                   s.date_created,
                   s.date_time_ordered,
                   p.product_type AS master_product_type,
                   p.product_description AS master_product_description,
                   p.uom,
                   p.umu,
                   p.umu_per,
                   c.storeName,
                   c.firstName,
                   c.middleName,
                   c.lastName,
                   c.customer_source,
                   sc.customer_channel AS source_channel
            FROM sales s
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            LEFT JOIN customer_register c ON c.customer_code = s.customer_code
            LEFT JOIN source_channel sc ON sc.customer_source = c.customer_source
            WHERE DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) BETWEEN ? AND ?
              AND (s.void_status IS NULL OR s.void_status = 0)
            ORDER BY c.customer_source, c.storeName, c.firstName, c.lastName,
                     COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created),
                     s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $orderedAt = resolveSalesDateTime(
                    isset($row['date_time_ordered']) ? $row['date_time_ordered'] : '',
                    isset($row['date_created']) ? $row['date_created'] : ''
                );
                $dateLabel = $orderedAt ? $orderedAt->format('Y-m-d') : '';

                $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
                if ($productDescription === '') {
                    $productDescription = trim((string)(isset($row['master_product_description']) ? $row['master_product_description'] : ''));
                }

                $productType = trim((string)(isset($row['master_product_type']) ? $row['master_product_type'] : ''));
                if ($productType === '') {
                    $productType = resolveProductTypeGroup(
                        $productDescription,
                        isset($row['product_code']) ? $row['product_code'] : ''
                    );
                }

                $rows[] = [
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'customer_name' => buildCustomerDisplayName($row),
                    'customer_source' => isset($row['customer_source']) ? (string)$row['customer_source'] : '',
                    'source_channel' => buildSourceChannelLabel($row),
                    'date' => $dateLabel,
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => $productDescription,
                    'product_type' => $productType,
                    'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : '')),
                    'umu' => trim((string)(isset($row['umu']) ? $row['umu'] : '')),
                    'umu_per' => isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0,
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function convertRegisterRowsToUmu($registerRows)
{
    $convertedRows = [];

    foreach ($registerRows as $row) {
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

function resolvePivotSection($row)
{
    $productCode = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
    if ($productCode === '') {
        return [
            'section' => '',
            'product_key' => ''
        ];
    }

    $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
    if ($productType === '') {
        $productType = resolveProductTypeGroup(
            isset($row['product_description']) ? $row['product_description'] : '',
            isset($row['product_code']) ? $row['product_code'] : ''
        );
    }

    return [
        'section' => strtoupper($productType) === 'OTHERS' ? 'others' : 'ice',
        'product_key' => $productCode,
    ];
}

function buildPivotColumns($registerRows, $section)
{
    $columns = [];

    foreach ($registerRows as $row) {
        $pivotMeta = resolvePivotSection($row);
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
                'uom' => $uom,
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

        $leftCode = strtoupper(trim((string)$left['product_code']));
        $rightCode = strtoupper(trim((string)$right['product_code']));
        if ($leftCode !== $rightCode) {
            return strcmp($leftCode, $rightCode);
        }

        return strcmp(
            strtoupper(trim((string)$left['product_description'])),
            strtoupper(trim((string)$right['product_description']))
        );
    });

    return $pivotRows;
}

function mergePivotColumnsForUmu($basePivotRows, $umuPivotRows)
{
    $umuByCode = [];
    foreach ($umuPivotRows as $row) {
        $codeKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($codeKey === '') {
            continue;
        }
        $umuByCode[$codeKey] = $row;
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

function buildCustomerDateSummary($registerRows)
{
    $customerBuckets = [];

    foreach ($registerRows as $index => $row) {
        $customerCode = trim((string)(isset($row['customer_code']) ? $row['customer_code'] : ''));
        if ($customerCode === '') {
            $customerCode = 'UNASSIGNED';
        }

        $customerName = trim((string)(isset($row['customer_name']) ? $row['customer_name'] : ''));
        if ($customerName === '' && $customerCode !== 'UNASSIGNED') {
            $customerName = $customerCode;
        }
        $sourceChannel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
        if ($sourceChannel === '') {
            $sourceChannel = 'UNASSIGNED';
        }
        $sourceSort = isset($row['customer_source']) && is_numeric($row['customer_source']) ? (int)$row['customer_source'] : 999999;
        $sourceKey = $sourceSort . '|' . strtoupper($sourceChannel);

        $saleDate = trim((string)(isset($row['date']) ? $row['date'] : ''));
        if ($saleDate === '') {
            $saleDate = 'NO DATE';
        }

        $orderNo = trim((string)(isset($row['order_no']) ? $row['order_no'] : ''));
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;

        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $pivotMeta = resolvePivotSection($row);
        $section = $pivotMeta['section'];
        $productKey = $pivotMeta['product_key'];
        $bucketKey = $sourceKey . '|' . $customerCode;

        if (!isset($customerBuckets[$bucketKey])) {
            $customerBuckets[$bucketKey] = [
                'source_channel' => $sourceChannel,
                'source_sort' => $sourceSort,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'subtotal_qty' => 0.0,
                'subtotal_transactions' => 0,
                'subtotal_products' => ['ice' => [], 'others' => []],
                'transaction_orders' => [],
                'dates' => []
            ];
        }

        if ($customerBuckets[$bucketKey]['customer_name'] === '' && $customerName !== '') {
            $customerBuckets[$bucketKey]['customer_name'] = $customerName;
        }

        $customerBuckets[$bucketKey]['transaction_orders'][$orderKey] = true;
        $customerBuckets[$bucketKey]['subtotal_qty'] += $qty;

        if ($section !== '' && $productKey !== '') {
            if (!isset($customerBuckets[$bucketKey]['subtotal_products'][$section][$productKey])) {
                $customerBuckets[$bucketKey]['subtotal_products'][$section][$productKey] = 0.0;
            }
            $customerBuckets[$bucketKey]['subtotal_products'][$section][$productKey] += $qty;
        }

        if (!isset($customerBuckets[$bucketKey]['dates'][$saleDate])) {
            $customerBuckets[$bucketKey]['dates'][$saleDate] = [
                'sale_date' => $saleDate,
                'transaction_count' => 0,
                'qty' => 0.0,
                'products' => ['ice' => [], 'others' => []],
                'transaction_orders' => []
            ];
        }

        $customerBuckets[$bucketKey]['dates'][$saleDate]['transaction_orders'][$orderKey] = true;
        $customerBuckets[$bucketKey]['dates'][$saleDate]['qty'] += $qty;

        if ($section !== '' && $productKey !== '') {
            if (!isset($customerBuckets[$bucketKey]['dates'][$saleDate]['products'][$section][$productKey])) {
                $customerBuckets[$bucketKey]['dates'][$saleDate]['products'][$section][$productKey] = 0.0;
            }
            $customerBuckets[$bucketKey]['dates'][$saleDate]['products'][$section][$productKey] += $qty;
        }
    }

    $customerRows = array_values($customerBuckets);

    foreach ($customerRows as &$customerRow) {
        $customerRow['subtotal_transactions'] = count($customerRow['transaction_orders']);
        unset($customerRow['transaction_orders']);

        $dateRows = array_values($customerRow['dates']);
        foreach ($dateRows as &$dateRow) {
            $dateRow['transaction_count'] = count($dateRow['transaction_orders']);
            unset($dateRow['transaction_orders']);
        }
        unset($dateRow);

        usort($dateRows, function ($left, $right) {
            $leftDate = isset($left['sale_date']) ? (string)$left['sale_date'] : '';
            $rightDate = isset($right['sale_date']) ? (string)$right['sale_date'] : '';

            if ($leftDate === 'NO DATE' && $rightDate !== 'NO DATE') {
                return 1;
            }
            if ($leftDate !== 'NO DATE' && $rightDate === 'NO DATE') {
                return -1;
            }

            return strcmp($leftDate, $rightDate);
        });

        $customerRow['dates'] = $dateRows;
    }
    unset($customerRow);

    usort($customerRows, function ($left, $right) {
        $leftSourceSort = isset($left['source_sort']) ? (int)$left['source_sort'] : 999999;
        $rightSourceSort = isset($right['source_sort']) ? (int)$right['source_sort'] : 999999;
        if ($leftSourceSort !== $rightSourceSort) {
            return $leftSourceSort < $rightSourceSort ? -1 : 1;
        }

        $sourceCompare = strcasecmp((string)$left['source_channel'], (string)$right['source_channel']);
        if ($sourceCompare !== 0) {
            return $sourceCompare;
        }

        $codeCompare = strcasecmp((string)$left['customer_code'], (string)$right['customer_code']);
        if ($codeCompare !== 0) {
            return $codeCompare;
        }

        return strcasecmp((string)$left['customer_name'], (string)$right['customer_name']);
    });

    return $customerRows;
}

function writeCustomerSummarySheet(
    $sheet,
    $sheetTitle,
    $rangeLabel,
    $generatedAt,
    $customerSummaryRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle,
    $includeIceTotalColumn = false
) {
    $sheet->setCellValue('A1', $sheetTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
    $sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

    $sectionRow = 6;
    $productTypeRow = 7;
    $headerRow = 8;
    $dataStartRow = 9;

    $baseHeaders = [
        'NO.',
        'CUSTOMER CODE',
        'CUSTOMER NAME',
        'DATE',
        'TRANSACTION COUNT',
        'TOTAL QTY'
    ];

    $sheet->fromArray($baseHeaders, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")
        ->getFont()
        ->getColor()
        ->setRGB('000000');
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(32);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(14);

    $currentColumnIndex = 7; // G
    $productColumnsBySection = [
        'ice' => [],
        'others' => []
    ];

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
            return null;
        }

        $startColumnIndex = $currentColumnIndex;

        $typeGroups = [];
        foreach ($pivotRows as $pivotData) {
            $columnLetter = columnIndexToLetter($currentColumnIndex);
            $productCode = trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : ''));
            $productDescription = trim((string)(isset($pivotData['product_description']) ? $pivotData['product_description'] : ''));
            $uom = trim((string)(isset($pivotData['uom']) ? $pivotData['uom'] : ''));
            $productType = trim((string)(isset($pivotData['product_type']) ? $pivotData['product_type'] : ''));
            if ($productType === '') {
                $productType = 'UNCLASSIFIED';
            }

            $label = $productCode !== '' ? $productCode : ($productDescription !== '' ? $productDescription : 'PRODUCT');
            if ($uom !== '') {
                $label .= "\n(" . $uom . ')';
            }

            $sheet->setCellValue($columnLetter . $headerRow, $label);
            $sheet->getColumnDimension($columnLetter)->setAutoSize(false)->setWidth(14);

            $productKey = strtoupper($productCode);
            if ($productKey !== '') {
                $productColumnsBySection[$sectionKey][$productKey] = [
                    'column' => $columnLetter,
                    'uom' => $uom,
                    'product_code' => $productCode
                ];
            }

            if ($sectionKey === 'ice') {
                $productTypeKey = strtoupper($productType);
                if (
                    empty($typeGroups)
                    || $typeGroups[count($typeGroups) - 1]['key'] !== $productTypeKey
                ) {
                    $typeGroups[] = [
                        'key' => $productTypeKey,
                        'label' => $productType,
                        'start_column_index' => $currentColumnIndex,
                        'end_column_index' => $currentColumnIndex
                    ];
                } else {
                    $lastTypeGroupIndex = count($typeGroups) - 1;
                    $typeGroups[$lastTypeGroupIndex]['end_column_index'] = $currentColumnIndex;
                }
            }

            $currentColumnIndex++;
        }

        $endColumnIndex = $currentColumnIndex - 1;
        if ($endColumnIndex < $startColumnIndex) {
            return;
        }

        $startColumnLetter = columnIndexToLetter($startColumnIndex);
        $endColumnLetter = columnIndexToLetter($endColumnIndex);

        if ($startColumnLetter !== $endColumnLetter) {
            $sheet->mergeCells("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}");
        }
        $sheet->setCellValue("{$startColumnLetter}{$sectionRow}", $sectionLabel);

        $sheet->getStyle("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}")
            ->getFont()
            ->getColor()
            ->setRGB('000000');
        $sheet->getStyle("{$startColumnLetter}{$sectionRow}:{$endColumnLetter}{$sectionRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        if ($sectionKey === 'ice' && !empty($typeGroups)) {
            foreach ($typeGroups as $typeGroup) {
                $typeStartColumnLetter = columnIndexToLetter($typeGroup['start_column_index']);
                $typeEndColumnLetter = columnIndexToLetter($typeGroup['end_column_index']);
                if ($typeStartColumnLetter !== $typeEndColumnLetter) {
                    $sheet->mergeCells("{$typeStartColumnLetter}{$productTypeRow}:{$typeEndColumnLetter}{$productTypeRow}");
                }
                $sheet->setCellValue("{$typeStartColumnLetter}{$productTypeRow}", $typeGroup['label']);
                $sheet->getStyle("{$typeStartColumnLetter}{$productTypeRow}:{$typeEndColumnLetter}{$productTypeRow}")
                    ->applyFromArray($headerStyle);
                $sheet->getStyle("{$typeStartColumnLetter}{$productTypeRow}:{$typeEndColumnLetter}{$productTypeRow}")
                    ->getFont()
                    ->getColor()
                    ->setRGB('000000');
            }
        } elseif ($sectionKey === 'others') {
            if ($startColumnLetter !== $endColumnLetter) {
                $sheet->mergeCells("{$startColumnLetter}{$productTypeRow}:{$endColumnLetter}{$productTypeRow}");
            }
            $sheet->setCellValue("{$startColumnLetter}{$productTypeRow}", 'OTHERS');
            $sheet->getStyle("{$startColumnLetter}{$productTypeRow}:{$endColumnLetter}{$productTypeRow}")
                ->applyFromArray($headerStyle);
            $sheet->getStyle("{$startColumnLetter}{$productTypeRow}:{$endColumnLetter}{$productTypeRow}")
                ->getFont()
                ->getColor()
                ->setRGB('000000');
        }

        $sheet->getStyle("{$startColumnLetter}{$headerRow}:{$endColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$startColumnLetter}{$headerRow}:{$endColumnLetter}{$headerRow}")
            ->getFont()
            ->getColor()
            ->setRGB('000000');
        $sheet->getStyle("{$startColumnLetter}{$headerRow}:{$endColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        return [
            'start_column_index' => $startColumnIndex,
            'end_column_index' => $endColumnIndex
        ];
    };

    $renderPivotSection('ice', 'ICE PRODUCTS', $icePivotRows);

    $iceTotalColumnLetter = '';
    if ($includeIceTotalColumn && !empty($productColumnsBySection['ice'])) {
        $iceTotalColumnLetter = columnIndexToLetter($currentColumnIndex);
        $sheet->mergeCells("{$iceTotalColumnLetter}{$sectionRow}:{$iceTotalColumnLetter}{$headerRow}");
        $sheet->setCellValue("{$iceTotalColumnLetter}{$sectionRow}", 'ICE TOTAL');
        $sheet->getStyle("{$iceTotalColumnLetter}{$sectionRow}:{$iceTotalColumnLetter}{$headerRow}")
            ->applyFromArray($headerStyle);
        $sheet->getStyle("{$iceTotalColumnLetter}{$sectionRow}:{$iceTotalColumnLetter}{$headerRow}")
            ->getFont()
            ->getColor()
            ->setRGB('000000');
        $sheet->getStyle("{$iceTotalColumnLetter}{$sectionRow}:{$iceTotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($iceTotalColumnLetter)->setAutoSize(false)->setWidth(14);
        $currentColumnIndex++;
    }

    $renderPivotSection('others', 'OTHERS PRODUCTS', $otherPivotRows);

    $lastColumnIndex = max(6, $currentColumnIndex - 1);
    $lastColumnLetter = columnIndexToLetter($lastColumnIndex);

    $sheet->getStyle("A{$sectionRow}:{$lastColumnLetter}{$headerRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $grandTransactions = 0;
    $grandQty = 0.0;
    $grandIceQty = 0.0;
    $grandProductTotals = ['ice' => [], 'others' => []];
    foreach ($productColumnsBySection['ice'] as $productKey => $meta) {
        $grandProductTotals['ice'][$productKey] = 0.0;
    }
    foreach ($productColumnsBySection['others'] as $productKey => $meta) {
        $grandProductTotals['others'][$productKey] = 0.0;
    }

    $currentRow = $dataStartRow;
    $lineNumber = 1;
    $currentSourceKey = '';
    $currentSourceLabel = '';
    $sourceTransactions = 0;
    $sourceQty = 0.0;
    $sourceIceQty = 0.0;
    $sourceProductTotals = ['ice' => [], 'others' => []];
    $sourceLabelRows = [];

    $resetSourceTotals = function () use (&$sourceTransactions, &$sourceQty, &$sourceIceQty, &$sourceProductTotals, $productColumnsBySection) {
        $sourceTransactions = 0;
        $sourceQty = 0.0;
        $sourceIceQty = 0.0;
        $sourceProductTotals = ['ice' => [], 'others' => []];
        foreach ($productColumnsBySection['ice'] as $productKey => $meta) {
            $sourceProductTotals['ice'][$productKey] = 0.0;
        }
        foreach ($productColumnsBySection['others'] as $productKey => $meta) {
            $sourceProductTotals['others'][$productKey] = 0.0;
        }
    };

    $writeSourceTotalRow = function () use (
        $sheet,
        &$currentRow,
        &$currentSourceLabel,
        &$sourceTransactions,
        &$sourceQty,
        &$sourceIceQty,
        &$sourceProductTotals,
        &$sourceLabelRows,
        $productColumnsBySection,
        $iceTotalColumnLetter,
        $lastColumnLetter
    ) {
        $sheet->setCellValue("A{$currentRow}", 'TOTAL - ' . $currentSourceLabel);
        $sheet->setCellValue("E{$currentRow}", $sourceTransactions);
        $sheet->setCellValue("F{$currentRow}", $sourceQty);
        if ($iceTotalColumnLetter !== '') {
            $sheet->setCellValue($iceTotalColumnLetter . $currentRow, $sourceIceQty);
        }

        foreach (['ice', 'others'] as $sectionKey) {
            foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
                $sheet->setCellValue(
                    $columnMeta['column'] . $currentRow,
                    isset($sourceProductTotals[$sectionKey][$productKey]) ? (float)$sourceProductTotals[$sectionKey][$productKey] : 0.0
                );
            }
        }

        $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
            ->getFont()
            ->setBold(true);
        $sourceLabelRows[] = $currentRow;
        $currentRow++;
    };
    $writeSourceHeaderRow = function () use ($sheet, &$currentRow, &$currentSourceLabel, &$sourceLabelRows, $lastColumnLetter) {
        $sheet->setCellValue("A{$currentRow}", $currentSourceLabel);
        $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
            ->getFont()
            ->setBold(true);
        $sourceLabelRows[] = $currentRow;
        $currentRow++;
    };
    $resetSourceTotals();

    if (empty($customerSummaryRows)) {
        $sheet->mergeCells("A{$currentRow}:{$lastColumnLetter}{$currentRow}");
        $sheet->setCellValue("A{$currentRow}", 'No sales were recorded for the selected date range.');
        $sheet->getStyle("A{$currentRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $currentRow++;
    } else {
        foreach ($customerSummaryRows as $customerRow) {
            $customerCode = isset($customerRow['customer_code']) ? (string)$customerRow['customer_code'] : '';
            $customerName = isset($customerRow['customer_name']) ? (string)$customerRow['customer_name'] : '';
            $sourceChannel = isset($customerRow['source_channel']) ? (string)$customerRow['source_channel'] : '';
            $sourceKey = isset($customerRow['source_sort']) ? (string)$customerRow['source_sort'] . '|' . strtoupper($sourceChannel) : strtoupper($sourceChannel);
            if ($currentSourceKey !== '' && $sourceKey !== $currentSourceKey) {
                $writeSourceTotalRow();
                $resetSourceTotals();
            }
            if ($currentSourceKey === '' || $sourceKey !== $currentSourceKey) {
                $currentSourceKey = $sourceKey;
                $currentSourceLabel = $sourceChannel !== '' ? $sourceChannel : 'UNASSIGNED';
                $writeSourceHeaderRow();
            }

            foreach ($customerRow['dates'] as $dateRow) {
                $transactionCount = isset($dateRow['transaction_count']) ? (int)$dateRow['transaction_count'] : 0;
                $totalQty = isset($dateRow['qty']) ? (float)$dateRow['qty'] : 0.0;
                $iceRowQty = 0.0;

                $sheet->setCellValue("A{$currentRow}", $lineNumber);
                $sheet->setCellValueExplicit("B{$currentRow}", $customerCode, DataType::TYPE_STRING);
                $sheet->setCellValue("C{$currentRow}", $customerName);
                $sheet->setCellValueExplicit("D{$currentRow}", isset($dateRow['sale_date']) ? (string)$dateRow['sale_date'] : '', DataType::TYPE_STRING);
                $sheet->setCellValue("E{$currentRow}", $transactionCount);
                $sheet->setCellValue("F{$currentRow}", $totalQty);

                foreach (['ice', 'others'] as $sectionKey) {
                    foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
                        $qtyValue = 0.0;
                        if (
                            isset($dateRow['products'][$sectionKey])
                            && isset($dateRow['products'][$sectionKey][$productKey])
                        ) {
                            $qtyValue = (float)$dateRow['products'][$sectionKey][$productKey];
                        }

                        if ($qtyValue != 0.0) {
                            $sheet->setCellValue($columnMeta['column'] . $currentRow, $qtyValue);
                            $grandProductTotals[$sectionKey][$productKey] += $qtyValue;
                            $sourceProductTotals[$sectionKey][$productKey] += $qtyValue;
                        }
                        if ($sectionKey === 'ice') {
                            $iceRowQty += $qtyValue;
                        }
                    }
                }
                if ($iceTotalColumnLetter !== '') {
                    $sheet->setCellValue($iceTotalColumnLetter . $currentRow, $iceRowQty);
                    $grandIceQty += $iceRowQty;
                }

                $grandTransactions += $transactionCount;
                $grandQty += $totalQty;
                $sourceTransactions += $transactionCount;
                $sourceQty += $totalQty;
                $sourceIceQty += $iceRowQty;

                $currentRow++;
                $lineNumber++;
            }

            $sheet->setCellValue("B{$currentRow}", 'SUBTOTAL');
            $sheet->setCellValue("C{$currentRow}", $customerName !== '' ? $customerName : $customerCode);
            $sheet->setCellValue("E{$currentRow}", isset($customerRow['subtotal_transactions']) ? (int)$customerRow['subtotal_transactions'] : 0);
            $sheet->setCellValue("F{$currentRow}", isset($customerRow['subtotal_qty']) ? (float)$customerRow['subtotal_qty'] : 0.0);
            $iceSubtotalQty = 0.0;

            foreach (['ice', 'others'] as $sectionKey) {
                foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
                    $subtotalQty = 0.0;
                    if (
                        isset($customerRow['subtotal_products'][$sectionKey])
                        && isset($customerRow['subtotal_products'][$sectionKey][$productKey])
                    ) {
                        $subtotalQty = (float)$customerRow['subtotal_products'][$sectionKey][$productKey];
                    }

                    if ($subtotalQty != 0.0) {
                        $sheet->setCellValue($columnMeta['column'] . $currentRow, $subtotalQty);
                    }
                    if ($sectionKey === 'ice') {
                        $iceSubtotalQty += $subtotalQty;
                    }
                }
            }
            if ($iceTotalColumnLetter !== '') {
                $sheet->setCellValue($iceTotalColumnLetter . $currentRow, $iceSubtotalQty);
            }

            $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
                ->getFont()
                ->setBold(true);
            $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('F3F4F6');

            $currentRow++;
        }
        if ($currentSourceKey !== '') {
            $writeSourceTotalRow();
        }
    }

    $sheet->setCellValue("B{$currentRow}", 'GRAND TOTAL');
    $sheet->setCellValue("E{$currentRow}", $grandTransactions);
    $sheet->setCellValue("F{$currentRow}", $grandQty);
    if ($iceTotalColumnLetter !== '') {
        $sheet->setCellValue($iceTotalColumnLetter . $currentRow, $grandIceQty);
    }

    foreach (['ice', 'others'] as $sectionKey) {
        foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
            $sheet->setCellValue(
                $columnMeta['column'] . $currentRow,
                isset($grandProductTotals[$sectionKey][$productKey]) ? (float)$grandProductTotals[$sectionKey][$productKey] : 0.0
            );
        }
    }

    $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
        ->getFont()
        ->setBold(true)
        ->getColor()
        ->setRGB('000000');

    $lastDataRow = $currentRow;

    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastColumnIndex >= 7) {
        $sheet->getStyle("G{$sectionRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray($borderStyle);
    }

    if ($lastDataRow >= $dataStartRow) {
        $sheet->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle("F{$dataStartRow}:{$lastColumnLetter}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    $sheet->getStyle("A{$headerRow}:A{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    foreach ($sourceLabelRows as $sourceLabelRow) {
        $sheet->getStyle("A{$sourceLabelRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
    $sheet->getStyle("B{$headerRow}:C{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D{$headerRow}:E{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    if ($lastDataRow >= $dataStartRow) {
        $sheet->getStyle("F{$dataStartRow}:{$lastColumnLetter}{$lastDataRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    $sheet->freezePane('A9');
}

$registerRows = fetchToDateRegisterRows($mysqli, $startDate, $endDate);
$customerSummaryRows = buildCustomerDateSummary($registerRows);
$icePivotRows = buildPivotColumns($registerRows, 'ice');
$otherPivotRows = buildPivotColumns($registerRows, 'others');

$registerRowsUmu = convertRegisterRowsToUmu($registerRows);
$customerSummaryRowsUmu = buildCustomerDateSummary($registerRowsUmu);
$icePivotRowsUmuRaw = buildPivotColumns($registerRowsUmu, 'ice');
$otherPivotRowsUmuRaw = buildPivotColumns($registerRowsUmu, 'others');
$icePivotRowsUmu = mergePivotColumnsForUmu($icePivotRows, $icePivotRowsUmuRaw);
$otherPivotRowsUmu = mergePivotColumnsForUmu($otherPivotRows, $otherPivotRowsUmuRaw);

$rangeLabel = formatRangeLabel($startDate, $endDate);

$headerStyle = [
    'font' => ['bold' => true],
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
$sheet->setTitle('Customer Summary');

writeCustomerSummarySheet(
    $sheet,
    'CUSTOMER ORDER SUMMARY BY DATE',
    $rangeLabel,
    $generatedAt,
    $customerSummaryRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle
);
$sheet->getColumnDimension('F')->setVisible(false);

$summaryUmuSheet = $spreadsheet->createSheet();
$summaryUmuSheet->setTitle('Customer Summary BY KG');

writeCustomerSummarySheet(
    $summaryUmuSheet,
    'CUSTOMER ORDER SUMMARY BY DATE (BY KG)',
    $rangeLabel,
    $generatedAt,
    $customerSummaryRowsUmu,
    $icePivotRowsUmu,
    $otherPivotRowsUmu,
    $headerStyle,
    $borderStyle,
    true
);
$summaryUmuSheet->getColumnDimension('F')->setVisible(false);

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'todate_daily_sales_summary_' . $startDate . '_to_' . $endDate . '_by_customer.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
