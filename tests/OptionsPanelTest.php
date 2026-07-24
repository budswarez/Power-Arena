<?php
declare(strict_types=1);

use Arena\OptionsPanel;

class OptionsPanelTest extends WP_UnitTestCase {
    public function test_field_definitions_shape(): void {
        $fields = OptionsPanel::fields();
        $keys = array_column($fields, 'name');
        $this->assertContains('arena_logo', $keys);
        $this->assertContains('arena_accent_color', $keys);
        $this->assertContains('arena_base_font', $keys);
        $this->assertContains('arena_sidebar_position', $keys);
    }

    public function test_register_is_noop_without_acf(): void {
        // Sem ACF carregado, register() não deve lançar erro.
        OptionsPanel::register();
        $this->assertTrue(true);
    }

    public function test_boot_is_noop_without_acf(): void {
        // Confirma que o ambiente de teste realmente não tem ACF carregado;
        // caso contrário este teste não estaria exercitando o guard real.
        $this->assertFalse(
            function_exists('acf_add_options_page'),
            'Este teste assume ACF ausente; se ACF estiver carregado, o guard não é exercitado.'
        );

        // Chama boot() diretamente (não apenas register()) para exercitar o
        // guard `if (!function_exists('acf_add_options_page')) { return; }`.
        // Sem ACF, isso deve retornar antecipadamente sem fatal error.
        OptionsPanel::boot();
        $this->assertTrue(true);

        // Nenhuma página de opções ACF deve ter sido registrada como resultado.
        $this->assertFalse(function_exists('acf_get_options_pages'));
    }
}
