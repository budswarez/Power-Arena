<?php
declare(strict_types=1);

namespace Arena;

/**
 * Tiny inline-SVG icon registry. Currently just the magnifier used by BOTH
 * the header's search-toggle button (template-parts/header/search.php) and
 * the submit button inside searchform.php (Fatia 2B Task 5) — extracted
 * here as soon as a 2nd inline copy would otherwise exist, per the Task 5
 * brief ("avoid a third copy"): one clean-room shape to maintain instead of
 * near-identical inline <svg> blocks that could drift apart. No icon font
 * is enqueued by this theme, so inline SVG (not an `<i class="fa …">`
 * glyph) is the only option that actually renders anything — see
 * `Arena\Listing\Renderer::commentIcon()` for the same reasoning applied to
 * the comment-bubble icon on listing cards.
 */
final class Icons {
    public static function search(): string {
        return '<svg class="icon-search" width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false">'
            . '<circle cx="8.5" cy="8.5" r="6"></circle>'
            . '<line x1="13.3" y1="13.3" x2="18" y2="18"></line>'
            . '</svg>';
    }
}
