<?php
/**
 * Obecna tabulka.
 * Promenne: $heading, $columns (nazvy sloupcu = klice v $rows), $rows, volitelne $raw (sloupce bez escapovani)
 */
if (!isset($raw)) {
    $raw = array();
}
?>
<h1><?php echo h($heading); ?></h1>
<?php if (count($rows) == 0) { ?>
    <p class="hint">Žádné záznamy.</p>
<?php } else { ?>
<table class="grid">
    <tr>
    <?php foreach ($columns as $col) { ?>
        <th><?php echo h($col); ?></th>
    <?php } ?>
    </tr>
    <?php foreach ($rows as $row) { ?>
    <tr>
        <?php foreach ($columns as $col) { ?>
            <td><?php echo in_array($col, $raw) ? $row[$col] : h(isset($row[$col]) ? $row[$col] : ''); ?></td>
        <?php } ?>
    </tr>
    <?php } ?>
</table>
<p class="hint">Celkem <?php echo count($rows); ?> záznamů.</p>
<?php } ?>
