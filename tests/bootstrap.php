<?php
declare(strict_types=1);

$_tests_dir = getenv('WP_TESTS_DIR') ?: '/wordpress-phpunit';
require $_tests_dir . '/includes/functions.php';

tests_add_filter('setup_theme', static function (): void {
    switch_theme('arena');
});

// Não requeremos functions.php diretamente aqui: neste ponto ABSPATH ainda não
// está definida (o WP core só a define dentro do require abaixo), e
// functions.php possui a guarda `if (!defined('ABSPATH')) { exit; }`. O core do
// WP já inclui o functions.php do tema ativo automaticamente, imediatamente
// após o action 'setup_theme' disparado pelo switch_theme() acima — ponto em
// que ABSPATH já está definida.
require $_tests_dir . '/includes/bootstrap.php';
