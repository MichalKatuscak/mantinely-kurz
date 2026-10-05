<?php
/**
 * Mesicni report trzeb.
 * Promenne: $header (title, generated, shop), $rows (label, value), $month
 */
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title><?php echo h($header['title']); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; margin: 20px; }
        h1 { font-size: 20px; margin-bottom: 0; }
        .generated { color: #777; margin-top: 2px; }
        table.report { border-collapse: collapse; margin-top: 15px; min-width: 400px; }
        table.report td { border: 1px solid #ccc; padding: 4px 8px; }
        table.report td.value { text-align: right; font-weight: bold; }
        .noprint { margin-top: 20px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
<div class="report-header">
    <h1><?php echo h($header['title']); ?></h1>
    <p class="generated"><?php echo h($header['generated']); ?></p>
</div>

<form method="get" class="noprint">
    Měsíc: <input type="text" name="month" value="<?php echo h($month); ?>" size="8" placeholder="RRRR-MM">
    <input type="submit" value="Zobrazit">
</form>

<table class="report">
<?php foreach ($rows as $row) { ?>
    <tr>
        <td><?php echo h($row['label']); ?></td>
        <td class="value"><?php echo h($row['value']); ?></td>
    </tr>
<?php } ?>
</table>

<p class="note">Započteny jsou pouze zaplacené objednávky. Ceny v cizí měně přepočteny kurzem z tabulky kurzů.</p>

<?php /* stary odkaz na PDF, FPDF uz neni
<p><a href="report_pdf.php?month=<?php echo h($month); ?>">Stáhnout PDF</a></p>
*/ ?>
<p class="noprint"><a href="javascript:window.print()">Vytisknout</a></p>
</body>
</html>
