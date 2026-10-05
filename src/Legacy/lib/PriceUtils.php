<?php
/**
 * Pomocne funkce pro ceny – "OOP verze" (martin 2017).
 *
 * Pozor: formatuje jinak nez format_price() v helpers.php
 * (tady bez mezery u tisicu u EUR, symbol meny pred castkou...).
 * Pouziva se na fakturach a v exportu zakazniku.
 */

namespace App\Legacy\lib;

class PriceUtils
{
    const VAT = 21;

    public static $symbols = array(
        'CZK' => 'Kč',
        'EUR' => '€',
        'USD' => '$',
    );

    /**
     * 123450 -> "1 234,50 Kč" / "€1234.50" / "$1,234.50"
     */
    public static function format($cents, $currency = 'CZK')
    {
        $amount = $cents / 100;
        if ($currency == 'CZK') {
            return number_format($amount, 2, ',', ' ') . ' Kč';
        }
        if ($currency == 'EUR') {
            return '€' . number_format($amount, 2, '.', '');
        }
        if ($currency == 'USD') {
            return '$' . number_format($amount, 2, '.', ',');
        }

        return number_format($amount, 2) . ' ' . $currency;
    }

    /**
     * Cena bez DPH z ceny s DPH (v halerich, zaokrouhleno dolu!).
     */
    public static function withoutVat($cents)
    {
        return (int) floor($cents / (1 + self::VAT / 100));
    }

    public static function vatPart($cents)
    {
        return $cents - self::withoutVat($cents);
    }

    /**
     * Prevod na CZK – vlastni kurzy, NE z tabulky exchange_rates.
     * (kurzy z doby, kdy tabulka jeste nebyla; FIXME sjednotit s toCzk())
     */
    public static function toCzk($cents, $currency)
    {
        $rates = array('CZK' => 1, 'EUR' => 27.0, 'USD' => 22.5);
        if (!isset($rates[$currency])) {
            return $cents;
        }

        return (int) round($cents * $rates[$currency]);
    }

    /**
     * Zaokrouhleni na cele koruny (hotovost).
     */
    public static function roundCash($cents)
    {
        return (int) (round($cents / 100) * 100);
    }

    public static function symbol($currency)
    {
        return isset(self::$symbols[$currency]) ? self::$symbols[$currency] : $currency;
    }

    /**
     * Parsuje "1 234,50" -> 123450
     */
    public static function parse($s)
    {
        $s = trim((string) $s);
        $s = preg_replace('/[^0-9,\.\-]/', '', $s);
        $s = str_replace(',', '.', $s);
        if ($s === '' || $s === '-') {
            return 0;
        }

        return (int) round(((float) $s) * 100);
    }

    /*
    public static function formatOld($kc)
    {
        return number_format($kc, 0, ',', '.') . ',-';
    }
    */
}
