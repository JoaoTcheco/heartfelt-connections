<?php
/**
 * ============================================================
 * FarmaPonto - Resposta (formato unico de respostas JSON)
 * ============================================================
 * Responsabilidade: garantir que todos os pontos JSON do sistema
 * (incluindo /api/v1/*) devolvem sempre a mesma forma:
 *   sucesso -> {"ok":true, ...dados}
 *   falha   -> {"ok":false,"erro":"mensagem","ref":"ABCD1234"}
 * Comunica com: Controller::json(), controladores de API.
 */

namespace App\Core;

final class Resposta {

    public static function json(array $dados, int $codigo = 200): void {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            if (defined('PEDIDO_ID')) { header('X-Pedido-Id: ' . PEDIDO_ID); }
        }
        echo json_encode($dados, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok(array $dados = []): void {
        self::json(['ok' => true] + $dados, 200);
    }

    /**
     * Erro legivel. A formula das mensagens e sempre:
     * o que aconteceu + porque + o que fazer.
     */
    public static function erro(string $mensagem, int $codigo = 400, array $extra = []): void {
        $ref = PaginaErro::referencia();
        if ($codigo >= 500) { Registador::erro($mensagem, ['ref' => $ref], 'api'); }
        self::json(['ok' => false, 'erro' => $mensagem, 'ref' => $ref] + $extra, $codigo);
    }
}
