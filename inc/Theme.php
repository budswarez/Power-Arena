<?php
declare(strict_types=1);

namespace Arena;

final class Theme {
    /** Registra todos os módulos do tema. */
    public static function boot(): void {
        Setup::register();
        Assets::register();
        Performance::register();
        OptionsPanel::register();
        // Painel próprio no admin: funciona sem plugin nenhum. O OptionsPanel
        // acima só aparece em sites com ACF PRO (páginas de opções são recurso
        // pago), motivo pelo qual a produção ficou sem tela de configuração.
        AdminPanel::register();
        Customizer::register();
        Compatibility::register();
        Blocks\Shortcodes::register();
        Blocks\VcMap::register();
        Blocks\SingleImageHeading::register();
        Blocks\Accordions::register();
    }
}
