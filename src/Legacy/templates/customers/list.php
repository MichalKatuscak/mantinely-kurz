<?php
/**
 * Seznam zakazniku.
 * Promenne: $customers, $q, $newsletter, $total, $pager
 */
?>
<h1>Zákazníci (<?php echo (int) $total; ?>)</h1>
<form method="get">
    <input type="text" name="q" value="<?php echo h($q); ?>" placeholder="jméno, e-mail, telefon">
    Newsletter: <select name="newsletter"><?php echo select_options(array('' => '– vše –', '1' => 'ano', '0' => 'ne'), $newsletter); ?></select>
    <input type="submit" value="Hledat">
    <a href="<?php echo h(admin_url('customer_edit')); ?>">+ nový zákazník</a>
</form>
<table class="grid">
    <tr><th>Jméno</th><th>E-mail</th><th>Telefon</th><th>Registrace</th><th>Newsletter</th><th class="num">Objednávek</th><th></th></tr>
<?php foreach ($customers as $c) { ?>
    <tr>
        <td><a href="<?php echo h(admin_url('customer', array('id' => $c['id']))); ?>"><?php echo h($c['name']); ?></a></td>
        <td><?php echo h($c['email']); ?></td>
        <td><?php echo h($c['phone']); ?></td>
        <td><?php echo format_date($c['created_at']); ?></td>
        <td><?php echo yes_no($c['newsletter']); ?></td>
        <td class="num"><?php echo (int) $c['orders_count']; ?></td>
        <td><a href="<?php echo h(admin_url('customer_edit', array('id' => $c['id']))); ?>">upravit</a></td>
    </tr>
<?php } ?>
</table>
<?php echo $pager; ?>
