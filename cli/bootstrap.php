<?php
/**
 * Arranque comum aos scripts de linha de comando (cli/*.php).
 * Carrega o autoloader e as constantes sem passar pelo Router.
 */

define('ROOT_PATH', dirname(__DIR__));
date_default_timezone_set('Africa/Maputo');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = ROOT_PATH . '/app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) { return; }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $segments = explode('/', $relative);
    $file = array_pop($segments);
    foreach ([
        $baseDir . implode('/', $segments) . ($segments ? '/' : '') . $file . '.php',
        $baseDir . strtolower(implode('/', $segments)) . ($segments ? '/' : '') . $file . '.php',
    ] as $caminho) {
        if (is_file($caminho)) { require $caminho; return; }
    }
});

if (!defined('BASE_PATH')) { define('BASE_PATH', ''); }
if (!defined('PEDIDO_ID')) { define('PEDIDO_ID', 'cli-' . substr(bin2hex(random_bytes(4)), 0, 8)); }
