<?php
namespace Helpers;

class Text {
    public static function toAscii(string $value): string|false {
        $current = $value;
        $hadSuccess = false;

        if (function_exists('transliterator_transliterate')) {
            $icu = @transliterator_transliterate('Any-Latin; Latin-ASCII; [:Nonspacing Mark:] Remove; NFC', $value);
            if (is_string($icu)) {
                $current = $icu;
                $hadSuccess = true;
            }
        }

        if ((!$hadSuccess || $current === '') && function_exists('iconv')) {
            $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($iconv !== false) {
                $current = $iconv;
                $hadSuccess = true;
            }
        }

        $current = trim($current);
        return ($hadSuccess && $current !== '') ? $current : false;
    }
}