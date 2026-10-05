<?php
/**
 * Hlavni menu. Polozky podle role (auth_has_role).
 */
$menuItems = array(
    'dashboard'     => array('Nástěnka', null),
    'orders'        => array('Objednávky', 'obchod'),
    'order_list'    => array('Objednávky (stará verze)', 'obchod'),
    'customers'     => array('Zákazníci', 'obchod'),
    'products'      => array('Produkty', 'obchod'),
    'stock'         => array('Sklad', 'sklad'),
    'stock_report'  => array('Report skladu', 'sklad'),
    'invoices'      => array('Faktury', 'ucetni'),
    'report'        => array('Report tržeb', 'ucetni'),
    'monthly'       => array('Měsíční přehled', 'ucetni'),
    'stats'         => array('Statistiky', null),
    'top_products'  => array('Top produkty', null),
    'export'        => array('Exporty', 'ucetni'),
    'newsletter'    => array('Newsletter', 'obchod'),
    'suppliers'     => array('Dodavatelé', 'sklad'),
    'users'         => array('Uživatelé', 'admin'),
    'settings'      => array('Nastavení', 'admin'),
    'audit'         => array('Audit', 'admin'),
);
?>
<div id="menu">
<?php foreach ($menuItems as $menuPage => $menuItem) { ?>
    <?php if ($menuItem[1] === null || auth_has_role($menuItem[1])) { ?>
        <a href="<?php echo h(admin_url($menuPage)); ?>"><?php echo h($menuItem[0]); ?></a>
    <?php } ?>
<?php } ?>
    <form method="get" action="<?php echo h(admin_url('search')); ?>" style="display:inline;float:right">
        <input type="text" name="q" size="15" placeholder="hledat…">
    </form>
</div>
