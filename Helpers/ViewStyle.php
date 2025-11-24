<?php
namespace Helpers;

class ViewStyle {
    public static function normalizeKey(?string $value): string|false|null {
        if ($value === null) return null;

        $ascii = Text::toAscii($value);
        if ($ascii === false)
            return false;

        $s = mb_strtolower($ascii, 'UTF-8');
        return $s;
    }

    public static function getElementStyle(string $element, array $palette): string {
        $key = self::normalizeKey($element);
        $key = $key === false ? $element : $key;

        $styles = [];

        if (isset($palette[$key])) {
            $bg = htmlspecialchars($palette[$key]['bg'] ?? '', ENT_QUOTES, 'UTF-8');
            $fg = htmlspecialchars($palette[$key]['fg'] ?? '', ENT_QUOTES, 'UTF-8');

            if ($bg) {
                $styles[] = "--el-bg: {$bg};";
            }
            if ($fg) {
                $styles[] = "--el-fg: {$fg};";
            }
        }

        return implode(' ', $styles);
    }
}
