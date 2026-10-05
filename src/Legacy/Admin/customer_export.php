<?php
/**
 * Export zakazniku do CSV (pro marketing).
 * ?all=1 – vsichni (i bez newsletteru) – jen admin!
 * ?spent=1 – vcetne utraty
 */

use App\Legacy\lib\CustomerExport;

global $db;
legacy_db();
auth_require('obchod');

$export = new CustomerExport();
if (get_param('all') == '1' && auth_has_role('admin')) {
    $export->onlyNewsletter = false;
}

$csv = $export->toCsv(get_param('spent') == '1');

audit_log('customer', '', 'export', array('all' => !$export->onlyNewsletter));

$GLOBALS['LEGACY_CONTENT_TYPE'] = 'text/csv; charset=utf-8';
$GLOBALS['LEGACY_FILENAME'] = $export->filename();

echo $csv;
