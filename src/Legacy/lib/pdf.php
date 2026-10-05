<?php
/**
 * "PDF" faktury.
 *
 * Puvodne FPDF (2015), knihovna se ztratila pri stehovani serveru (2019).
 * Od te doby se faktura tiskne z prohlizece (invoice_print.php, Ctrl+P).
 * Funkce tu zustavaji, protoze je vola InvoiceHelper.
 */

function pdf_available()
{
    return class_exists('FPDF', false);
}

/**
 * Vrati "PDF" – ve skutecnosti HTML pro tisk.
 */
function pdf_invoice($invoice, $items)
{
    if (pdf_available()) {
        // nikdy se nespusti, FPDF uz neni
        return '';
    }

    $html = '<html><head><meta charset="utf-8"><title>Faktura ' . h($invoice['number']) . '</title>';
    $html .= '<style>body{font-family:DejaVu Sans,Arial;font-size:12px} table{border-collapse:collapse;width:100%} td,th{border:1px solid #999;padding:3px}</style>';
    $html .= '</head><body onload="window.print()">';
    $html .= '<h1>Faktura č. ' . h($invoice['number']) . '</h1>';
    $html .= '<p>Datum vystavení: ' . format_date($invoice['issued_at']) . '</p>';
    $html .= '<table><tr><th>Položka</th><th>Ks</th><th>Cena/ks</th><th>Celkem</th></tr>';
    foreach ($items as $it) {
        $html .= '<tr><td>' . h($it['name']) . '</td><td>' . (int) $it['quantity'] . '</td>'
            . '<td>' . format_price($it['unit_price_amount_in_cents'], $it['unit_price_currency']) . '</td>'
            . '<td>' . format_price($it['quantity'] * $it['unit_price_amount_in_cents'], $it['unit_price_currency']) . '</td></tr>';
    }
    $html .= '</table>';
    $html .= '<p><strong>Celkem k úhradě: ' . format_price($invoice['total_cents'], $invoice['currency']) . '</strong></p>';
    $html .= '</body></html>';

    return $html;
}

function pdf_filename($invoice)
{
    return 'faktura-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string) $invoice['number']) . '.pdf';
}
