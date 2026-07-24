<?php
declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

define('ARENA_DIR', get_template_directory());
define('ARENA_URI', get_template_directory_uri());
define('ARENA_VERSION', '0.1.0');

// Autoloader PSR-4 próprio: Arena\Foo\Bar -> inc/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Arena\\';
    if (!str_starts_with($class, $prefix)) { return; }
    $relative = substr($class, strlen($prefix));
    $path = ARENA_DIR . '/inc/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($path)) { require $path; }
});

Arena\Theme::boot();
