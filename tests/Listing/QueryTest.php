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
}
