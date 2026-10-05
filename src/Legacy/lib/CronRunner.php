<?php
/**
 * Spousteni periodickych uloh. Vola cron.php (crontab: kazdou hodinu).
 *
 * Storno nezaplacenych objednavek je primo v cron.php (historicky),
 * tady jsou ostatni ulohy.
 */

namespace App\Legacy\lib;

class CronRunner
{
    public $log = array();

    public $dryRun = false;

    public function run($jobs = null)
    {
        $all = array('newsletter', 'cache', 'lowStock', 'auditCleanup');
        if ($jobs === null) {
            $jobs = $all;
        }
        foreach ($jobs as $job) {
            $method = 'job' . ucfirst($job);
            if (!method_exists($this, $method)) {
                $this->log('neznama uloha ' . $job);
                continue;
            }
            try {
                $this->$method();
            } catch (\Exception $e) {
                $this->log('CHYBA ' . $job . ': ' . $e->getMessage());
                notify_admin('Chyba cronu', $job . ': ' . $e->getMessage());
            }
        }

        return $this->log;
    }

    public function log($msg)
    {
        $this->log[] = date('H:i:s') . ' ' . $msg;
    }

    /**
     * Odesle frontu newsletteru (max 100 za beh, limit hostingu).
     */
    public function jobNewsletter()
    {
        global $db;
        legacy_db();
        $rows = $db->query("SELECT * FROM newsletter_queue WHERE sent_at IS NULL ORDER BY id LIMIT 100");
        $n = 0;
        foreach ($rows as $r) {
            if ($this->dryRun) {
                continue;
            }
            if (send_mail($r['email'], $r['subject'], $r['body'])) {
                $db->exec("UPDATE newsletter_queue SET sent_at = '" . date('Y-m-d H:i:s') . "' WHERE id = " . (int) $r['id']);
                $n++;
            }
        }
        $this->log('newsletter: odeslano ' . $n);
    }

    public function jobCache()
    {
        $n = cache_clear();
        $this->log('cache: smazano ' . $n);
    }

    /**
     * Upozorneni na nizky sklad. Max jednou denne (settings.low_stock_notified).
     */
    public function jobLowStock()
    {
        $last = settings_get('low_stock_notified', '');
        if ($last == date('Y-m-d')) {
            return;
        }
        $low = StockReport::lowStock();
        if (count($low) > 0) {
            $body = '';
            foreach ($low as $r) {
                $body .= ($r['name'] !== null ? $r['name'] : $r['product_id']) . ': ' . $r['available'] . " ks\n";
            }
            if (!$this->dryRun) {
                notify_admin('Nízký stav skladu', $body);
            }
        }
        settings_set('low_stock_notified', date('Y-m-d'));
        $this->log('lowStock: ' . count($low));
    }

    /**
     * Mazani audit logu starsiho nez rok.
     */
    public function jobAuditCleanup()
    {
        global $db;
        legacy_db();
        $limit = date('Y-m-d H:i:s', strtotime('-1 year'));
        if ($this->dryRun) {
            return;
        }
        $n = $db->exec("DELETE FROM audit_log WHERE created_at < '" . $limit . "'");
        $this->log('audit: smazano ' . $n);
    }

    // stare stahovani kurzu z CNB – CNB zmenila format, nefunguje od 2019
    public function jobRates()
    {
        // $txt = file_get_contents('https://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt');
        $this->log('rates: vypnuto');
    }
}
