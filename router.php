<?php
/**
 * ============================================================
 * FarmaPonto - Router do servidor local (php -S)
 * ============================================================
 * Responsabilidade: substituir o .htaccess/nginx quando a aplicacao corre
 * no servidor embutido do PHP (versao de secretaria). Serve apenas
 * ficheiros estaticos autorizados e encaminha tudo o resto para index.php.
 *
 * Seguranca (protecao do codigo):
 *   - nenhum .php e servido nem executado, excepto o front controller;
 *   - as pastas /app, /config, /storage, /cofre, /scripts, /tests, /cli
 *     ficam inacessiveis por HTTP;
 *   - extensoes permitidas em lista branca (nada de descarregar fontes,
 *     esquemas .sql, .md, .json de configuracao, etc.).
 */

declare(strict_types=1);

$raiz = __DIR__;
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri  = urldecode($uri);

// Normalizar e impedir travessia de pastas
$caminho = realpath($raiz . '/' . ltrim(str_replace('\\', '/', $uri), '/'));
$dentro  = $caminho !== false && str_starts_with($caminho, $raiz . DIRECTORY_SEPARATOR);

$pastasProibidas = ['app', 'config', 'storage', 'cofre', 'scripts', 'tests', 'cli', 'docs', '.git'];
$primeiro = strtolower(explode('/', trim($uri, '/'))[0] ?? '');
if ($primeiro !== '' && in_array($primeiro, $pastasProibidas, true)) {
    http_response_code(404);
    echo 'Nao encontrado';
    return true;
}

$extensoesPermitidas = [
    'css' => 'text/css',
    'js'  => 'application/javascript',
    'map' => 'application/json',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'webp' => 'image/webp',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'eot' => 'application/vnd.ms-fontobject',
    'webmanifest' => 'application/manifest+json',
    'txt' => 'text/plain',
    'pdf' => 'application/pdf',
];

if ($dentro && is_file($caminho)) {
    $ext = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
    if (!isset($extensoesPermitidas[$ext])) {
        http_response_code(404);
        echo 'Nao encontrado';
        return true;
    }
    header('Content-Type: ' . $extensoesPermitidas[$ext]);
    header('Cache-Control: public, max-age=31536000, immutable');
    header('X-Content-Type-Options: nosniff');
    readfile($caminho);
    return true;
}

// Tudo o resto: front controller
require $raiz . '/index.php';
return true;
