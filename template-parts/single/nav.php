<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Previous/next post navigation. get_previous_post_link()/get_next_post_link()
 * RETURN their markup (unlike previous_post_link()/next_post_link(), which
 * echo) — used here so the whole <nav> can be skipped when neither link
 * exists (e.g. only one published post on the site) instead of always
 * printing an empty wrapper.
 */

$previous = get_previous_post_link('%link', '&laquo; %title');
$next = get_next_post_link('%link', '%title &raquo;');

if ($previous === '' && $next === '') {
    return;
}
?>
<nav class="next-prev">
    <?php if ($previous !== ''): ?>
        <div class="nav-previous"><?php echo $previous; ?></div>
    <?php endif; ?>
    <?php if ($next !== ''): ?>
        <div class="nav-next"><?php echo $next; ?></div>
    <?php endif; ?>
</nav>
