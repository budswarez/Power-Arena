<?php
declare(strict_types=1);

namespace Arena\Listing;

/**
 * Shared, pure helpers for parsing human-typed `[bs-*]` shortcode
 * attributes — every attribute WPBakery's `VcMap` exposes as a plain
 * `textfield` arrives here as an arbitrary string (an editor can type
 * anything, including negative numbers, decimals or comma lists), so both
 * `Arena\Listing\Query` (attrs -> WP_Query args) and `Arena\Listing\Renderer`
 * (attrs -> render options) need the exact same bounded-parsing rules.
 *
 * This used to be two private `intOrDefault()` copies, one per class, that
 * had already drifted: Query's had no positivity guard at all, so
 * `count="-1"` fed `posts_per_page => -1` straight into WP_Query — which
 * WP_Query treats as "no limit" and returns every post (~846 on this site)
 * in a single request. Renderer's copy *did* guard `> 0`. One shared,
 * fully-tested implementation so the two can't diverge again.
 */
final class Attrs {
    /**
     * Parses a human-typed integer attribute and CLAMPS it to [$min, $max]
     * (when $max is given) rather than rejecting out-of-range values
     * outright — a typo like `count="-1"` or an unreasonably large
     * `count="999999"` should still render *something* bounded, not fall
     * through to an unbounded query. Only a non-numeric/empty/missing
     * value falls back to $default.
     */
    public static function intOrDefault(mixed $value, int $default, int $min = 1, ?int $max = null): int {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }

        $int = (int) $value;

        if ($int < $min) {
            $int = $min;
        }
        if ($max !== null && $int > $max) {
            $int = $max;
        }

        return $int;
    }

    /**
     * Parses a comma-separated list of IDs (Publisher supports
     * `category="14236,17458"` alongside a single `category="14236"`) into
     * a list of positive ints. Blank/non-numeric/non-positive tokens
     * (including ones produced by a trailing comma, e.g. `"1,2,"`) are
     * silently dropped rather than surfacing as `0` — matching how a
     * single bad ID has always been treated (omitted, not passed through).
     *
     * @return int[]
     */
    public static function parseIdList(string $raw): array {
        if (trim($raw) === '') {
            return [];
        }

        $ids = array_map(
            static fn (string $id): int => (int) trim($id),
            explode(',', $raw)
        );

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}
