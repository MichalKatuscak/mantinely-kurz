<?php
/**
 * Proceduralni obalka nad LegacyMailer (pro stare stranky).
 */

use App\Legacy\lib\LegacyMailer;

function send_mail($to, $subject, $body)
{
    static $mailer = null;
    if ($mailer === null) {
        $mailer = new LegacyMailer();
    }

    return $mailer->send($to, $subject, $body);
}

/**
 * Upozorneni adminovi (nizky sklad, chyba cronu...).
 */
function notify_admin($subject, $body)
{
    return send_mail(config('admin_email'), '[admin] ' . $subject, $body);
}

/**
 * Prida mail do fronty newsletteru (odesila cron).
 */
function newsletter_enqueue($customerId, $email, $subject, $body)
{
    global $db;
    legacy_db();
    $db->exec("INSERT INTO newsletter_queue (customer_id, email, subject, body, created_at, sent_at) VALUES ('"
        . $customerId . "', '" . $email . "', " . $db->quote($subject) . ", " . $db->quote($body) . ", '" . date('Y-m-d H:i:s') . "', NULL)");
}
