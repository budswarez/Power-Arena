<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Opens the 2-column ROW inside the shell (`.container` + `.content-
 * column`) — split out of template-parts/layout/content-open.php
 * (task-uifix BUG 5/6) so a caller can render full-width content (the
 * Yoast breadcrumb, an archive's featured mosaic) directly inside `<main>`
 * BEFORE this row opens, instead of that content being forced inside the
 * 8/12 content column this partial opens.
 *
 * Always called AFTER template-parts/layout/content-open.php and BEFORE
 * template-parts/layout/content-close.php, with the SAME `$args['layout']`
 * value passed to content-close.php — get_template_part() gives each
 * partial its own isolated variable scope, so all three must be told the
 * same layout key independently.
 *
 * Column widths/classes come from Arena\Layout::columnClasses(); container
 * classes come from Arena\Layout::containerClasses() (both pure, unit-
 * tested in tests/LayoutTest.php) — this partial only wires them into
 * markup, reproducing the reference's measured structure:
 *   <div class="container layout-2-col layout-2-col-1 layout-right-sidebar layout-bc-before">
 *     <div class="content-column col-8">
 *
 * @var array<string, mixed> $args {
 *     @type string $layout Layout key understood by Arena\Layout::columnClasses(). Default '2col-right'.
 * }
 */

$args = is_array($args ?? null) ? $args : [];
$layout = is_string($args['layout'] ?? null) && $args['layout'] !== '' ? $args['layout'] : '2col-right';
$columns = \Arena\Layout::columnClasses($layout);
$containerClasses = \Arena\Layout::containerClasses($layout);
?>
    <div class="<?php echo esc_attr(implode(' ', $containerClasses)); ?>">
        <div class="<?php echo esc_attr($columns['content']); ?>">
