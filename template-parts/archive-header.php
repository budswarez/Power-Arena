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
 * The filter is added immediately before, and removed immediately after,
 * the single `get_the_archive_title()` call below (whole-branch review,
 * minor finding #5) — it used to be added here and never removed, which
 * leaked it for the rest of the request: any later `get_the_archive_title()`
 * call from a widget/plugin (e.g. in the sidebar) would silently lose its
 * "Category:"/"Tag:" prefix too.
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
 *
 * Label chip + RSS link (`with-actions`, Task 2B polish pass): the
 * reference shows a small dark "pre-title" chip above the H1
 * ("Navegando pela Categoria" for a category archive — measured verbatim
 * in ref-category.html) plus an `.actions-container` holding a feed link,
 * BOTH placed before the `<h1>` in source order. `.actions-container` is
 * floated right in CSS, so — being a float that starts right where the
 * flow is after `.pre-title`'s own block — it visually lands beside the
 * H1's first line (the H1 immediately follows it in the flow) without any
 * extra wrapper markup, matching the reference's exact DOM shape.
 */

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

$labelText = '';
if (is_category()) {
    $labelText = __('Navegando pela Categoria', 'arena');
} elseif (is_tag()) {
    $labelText = __('Navegando pela Tag', 'arena');
} elseif ($isAuthorArchive) {
    $labelText = __('Autor', 'arena');
} elseif (is_date()) {
    $labelText = __('Navegando pelo Arquivo', 'arena');
} elseif ($isTermArchive) {
    $labelText = __('Navegando pelo Termo', 'arena');
}

$feedLink = '';
if ($isTermArchive) {
    $termFeedLink = get_term_feed_link($queriedObject->term_id, $queriedObject->taxonomy);
    $feedLink = is_string($termFeedLink) ? $termFeedLink : '';
} elseif ($isAuthorArchive) {
    $authorFeedLink = get_author_feed_link($queriedObject->ID);
    $feedLink = is_string($authorFeedLink) ? $authorFeedLink : '';
}
if ($feedLink === '') {
    $feedLink = (string) get_bloginfo('rss2_url');
}

if ($labelText !== '') {
    $titleClass .= ' with-actions';
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
        // Capped (whole-branch review, minor finding #5): `child_of` with
        // no `number` returns EVERY descendant, however many there are —
        // a large taxonomy tree could mean an unbounded query and an
        // unbounded chip list. 50 is generous for what's rendered as a
        // flat inline list of chips.
        // `hide_empty` stays false, deliberately NOT flipped to true: this
        // is a "browse the subcategories of this category" nav aid, not a
        // "does this subcategory currently have published posts" filter —
        // a freshly-created child category with no posts yet should still
        // be reachable here (see
        // ArchiveTemplateTest::test_category_with_children_shows_term_chips,
        // which asserts exactly that for a childless-of-posts child term).
        'number'     => 50,
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

// Scoped strictly to this one call — added immediately before, removed
// immediately after — so the prefix-suppression never leaks into any
// OTHER `get_the_archive_title()` call later in the same request (e.g.
// from a widget or another plugin).
add_filter('get_the_archive_title_prefix', '__return_empty_string');
$archiveTitle = get_the_archive_title();
remove_filter('get_the_archive_title_prefix', '__return_empty_string');
?>
<section class="<?php echo esc_attr($titleClass); ?>">
    <?php if ($labelText !== ''): ?>
        <div class="pre-title"><span><?php echo esc_html($labelText); ?></span></div>
        <div class="actions-container">
            <a class="rss-link" href="<?php echo esc_url($feedLink); ?>" aria-label="<?php esc_attr_e('Feed RSS', 'arena'); ?>"><?php echo \Arena\Icons::rss(); ?></a>
        </div>
    <?php endif; ?>
    <h1 class="page-heading"><span class="h-title"><?php echo esc_html($archiveTitle); ?></span></h1>
    <?php if ($description !== ''): ?>
        <div class="archive-description"><?php echo wp_kses_post($description); ?></div>
    <?php endif; ?>
    <?php if ($childTerms !== []): ?>
        <ul class="archive-terms">
            <?php foreach ($childTerms as $childTerm): ?>
                <?php if (!($childTerm instanceof WP_Term)): continue; endif; ?>
                <?php $childTermLink = get_term_link($childTerm); ?>
                <?php if (is_wp_error($childTermLink)): continue; endif; ?>
                <li class="archive-terms__item">
                    <a class="archive-terms__link" href="<?php echo esc_url($childTermLink); ?>"><?php echo esc_html($childTerm->name); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
