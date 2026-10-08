<?php
/**
 * Ochrana formularu proti CSRF (podvrzenemu pozadavku z ciziho webu).
 *
 * Kazda akce, ktera meni data:
 *
 *     // formular: hned za <form method="post">
 *     echo csrf_field();
 *
 *     // akce: hned po auth_require()
 *     if (is_post()) {
 *         csrf_check();
 *         ...
 *     }
 *
 * Token je jeden na session. Ulozi ho do session LegacyFrontController
 * a preda v $GLOBALS['LEGACY_CSRF_TOKEN']; mimo nej (cron, testy) se vygeneruje.
 */

use App\Legacy\lib\AccessDenied;

function csrf_token()
{
    if (empty($GLOBALS['LEGACY_CSRF_TOKEN']) || !is_string($GLOBALS['LEGACY_CSRF_TOKEN'])) {
        $GLOBALS['LEGACY_CSRF_TOKEN'] = bin2hex(random_bytes(32));
    }

    return $GLOBALS['LEGACY_CSRF_TOKEN'];
}

/**
 * Skryte pole s tokenem do kazdeho formulare s method="post".
 */
function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

/**
 * Overi token z POSTu. Bez platneho tokenu vyhodi AccessDenied (-> 403), nic se neulozi.
 */
function csrf_check()
{
    $sent = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        throw new AccessDenied('Neplatný bezpečnostní token formuláře. Načtěte stránku znovu a akci opakujte.');
    }

    return true;
}
