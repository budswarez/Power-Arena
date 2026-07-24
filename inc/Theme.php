<?php
declare(strict_types=1);

namespace Arena;

final class Theme {
    /** Registra todos os módulos do tema. */
    public static function boot(): void {
        Setup::register();
        Assets::register();
        OptionsPanel::register();
    }
}
