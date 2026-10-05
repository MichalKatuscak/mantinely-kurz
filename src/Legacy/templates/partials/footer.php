<?php
/**
 * Paticka. Debug info jen pri config['debug'].
 */
?>
<div id="footer">
    Administrace v<?php echo h(LEGACY_VERSION); ?> © 2014–2018
<?php if (config('debug') && isset($GLOBALS['db']) && $GLOBALS['db'] !== null) { ?>
    | dotazů: <?php echo (int) $GLOBALS['db']->queryCount; ?>
    | poslední: <code><?php echo h(truncate($GLOBALS['db']->lastSql, 120)); ?></code>
<?php } ?>
</div>
