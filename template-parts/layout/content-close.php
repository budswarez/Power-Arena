<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Closes the shell opened by template-parts/layout/content-open.php: ends
 * the content column, renders the sidebar column (via get_template_part()
 * on `sidebar.php`) when Arena\Layout::columnClasses() says the layout has
 * one, then closes `.container`/`#content`.
 *
 * Must be called with the SAME $args['layout'] passed to content-open.php —
 * get_template_part() gives each partial its own isolated variable scope,
 * so the two don't share state automatically; passing the same key is what
 * keeps them in agreement about which columns to render/close.
 *
 * @var array<string, mixed> $args {
 *     @type string $layout Same layout key passed to content-open.php. Default '2col-right'.
 * }
 */

$args = is_array($args ?? null) ? $args : [];
$layout = is_string($args['layout'] ?? null) && $args['layout'] !== '' ? $args['layout'] : '2col-right';
$columns = \Arena\Layout::columnClasses($layout);
?>
        </div>
        <?php if ($columns['sidebar'] !== ''): ?>
            <?php get_template_part('sidebar', null, ['classes' => $columns['sidebar']]); ?>
        <?php endif; ?>
    </div>
</main>
