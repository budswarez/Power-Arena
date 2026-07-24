<?php
declare(strict_types=1);

namespace Arena;

final class Options {
    /** Lê uma opção do ACF; degrada para $default se ACF ausente ou vazio. */
    public static function get(string $key, mixed $default = null): mixed {
        if (!function_exists('get_field')) { return $default; }
        $value = get_field($key, 'option');
        return ($value === null || $value === '' || $value === false) ? $default : $value;
    }

    public static function accentColor(): string {
        $c = self::get('arena_accent_color', '#e00000');
        return is_string($c) ? $c : '#e00000';
    }

    public static function logoId(): ?int {
        $v = self::get('arena_logo', null);
        return is_numeric($v) ? (int) $v : null;
    }
}
