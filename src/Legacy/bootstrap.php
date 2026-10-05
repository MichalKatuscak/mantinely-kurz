<?php
/**
 * Inicializace stare administrace.
 *
 * Nacita vsechny knihovny. Spojeni s DB se vytvori az pri prvnim dotazu
 * (viz legacy_db() v lib/db.php).
 *
 * Pouziti:
 *     require_once __DIR__ . '/bootstrap.php';
 */

// cesky cas – server byl v UTC a reporty pak ujizdely o den
if (ini_get('date.timezone') == '') {
    date_default_timezone_set('Europe/Prague');
}

// fallback autoload pro pripad, ze se skript spousti bez composeru (cron na starem serveru)
if (!function_exists('legacy_autoload')) {
    function legacy_autoload($class)
    {
        $prefix = 'App\\Legacy\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
    spl_autoload_register('legacy_autoload');
}

require_once __DIR__ . '/lib/LegacyDb.php';
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/lib/cache.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/LegacyMailer.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/revenue.php';
require_once __DIR__ . '/lib/report.php';
require_once __DIR__ . '/lib/csv.php';
require_once __DIR__ . '/lib/pdf.php';
require_once __DIR__ . '/lib/PriceUtils.php';
require_once __DIR__ . '/lib/InvoiceHelper.php';
require_once __DIR__ . '/lib/OrderReport.php';
require_once __DIR__ . '/lib/StockReport.php';
require_once __DIR__ . '/lib/CustomerExport.php';
require_once __DIR__ . '/lib/CronRunner.php';

// globalni $db – naplni se lazy v legacy_db()
if (!isset($GLOBALS['db'])) {
    $GLOBALS['db'] = null;
}

// require_once __DIR__ . '/lib/memcache.php';   // uz neni
// require_once __DIR__ . '/lib/fpdf/fpdf.php';  // ztraceno pri stehovani 2019
