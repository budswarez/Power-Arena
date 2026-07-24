<?php
declare(strict_types=1);

namespace Arena;

final class Assets {
    private const MANIFEST = '/assets/dist/.vite/manifest.json';

    /** URL real do Google Fonts para os tokens do tema (Barlow + Oswald). */
    private const FONTS_URL = 'https://fonts.googleapis.com/css?family=Barlow:400,500,600,700|Oswald:500,400&display=swap';

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('script_loader_tag', [self::class, 'deferOwnScripts'], 10, 2);
        add_filter('wp_resource_hints', [self::class, 'resourceHints'], 10, 2);
    }

    /** Resolvedor puro (testável): URL exata do Google Fonts (Barlow + Oswald, display=swap). */
    public static function fontsUrl(): string {
        return self::FONTS_URL;
    }

    /**
     * Resolvedor puro (testável): adiciona preconnect para os hosts do Google
     * Fonts, preservando quaisquer hints já presentes de outras relações.
     *
     * @param array<int, string|array<string, string>> $hints
     * @return array<int, string|array<string, string>>
     */
    public static function resourceHints(array $hints, string $relationType): array {
        if ($relationType !== 'preconnect') {
            return $hints;
        }
        $hints[] = ['href' => 'https://fonts.googleapis.com'];
        $hints[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => 'anonymous'];
        return $hints;
    }

    /** Resolvedor puro (testável): entrada -> dados do manifest. */
    public static function resolve(array $manifest, string $entry): ?array {
        if (!isset($manifest[$entry]['file'])) { return null; }
        return [
            'file' => $manifest[$entry]['file'],
            'css'  => $manifest[$entry]['css'] ?? [],
        ];
    }

    private static function manifest(): array {
        $path = ARENA_DIR . self::MANIFEST;
        if (!is_readable($path)) { return []; }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Resolvedor puro (testável): lista de arquivos CSS -> pares handle/file.
     * O primeiro arquivo recebe o handle 'arena-main' (preservado para
     * compatibilidade com dependências externas); os demais recebem
     * handles únicos indexados: 'arena-main-1', 'arena-main-2', ...
     *
     * @param string[] $cssFiles
     * @return array<int, array{handle: string, file: string}>
     */
    public static function styleHandles(array $cssFiles): array {
        $pairs = [];
        foreach (array_values($cssFiles) as $index => $file) {
            $handle = $index === 0 ? 'arena-main' : 'arena-main-' . $index;
            $pairs[] = ['handle' => $handle, 'file' => $file];
        }
        return $pairs;
    }

    public static function enqueue(): void {
        wp_enqueue_style('arena-fonts', self::fontsUrl(), [], null);

        $manifest = self::manifest();
        $dist = ARENA_URI . '/assets/dist/';

        $js = self::resolve($manifest, 'assets/src/js/main.js');
        if ($js !== null) {
            foreach (self::styleHandles($js['css']) as $pair) {
                wp_enqueue_style($pair['handle'], $dist . $pair['file'], [], null);
            }
            wp_enqueue_script('arena-main', $dist . $js['file'], [], null, true);
        }
    }

    /** Aplica defer apenas aos scripts do próprio tema. */
    public static function deferOwnScripts(string $tag, string $handle): string {
        if (str_starts_with($handle, 'arena-')) {
            return str_replace(' src=', ' defer src=', $tag);
        }
        return $tag;
    }
}
