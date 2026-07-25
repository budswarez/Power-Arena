<?php
declare(strict_types=1);

namespace Arena;

final class Compatibility {
    public static function shouldLoadEmojis(): bool {
        return false;
    }

    public static function register(): void {
        add_action('init', [self::class, 'trimCoreBloat']);
    }

    public static function trimCoreBloat(): void {
        if (!self::shouldLoadEmojis()) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            // Explicit priority 10 (WP core's own default for this action)
            // for symmetry with the `wp_head` removal above, which must
            // name its non-default priority 7 explicitly to match.
            remove_action('wp_print_styles', 'print_emoji_styles', 10);
        }
        // RankMath, anúncios e cache: nenhuma ação necessária aqui — o tema
        // preserva wp_head()/wp_footer() e o loop padrão, então esses plugins
        // continuam funcionando. Ajustes específicos entram sob demanda.
    }
}
