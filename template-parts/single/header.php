<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Article title — the ONLY <h1> on the single template (single.php has no
 * other heading at this level; header.php's own branding/menu markup uses
 * no heading tags at all). Matches the reference's measured
 * `.post-header-inner > .post-header-title > h1.single-post-title >
 * span.post-title` nesting.
 */
?>
<div class="post-header-inner">
    <div class="post-header-title">
        <h1 class="single-post-title"><span class="post-title"><?php the_title(); ?></span></h1>
    </div>
</div>
