<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Opens the 2-column shell shared by single/archive/search (and any future
 * template that needs the same shape): `<main id="content">` down to the
 * open `<div class="…content-column…">`. Always paired with
 * template-parts/layout/content-close.php, which closes the content column,
 * renders the sidebar column (when the layout has one) and closes
 * `.container`/`#content`.
 *
 * Column widths/classes come from Arena\Layout::columnClasses() (pure,
 * unit-tested in tests/LayoutTest.php) — this partial only wires them into
 * markup, reproducing the reference's measured structure:
 *   <main id="content" class="content-container">
 *     <div class="container layout-2-col layout-2-col-1 layout-right-sidebar layout-bc-before">
 *       <div class="content-column col-8">
 *
 * `layout-bc-before` signals that breadcrumbs render BEFORE the rest of the
 * content column's own markup — the consuming template (e.g. single.php,
 * Fatia 2B Task 2) is responsible for calling yoast_breadcrumb() right
 * after this partial, not this partial itself, since not every consumer of
 * the shell wants breadcrumbs.
 *
 * get_template_part('template-parts/layout/content-open', null, ['layout' => '…'])
 * — pass the SAME 'layout' value to the matching content-close.php call;
 * get_template_part() gives each partial its own isolated variable scope,
 * so the two don't share state automatically.
 *
 * @var array<string, mixed> $args {
 *     @type string $layout Layout key understood by Arena\Layout::columnClasses(). Default '2col-right'.
 * }
 */

$args = is_array($args ?? null) ? $args : [];
$layout = is_string($args['layout'] ?? null) && $args['layout'] !== '' ? $args['layout'] : '2col-right';
$columns = \Arena\Layout::columnClasses($layout);
$hasSidebar = $columns['sidebar'] !== '';

$containerClasses = ['container'];
if ($hasSidebar) {
    $containerClasses[] = 'layout-2-col';
    $containerClasses[] = 'layout-2-col-1';
    $containerClasses[] = 'layout-right-sidebar';
} else {
    $containerClasses[] = 'layout-1-col';
    $containerClasses[] = 'layout-no-sidebar';
}
$containerClasses[] = 'layout-bc-before';
?>
<main id="content" class="content-container">
    <div class="<?php echo esc_attr(implode(' ', $containerClasses)); ?>">
        <div class="<?php echo esc_attr($columns['content']); ?>">
