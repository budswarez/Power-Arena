<?php
declare(strict_types=1);

use Arena\Listing\Attrs;

class AttrsTest extends WP_UnitTestCase {
    public function test_int_or_default_falls_back_on_non_numeric(): void {
        $this->assertSame(5, Attrs::intOrDefault('not-a-number', 5));
    }

    public function test_int_or_default_falls_back_on_null(): void {
        $this->assertSame(5, Attrs::intOrDefault(null, 5));
    }

    public function test_int_or_default_falls_back_on_empty_string(): void {
        $this->assertSame(5, Attrs::intOrDefault('', 5));
    }

    public function test_int_or_default_passes_through_in_range_value(): void {
        $this->assertSame(4, Attrs::intOrDefault('4', 5, 1, 50));
    }

    public function test_int_or_default_clamps_negative_to_min(): void {
        $this->assertSame(1, Attrs::intOrDefault('-1', 5, 1, 50));
    }

    public function test_int_or_default_clamps_zero_to_min(): void {
        $this->assertSame(1, Attrs::intOrDefault('0', 5, 1, 50));
    }

    public function test_int_or_default_clamps_huge_value_to_max(): void {
        $this->assertSame(50, Attrs::intOrDefault('999999', 5, 1, 50));
    }

    public function test_int_or_default_with_no_max_leaves_large_value_untouched(): void {
        $this->assertSame(999999, Attrs::intOrDefault('999999', 5, 1));
    }

    public function test_parse_id_list_empty_string_returns_empty_array(): void {
        $this->assertSame([], Attrs::parseIdList(''));
    }

    public function test_parse_id_list_single_id(): void {
        $this->assertSame([14236], Attrs::parseIdList('14236'));
    }

    public function test_parse_id_list_comma_separated_ids(): void {
        $this->assertSame([14236, 17458], Attrs::parseIdList('14236,17458'));
    }

    public function test_parse_id_list_filters_non_positive_and_non_numeric_tokens(): void {
        $this->assertSame([1, 2], Attrs::parseIdList('1,2,'));
        $this->assertSame([], Attrs::parseIdList('0,abc,'));
    }
}
