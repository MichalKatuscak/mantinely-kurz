<?php
/**
 * Ucetni export – samostatny skript, ucetni si ho otevira primo:
 *
 *     /legacy/export.php?from=2017-01-01&to=2017-01-31
 *
 * (pres administraci viz Admin/ExportController.php – dela totez)
 * Soucet dole v CSV pocita exportRevenueSum() v lib/csv.php.
 */

require_once __DIR__ . '/bootstrap.php';

global $db;
legacy_db();

$from = isset($_GET['from']) ? (string) $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) ? (string) $_GET['to'] : date('Y-m-t');

// jednoducha "ochrana" – ucetni ma heslo v URL (2016)
// if (($_GET['key'] ?? '') != 'ucetni2016') { die('Přístup odepřen'); }

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    echo "Chybné datum (formát RRRR-MM-DD)";
    return;
}

$filename = 'export-' . $from . '_' . $to . '.csv';
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

$csv = exportCsv($from, $to);
audit_log('export', $from . '_' . $to, 'ucetni_export', array('rows' => substr_count($csv, "\r\n") - 2));

echo $csv;
