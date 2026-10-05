<?php
/**
 * Pomocne funkce pro sablony a stranky administrace.
 * (helpers.php = novejsi, functions.php = starsi; nekdy je neco v obou. sorry. -- petr)
 */

/**
 * HTML escape. Pouzivat v sablonach VSUDE.
 */
function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Parametr z GET, nikdy null.
 */
function get_param($name, $default = '')
{
    if (!isset($_GET[$name])) {
        return $default;
    }
    if (is_array($_GET[$name])) {
        return $_GET[$name];
    }

    return trim((string) $_GET[$name]);
}

function post_param($name, $default = '')
{
    if (!isset($_POST[$name])) {
        return $default;
    }
    if (is_array($_POST[$name])) {
        return $_POST[$name];
    }

    return trim((string) $_POST[$name]);
}

function is_post()
{
    return isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' || !empty($_POST);
}

/**
 * Cena v halerich -> "1 234,50 Kč"
 */
function format_price($cents, $currency = 'CZK')
{
    $amount = ((int) $cents) / 100;
    $s = number_format($amount, 2, ',', ' ');
    switch ($currency) {
        case 'CZK':
            return $s . ' Kč';
        case 'EUR':
            return $s . ' €';
        case 'USD':
            return '$' . $s;
        default:
            return $s . ' ' . $currency;
    }
}

/**
 * Datum z DB -> 5. 10. 2026
 */
function format_date($dbDate, $withTime = false)
{
    if ($dbDate === null || $dbDate === '' || $dbDate === '0000-00-00 00:00:00') {
        return '–';
    }
    $ts = strtotime((string) $dbDate);
    if ($ts === false) {
        return (string) $dbDate;
    }

    return $withTime ? date('j. n. Y H:i', $ts) : date('j. n. Y', $ts);
}

/**
 * Presmerovani. Driv header('Location') + exit, ted si to vezme LegacyFrontController.
 */
function redirect($url)
{
    $GLOBALS['LEGACY_REDIRECT'] = $url;
}

function flash($msg, $type = 'info')
{
    if (!isset($GLOBALS['LEGACY_FLASH'])) {
        $GLOBALS['LEGACY_FLASH'] = array();
    }
    $GLOBALS['LEGACY_FLASH'][] = array('msg' => $msg, 'type' => $type);
    if (isset($_SESSION)) {
        $_SESSION['flash'][] = array('msg' => $msg, 'type' => $type);
    }
}

function flash_messages()
{
    $out = isset($GLOBALS['LEGACY_FLASH']) ? $GLOBALS['LEGACY_FLASH'] : array();
    $GLOBALS['LEGACY_FLASH'] = array();

    return $out;
}

function order_state_label($state)
{
    global $ORDER_STATES;
    if (isset($ORDER_STATES[$state])) {
        return $ORDER_STATES[$state];
    }

    return $state;
}

/**
 * Strankovani: vraci array(offset, limit, page)
 */
function paginate($page, $perPage = null)
{
    if ($perPage === null) {
        $perPage = config('per_page', 50);
    }
    $page = (int) $page;
    if ($page < 1) {
        $page = 1;
    }

    return array(($page - 1) * $perPage, $perPage, $page);
}

function pager_html($page, $total, $perPage, $baseUrl)
{
    $pages = (int) ceil($total / max(1, $perPage));
    if ($pages <= 1) {
        return '';
    }
    $html = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($i == $page) {
            $html .= '<strong>' . $i . '</strong> ';
        } else {
            $html .= '<a href="' . h($baseUrl) . '&amp;page=' . $i . '">' . $i . '</a> ';
        }
    }
    $html .= '</div>';

    return $html;
}

function admin_url($page, $params = array())
{
    $url = '/admin/legacy/' . $page;
    if (count($params) > 0) {
        $url .= '?' . http_build_query($params);
    }

    return $url;
}

/**
 * Validace mesice YYYY-MM. Vraci true/false.
 */
function is_month($s)
{
    return (bool) preg_match('/^\d{4}-\d{2}$/', (string) $s);
}

/**
 * Prvni a posledni den mesice pro SQL.
 */
function month_range($month)
{
    $from = $month . '-01 00:00:00';
    $to = date('Y-m-t 23:59:59', strtotime($month . '-01'));

    return array($from, $to);
}

function select_options($options, $selected)
{
    $html = '';
    foreach ($options as $k => $v) {
        $html .= '<option value="' . h($k) . '"' . ((string) $k === (string) $selected ? ' selected' : '') . '>' . h($v) . '</option>';
    }

    return $html;
}

/**
 * Zkrati text na N znaku. mb_ funkce az od 2017.
 */
function truncate($text, $len = 50)
{
    $text = (string) $text;
    if (mb_strlen($text) <= $len) {
        return $text;
    }

    return mb_substr($text, 0, $len - 1) . '…';
}

function yes_no($v)
{
    return $v ? 'ano' : 'ne';
}

function debug_dump($var)
{
    if (!config('debug')) {
        return '';
    }

    return '<pre class="debug">' . h(print_r($var, true)) . '</pre>';
}
