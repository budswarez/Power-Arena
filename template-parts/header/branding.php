<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * `#site-branding`: logo from `Arena\Options::logoId()` when the site owner
 * set one via the ACF options page; falls back to `.site-name` (bloginfo
 * 'name') + `.site-description` (bloginfo 'description') otherwise.
 */

$logo_id = \Arena\Options::logoId();
?>
<div id="site-branding" class="site-branding">
    <a class="site-branding__link" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
        <?php if ($logo_id): ?>
            <?php
            echo wp_get_attachment_image($logo_id, 'full', false, [
                'class' => 'site-logo',
                'alt'   => get_bloginfo('name'),
            ]);
            ?>
        <?php else: ?>
            <div class="site-name"><?php bloginfo('name'); ?></div>
            <div class="site-description"><?php bloginfo('description'); ?></div>
        <?php endif; ?>
    </a>
</div>
