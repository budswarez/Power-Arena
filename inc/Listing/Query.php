<?php
declare(strict_types=1);

namespace Arena\Listing;

final class Query {
    /**
     * `count` (`posts_per_page`) is clamped to this range — see
     * `Attrs::intOrDefault()`. A free `textfield` in WPBakery's `VcMap`
     * means editors can type anything, including `-1`, which WP_Query
     * treats as "no limit" (every published post — ~846 on this site — in
     * one request). 50 is a generous ceiling for any single `[bs-*]` block
     * on a page (the widest layout here renders in a grid of a handful of
     * columns), well above real editorial use, while still ruling out an
     * accidental full-table render.
     */
    private const COUNT_MIN = 1;
    private const COUNT_MAX = 50;
    private const COUNT_DEFAULT = 5;

    /**
     * Mapeia atributos de shortcode `[bs-*]` para args de WP_Query.
     * "Agora" é injetado via $now (timestamp Unix, UTC) para manter
     * testabilidade — nunca usa time() internamente. Se $now for null, o
     * filtro de data (time_filter) é omitido. `$now` is interpreted in the
     * SITE's own timezone (via `wp_date()`), not UTC — this site runs
     * UTC-3, and formatting the injected timestamp with `gmdate()` used to
     * roll `time_filter="month"` into the wrong month for roughly 3 hours
     * around every month boundary (21:00–24:00 local on the last day: UTC
     * has already ticked into the 1st of the next month). This makes
     * `args()` call `wp_date()` (a WordPress function, unlike the rest of
     * this method) — the trade-off is deliberate: correctness of "what
     * month is it, locally" requires knowing the site's configured
     * timezone, which only WordPress's own option storage has.
     *
     * @param array<string, mixed> $atts
     */
    public static function args(array $atts, ?int $now = null): array {
        $args = [
            'post_status'         => 'publish',
            /*
             * `count` do bloco vence; sem ele, o padrão global do painel
             * (Arena → Blocos e listagens → "Itens por bloco"); sem painel,
             * COUNT_DEFAULT. A faixa COUNT_MIN..COUNT_MAX continua valendo
             * para qualquer origem, então nem o painel pode pedir 500 posts.
             */
            'posts_per_page'      => Attrs::intOrDefault(
                $atts['count'] ?? null,
                \Arena\Settings::itemsPerBlock() ?? self::COUNT_DEFAULT,
                self::COUNT_MIN,
                self::COUNT_MAX
            ),
            'order'               => self::normalizeOrder($atts['order'] ?? null),
            'orderby'             => self::normalizeOrderBy($atts['order_by'] ?? null),
            'ignore_sticky_posts' => self::normalizeBool($atts['ignore_sticky_posts'] ?? null, true),
        ];

        $categoryIds = Attrs::parseIdList(isset($atts['category']) ? (string) $atts['category'] : '');
        if (count($categoryIds) === 1) {
            $args['cat'] = $categoryIds[0];
        } elseif (count($categoryIds) > 1) {
            $args['category__in'] = $categoryIds;
        }

        $tagIds = Attrs::parseIdList(isset($atts['tag']) ? (string) $atts['tag'] : '');
        if (count($tagIds) === 1) {
            $args['tag_id'] = $tagIds[0];
        } elseif (count($tagIds) > 1) {
            $args['tag__in'] = $tagIds;
        }

        $offset = isset($atts['offset']) ? (string) $atts['offset'] : '';
        if ($offset !== '') {
            $args['offset'] = (int) $offset;
        }

        $postIds = Attrs::parseIdList(isset($atts['post_ids']) ? (string) $atts['post_ids'] : '');
        if ($postIds !== []) {
            $args['post__in'] = $postIds;
            // Whole-branch review, minor finding #9: `post_ids` is a
            // curated, editor-ordered pick — without `orderby: 'post__in'`,
            // WP_Query re-sorts the result by `$args['orderby']` (date, or
            // even `rand`) instead of respecting that order, silently
            // undoing the curation. `post__in` always wins here regardless
            // of `order_by`/`order`, since an explicit curated list is a
            // stronger signal of intended order than the shortcode's
            // generic date/rand default.
            $args['orderby'] = 'post__in';
        }

        $timeFilter = isset($atts['time_filter']) ? (string) $atts['time_filter'] : '';
        if ($timeFilter === 'month' && $now !== null) {
            $args['date_query'] = [
                [
                    'year'  => (int) wp_date('Y', $now),
                    'month' => (int) wp_date('n', $now),
                ],
            ];
        }

        return $args;
    }

    private static function normalizeOrder(mixed $value): string {
        $value = is_string($value) ? strtoupper($value) : '';
        return $value === 'ASC' ? 'ASC' : 'DESC';
    }

    private static function normalizeOrderBy(mixed $value): string {
        return $value === 'rand' ? 'rand' : 'date';
    }

    private static function normalizeBool(mixed $value, bool $default): bool {
        if ($value === null) {
            return $default;
        }
        return !in_array(strtolower((string) $value), ['0', 'false', 'no', 'off', ''], true);
    }
}
