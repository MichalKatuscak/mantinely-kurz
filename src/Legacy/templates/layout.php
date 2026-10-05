<?php
/**
 * Hlavni layout administrace.
 * Promenne: $content, $title, $flashes
 */
if (!isset($flashes)) {
    $flashes = array();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title><?php echo h($title); ?> | Administrace <?php echo h(config('shop_name')); ?></title>
    <style>
        body { font-family: Verdana, Arial, sans-serif; font-size: 12px; margin: 0; background: #f4f4f4; }
        #header { background: #2b4a6b; color: #fff; padding: 8px 15px; }
        #header a { color: #fff; }
        #menu { background: #dde4ec; padding: 5px 15px; border-bottom: 1px solid #aab; }
        #menu a { margin-right: 12px; color: #223; text-decoration: none; }
        #menu a:hover { text-decoration: underline; }
        #content { background: #fff; margin: 10px 15px; padding: 10px 15px; border: 1px solid #ccc; }
        table.grid { border-collapse: collapse; width: 100%; }
        table.grid th { background: #e8e8e8; text-align: left; }
        table.grid td, table.grid th { border: 1px solid #ccc; padding: 3px 5px; }
        table.grid tr:nth-child(even) td { background: #fafafa; }
        .num { text-align: right; white-space: nowrap; }
        .flash { padding: 5px 10px; margin-bottom: 8px; border: 1px solid #9c9; background: #efe; }
        .flash.error { border-color: #c99; background: #fee; }
        .low { color: #c00; font-weight: bold; }
        .state-cancelled { color: #999; text-decoration: line-through; }
        .state-paid { color: #070; }
        .hint { color: #777; }
        #footer { color: #999; font-size: 11px; margin: 0 15px 15px; }
    </style>
    <!-- <script src="/js/jquery-1.11.1.min.js"></script> -->
</head>
<body>
<div id="header">
    <strong><?php echo h(config('shop_name')); ?></strong> – administrace
    <span style="float:right">přihlášen: <?php echo h(auth_login_name()); ?> | <a href="<?php echo h(admin_url('logout')); ?>">odhlásit</a></span>
</div>
<?php include __DIR__ . '/partials/menu.php'; ?>
<div id="content">
<?php foreach ($flashes as $f) { ?>
    <div class="flash <?php echo h($f['type']); ?>"><?php echo h($f['msg']); ?></div>
<?php } ?>
<?php echo $content; ?>
</div>
<?php include __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
