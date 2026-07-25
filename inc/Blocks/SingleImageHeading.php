<?php
declare(strict_types=1);

namespace Arena\Blocks;

/**
 * Reproduces the reference site's title treatment for WPBakery's
 * `[vc_single_image]` block (e.g. "EVENTO PICHAU ARENA 2026" above the
 * homepage banner): a light-grey full-width bar with slanted (chevron)
 * ends, holding a smaller dark chevron with white uppercase text centered
 * inside it — measured against the reference, where the plain
 * `.wpb_singleimage_heading` `<h2>` renders as an unstyled bold heading by
 * default (neither Arena's nor js_composer's own CSS style it at all).
 *
 * `wpb_widget_title` is js_composer's OWN documented extension point for
 * overriding a content element's title markup (see the doc-comment on
 * `wpb_widget_title()` in
 * `js_composer/include/helpers/helpers.php`) — it fires for EVERY titled
 * element (accordions, tabs, single images, …), not just `vc_single_image`,
 * so this only rewrites output tagged with the `wpb_singleimage_heading`
 * extra class and passes every other element's title through untouched.
 *
 * Splits the `<h2>` into two child `<span>`s — `.sih-bar` (the grey
 * full-width bar, absolutely positioned to fill the `<h2>`'s own box) and
 * `.sih-text` (the dark chevron + text, sized to the text itself via
 * `display:inline-block`) — because the default markup
 * (`wpb_widget_title()`'s own `<h2 class="…">{title}</h2>`, no inner
 * wrapper) gives CSS nothing to shrink-wrap a background around just the
 * text while a 2nd, independently-sized background spans the full width
 * behind it. Same wrapper/inner-span split already used for the homepage
 * section headings (`Arena\Listing\Renderer::renderHeading()` +
 * `.section-heading`/`.h-text` in main.css) — see that method's own
 * doc-comment for the underlying "2 layers need 2 elements" reasoning.
 */
final class SingleImageHeading {
    private const EXTRA_CLASS = 'wpb_singleimage_heading';

    public static function register(): void {
        add_filter('wpb_widget_title', [self::class, 'filterTitle'], 10, 2);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function filterTitle(string $output, array $params): string {
        $title = isset($params['title']) ? trim((string) $params['title']) : '';
        $extraClass = isset($params['extraclass']) ? trim((string) $params['extraclass']) : '';

        if ($title === '' || $extraClass !== self::EXTRA_CLASS) {
            return $output;
        }

        return '<h2 class="wpb_heading ' . esc_attr(self::EXTRA_CLASS) . '">'
            . '<span class="sih-bar" aria-hidden="true"></span>'
            . '<span class="sih-text">' . esc_html($title) . '</span>'
            . '</h2>';
    }
}
