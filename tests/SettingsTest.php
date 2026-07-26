<?php
declare(strict_types=1);

use Arena\Settings;
use Arena\Options;

/**
 * Cobre o esquema de opções de estilização do tema.
 *
 * A garantia central testada aqui: valor vazio = "padrão do tema", e nesse caso
 * NENHUMA variável CSS é emitida — é isso que faz publicar esta versão num site
 * em produção não mudar um pixel até alguém configurar algo. Se essa regra
 * quebrar, um site atualizado passa a receber overrides inline que ele nunca
 * pediu.
 */
final class SettingsTest extends WP_UnitTestCase {
    public function tear_down(): void {
        foreach (array_keys(Settings::fields()) as $id) {
            remove_theme_mod($id);
        }
        parent::tear_down();
    }

    public function test_every_field_declares_the_shape_the_uis_depend_on(): void {
        foreach (Settings::fields() as $id => $campo) {
            $this->assertArrayHasKey('group', $campo, "{$id} sem grupo");
            $this->assertArrayHasKey('label', $campo, "{$id} sem label");
            $this->assertArrayHasKey('type', $campo, "{$id} sem type");
            $this->assertArrayHasKey($campo['group'], Settings::GROUPS, "{$id} aponta para grupo inexistente");
            $this->assertContains(
                $campo['type'],
                ['color', 'select', 'number', 'checkbox', 'image', 'text'],
                "{$id} tem tipo desconhecido"
            );
            if ($campo['type'] === 'select') {
                $this->assertArrayHasKey('choices', $campo, "{$id} é select sem choices");
            }
        }
    }

    public function test_every_group_has_at_least_one_field(): void {
        foreach (array_keys(Settings::GROUPS) as $grupo) {
            $this->assertNotEmpty(Settings::fieldsByGroup($grupo), "grupo {$grupo} está vazio");
        }
    }

    /** Nada configurado: nenhum token — o main.css continua manda. */
    public function test_emits_no_css_tokens_when_nothing_is_configured(): void {
        $this->assertSame([], Settings::cssTokens());
    }

    public function test_emits_only_the_configured_tokens(): void {
        set_theme_mod('arena_header_bg', '#123456');

        $tokens = Settings::cssTokens();

        $this->assertSame(['--arena-header-bg' => '#123456'], $tokens);
    }

    public function test_number_fields_carry_their_unit(): void {
        set_theme_mod('arena_site_width', '1440');

        $this->assertSame('1440px', Settings::cssTokens()['--arena-site-width']);
    }

    public function test_color_is_normalised_and_garbage_is_ignored(): void {
        $this->assertSame('#abcdef', Settings::sanitize('arena_header_bg', 'abcdef'));
        $this->assertSame('', Settings::sanitize('arena_header_bg', 'vermelho'));
        $this->assertSame('', Settings::sanitize('arena_header_bg', '<script>'));
    }

    /**
     * Um número fora da faixa é tratado como "não configurado" em vez de ser
     * cortado para o limite: um `--arena-site-width: 20000px` salvo à mão
     * nunca chega ao HTML.
     */
    public function test_out_of_range_numbers_are_discarded(): void {
        $this->assertSame('', Settings::sanitize('arena_site_width', 99999));
        $this->assertSame('', Settings::sanitize('arena_site_width', 10));
        $this->assertSame('1200', Settings::sanitize('arena_site_width', 1200));
    }

    public function test_decimals_survive_for_line_height(): void {
        $this->assertSame('1.65', Settings::sanitize('arena_line_height', '1.65'));
        $this->assertSame('1.5', Settings::sanitize('arena_line_height', 1.5));
        $this->assertSame('', Settings::sanitize('arena_line_height', 3)); // fora da faixa
    }

    public function test_select_only_accepts_declared_choices(): void {
        $this->assertSame('800', Settings::sanitize('arena_headline_weight', '800'));
        $this->assertSame('', Settings::sanitize('arena_headline_weight', '123'));
    }

    public function test_checkbox_normalises_to_one_or_zero(): void {
        $this->assertSame('1', Settings::sanitize('arena_dark_latest_row', 'on'));
        $this->assertSame('1', Settings::sanitize('arena_dark_latest_row', '1'));
        $this->assertSame('0', Settings::sanitize('arena_dark_latest_row', '0'));
    }

    /** A faixa escura de "Últimas notícias" só desliga quando explicitamente desligada. */
    public function test_dark_latest_row_defaults_to_enabled(): void {
        $this->assertTrue(Settings::darkLatestRowEnabled());

        set_theme_mod('arena_dark_latest_row', '0');
        $this->assertFalse(Settings::darkLatestRowEnabled());

        set_theme_mod('arena_dark_latest_row', '1');
        $this->assertTrue(Settings::darkLatestRowEnabled());
    }

    public function test_helpers_return_null_when_unset(): void {
        $this->assertNull(Settings::itemsPerBlock());
        $this->assertNull(Settings::cardsPerRow());
        $this->assertNull(Settings::defaultThumbnailId());

        set_theme_mod('arena_items_per_block', '8');
        set_theme_mod('arena_cards_per_row', '3');
        $this->assertSame(8, Settings::itemsPerBlock());
        $this->assertSame(3, Settings::cardsPerRow());
    }

    /**
     * Options::cssTokens() precisa continuar emitindo os 4 tokens históricos
     * (accent, accent-text, fontes) E incorporar os novos — as duas coisas na
     * mesma passada, senão o inline `:root` perde metade do trabalho.
     */
    public function test_options_css_tokens_merge_the_schema(): void {
        $semNada = Options::cssTokens();
        $this->assertArrayHasKey('--arena-accent', $semNada);
        $this->assertArrayHasKey('--arena-accent-text', $semNada);
        $this->assertArrayHasKey('--arena-font-body', $semNada);
        $this->assertArrayNotHasKey('--arena-footer-bg', $semNada);

        set_theme_mod('arena_footer_bg', '#202020');

        $comOpcao = Options::cssTokens();
        $this->assertSame('#202020', $comOpcao['--arena-footer-bg']);
        $this->assertArrayHasKey('--arena-accent', $comOpcao, 'os tokens originais não podem ser perdidos');
    }

    /**
     * Toda variável declarada no esquema precisa ser realmente consumida pelo
     * CSS — uma opção que não afeta nada é pior que não existir, porque o dono
     * do site mexe nela e conclui que o tema está quebrado.
     */
    public function test_every_declared_css_var_is_consumed_by_the_stylesheet(): void {
        $css = (string) file_get_contents(ARENA_DIR . '/assets/src/css/main.css');

        foreach (Settings::fields() as $id => $campo) {
            $var = (string) ($campo['css_var'] ?? '');
            if ($var === '') {
                continue;
            }

            $this->assertStringContainsString(
                'var(' . $var,
                $css,
                "A opção {$id} declara {$var}, mas nenhuma regra do main.css lê essa variável."
            );
        }
    }
}
