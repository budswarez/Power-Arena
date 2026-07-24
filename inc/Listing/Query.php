<?php
declare(strict_types=1);

namespace Arena\Listing;

final class Query {
    /**
     * Mapeia atributos de shortcode `[bs-*]` para args de WP_Query.
     * Função PURA: nenhuma chamada ao WordPress; "agora" é injetado via
     * $now (timestamp Unix) para manter testabilidade — nunca usa time()
     * internamente. Se $now for null, o filtro de data (time_filter) é
     * omitido.
     *
     * @param array<string, mixed> $atts
     */
    public static function args(array $atts, ?int $now = null): array {
        $args = [
            'post_status'         => 'publish',
            'posts_per_page'      => self::intOrDefault($atts['count'] ?? null, 5),
            'order'               => self::normalizeOrder($atts['order'] ?? null),
            'orderby'             => self::normalizeOrderBy($atts['order_by'] ?? null),
            'ignore_sticky_posts' => self::normalizeBool($atts['ignore_sticky_posts'] ?? null, true),
        ];

        $category = isset($atts['category']) ? (string) $atts['category'] : '';
        if ($category !== '' && (int) $category > 0) {
            $args['cat'] = (int) $category;
        }

        $tag = isset($atts['tag']) ? (string) $atts['tag'] : '';
        if ($tag !== '' && (int) $tag > 0) {
            $args['tag_id'] = (int) $tag;
        }

        $offset = isset($atts['offset']) ? (string) $atts['offset'] : '';
        if ($offset !== '') {
            $args['offset'] = (int) $offset;
        }

        $postIds = isset($atts['post_ids']) ? (string) $atts['post_ids'] : '';
        if ($postIds !== '') {
            $ids = array_map(
                static fn (string $id): int => (int) trim($id),
                explode(',', $postIds)
            );
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
            if ($ids !== []) {
                $args['post__in'] = $ids;
            }
        }

        $timeFilter = isset($atts['time_filter']) ? (string) $atts['time_filter'] : '';
        if ($timeFilter === 'month' && $now !== null) {
            $args['date_query'] = [
                [
                    'year'  => (int) gmdate('Y', $now),
                    'month' => (int) gmdate('n', $now),
                ],
            ];
        }

        return $args;
    }

    private static function intOrDefault(mixed $value, int $default): int {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return (int) $value;
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
