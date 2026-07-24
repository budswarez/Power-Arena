<?php
declare(strict_types=1);

namespace Arena;

final class Assets {
    private const MANIFEST = '/assets/dist/.vite/manifest.json';

    public static function register(): void {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue']);
        add_filter('script_loader_tag', [self::class, 'deferOwnScripts'], 10, 2);
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
