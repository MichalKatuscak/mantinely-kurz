<?php
/**
 * Prohlizeni auditniho logu.
 * ?entity=order&id=...&akce=...
 */

global $db;
legacy_db();
auth_require('admin');

$entity = get_param('entity');
$entityId = get_param('id');
$akce = get_param('akce');
list($offset, $limit, $page) = paginate(get_param('page', 1), 100);

$where = array();
if ($entity != '') {
    $where[] = "entity = '" . $entity . "'";
}
if ($entityId != '') {
    $where[] = "entity_id = '" . $entityId . "'";
}
if ($akce != '') {
    $where[] = "action = '" . $akce . "'";
}
$whereSql = count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '';

$celkem = (int) $db->value("SELECT COUNT(*) FROM audit_log" . $whereSql);
$zaznamy = $db->query("SELECT * FROM audit_log" . $whereSql . " ORDER BY id DESC LIMIT " . $limit . " OFFSET " . $offset);

$entity_list = array('' => '– vše –');
foreach ($db->query("SELECT DISTINCT entity FROM audit_log ORDER BY entity") as $r) {
    $entity_list[$r['entity']] = $r['entity'];
}

$pageTitle = 'Audit';
include LEGACY_TEMPLATES . '/partials/old_header.php';
?>
<h1>Audit log (<?php echo $celkem; ?>)</h1>
<form method="get">
    <select name="entity"><?php echo select_options($entity_list, $entity); ?></select>
    ID: <input type="text" name="id" value="<?php echo h($entityId); ?>">
    Akce: <input type="text" name="akce" value="<?php echo h($akce); ?>" size="10">
    <input type="submit" value="Filtrovat">
</form>
<table class="grid">
    <tr><th>Kdy</th><th>Entita</th><th>ID</th><th>Akce</th><th>Data</th></tr>
<?php foreach ($zaznamy as $z) { ?>
    <?php $data = json_decode((string) $z['payload'], true); ?>
    <tr>
        <td><?php echo format_date($z['created_at'], true); ?></td>
        <td><?php echo h($z['entity']); ?></td>
        <td>
            <?php if ($z['entity'] == 'order') { ?>
                <a href="<?php echo h(admin_url('order', array('id' => $z['entity_id']))); ?>"><?php echo h(truncate($z['entity_id'], 13)); ?></a>
            <?php } else { ?>
                <?php echo h(truncate($z['entity_id'], 30)); ?>
            <?php } ?>
        </td>
        <td><?php echo h($z['action']); ?></td>
        <td><small><?php
            if (is_array($data)) {
                $parts = array();
                foreach ($data as $k => $v) {
                    $parts[] = $k . '=' . (is_scalar($v) ? (string) $v : json_encode($v));
                }
                echo h(implode(', ', $parts));
            } else {
                echo h($z['payload']);
            }
        ?></small></td>
    </tr>
<?php } ?>
</table>
<?php echo pager_html($page, $celkem, $limit, admin_url('audit', array('entity' => $entity, 'id' => $entityId))); ?>
<?php
include LEGACY_TEMPLATES . '/partials/old_footer.php';
