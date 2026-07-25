<?php
declare(strict_types=1);

use Arena\Listing\Query;

class QueryTest extends WP_UnitTestCase {
    public function test_maps_category_count_order_orderby_date(): void {
        $args = Query::args([
            'category' => '14236',
            'count'    => '4',
            'order'    => 'DESC',
            'order_by' => 'date',
        ]);

        $this->assertSame(14236, $args['cat']);
        $this->assertSame(4, $args['posts_per_page']);
        $this->assertSame('DESC', $args['order']);
        $this->assertSame('date', $args['orderby']);
        $this->assertTrue($args['ignore_sticky_posts']);
        $this->assertSame('publish', $args['post_status']);
    }

    public function test_order_by_rand_maps_to_orderby_rand(): void {
        $args = Query::args(['order_by' => 'rand']);
        $this->assertSame('rand', $args['orderby']);
    }

    public function test_time_filter_month_with_fixed_now_sets_date_query(): void {
        $now = gmmktime(0, 0, 0, 3, 15, 2026);
        $args = Query::args(['time_filter' => 'month'], $now);

        $this->assertArrayHasKey('date_query', $args);
        $dateQuery = $args['date_query'][0];
        $this->assertSame(2026, $dateQuery['year']);
        $this->assertSame(3, $dateQuery['month']);
    }

    public function test_time_filter_month_without_now_omits_date_query(): void {
        $args = Query::args(['time_filter' => 'month']);
        $this->assertArrayNotHasKey('date_query', $args);
    }

    public function test_post_ids_maps_to_post_in(): void {
        $args = Query::args(['post_ids' => '1,2,3']);
        $this->assertSame([1, 2, 3], $args['post__in']);
    }

    /**
     * Minor finding #9 (whole-branch review): `post_ids` is a curated,
     * editor-ordered pick — without `orderby: 'post__in'`, WP_Query
     * silently re-sorts it by the shortcode's normal `order_by` (date, or
     * even `rand`), undoing the curation.
     */
    public function test_post_ids_sets_orderby_post_in_overriding_order_by(): void {
        $args = Query::args(['post_ids' => '3,1,2', 'order_by' => 'rand']);
        $this->assertSame([3, 1, 2], $args['post__in']);
        $this->assertSame('post__in', $args['orderby']);
    }

    public function test_without_post_ids_orderby_is_unaffected(): void {
        $args = Query::args(['order_by' => 'date']);
        $this->assertSame('date', $args['orderby']);
    }

    public function test_empty_category_omits_cat_key(): void {
        $args = Query::args(['category' => '']);
        $this->assertArrayNotHasKey('cat', $args);
    }

    public function test_tag_maps_to_tag_id(): void {
        $args = Query::args(['tag' => '77']);
        $this->assertSame(77, $args['tag_id']);
    }

    public function test_offset_maps_to_offset(): void {
        $args = Query::args(['offset' => '10']);
        $this->assertSame(10, $args['offset']);
    }

    public function test_empty_offset_omits_offset_key(): void {
        $args = Query::args(['offset' => '']);
        $this->assertArrayNotHasKey('offset', $args);
    }

    public function test_empty_tag_omits_tag_id_key(): void {
        $args = Query::args(['tag' => '']);
        $this->assertArrayNotHasKey('tag_id', $args);
    }

    public function test_defaults_when_atts_minimal(): void {
        $args = Query::args([]);

        $this->assertArrayNotHasKey('cat', $args);
        $this->assertArrayNotHasKey('tag_id', $args);
        $this->assertArrayNotHasKey('offset', $args);
        $this->assertArrayNotHasKey('post__in', $args);
        $this->assertArrayNotHasKey('date_query', $args);
        $this->assertSame(5, $args['posts_per_page']);
        $this->assertSame('DESC', $args['order']);
        $this->assertSame('date', $args['orderby']);
        $this->assertTrue($args['ignore_sticky_posts']);
        $this->assertSame('publish', $args['post_status']);
    }

    public function test_ignore_sticky_posts_can_be_disabled(): void {
        $args = Query::args(['ignore_sticky_posts' => '0']);
        $this->assertFalse($args['ignore_sticky_posts']);
    }

    public function test_invalid_count_falls_back_to_default(): void {
        $args = Query::args(['count' => 'not-a-number']);
        $this->assertSame(5, $args['posts_per_page']);
    }

    public function test_ignore_sticky_posts_false_string_is_false(): void {
        $args = Query::args(['ignore_sticky_posts' => 'false']);
        $this->assertFalse($args['ignore_sticky_posts']);
    }

    public function test_ignore_sticky_posts_no_string_is_false(): void {
        $args = Query::args(['ignore_sticky_posts' => 'no']);
        $this->assertFalse($args['ignore_sticky_posts']);
    }

    public function test_ignore_sticky_posts_off_string_is_false(): void {
        $args = Query::args(['ignore_sticky_posts' => 'off']);
        $this->assertFalse($args['ignore_sticky_posts']);
    }

    public function test_ignore_sticky_posts_one_string_is_true(): void {
        $args = Query::args(['ignore_sticky_posts' => '1']);
        $this->assertTrue($args['ignore_sticky_posts']);
    }

    public function test_ignore_sticky_posts_default_is_true(): void {
        $args = Query::args([]);
        $this->assertTrue($args['ignore_sticky_posts']);
    }

    public function test_empty_post_ids_omits_post_in_key(): void {
        $args = Query::args(['post_ids' => '']);
        $this->assertArrayNotHasKey('post__in', $args);
    }

    public function test_post_ids_with_trailing_comma_filters_non_positive(): void {
        $args = Query::args(['post_ids' => '1,2,']);
        $this->assertSame([1, 2], $args['post__in']);
    }

    public function test_post_ids_all_non_positive_omits_post_in_key(): void {
        $args = Query::args(['post_ids' => '0,abc,']);
        $this->assertArrayNotHasKey('post__in', $args);
    }

    public function test_category_non_numeric_omits_cat_key(): void {
        $args = Query::args(['category' => 'abc']);
        $this->assertArrayNotHasKey('cat', $args);
    }

    public function test_category_zero_omits_cat_key(): void {
        $args = Query::args(['category' => '0']);
        $this->assertArrayNotHasKey('cat', $args);
    }

    public function test_tag_zero_omits_tag_id_key(): void {
        $args = Query::args(['tag' => '0']);
        $this->assertArrayNotHasKey('tag_id', $args);
    }

    /**
     * FIX B.1: a free VcMap textfield lets an editor type `count="-1"`,
     * which used to feed `posts_per_page => -1` straight into WP_Query —
     * WP_Query's own "no limit" sentinel, returning every published post
     * (~846 on this site) in one request. Must clamp to the minimum (1),
     * never pass a negative value through.
     */
    public function test_negative_count_clamps_to_minimum_one(): void {
        $args = Query::args(['count' => '-1']);
        $this->assertSame(1, $args['posts_per_page']);
    }

    public function test_zero_count_clamps_to_minimum_one(): void {
        $args = Query::args(['count' => '0']);
        $this->assertSame(1, $args['posts_per_page']);
    }

    /** FIX B.1: a hard ceiling so an absurd typo can't render the whole table either. */
    public function test_huge_count_clamps_to_maximum(): void {
        $args = Query::args(['count' => '999999']);
        $this->assertSame(50, $args['posts_per_page']);
    }

    /**
     * FIX B.2: Publisher supports comma-separated term IDs
     * (`category="14236,17458"`). `(int) $category` used to silently
     * collapse this to just the first ID; now it must produce
     * `category__in` with every ID, and NOT set the single-ID `cat` key.
     */
    public function test_comma_separated_category_maps_to_category_in(): void {
        $args = Query::args(['category' => '14236,17458']);
        $this->assertSame([14236, 17458], $args['category__in']);
        $this->assertArrayNotHasKey('cat', $args);
    }

    public function test_comma_separated_tag_maps_to_tag_in(): void {
        $args = Query::args(['tag' => '10,20']);
        $this->assertSame([10, 20], $args['tag__in']);
        $this->assertArrayNotHasKey('tag_id', $args);
    }

    public function test_comma_separated_category_with_trailing_comma_filters_blank_token(): void {
        $args = Query::args(['category' => '14236,17458,']);
        $this->assertSame([14236, 17458], $args['category__in']);
    }

    /**
     * FIX B.3: post dates are stored in the site's LOCAL timezone, but
     * `time_filter="month"`'s `date_query` used to be built with
     * `gmdate()` against the injected `$now` timestamp — treating it as if
     * it were already UTC-agnostic. This site runs America/Sao_Paulo
     * (UTC-3, no DST since 2019): pick a `$now` that is 01:00 UTC on the
     * 1st of a month, i.e. still 22:00 local time on the LAST day of the
     * PREVIOUS month. The old `gmdate()` code would resolve this to the
     * new month; the site-timezone-aware code must resolve it to the
     * previous one.
     */
    public function test_time_filter_month_uses_site_timezone_not_utc(): void {
        update_option('timezone_string', 'America/Sao_Paulo');

        // 2026-02-01 01:00:00 UTC == 2026-01-31 22:00:00 America/Sao_Paulo.
        $now = gmmktime(1, 0, 0, 2, 1, 2026);

        $args = Query::args(['time_filter' => 'month'], $now);

        $this->assertSame(2026, $args['date_query'][0]['year']);
        $this->assertSame(1, $args['date_query'][0]['month']);

        update_option('timezone_string', '');
    }
}
