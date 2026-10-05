<?php
/**
 * Cron administrace. Crontab:
 *
 *     0 * * * *  php /var/www/eshop/src/Legacy/cron.php >> /var/log/eshop-cron.log
 *
 * 1) stornuje potvrzene, ale nezaplacene objednavky starsi nez 14 dni
 * 2) ostatni ulohy (newsletter, cache, sklad) – viz lib/CronRunner.php
 */

require_once __DIR__ . '/bootstrap.php';

use App\Legacy\lib\CronRunner;

global $db;
legacy_db();

$cronDryRun = in_array('--dry-run', isset($argv) ? $argv : array());
$cronDays = (int) config('unpaid_days', 14);
$cronLimit = date('Y-m-d H:i:s', strtotime('-' . $cronDays . ' days'));

echo date('Y-m-d H:i:s') . " cron start\n";

// --- 1) storno nezaplacenych -------------------------------------------
$unpaid = $db->query("SELECT id, customer_id, placed_at FROM orders WHERE status = 'confirmed' AND placed_at < '" . $cronLimit . "'");
echo "nezaplacene starsi nez " . $cronDays . " dni: " . count($unpaid) . "\n";

foreach ($unpaid as $o) {
    if ($cronDryRun) {
        echo "  [dry-run] " . $o['id'] . "\n";
        continue;
    }
    $db->exec("UPDATE orders SET status = 'cancelled' WHERE id = '" . $o['id'] . "' AND status = 'confirmed'");
    // TODO: uvolnit rezervace ve stock_items? (novy e-shop to dela sam? overit!)
    audit_log('order', $o['id'], 'cron_storno', array('placed_at' => $o['placed_at']));

    $c = $db->one("SELECT email FROM customers WHERE id = '" . $o['customer_id'] . "'");
    if ($c && $c['email'] != '') {
        send_mail($c['email'], 'Objednávka byla stornována', "Dobrý den,\n\nvaše objednávka " . $o['id'] . " nebyla do " . $cronDays . " dnů zaplacena, a proto byla stornována.\n");
    }
    echo "  storno " . $o['id'] . "\n";
}

// --- 2) ostatni ulohy ----------------------------------------------------
$runner = new CronRunner();
$runner->dryRun = $cronDryRun;
foreach ($runner->run() as $line) {
    echo "  " . $line . "\n";
}

// stare: prepocet kurzu, vypnuto 2019
// $runner->run(array('rates'));

echo date('Y-m-d H:i:s') . " cron konec\n";
