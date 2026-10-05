<?php
/**
 * Odesilani e-mailu z administrace.
 *
 * Driv PHPMailer pres SMTP seznamu, ted se maily jen zapisuji do logu
 * a posila je newsletter sluzba (externi). Objednavkove maily posila novy e-shop.
 */

namespace App\Legacy\lib;

class LegacyMailer
{
    public $from = 'obchod@example.cz';

    public $fromName = 'Obchod u Mantinelu';

    private $logFile;

    public $sent = array();

    public function __construct($logFile = null)
    {
        $this->logFile = $logFile !== null ? $logFile : config('mail_log', sys_get_temp_dir() . '/legacy_mail.log');
    }

    public function send($to, $subject, $body, $html = false)
    {
        if (!is_email($to)) {
            return false;
        }

        $line = date('Y-m-d H:i:s') . "\t" . $to . "\t" . $subject . "\t" . ($html ? 'html' : 'text') . "\t" . strlen((string) $body) . "\n";
        $this->sent[] = array('to' => $to, 'subject' => $subject);

        // TODO: skutecne odesilani (SMTP) – rozhodnout, jestli vubec
        return @file_put_contents($this->logFile, $line, FILE_APPEND) !== false;
    }

    /**
     * Stara metoda z PHPMaileru, nekde se jeste vola.
     */
    public function sendTemplate($to, $template, $vars = array())
    {
        $file = LEGACY_TEMPLATES . '/mail/' . $template . '.php';
        if (!file_exists($file)) {
            // sablony mailu se nikdy neprenesly ze stareho serveru
            return $this->send($to, isset($vars['subject']) ? $vars['subject'] : $template, print_r($vars, true));
        }
        extract($vars);
        ob_start();
        include $file;
        $body = ob_get_clean();

        return $this->send($to, isset($vars['subject']) ? $vars['subject'] : $template, $body, true);
    }
}
