<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Layout `modern-grid-1`: the ARCHIVE (category/tag) featured mosaic —
 * measured against the reference's `listing-modern-grid-1`
 * (ref-category.html, search `listing-modern-grid-1`): an IRREGULAR
 * 2-column split, NOT the home's uniform 2-large + 3-compact `modern-grid`
 * (template-parts/listing/modern-grid.php, untouched — the home still
 * depends on it).
 *
 * Structure (measured `.mg-col-1`/`.mg-col-2`/`.item-3-cont`/
 * `.item-4-cont` widths, see assets/src/css/main.css for the exact CSS):
 *   - col-1 (56%): ONE large tile (post 1) — the archive's LCP image.
 *   - col-2 (44%): ONE medium tile on top (post 2), then a bottom row
 *     with two small tiles side by side (posts 3 & 4).
 * Every tile reuses template-parts/card/hero.php (same overlay/gradient/
 * badge/meta as the home hero) instead of duplicating card markup; only
 * posts 1 & 2 get the badge+meta ($compact = false), posts 3 & 4 are
 * title-only, matching the reference (its items 3/4 carry no
 * `.term-badges`/`.post-meta`).
 *
 * Degrades gracefully below 4 available posts (fewer than 4 matching
 * posts is common on a small/young category): col-2 is omitted entirely
 * with only 1 post, the bottom row is omitted with only 2, and a lone
 * 3rd post fills the bottom row at full width (`:only-child` in CSS) —
 * never an empty tile.
 *
 * @var array<string, mixed> $args {
 *     @type WP_Query             $query   Query já executada (Renderer).
 *     @type array<string, mixed> $options Opções vindas de Renderer::buildOptions().
 * }
 */

$query = $args['query'] instanceof WP_Query ? $args['query'] : new WP_Query(['post__in' => [0]]);
$options = is_array($args['options'] ?? null) ? $args['options'] : [];
// Só o PRIMEIRO bloco de listagem da requisição pode conter o elemento LCP.
// Ver Arena\Media::claimAboveTheFoldBlock(): sem esse trinco, cada bloco marcava
// o próprio primeiro card e a home ficava com 6 imagens em fetchpriority=high.
$blocoAcimaDaDobra = \Arena\Media::claimAboveTheFoldBlock();


$scheme = ($options['dark_scheme'] ?? false) ? 'bs-dark-scheme' : 'bs-light-scheme';
$visibilityClass = (string) ($options['visibility_class'] ?? '');

$tiles = [];
if ($query->have_posts()) {
    while ($query->have_posts() && count($tiles) < 4) {
        $query->the_post();
        $position = count($tiles) + 1;

        ob_start();
        get_template_part('template-parts/card/hero', null, [
            'is_first' => $position === 1,
            'above_fold' => $blocoAcimaDaDobra && $position === 1,
            'compact'  => $position > 2,
            'options'  => $options,
        ]);
        $tiles[] = (string) ob_get_clean();
    }
}

$tile1 = $tiles[0] ?? null;
$tile2 = $tiles[1] ?? null;
$tile3 = $tiles[2] ?? null;
$tile4 = $tiles[3] ?? null;
?>
<div class="bs-listing bs-listing-modern-grid-1 <?php echo esc_attr(trim($scheme . ' ' . $visibilityClass)); ?>">
    <?php echo $options['heading_html'] ?? ''; ?>
    <div class="listing listing-modern-grid-1 clearfix">
        <?php if ($tile1 !== null): ?>
            <div class="mg1-col mg1-col-1"><?php echo $tile1; ?></div>
            <?php if ($tile2 !== null || $tile3 !== null || $tile4 !== null): ?>
                <div class="mg1-col mg1-col-2">
                    <?php if ($tile2 !== null): ?>
                        <div class="mg1-top"><?php echo $tile2; ?></div>
                    <?php endif; ?>
                    <?php if ($tile3 !== null || $tile4 !== null): ?>
                        <div class="mg1-row-bottom clearfix">
                            <?php if ($tile3 !== null): ?>
                                <div class="mg1-tile"><?php echo $tile3; ?></div>
                            <?php endif; ?>
                            <?php if ($tile4 !== null): ?>
                                <div class="mg1-tile"><?php echo $tile4; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="listing-empty"><?php esc_html_e('Nenhum post encontrado.', 'arena'); ?></p>
        <?php endif; ?>
    </div>
</div>
