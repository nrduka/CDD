<?php

if (!function_exists('applySpreadsheetExportRuntimeLimits')) {
    function applySpreadsheetExportRuntimeLimits()
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
    }
}

if (!function_exists('getSpreadsheetExportTempDirectory')) {
    function getSpreadsheetExportTempDirectory()
    {
        $tempDirectory = rtrim((string)sys_get_temp_dir(), '\\/');
        if ($tempDirectory !== '' && is_dir($tempDirectory) && is_writable($tempDirectory)) {
            return $tempDirectory;
        }

        $fallbackTmpDirectory = realpath(__DIR__ . '/../tmp');
        if ($fallbackTmpDirectory !== false && is_dir($fallbackTmpDirectory) && is_writable($fallbackTmpDirectory)) {
            return rtrim($fallbackTmpDirectory, '\\/');
        }

        return __DIR__;
    }
}

applySpreadsheetExportRuntimeLimits();
