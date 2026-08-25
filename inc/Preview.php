<?php
declare(strict_types=1);

namespace Arena;

final class Preview {
    /**
     * Canonical theme slug used to force the Arena preview
     * (mu-plugins/arena-preview.php's `template`/`stylesheet` filters).
     * Documented single source of truth: the mu-plugin runs at
     * `setup_theme`, BEFORE this class's own autoloader
     * (functions.php's spl_autoload_register(), itself only registered
     * once a theme has already been resolved) is available, so it can't
     * reference this constant directly and keeps its own inline `'arena'`
     * literal — with a comment pointing back here. Covered by
     * PreviewTest::test_theme_slug_matches_mu_plugin_literal() so the two
     * can't silently drift apart.
     */
    public const THEME = 'arena';

    /** Decide se a requisição deve visualizar o Arena. Lógica pura. */
    public static function shouldPreview(bool $loggedInCan, ?string $tokenParam, ?string $expectedToken): bool {
        if ($loggedInCan) { return true; }
        if ($expectedToken === null || $expectedToken === '') { return false; }
        return is_string($tokenParam) && hash_equals($expectedToken, $tokenParam);
    }

    /**
     * Resolves which STYLESHEET (not template — the parent theme dir is
     * always `self::THEME`) the preview should force. Mirrors the same
     * decision `mu-plugins/arena-preview.php` makes inline for its
     * `stylesheet` filter (that file can't reference this class directly —
     * see the class docblock above), from an optional
     * `ARENA_PREVIEW_STYLESHEET` constant.
     *
     * Without this, the mu-plugin hardcoded `stylesheet => 'arena'` — so
     * if production ever runs `arena-child` as the active theme, the
     * "preview" would show the PARENT theme, not what actually ships.
     *
     * @param string|null $configured The raw `ARENA_PREVIEW_STYLESHEET` constant value, or null if undefined.
     */
    public static function resolveStylesheet(?string $configured): string {
        return ($configured !== null && $configured !== '') ? $configured : self::THEME;
    }
}
