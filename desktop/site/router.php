<?php
/**
 * ============================================================
 * FarmaPonto - Router do servidor local (versao de secretaria)
 * ============================================================
 * Serve apenas os ficheiros publicos (estilos, imagens, tipos de letra) e
 * entrega tudo o mais a aplicacao. Nada mais e legivel por HTTP: o cofre,
 * as pastas internas e qualquer .php ficam fora de alcance.
 */

declare(strict_types=1);

$raiz = __DIR__;
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri  = urldecode($uri);

$permitidos = [
    'css' => 'text/css',
    'js'  => 'application/javascript',
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
];

// Apenas a pasta publica de recursos pode ser servida como ficheiro
$primeiro = strtolower(explode('/', trim($uri, '/'))[0] ?? '');
$estatico = in_array($primeiro, ['assets'], true)
    || in_array(trim($uri, '/'), ['sw.js', 'manifest.webmanifest', 'offline.html', 'favicon.ico'], true);

if ($estatico) {
    $caminho = realpath($raiz . '/' . ltrim($uri, '/'));
    $dentro = $caminho !== false && str_starts_with($caminho, $raiz . DIRECTORY_SEPARATOR) && is_file($caminho);
    $ext = $dentro ? strtolower(pathinfo($caminho, PATHINFO_EXTENSION)) : '';
    if ($dentro && isset($permitidos[$ext])) {
        header('Content-Type: ' . $permitidos[$ext]);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('X-Content-Type-Options: nosniff');
        readfile($caminho);
        return true;
    }
    if ($dentro && $ext === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        readfile($caminho);
        return true;
    }
    http_response_code(404);
    echo 'Nao encontrado';
    return true;
}

require $raiz . '/index.php';
return true;
