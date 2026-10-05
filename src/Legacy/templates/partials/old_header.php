<?php
/**
 * Hlavicka pro stare proceduralni stranky (pred 2017).
 * Pred includem nastavit $pageTitle.
 * (kopie layout.php, FIXME sjednotit)
 */
if (!isset($pageTitle)) {
    $pageTitle = 'Administrace';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title><?php echo h($pageTitle); ?> | Administrace</title>
    <style>
        body { font-family: Verdana, Arial, sans-serif; font-size: 12px; margin: 0; background: #f4f4f4; }
        #header { background: #2b4a6b; color: #fff; padding: 8px 15px; }
        #header a { color: #fff; }
        #menu { background: #dde4ec; padding: 5px 15px; border-bottom: 1px solid #aab; }
        #menu a { margin-right: 12px; color: #223; text-decoration: none; }
        #content { background: #fff; margin: 10px 15px; padding: 10px 15px; border: 1px solid #ccc; }
        table.grid { border-collapse: collapse; width: 100%; }
        table.grid th { background: #e8e8e8; text-align: left; }
        table.grid td, table.grid th { border: 1px solid #ccc; padding: 3px 5px; }
        .num { text-align: right; white-space: nowrap; }
        .msg { padding: 5px 10px; border: 1px solid #9c9; background: #efe; }
        .err { padding: 5px 10px; border: 1px solid #c99; background: #fee; }
        .low { color: #c00; font-weight: bold; }
        .hint { color: #777; }
    </style>
</head>
<body>
<div id="header"><strong><?php echo h(config('shop_name')); ?></strong> – administrace</div>
<?php include __DIR__ . '/menu.php'; ?>
<div id="content">
