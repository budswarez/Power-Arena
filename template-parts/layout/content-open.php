<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Opens the shared page-level landmark: just `<main id="content"
 * class="content-container">` — the box `.main-content.boxed`-style rules
 * key off (max-width 1200px, 15px side padding, white surface). Always
 * paired with template-parts/layout/content-close.php, which closes it.
 *
 * task-uifix (BUG 5/6): this used to ALSO open the 2-column row
 * (`.container` + `.content-column`) in the same partial, which forced
 * every full-width thing a caller wanted to render before the row (the
 * Yoast breadcrumb, and — on archives — the featured mosaic) to actually
 * render INSIDE the 8/12 content column instead, squeezed to that column's
 * own narrower width with an empty sidebar column beside it. Opening the
 * row is now a SEPARATE step
 * (template-parts/layout/content-row-open.php) so a caller can render
 * full-width content directly inside `<main>`, BEFORE opening the row —
 * single.php and archive.php do exactly that for the breadcrumb (and, on
 * archive.php, the featured mosaic); every other consumer
 * (page.php/search.php/attachment.php/404.php/index.php) still opens the
 * row immediately afterward, reproducing their previous, unchanged shape.
 *
 * get_template_part('template-parts/layout/content-open') takes no args —
 * the layout key only matters once the row itself opens
 * (template-parts/layout/content-row-open.php), which is where
 * Arena\Layout::columnClasses()/containerClasses() actually get consulted.
 */
?>
<main id="content" class="content-container">
