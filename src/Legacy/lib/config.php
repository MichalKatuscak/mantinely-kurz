<?php
/**
 * Konfigurace administrace.
 *
 * Driv to bylo v config.ini na serveru, pak se to presunulo sem,
 * cast hodnot je v tabulce settings (viz settings_get()).
 */

if (!defined('LEGACY_ROOT')) {
    define('LEGACY_ROOT', dirname(__DIR__));
}
if (!defined('LEGACY_TEMPLATES')) {
    define('LEGACY_TEMPLATES', LEGACY_ROOT . '/templates');
}
if (!defined('LEGACY_VERSION')) {
    define('LEGACY_VERSION', '2.7.3');
}

// DPH – zmenit pri zmene zakona!!! (2015: 21 %)
if (!defined('VAT_RATE')) {
    define('VAT_RATE', 21);
}

$GLOBALS['config'] = array(
    'shop_name'       => 'Obchod u Mantinelu',
    'admin_email'     => 'admin@example.cz',
    'invoice_prefix'  => 'FV',
    'per_page'        => 50,
    'default_currency'=> 'CZK',
    'date_format'     => 'j. n. Y',
    'debug'           => false,
    'cache_dir'       => sys_get_temp_dir() . '/legacy_cache',
    'mail_log'        => sys_get_temp_dir() . '/legacy_mail.log',
    'unpaid_days'     => 14,          // po kolika dnech cron stornuje nezaplacene
    'low_stock'       => 5,           // pod kolik kusu se zobrazuje cervene
    // 'pdf_engine'   => 'fpdf',      // uz neni, viz lib/pdf.php
    'roles'           => array('admin', 'obchod', 'sklad', 'ucetni'),
);

// stare nazvy stavu z MySQL doby (pred 2024 se pouzivaly ciselne kody)
$GLOBALS['ORDER_STATES'] = array(
    'draft'     => 'Rozpracovaná',
    'confirmed' => 'Potvrzená',
    'paid'      => 'Zaplacená',
    'shipped'   => 'Odeslaná',
    'delivered' => 'Doručená',
    'cancelled' => 'Stornovaná',
);

// mapovani starych ciselnych stavu, uz se nepouziva
$GLOBALS['ORDER_STATES_OLD'] = array(
    0 => 'draft',
    1 => 'confirmed',
    2 => 'paid',
    3 => 'shipped',
    4 => 'delivered',
    9 => 'cancelled',
);

$GLOBALS['CURRENCIES'] = array('CZK', 'EUR', 'USD');

function config($key, $default = null)
{
    global $config;
    if (isset($config[$key])) {
        return $config[$key];
    }

    return $default;
}

/**
 * Hodnota z tabulky settings. Pozor: kazde volani = dotaz do DB.
 */
function settings_get($name, $default = null)
{
    $row = db_one("SELECT value FROM settings WHERE name = '" . $name . "'");
    if ($row === null) {
        return $default;
    }

    return $row['value'];
}

function settings_set($name, $value)
{
    $exists = db_one("SELECT name FROM settings WHERE name = '" . $name . "'");
    if ($exists) {
        db_exec("UPDATE settings SET value = " . db_escape($value) . " WHERE name = '" . $name . "'");
    } else {
        db_exec("INSERT INTO settings (name, value) VALUES ('" . $name . "', " . db_escape($value) . ")");
    }
}
