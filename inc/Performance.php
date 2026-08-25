<?php
declare(strict_types=1);

namespace Arena;

/**
 * Ajustes de performance que dependem da integração com plugins/conteúdo.
 *
 * Mantidos separados de Assets: aqui não há asset próprio do tema, e sim
 * decisões condicionais sobre markup e integrações externas.
 */
final class Performance {
    private const BANNER_SIZES = '(max-width: 768px) 100vw, (max-width: 1200px) calc(100vw - 30px), 1170px';

    public static function register(): void {
        add_filter('wpcf7_load_js', [self::class, 'contactFormAssetsRequired']);
        add_filter('wpcf7_load_css', [self::class, 'contactFormAssetsRequired']);
        add_filter('do_shortcode_tag', [self::class, 'responsiveVcSingleImage'], 10, 4);
        add_filter('script_loader_tag', [self::class, 'deferWpdiscuzRecaptcha'], 20, 3);

        // A versão 3 do plugin imprime o SDK diretamente em wp_head, sem um
        // handle que possa receber strategy/defer. Preservamos integralmente
        // a inicialização produzida pelo plugin e adiamos somente o download
        // externo até interação ou quatro segundos após window.load.
        if (has_action('wp_head', 'onesignal_init')) {
            remove_action('wp_head', 'onesignal_init');
            add_action('wp_head', [self::class, 'printDeferredOneSignal']);
        }
    }

    /**
     * Contact Form 7 só precisa de CSS/JS quando o conteúdo consultado contém
     * seu shortcode. Respeita uma decisão anterior do plugin/outro filtro de
     * não carregar (`$load === false`) e não interfere no wp-admin.
     */
    public static function contactFormAssetsRequired(bool $load): bool {
        if (!$load || is_admin()) {
            return $load;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return false;
        }

        $content = (string) $post->post_content;

        return has_shortcode($content, 'contact-form-7')
            || preg_match('/\[contact-form-7(?:\s|\]|\/)/i', $content) === 1;
    }

    /**
     * Acrescenta `srcset` ao vc_single_image. O WPBakery 9 monta uma tag com
     * URL direta e descarta o responsive markup que wp_get_attachment_image()
     * forneceria; por isso o banner de 1170 px era enviado também no mobile.
     *
     * @param mixed                $output HTML devolvido pelo shortcode.
     * @param mixed                $tag    Nome do shortcode.
     * @param mixed                $attr   Atributos normalizados pelo core.
     * @param array<int, string>   $m      Match original (não utilizado).
     */
    public static function responsiveVcSingleImage(mixed $output, mixed $tag, mixed $attr, array $m = []): mixed {
        if ($tag !== 'vc_single_image' || !is_string($output) || !is_array($attr)) {
            return $output;
        }

        $attachmentId = isset($attr['image']) ? absint($attr['image']) : 0;
        if ($attachmentId === 0 || stripos($output, '<img') === false || stripos($output, ' srcset=') !== false) {
            return $output;
        }

        $srcset = wp_get_attachment_image_srcset($attachmentId, 'full');
        if (!is_string($srcset) || $srcset === '') {
            return $output;
        }

        return self::addImageSrcset($output, $srcset, self::BANNER_SIZES);
    }

    /** Função pura/testável que altera somente a primeira tag img recebida. */
    public static function addImageSrcset(string $html, string $srcset, string $sizes): string {
        if ($srcset === '' || stripos($html, '<img') === false || stripos($html, ' srcset=') !== false) {
            return $html;
        }

        $attributes = sprintf(
            ' srcset="%s" sizes="%s"',
            esc_attr($srcset),
            esc_attr($sizes)
        );

        return (string) preg_replace('/<img\b/i', '<img' . $attributes, $html, 1);
    }

    /**
     * O wpDiscuz enfileira ~782 KiB de reCAPTCHA em toda leitura de matéria,
     * embora ele só seja usado no formulário de comentários, bem abaixo da
     * dobra. Preserva a URL/configuração oficial do plugin, mas inicia o
     * download quando `#wpdcom` se aproxima da viewport ou recebe interação.
     *
     * @param string $tag    Tag criada pelo WordPress/plugin.
     * @param string $handle Handle registrado pelo wpDiscuz.
     * @param string $src    URL resolvida pelo registro do script.
     */
    public static function deferWpdiscuzRecaptcha(string $tag, string $handle, string $src): string {
        if ($handle !== 'wpdiscuz-google-recaptcha' || !str_contains($src, '/recaptcha/api.js')) {
            return $tag;
        }

        $encodedSrc = wp_json_encode(
            $src,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
        if (!is_string($encodedSrc)) {
            return $tag;
        }

        return sprintf(
            '<script id="power-arenadiscuz-recaptcha-loader">%s</script>',
            '(()=>{let loaded=false;const load=()=>{if(loaded)return;loaded=true;const s=document.createElement("script");s.src=' . $encodedSrc . ';s.defer=true;document.head.appendChild(s)};const init=()=>{const root=document.getElementById("wpdcom");if(!root)return;for(const e of ["focusin","pointerenter","touchstart","click"])root.addEventListener(e,load,{once:true,passive:true});if("IntersectionObserver"in window){const o=new IntersectionObserver(es=>{if(es.some(e=>e.isIntersecting)){o.disconnect();load()}},{rootMargin:"600px 0px"});o.observe(root)}};document.readyState==="loading"?document.addEventListener("DOMContentLoaded",init,{once:true}):init()})();'
        );
    }

    /** Reimprime o markup oficial do plugin com o SDK externo postergado. */
    public static function printDeferredOneSignal(): void {
        if (!function_exists('onesignal_init')) {
            return;
        }

        ob_start();
        onesignal_init();
        $markup = (string) ob_get_clean();

        echo self::deferOneSignalMarkup($markup);
    }

    /**
     * Remove apenas a tag externa do SDK e injeta um loader pequeno. O bloco
     * `OneSignalDeferred.push()` do próprio plugin continua intacto e será
     * consumido normalmente assim que o SDK chegar.
     */
    public static function deferOneSignalMarkup(string $markup): string {
        $pattern = '#<script\s+src=["\']https://cdn\.onesignal\.com/sdks/web/v16/OneSignalSDK\.page\.js["\']\s+defer></script>\s*#i';
        $withoutSdk = preg_replace($pattern, '', $markup, 1, $count);
        if ($count !== 1 || !is_string($withoutSdk)) {
            return $markup;
        }

        $loader = <<<'HTML'
<script id="arena-onesignal-loader">
(()=>{let loaded=false,timer;const load=()=>{if(loaded)return;loaded=true;clearTimeout(timer);for(const e of events)removeEventListener(e,load,opts);const s=document.createElement('script');s.src='https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js';s.defer=true;document.head.appendChild(s)};const events=['pointerdown','keydown','touchstart','scroll'];const opts={once:true,passive:true};for(const e of events)addEventListener(e,load,opts);addEventListener('load',()=>{timer=setTimeout(load,4000)},{once:true})})();
</script>
HTML;

        return $loader . "\n" . $withoutSdk;
    }
}
