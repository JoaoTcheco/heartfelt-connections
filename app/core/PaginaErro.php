<?php
/**
 * ============================================================
 * FarmaPonto - PaginaErro
 * ============================================================
 * Responsabilidade: mostrar uma pagina de erro compreensivel ao
 * utilizador, com um codigo de referencia que aparece tambem nos
 * registos tecnicos (facilita o apoio).
 * Sem dependencias externas: HTML e CSS inline.
 */

namespace App\Core;

final class PaginaErro {

    /** Gera um codigo curto de referencia (8 caracteres). */
    public static function referencia(): string {
        try {
            return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } catch (\Throwable) {
            return strtoupper(substr(md5((string) microtime(true)), 0, 8));
        }
    }

    /**
     * Envia a pagina de erro e termina o pedido.
     *
     * @param string $titulo   O que aconteceu (linguagem simples)
     * @param string $detalhe  O que o utilizador pode fazer
     */
    public static function mostrar(string $titulo, string $detalhe, string $ref, int $codigo = 500): void {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: text/html; charset=utf-8');
        }
        $t = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($detalhe, ENT_QUOTES, 'UTF-8');
        $r = htmlspecialchars($ref, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="pt"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$t} · FarmaPonto</title>
<style>
 body{margin:0;font-family:system-ui,Arial,sans-serif;background:#f9fafb;color:#1f2937;
      display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
 .caixa{max-width:520px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:32px;text-align:center}
 h1{font-size:20px;margin:0 0 12px}
 p{margin:0 0 16px;line-height:1.6;color:#4b5563}
 code{display:inline-block;background:#f3f4f6;border-radius:8px;padding:6px 12px;font-size:13px;letter-spacing:1px}
 a{display:inline-block;margin-top:20px;background:#047857;color:#fff;text-decoration:none;
   padding:10px 20px;border-radius:10px;font-size:14px}
</style></head>
<body><main class="caixa">
 <h1>{$t}</h1>
 <p>{$d}</p>
 <p>Indique este código a quem administra o sistema:<br><code>{$r}</code></p>
 <a href="javascript:location.reload()">Tentar novamente</a>
</main></body></html>
HTML;
        exit;
    }
}
