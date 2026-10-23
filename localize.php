<?php
// Setup locale and translation
setlocale(LC_ALL, $locale);
include "lang/$language.php";

function T($str)
{
    global $_L;
    return isset($_L[$str]) ? $_L[$str] : $str;
}

//
// strftime() was deprecated in PHP 8.1 — use IntlDateFormatter instead.
// Accepts legacy strftime format strings from the language files
// (e.g. '%d %B', '%B %Y', '%l%p', '%d %B %Y') and formats using the
// configured locale when the intl extension is available.
//
//
// HTML-escape helper — escapes for both HTML and XML contexts.
// Use on ANY output that comes from config, translations, or interface names.
//
function h($s)
{
    return htmlspecialchars(strval($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function _strftime($format, $timestamp = null)
{
    global $locale;
    if ($timestamp === null) $timestamp = time();

    if (class_exists('IntlDateFormatter'))
    {
        // strftime token -> ICU pattern token
        $map = array(
            '%d' => 'dd',      // day, 2-digit
            '%-d' => 'd',      // day, no leading zero
            '%B' => 'MMMM',    // full month name
            '%b' => 'MMM',     // abbreviated month
            '%h' => 'MMM',
            '%m' => 'MM',      // month number
            '%Y' => 'yyyy',    // 4-digit year
            '%y' => 'yy',
            '%l' => 'h',       // 12-hour, 1-12
            '%I' => 'hh',      // 12-hour, 2-digit
            '%H' => 'HH',      // 24-hour
            '%M' => 'mm',
            '%S' => 'ss',
            '%p' => 'a',       // AM/PM
            '%P' => 'a',
            '%e' => 'd',       // day of month, space padded
        );

        $pattern = strtr($format, $map);
        try {
            $fmt = new IntlDateFormatter(
                isset($locale) ? str_replace('_', '-', strtok($locale, '.')) : 'en-US',
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                date_default_timezone_get(),
                null,
                $pattern
            );
            $out = $fmt->format($timestamp);
            if ($out !== false && $out !== '')
                return $out;
        } catch (\Exception $e) {
            // fall through to date()
        }
    }

    // Fallback: convert the most common tokens to date() format
    $conv = array(
        '%d' => 'd', '%B' => 'F', '%b' => 'M', '%h' => 'M',
        '%Y' => 'Y', '%y' => 'y', '%m' => 'm',
        '%l' => 'g', '%I' => 'h', '%H' => 'H', '%M' => 'i', '%S' => 's',
        '%p' => 'A', '%P' => 'a', '%e' => 'j',
        '%%' => '%',
    );
    return date(strtr($format, $conv), $timestamp);
}
?>