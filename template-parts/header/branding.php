<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * `#site-branding` logo precedence (task-native-settings; TESTED in
 * tests/AttachmentTemplateTest.php-adjacent coverage — see
 * tests/SetupTest.php/OptionsTest.php for the underlying resolvers):
 *
 *   1. WordPress' own NATIVE custom logo (`has_custom_logo()`/
 *      `get_custom_logo()`) — set at Aparência → Personalizar → Identidade
 *      do site, zero plugins required. This is the PRIMARY path now (see
 *      Arena\Setup::themeSupports()'s `add_theme_support('custom-logo', …)`).
 *      `get_custom_logo()` already renders the `<a href=home>` wrapper
 *      itself, so this branch does NOT reuse `.site-branding__link` below.
 *   2. The ACF `arena_logo` option (`Arena\Options::logoId()`, only
 *      non-null when ACF is active AND a value is actually saved) — kept
 *      as an optional enhancement for sites that have ACF but never
 *      touched the native Customizer logo.
 *   3. Text fallback: `.site-name` (bloginfo 'name') + `.site-description`
 *      (bloginfo 'description') — unchanged from before this change.
 */
?>
<?php if (has_custom_logo()): ?>
    <div id="site-branding" class="site-branding site-branding--native-logo">
        <?php echo get_custom_logo(); ?>
    </div>
<?php else: ?>
    <?php $logo_id = \Arena\Options::logoId(); ?>
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
<?php endif; ?>
