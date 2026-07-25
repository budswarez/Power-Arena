<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * `<section class="archive-title">` header shared by every archive kind
 * archive.php routes to (category/tag/author/date) — one partial with
 * small conditionals rather than 4 near-duplicate copies, per the Task 4
 * brief. Reproduces the reference's measured `with-desc`/`with-terms`
 * state classes (added only when that block actually renders) on the
 * wrapping `<section>`.
 *
 * H1: `get_the_archive_title()` normally prefixes its result with
 * "Category:"/"Tag:"/"Author:"/etc. (WP core, since 5.5 via the
 * `get_the_archive_title_prefix` filter) — the reference shows just the
 * bare term/author/date title, so that prefix is filtered away to an empty
 * string right here. This is the ONLY <h1> this partial renders, and
 * archive.php renders no other H1, so exactly one <h1> reaches the page.
 *
 * Description: `term_description()` (category/tag/generic taxonomy terms)
 * or the author's own bio (`description` user meta) — rendered via
 * `wp_kses_post()`, and the wrapping `<div class="archive-description">`
 * is omitted entirely when empty (no empty box). Date archives have no
 * description concept and get neither block.
 *
 * Child-term chips (`with-terms`): only for taxonomy term archives
 * (category/tag/custom taxonomy) that actually have children — an author
 * or date archive never gets this block.
 */

add_filter('get_the_archive_title_prefix', '__return_empty_string');

$queriedObject = get_queried_object();
$isTermArchive = $queriedObject instanceof WP_Term;
$isAuthorArchive = is_author() && $queriedObject instanceof WP_User;

$titleClass = 'archive-title';
if (is_category()) {
    $titleClass .= ' category-title';
} elseif (is_tag()) {
    $titleClass .= ' tag-title';
} elseif ($isAuthorArchive) {
    $titleClass .= ' author-title';
} elseif (is_date()) {
    $titleClass .= ' date-title';
} elseif ($isTermArchive) {
    $titleClass .= ' ' . sanitize_html_class($queriedObject->taxonomy) . '-title';
}

$description = '';
if ($isTermArchive) {
    $rawDescription = term_description($queriedObject->term_id, $queriedObject->taxonomy);
    $description = is_string($rawDescription) ? trim($rawDescription) : '';
} elseif ($isAuthorArchive) {
    $description = trim((string) get_the_author_meta('description', $queriedObject->ID));
}

$childTerms = [];
if ($isTermArchive) {
    $terms = get_terms([
        'taxonomy'   => $queriedObject->taxonomy,
        'child_of'   => $queriedObject->term_id,
        'hide_empty' => false,
    ]);
    if (is_array($terms)) {
        $childTerms = $terms;
    }
}

if ($description !== '') {
    $titleClass .= ' with-desc';
}
if ($childTerms !== []) {
    $titleClass .= ' with-terms';
}
?>
<section class="<?php echo esc_attr($titleClass); ?>">
    <h1 class="page-heading"><?php echo esc_html(get_the_archive_title()); ?></h1>
    <?php if ($description !== ''): ?>
        <div class="archive-description"><?php echo wp_kses_post($description); ?></div>
    <?php endif; ?>
    <?php if ($childTerms !== []): ?>
        <ul class="archive-terms">
            <?php foreach ($childTerms as $childTerm): ?>
                <?php if (!($childTerm instanceof WP_Term)): continue; endif; ?>
                <li class="archive-terms__item">
                    <a class="archive-terms__link" href="<?php echo esc_url((string) get_term_link($childTerm)); ?>"><?php echo esc_html($childTerm->name); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
