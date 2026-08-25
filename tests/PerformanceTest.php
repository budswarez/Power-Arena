<?php
declare(strict_types=1);

use Arena\Performance;

class PerformanceTest extends WP_UnitTestCase {
    public function test_theme_registers_contact_form_and_responsive_image_filters(): void {
        $this->assertNotFalse(has_filter('wpcf7_load_js', [Performance::class, 'contactFormAssetsRequired']));
        $this->assertNotFalse(has_filter('wpcf7_load_css', [Performance::class, 'contactFormAssetsRequired']));
        $this->assertNotFalse(has_filter('do_shortcode_tag', [Performance::class, 'responsiveVcSingleImage']));
        $this->assertNotFalse(has_filter('script_loader_tag', [Performance::class, 'deferWpdiscuzRecaptcha']));
    }

    public function test_contact_form_assets_are_disabled_on_content_without_shortcode(): void {
        $pageId = $this->factory()->post->create([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '<p>Página comum.</p>',
        ]);
        $this->go_to(get_permalink($pageId));

        $this->assertFalse(Performance::contactFormAssetsRequired(true));
    }

    public function test_contact_form_assets_are_preserved_when_shortcode_exists(): void {
        $pageId = $this->factory()->post->create([
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_content' => '[contact-form-7 id="42"]',
        ]);
        $this->go_to(get_permalink($pageId));

        $this->assertTrue(Performance::contactFormAssetsRequired(true));
    }

    public function test_contact_form_respects_an_existing_false_decision(): void {
        $this->assertFalse(Performance::contactFormAssetsRequired(false));
    }

    public function test_add_image_srcset_adds_attributes_only_to_first_image(): void {
        $html = '<figure><img src="banner.webp"><img src="other.webp"></figure>';
        $result = Performance::addImageSrcset(
            $html,
            'banner-640.webp 640w, banner.webp 1170w',
            '(max-width: 768px) 100vw, 1170px'
        );

        $this->assertSame(1, substr_count($result, ' srcset='));
        $this->assertStringContainsString('banner-640.webp 640w, banner.webp 1170w', $result);
        $this->assertStringContainsString('sizes="(max-width: 768px) 100vw, 1170px"', $result);
    }

    public function test_add_image_srcset_does_not_replace_an_existing_srcset(): void {
        $html = '<img src="banner.webp" srcset="existing.webp 640w">';
        $this->assertSame($html, Performance::addImageSrcset($html, 'new.webp 640w', '100vw'));
    }

    public function test_onesignal_markup_is_deferred_without_changing_plugin_initialization(): void {
        $markup = '<meta name="onesignal-plugin" content="wordpress-3.9.2">'
            . '<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>'
            . '<script>window.OneSignalDeferred=[];OneSignalDeferred.push(function(){});</script>';

        $result = Performance::deferOneSignalMarkup($markup);

        $this->assertStringContainsString('id="arena-onesignal-loader"', $result);
        $this->assertStringContainsString("setTimeout(load,4000)", $result);
        $this->assertSame(1, substr_count($result, 'OneSignalSDK.page.js'));
        $this->assertStringContainsString('OneSignalDeferred.push', $result);
        $this->assertStringContainsString('onesignal-plugin', $result);
    }

    public function test_onesignal_unknown_markup_is_left_untouched(): void {
        $markup = '<script src="https://example.com/unrelated.js"></script>';
        $this->assertSame($markup, Performance::deferOneSignalMarkup($markup));
    }

    public function test_wpdiscuz_recaptcha_waits_for_comment_area(): void {
        $src = 'https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit&hl=pt-BR';
        $tag = '<script id="wpdiscuz-google-recaptcha-js" src="' . esc_url($src) . '"></script>';

        $result = Performance::deferWpdiscuzRecaptcha($tag, 'wpdiscuz-google-recaptcha', $src);

        $this->assertStringContainsString('id="power-arenadiscuz-recaptcha-loader"', $result);
        $this->assertStringContainsString('IntersectionObserver', $result);
        $this->assertStringContainsString('rootMargin:"600px 0px"', $result);
        $this->assertStringContainsString('getElementById("wpdcom")', $result);
        $this->assertStringContainsString('/recaptcha/api.js', $result);
        $this->assertStringNotContainsString(' src="https://www.google.com/recaptcha', $result);
    }

    public function test_recaptcha_filter_leaves_other_scripts_untouched(): void {
        $tag = '<script src="https://example.com/app.js"></script>';

        $this->assertSame($tag, Performance::deferWpdiscuzRecaptcha($tag, 'other-handle', 'https://example.com/app.js'));
    }
}
