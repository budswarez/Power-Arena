<?php
declare(strict_types=1);

namespace Arena;

final class Options {
    /**
     * Single source of truth for the accent colour default — matches the
     * real accent baked into assets/src/css/main.css (`--arena-accent`) and
     * theme.json. Also consumed by Arena\OptionsPanel (the ACF field's own
     * `default_value`) so the two can't drift apart again; they previously
     * each carried their own `#e00000` literal, one hex off from the real
     * `#f42c1a` accent.
     */
    public const DEFAULT_ACCENT = '#f42c1a';

    /** Lê uma opção do ACF; degrada para $default se ACF ausente ou vazio. */
    public static function get(string $key, mixed $default = null): mixed {
        if (!function_exists('get_field')) { return $default; }
        $value = get_field($key, 'option');
        return ($value === null || $value === '' || $value === false) ? $default : $value;
    }

    public static function accentColor(): string {
        $c = self::get('arena_accent_color', self::DEFAULT_ACCENT);
        return is_string($c) ? $c : self::DEFAULT_ACCENT;
    }

    public static function logoId(): ?int {
        return self::parseLogoId(self::get('arena_logo', null));
    }

    /**
     * Pure parsing logic behind logoId(), pulled out so it's directly unit-
     * testable without needing to mock ACF's get_field(). Tightened against
     * `is_numeric()`, which also accepts float-strings like `"3.9"` — a
     * value that can never be a valid attachment ID. Round-tripping through
     * `(int)` and comparing back to the original string only accepts clean
     * positive-integer values: "12" -> 12; "3.9", "abc", "-1", "0", "" ->
     * null.
     */
    public static function parseLogoId(mixed $value): ?int {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $str = (string) $value;
        if ($str === '' || (string) (int) $str !== $str) {
            return null;
        }
        $int = (int) $str;

        return $int > 0 ? $int : null;
    }
}
