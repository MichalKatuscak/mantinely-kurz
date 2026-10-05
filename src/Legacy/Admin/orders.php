<?php
/**
 * Hromadne akce s objednavkami (formular ze seznamu objednavek).
 *
 * POST ids[] + action:
 *   storno   – stornuje vybrane objednavky
 *   paid     – oznaci jako zaplacene (parovani plateb z banky, 2016)
 *   shipped  – oznaci jako odeslane (hromadne odeslani balíku)
 */

global $db;
legacy_db();
auth_require('obchod');

$ids = isset($_POST['ids']) ? $_POST['ids'] : array();
$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

if (!is_array($ids) || count($ids) == 0) {
    flash('Nevybrali jste žádné objednávky', 'error');
    redirect(admin_url('orders'));
    return;
}

$idList = ids_to_sql($ids);

if ($action == 'storno') {
    // stornovat lze cokoliv krome dorucenych (2017: i odeslane, kdyz se balik vrati)
    $before = $db->query("SELECT id, status FROM orders WHERE id IN (" . $idList . ")");
    $n = $db->exec("UPDATE orders SET status = 'cancelled' WHERE id IN (" . $idList . ") AND status != 'delivered'");
    foreach ($before as $b) {
        if ($b['status'] != 'delivered') {
            audit_log('order', $b['id'], 'storno', array('from' => $b['status'], 'bulk' => true));
        }
    }
    // FIXME: rezervace ve skladu zustanou viset, viz StockReport::orphanReservations()
    flash('Stornováno objednávek: ' . $n);
} elseif ($action == 'paid') {
    $n = $db->exec("UPDATE orders SET status = 'paid' WHERE id IN (" . $idList . ") AND status = 'confirmed'");
    foreach ($ids as $oid) {
        audit_log('order', $oid, 'status', array('to' => 'paid', 'bulk' => true));
    }
    flash('Označeno jako zaplacené: ' . $n);
} elseif ($action == 'shipped') {
    $n = $db->exec("UPDATE orders SET status = 'shipped' WHERE id IN (" . $idList . ") AND status = 'paid'");
    foreach ($ids as $oid) {
        audit_log('order', $oid, 'status', array('to' => 'shipped', 'bulk' => true));
    }
    flash('Označeno jako odeslané: ' . $n);
} else {
    flash('Neznámá akce', 'error');
}

cache_delete('dashboard_stats');
redirect(admin_url('orders'));
