<?php
declare(strict_types=1);

namespace Arena;

final class Preview {
    public const THEME = 'arena';

    /** Decide se a requisição deve visualizar o Arena. Lógica pura. */
    public static function shouldPreview(bool $loggedInCan, ?string $tokenParam, ?string $expectedToken): bool {
        if ($loggedInCan) { return true; }
        if ($expectedToken === null || $expectedToken === '') { return false; }
        return is_string($tokenParam) && hash_equals($expectedToken, $tokenParam);
    }
}
