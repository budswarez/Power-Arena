<?php
declare(strict_types=1);

/**
 * Conteúdo local determinístico para validar templates, listagens e menus.
 *
 * Execute com `npm run env:seed`. O script é idempotente: posts, página,
 * comentários e itens de menu existentes são atualizados ou reaproveitados.
 * Nenhum dado externo ou de produção é necessário.
 */

if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) {
    exit("Este arquivo deve ser executado pelo WP-CLI.\n");
}

/** @return int ID criado ou já existente. */
function arena_sample_term(string $name, string $slug): int {
    $existing = get_term_by('slug', $slug, 'category');
    if ($existing instanceof WP_Term) {
        return (int) $existing->term_id;
    }

    $created = wp_insert_term($name, 'category', ['slug' => $slug]);
    if (is_wp_error($created)) {
        WP_CLI::error($created->get_error_message());
    }

    return (int) $created['term_id'];
}

/** @param array<string, mixed> $postarr */
function arena_sample_post(array $postarr): int {
    $existing = get_page_by_path((string) $postarr['post_name'], OBJECT, (string) $postarr['post_type']);
    if ($existing instanceof WP_Post) {
        $postarr['ID'] = $existing->ID;
    }

    $postId = wp_insert_post($postarr, true);
    if (is_wp_error($postId)) {
        WP_CLI::error($postId->get_error_message());
    }

    update_post_meta((int) $postId, '_arena_sample', '1');
    return (int) $postId;
}

/** Adiciona um item de menu apenas quando o objeto ainda não estiver nele. */
function arena_sample_menu_item(int $menuId, int $objectId, string $type): void {
    foreach (wp_get_nav_menu_items($menuId) ?: [] as $item) {
        if ((int) $item->object_id === $objectId && $item->object === $type) {
            return;
        }
    }

    wp_update_nav_menu_item($menuId, 0, [
        'menu-item-object-id' => $objectId,
        'menu-item-object'    => $type,
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
    ]);
}

$categories = [
    arena_sample_term('Esports', 'arena-sample-esports'),
    arena_sample_term('Hardware', 'arena-sample-hardware'),
    arena_sample_term('Games', 'arena-sample-games'),
];

$homeId = arena_sample_post([
    'post_type'    => 'page',
    'post_status'  => 'publish',
    'post_title'   => 'Arena — Home de demonstração',
    'post_name'    => 'arena-home-sample',
    'post_content' => implode("\n\n", [
        '[bs-modern-grid-listing-7 count="5" title="Destaques" disable_duplicate="1"]',
        '[bs-mix-listing-3-1 count="6" title="Mais notícias" disable_duplicate="1"]',
        '[bs-blog-listing-1 count="5" title="Leitura recomendada" disable_duplicate="1"]',
        '[bs-grid-listing-1 count="8" columns="4" title="Últimas notícias"]',
    ]),
]);

$seedNow = current_datetime();
for ($index = 1; $index <= 24; $index++) {
    $categoryId = $categories[($index - 1) % count($categories)];
    $postId = arena_sample_post([
        'post_type'     => 'post',
        'post_status'   => 'publish',
        'post_title'    => sprintf('Notícia de demonstração %02d', $index),
        'post_name'     => sprintf('arena-sample-noticia-%02d', $index),
        'post_excerpt'  => 'Resumo curto para validar cards, espaçamento e cortes de texto nas listagens.',
        'post_content'  => sprintf(
            '<p>Conteúdo local da notícia %1$02d. Este texto existe apenas para validar o tema Arena sem copiar dados de produção.</p>\n\n[accordions title="Resumo da matéria"][accordion title="Pontos principais" load="hide"]- Item de teste %1$02d\n- Segundo item para validar o componente[/accordion][/accordions]\n\n<p>Parágrafo adicional para testar tipografia, links e ritmo vertical.</p>',
            $index
        ),
        'post_category' => [$categoryId],
        'post_date'     => $seedNow->modify("-{$index} days")->format('Y-m-d H:i:s'),
    ]);

    if ($index % 4 === 0) {
        $existingComments = get_comments([
            'post_id'      => $postId,
            'author_email' => 'sample@invalid.example',
            'count'        => true,
        ]);
        if ((int) $existingComments === 0) {
            wp_insert_comment([
                'comment_post_ID'      => $postId,
                'comment_author'       => 'Leitor de demonstração',
                'comment_author_email' => 'sample@invalid.example',
                'comment_content'      => 'Comentário local para validar a apresentação da área de discussão.',
                'comment_approved'     => 1,
            ]);
        }
    }
}

update_option('show_on_front', 'page');
update_option('page_on_front', $homeId);
update_option('posts_per_page', 8);

$menuName = 'Arena — Menu de demonstração';
$menu = wp_get_nav_menu_object($menuName);
$menuId = $menu instanceof WP_Term ? (int) $menu->term_id : wp_create_nav_menu($menuName);
if (is_wp_error($menuId)) {
    WP_CLI::error($menuId->get_error_message());
}
$menuId = (int) $menuId;
arena_sample_menu_item($menuId, $homeId, 'page');
foreach ($categories as $categoryId) {
    foreach (wp_get_nav_menu_items($menuId) ?: [] as $item) {
        if ((int) $item->object_id === $categoryId && $item->object === 'category') {
            continue 2;
        }
    }
    wp_update_nav_menu_item($menuId, 0, [
        'menu-item-object-id' => $categoryId,
        'menu-item-object'    => 'category',
        'menu-item-type'      => 'taxonomy',
        'menu-item-status'    => 'publish',
    ]);
}

$locations = get_theme_mod('nav_menu_locations', []);
foreach (['main-menu', 'top-menu', 'resp-menu', 'footer-menu'] as $location) {
    $locations[$location] = $menuId;
}
set_theme_mod('nav_menu_locations', $locations);
update_option('arena_sample_seed_version', '1');

WP_CLI::success('Conteúdo de demonstração pronto: 24 posts, 3 categorias, home, comentários e menus.');
