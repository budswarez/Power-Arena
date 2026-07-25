<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Renders the sidebar column's own wrapper div + `dynamic_sidebar()` call
 * for the 'arena-primary' sidebar (registered in Arena\Setup::sidebars()).
 * Invoked from template-parts/layout/content-close.php via
 * get_template_part('sidebar', …) so it always gets the caller's own
 * column classes (Arena\Layout::columnClasses()['sidebar']) rather than
 * hardcoding them here — this file can still be reached through WP's
 * conventional get_sidebar() as a fallback (hence the default below), but
 * that path currently isn't used by any Arena template.
 *
 * Empty-sidebar decision: if 'arena-primary' has no widgets assigned, this
 * renders NOTHING — no empty `<div class="sidebar-column">`, no visible
 * box. That does not break the 2-column flex grid (see main.css): the
 * content column keeps its own fixed flex-basis regardless of whether a
 * sibling sidebar column is present, so omitting the sidebar just leaves
 * the remaining ~33% as blank space to its right rather than corrupting
 * the row — an acceptable, self-healing state for a site where no widget
 * has been added to the sidebar yet (rather than shipping an empty box with
 * padding/border for no reason).
 *
 * @var array<string, mixed> $args {
 *     @type string $classes Column classes from Arena\Layout::columnClasses()['sidebar'].
 * }
 */

if (!is_active_sidebar('arena-primary')) {
    return;
}

$args = is_array($args ?? null) ? $args : [];
$classes = is_string($args['classes'] ?? null) && $args['classes'] !== ''
    ? $args['classes']
    : 'sidebar-column sidebar-column-primary col-4';
?>
<div class="<?php echo esc_attr($classes); ?>">
    <div class="sidebar-inner-wrap">
        <?php dynamic_sidebar('arena-primary'); ?>
    </div>
</div>
