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

    /** Minimum WCAG contrast ratio (small text/AA) accessibleTextColor() derives towards. */
    public const MIN_ACCESSIBLE_CONTRAST = 4.5;

    /**
     * Slightly ABOVE the bare 4.5:1 AA minimum — cssTokens() (the value
     * that actually ships in the page) derives towards this, not the bare
     * minimum. accessibleTextColor()'s HSL-lightness loop stops at the
     * FIRST step that clears the bar, which can land razor-thin (e.g.
     * 4.51:1 for the theme's own default accent) — fine mathematically,
     * but too close to the line to trust across a real browser's own
     * rounding/gamma handling during an automated contrast audit
     * (Lighthouse). This margin costs nothing (still visually "the accent,
     * darkened") and removes that flakiness risk.
     */
    public const SAFE_ACCESSIBLE_CONTRAST = 4.6;

    /**
     * Whitelist of `arena_base_font` values -> the two CSS font stacks the
     * theme actually ships. Deliberately NOT a free-text/arbitrary-family
     * picker (task-review-fixes-3, FIX 1): self-hosting arbitrary Google
     * Fonts families on demand is out of scope, so the option is narrowed
     * to the theme's own Barlow/Oswald pairing (assets/fonts/, see
     * Assets.php) vs. a zero-network system-font stack.
     */
    public const FONT_STACKS = [
        'barlow-oswald' => [
            'body' => "'Barlow', sans-serif",
            'head' => "'Oswald', sans-serif",
        ],
        'system' => [
            'body' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
            'head' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
        ],
    ];

    public const DEFAULT_BASE_FONT = 'barlow-oswald';

    /** Whitelist of `arena_sidebar_position` values Layout::columnClasses() understands via the mapping below. */
    public const SIDEBAR_POSITIONS = ['right', 'left', 'none'];

    public const DEFAULT_SIDEBAR_POSITION = 'right';

    /** Lê uma opção do ACF; degrada para $default se ACF ausente ou vazio. */
    public static function get(string $key, mixed $default = null): mixed {
        if (!function_exists('get_field')) { return $default; }
        $value = get_field($key, 'option');
        return ($value === null || $value === '' || $value === false) ? $default : $value;
    }

    /**
     * task-native-settings: every public getter below now resolves through
     * an explicit 3-step precedence chain, checked in this order:
     *
     *   1. `get_theme_mod($key)` — set via the native Customizer
     *      (Arena\Customizer registers these same option names as
     *      `theme_mod` settings). This is the PRIMARY path: it works with
     *      zero plugins, which is the whole point of this change (ACF is
     *      not installed on production, so the ACF options page previously
     *      left the theme with no configuration UI at all).
     *   2. `self::get($key, ...)` — the ACF options page (`arena-options`),
     *      kept as an optional enhancement for sites that DO have ACF.
     *      Only consulted when step 1 found nothing usable.
     *   3. The theme's own hard default (`DEFAULT_ACCENT`,
     *      `DEFAULT_SIDEBAR_POSITION`, `DEFAULT_BASE_FONT`, or `null` for
     *      the logo) — used when neither of the above set anything.
     *
     * A theme_mod value that fails validation (never happens through the
     * Customizer's own sanitize callbacks, but defensively checked here too
     * — e.g. a stale/hand-edited option) is treated as "not set", falling
     * through to the ACF step exactly like an empty theme_mod would.
     *
     * `logoId()` returns null on a total default: contrary to the string
     * options, the theme's own baseline for the logo is the wordless
     * site-name/tagline fallback rendered by template-parts/header/branding.php,
     * not a baked-in attachment ID.
     */
    public static function accentColor(): string {
        $mod = get_theme_mod('arena_accent_color');
        if (is_string($mod) && self::isValidHexColor($mod)) {
            return self::normalizeHexColor($mod) ?? self::DEFAULT_ACCENT;
        }

        $c = self::get('arena_accent_color', self::DEFAULT_ACCENT);
        if (!is_string($c)) { return self::DEFAULT_ACCENT; }
        return self::normalizeHexColor($c) ?? self::DEFAULT_ACCENT;
    }

    /**
     * Sidebar position: theme_mod (`arena_sidebar_position`, Customizer) →
     * ACF option (`arena_sidebar_position`, if ACF active) → the shell's own
     * default ('right'). Sanitised against SIDEBAR_POSITIONS at every step —
     * anything else (ACF absent, a stale/unknown value) degrades to the
     * next step in the chain.
     */
    public static function sidebarPosition(): string {
        $mod = get_theme_mod('arena_sidebar_position');
        if (is_string($mod) && in_array($mod, self::SIDEBAR_POSITIONS, true)) {
            return $mod;
        }

        $v = self::get('arena_sidebar_position', self::DEFAULT_SIDEBAR_POSITION);
        return is_string($v) && in_array($v, self::SIDEBAR_POSITIONS, true)
            ? $v
            : self::DEFAULT_SIDEBAR_POSITION;
    }

    /**
     * Pure mapping (task-review-fixes-3, FIX 1): ACF's `arena_sidebar_position`
     * value -> the layout key Arena\Layout::columnClasses() understands.
     * Unknown input falls back to the shell's own default shape
     * ('2col-right'), matching Layout::columnClasses()'s own fallback.
     */
    public static function mapSidebarPositionToLayout(string $position): string {
        return match ($position) {
            'left' => '2col-left',
            'none' => '1col',
            default => '2col-right',
        };
    }

    /** Layout key for the current saved sidebar position — what every template now passes to the shell. */
    public static function sidebarLayout(): string {
        return self::mapSidebarPositionToLayout(self::sidebarPosition());
    }

    /**
     * Base font: theme_mod (`arena_base_font`, Customizer) → ACF option (if
     * active) → the theme's own default pairing. Sanitised against
     * FONT_STACKS at every step — anything else degrades to the next step.
     */
    public static function baseFont(): string {
        $mod = get_theme_mod('arena_base_font');
        if (is_string($mod) && isset(self::FONT_STACKS[$mod])) {
            return $mod;
        }

        $v = self::get('arena_base_font', self::DEFAULT_BASE_FONT);
        return is_string($v) && isset(self::FONT_STACKS[$v]) ? $v : self::DEFAULT_BASE_FONT;
    }

    /** @return array{body: string, head: string} */
    public static function fontStack(string $key): array {
        return self::FONT_STACKS[$key] ?? self::FONT_STACKS[self::DEFAULT_BASE_FONT];
    }

    /**
     * Resolved CSS custom-property tokens for the inline `:root{…}` block
     * Arena\Assets::printInlineTokens() prints in `wp_head` (task-review-
     * fixes-3, FIX 1) — the single place that turns the 3 previously-inert
     * ACF fields (accent, base font) into something a template actually
     * reads. `--arena-accent-text` is ALWAYS derived via
     * accessibleTextColor(), never the raw saved accent: an owner picking a
     * pale/light custom accent must not silently drop the theme below WCAG
     * AA on the white-on-accent/accent-on-white surfaces that rely on this
     * token (badges, author links — see main.css's own comments).
     *
     * @return array<string, string>
     */
    public static function cssTokens(): array {
        $accent = self::accentColor();
        $font = self::fontStack(self::baseFont());

        return [
            '--arena-accent'      => $accent,
            '--arena-accent-text' => self::accessibleTextColor($accent, self::SAFE_ACCESSIBLE_CONTRAST),
            '--arena-font-body'   => $font['body'],
            '--arena-font-head'   => $font['head'],
        ];
    }

    /** True for a well-formed `#rgb` or `#rrggbb` hex colour string. */
    public static function isValidHexColor(string $hex): bool {
        return (bool) preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex);
    }

    /** Normalizes a valid hex colour to lowercase `#rrggbb`; null if malformed. */
    public static function normalizeHexColor(string $hex): ?string {
        if (!self::isValidHexColor($hex)) { return null; }
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        return '#' . strtolower($h);
    }

    /**
     * Pure (task-review-fixes-3, FIX 1): WCAG contrast ratio of a hex colour
     * against opaque white, e.g. `contrastWithWhite('#000000') === 21.0`.
     * Since white's own relative luminance is always 1.0, the general
     * "(L1+.05)/(L2+.05), L1>=L2" formula collapses to `1.05/(L+.05)`.
     * Malformed input degrades to the theme's own default accent rather
     * than throwing, so a bad saved option can never fatal the page.
     */
    public static function contrastWithWhite(string $hex): float {
        $normalized = self::normalizeHexColor($hex) ?? self::DEFAULT_ACCENT;
        [$r, $g, $b] = self::hexToRgb($normalized);
        return 1.05 / (self::relativeLuminance($r, $g, $b) + 0.05);
    }

    /**
     * Pure (task-review-fixes-3, FIX 1): the accessible text/surface variant
     * of an arbitrary hex colour — used to derive `--arena-accent-text` from
     * whatever accent the site owner saves, so the theme's WCAG AA
     * guarantee on white-on-accent/accent-on-white surfaces never depends
     * on a hand-picked pairing (as the current baked-in
     * #f42c1a/#c81f10 pair does). Colours that already clear $minRatio
     * against white are returned unchanged (normalized) — never darkened
     * further. Failing colours are darkened in HSL lightness steps until
     * the ratio clears the bar; lightness floors at 0 (black), which always
     * clears any realistic bar (contrast 21:1), so the loop is guaranteed
     * to terminate with a passing colour.
     */
    public static function accessibleTextColor(string $hex, float $minRatio = self::MIN_ACCESSIBLE_CONTRAST): string {
        $normalized = self::normalizeHexColor($hex) ?? self::DEFAULT_ACCENT;

        if (self::contrastWithWhite($normalized) >= $minRatio) {
            return $normalized;
        }

        [$r, $g, $b] = self::hexToRgb($normalized);
        [$h, $s, $l] = self::rgbToHsl($r, $g, $b);

        $step = 0.01;
        while ($l > 0.0) {
            $l = max(0.0, $l - $step);
            [$r, $g, $b] = self::hslToRgb($h, $s, $l);
            if (1.05 / (self::relativeLuminance($r, $g, $b) + 0.05) >= $minRatio) {
                return self::rgbToHex($r, $g, $b);
            }
        }

        return '#000000';
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hexToRgb(string $hex): array {
        $h = ltrim($hex, '#');
        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    private static function rgbToHex(int $r, int $g, int $b): string {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /** WCAG relative luminance (sRGB gamma-corrected), 0.0 (black) .. 1.0 (white). */
    private static function relativeLuminance(int $r, int $g, int $b): float {
        $channel = static function (int $c): float {
            $c = $c / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }

    /** @return array{0: float, 1: float, 2: float} h in [0,360), s/l in [0,1] */
    private static function rgbToHsl(int $r, int $g, int $b): array {
        $r /= 255;
        $g /= 255;
        $b /= 255;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => (($g - $b) / $d) + ($g < $b ? 6 : 0),
            $g => (($b - $r) / $d) + 2,
            default => (($r - $g) / $d) + 4,
        };
        $h *= 60;

        return [$h, $s, $l];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hslToRgb(float $h, float $s, float $l): array {
        if ($s === 0.0) {
            $v = (int) round($l * 255);
            return [$v, $v, $v];
        }

        $hue2rgb = static function (float $p, float $q, float $t): float {
            if ($t < 0) { $t += 1; }
            if ($t > 1) { $t -= 1; }
            if ($t < 1 / 6) { return $p + ($q - $p) * 6 * $t; }
            if ($t < 1 / 2) { return $q; }
            if ($t < 2 / 3) { return $p + ($q - $p) * (2 / 3 - $t) * 6; }
            return $p;
        };

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $hk = $h / 360;

        $r = $hue2rgb($p, $q, $hk + 1 / 3);
        $g = $hue2rgb($p, $q, $hk);
        $b = $hue2rgb($p, $q, $hk - 1 / 3);

        return [(int) round($r * 255), (int) round($g * 255), (int) round($b * 255)];
    }

    /**
     * Logo attachment ID: WordPress' own native `custom_logo` theme_mod
     * (set at Aparência → Personalizar → Identidade do site,
     * `add_theme_support('custom-logo', …)` in Arena\Setup) → the ACF
     * `arena_logo` option (if ACF active and set) → null (the theme's own
     * default is the wordless site-name/tagline fallback rendered by
     * template-parts/header/branding.php, not an attachment ID).
     *
     * template-parts/header/branding.php calls `has_custom_logo()`/
     * `get_custom_logo()` directly for the FIRST step (those core functions
     * already handle the `<a>` wrapper, `width`/`height`, and retina
     * `srcset` correctly) — this method exists for call sites that just
     * need the resolved ID with the full precedence chain applied (and is
     * itself what branding.php's ACF-fallback branch calls).
     */
    public static function logoId(): ?int {
        $mod = self::parseLogoId(get_theme_mod('custom_logo'));
        if ($mod !== null) {
            return $mod;
        }

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
