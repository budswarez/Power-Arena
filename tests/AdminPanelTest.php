<?php
declare(strict_types=1);

use Arena\AdminPanel;
use Arena\Settings;

/**
 * O painel próprio no admin — a tela que substitui a página de opções do ACF
 * (que nunca apareceu na produção, porque páginas de opções são recurso do ACF
 * PRO e o site tem a versão gratuita).
 */
final class AdminPanelTest extends WP_UnitTestCase {
    public function tear_down(): void {
        foreach (array_keys(Settings::fields()) as $id) {
            remove_theme_mod($id);
        }
        parent::tear_down();
    }

    public function test_register_hooks_the_menu_and_the_save_handler(): void {
        AdminPanel::register();

        $this->assertNotFalse(has_action('admin_menu', [AdminPanel::class, 'addMenu']));
        $this->assertNotFalse(has_action('admin_post_arena_salvar_opcoes', [AdminPanel::class, 'handleSave']));
    }

    /** Aba inválida (ou ausente) cai na primeira, nunca gera tela vazia. */
    public function test_unknown_tab_falls_back_to_the_first_group(): void {
        $primeira = (string) array_key_first(Settings::GROUPS);

        $this->assertSame($primeira, AdminPanel::currentGroup(null));
        $this->assertSame($primeira, AdminPanel::currentGroup('nao-existe'));
        $this->assertSame($primeira, AdminPanel::currentGroup(['array']));
        $this->assertSame('layout', AdminPanel::currentGroup('layout'));
    }

    public function test_saves_only_the_submitted_group(): void {
        set_theme_mod('arena_site_width', '1400'); // pertence ao grupo "layout"

        AdminPanel::saveGroup('cores', ['arena_header_bg' => '#111111']);

        $this->assertSame('#111111', get_theme_mod('arena_header_bg'));
        $this->assertSame(
            '1400',
            get_theme_mod('arena_site_width'),
            'salvar a aba Cores não pode apagar o que está em Layout'
        );
    }

    /** Campo esvaziado remove o theme_mod: volta ao padrão do tema, não grava vazio. */
    public function test_clearing_a_field_removes_the_theme_mod(): void {
        set_theme_mod('arena_header_bg', '#111111');

        AdminPanel::saveGroup('cores', ['arena_header_bg' => '']);

        $this->assertFalse(get_theme_mod('arena_header_bg', false));
        $this->assertSame('', Settings::value('arena_header_bg'));
    }

    public function test_invalid_values_are_never_stored(): void {
        AdminPanel::saveGroup('cores', ['arena_header_bg' => 'javascript:alert(1)']);

        $this->assertFalse(get_theme_mod('arena_header_bg', false));
    }

    /** Checkbox desmarcado não vem no POST — precisa ser gravado como "0", não ignorado. */
    public function test_unchecked_checkbox_is_saved_as_zero(): void {
        set_theme_mod('arena_dark_latest_row', '1');

        AdminPanel::saveGroup('blocos', []); // nenhum checkbox enviado

        $this->assertSame('0', get_theme_mod('arena_dark_latest_row'));
        $this->assertFalse(Settings::darkLatestRowEnabled());
    }

    public function test_save_returns_what_was_persisted(): void {
        $salvos = AdminPanel::saveGroup('layout', [
            'arena_site_width'      => '1320',
            'arena_sidebar_position' => 'left',
        ]);

        $this->assertSame('1320', $salvos['arena_site_width']);
        $this->assertSame('left', $salvos['arena_sidebar_position']);
        $this->assertSame('1320', get_theme_mod('arena_site_width'));
    }

    /** A capacidade exigida é a de quem edita aparência — não `manage_options`. */
    public function test_requires_theme_options_capability(): void {
        $this->assertSame('edit_theme_options', AdminPanel::CAPABILITY);

        $editor = self::factory()->user->create(['role' => 'editor']);
        $admin  = self::factory()->user->create(['role' => 'administrator']);

        wp_set_current_user($editor);
        $this->assertFalse(current_user_can(AdminPanel::CAPABILITY));

        wp_set_current_user($admin);
        $this->assertTrue(current_user_can(AdminPanel::CAPABILITY));
    }
}
