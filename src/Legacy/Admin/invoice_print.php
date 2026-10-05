<?php
/**
 * Tisk faktury (HTML -> Ctrl+P). FPDF uz neni, viz lib/pdf.php.
 * ?id=<invoice id>
 */

use App\Legacy\lib\InvoiceHelper;
use App\Legacy\lib\PriceUtils;

global $db;
legacy_db();
auth_require('ucetni');

$id = (int) get_param('id');
$faktura = InvoiceHelper::load($id);
if ($faktura === null) {
    echo '<p>Faktura nenalezena</p>';
    return;
}

$objednavka = $db->one("SELECT * FROM orders WHERE id = '" . $faktura['order_id'] . "'");
$zakaznik = $objednavka ? $db->one("SELECT * FROM customers WHERE id = '" . $objednavka['customer_id'] . "'") : null;
$polozky = InvoiceHelper::items($faktura['order_id']);
$dph = InvoiceHelper::vatSummary($faktura);
$ucet = settings_get('bank_account', '123456789/0100');
$paticka = settings_get('invoice_footer', 'Zapsáno v obchodním rejstříku vedeném Krajským soudem v Brně.');

// stara cesta pres "PDF"
if (get_param('pdf') == '1') {
    echo pdf_invoice($faktura, $polozky);
    return;
}

// sleva na fakture – jako zaporna polozka
$sleva = $objednavka ? (int) $objednavka['discount_amount_in_cents'] : 0;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Faktura <?php echo h($faktura['number']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; width: 190mm; margin: 10mm auto; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #999; padding: 4px; }
        .num { text-align: right; }
        .noborder td { border: none; }
        h1 { font-size: 22px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
<p class="noprint"><a href="javascript:window.print()">Vytisknout</a> | <a href="<?php echo h(admin_url('invoices')); ?>">« faktury</a></p>
<h1>Faktura – daňový doklad č. <?php echo h($faktura['number']); ?></h1>

<table class="noborder">
    <tr>
        <td style="width:50%;vertical-align:top">
            <strong>Dodavatel:</strong><br>
            <?php echo h(config('shop_name')); ?><br>
            Masarykova 1, 602 00 Brno<br>
            IČ: 12345678, DIČ: CZ12345678
        </td>
        <td style="vertical-align:top">
            <strong>Odběratel:</strong><br>
            <?php if ($zakaznik) { ?>
                <?php echo h($zakaznik['name']); ?><br>
                <?php echo h($zakaznik['email']); ?><br>
                <?php echo h($zakaznik['phone']); ?>
            <?php } else { ?>
                zákazník <?php echo h($objednavka ? $objednavka['customer_id'] : ''); ?>
            <?php } ?>
        </td>
    </tr>
</table>

<p>
    Datum vystavení: <?php echo format_date($faktura['issued_at']); ?><br>
    Datum zdanitelného plnění: <?php echo format_date($faktura['issued_at']); ?><br>
    Datum splatnosti: <?php echo date('j. n. Y', strtotime((string) $faktura['issued_at'] . ' +14 days')); ?><br>
    Číslo účtu: <?php echo h($ucet); ?>, VS: <?php echo h(preg_replace('/\D/', '', (string) $faktura['number'])); ?>
</p>

<table>
    <tr><th>Položka</th><th class="num">Množství</th><th class="num">Cena/ks</th><th class="num">Celkem</th></tr>
<?php foreach ($polozky as $p) { ?>
    <tr>
        <td><?php echo h($p['name']); ?></td>
        <td class="num"><?php echo (int) $p['quantity']; ?></td>
        <td class="num"><?php echo PriceUtils::format((int) $p['unit_price_amount_in_cents'], $p['unit_price_currency']); ?></td>
        <td class="num"><?php echo PriceUtils::format($p['quantity'] * $p['unit_price_amount_in_cents'], $p['unit_price_currency']); ?></td>
    </tr>
<?php } ?>
<?php if ($sleva > 0) { ?>
    <tr><td>Sleva</td><td class="num">1</td><td class="num"></td><td class="num">-<?php echo PriceUtils::format($sleva, $faktura['currency']); ?></td></tr>
<?php } ?>
</table>

<table style="width:50%;margin-left:50%;margin-top:10px">
    <tr><td>Základ daně</td><td class="num"><?php echo PriceUtils::format($dph['base'], $faktura['currency']); ?></td></tr>
    <tr><td>DPH <?php echo (int) $dph['rate']; ?> %</td><td class="num"><?php echo PriceUtils::format($dph['vat'], $faktura['currency']); ?></td></tr>
    <tr><td><strong>Celkem k úhradě</strong></td><td class="num"><strong><?php echo PriceUtils::format($dph['total'], $faktura['currency']); ?></strong></td></tr>
</table>

<p style="margin-top:30px;font-size:10px;color:#666"><?php echo h($paticka); ?></p>
</body>
</html>
