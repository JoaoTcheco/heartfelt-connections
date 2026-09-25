<?php
/**
 * ============================================================
 * FarmaPonto - RateLimit Helper
 * ============================================================
 * Limitação de taxa por chave+IP usando a tabela rate_limits.
 */

namespace App\Helpers;

use App\Core\Database;
use App\Models\Config;

final class RateLimit {

    private static ?bool $bloqueioAtivo = null;

    /**
     * O bloqueio por IP esta DESLIGADO por omissao: em farmacias varios
     * funcionarios partilham o mesmo computador e uma falha de um nao pode
     * impedir os restantes de entrar ou marcar ponto.
     * Pode ser ligado em Configuracoes (chave 'bloqueio_tentativas' = '1').
     */
    public static function bloqueioAtivo(): bool {
        if (self::$bloqueioAtivo === null) {
            try {
                self::$bloqueioAtivo = (new Config())->get('bloqueio_tentativas', '0') === '1';
            } catch (\Throwable $e) {
                self::$bloqueioAtivo = false;
            }
        }
        return self::$bloqueioAtivo;
    }

    /**
     * Verifica e regista uma tentativa.
     * Devolve true se permitido, false se excedeu o limite.
     */
    public static function attempt(string $chave, int $maxTentativas = 5, int $janelaSegundos = 60): bool {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            $st = Database::pdo()->prepare(
                "SELECT COUNT(*) FROM rate_limits
                 WHERE chave = ? AND ip = INET6_ATON(?)
                 AND criado_em > (NOW() - INTERVAL ? SECOND)"
            );
            $st->execute([$chave, $ip, $janelaSegundos]);
            $count = (int) $st->fetchColumn();

            if ($count >= $maxTentativas && self::bloqueioAtivo()) {
                return false;
            }

            $st = Database::pdo()->prepare(
                "INSERT INTO rate_limits (chave, ip) VALUES (?, INET6_ATON(?))"
            );
            $st->execute([$chave, $ip]);

            // Limpeza ocasional (1% de probabilidade)
            if (random_int(1, 100) === 1) {
                Database::pdo()->exec("DELETE FROM rate_limits WHERE criado_em < (NOW() - INTERVAL 1 DAY)");
            }
            return true;
        } catch (\Throwable $e) {
            // Falha silenciosa: se a tabela não existir ainda, permite (degradação graciosa)
            error_log('[RateLimit] ' . $e->getMessage());
            return true;
        }
    }

    /** Limpa as tentativas desta chave para o IP actual (usar apos sucesso). */
    public static function limpar(string $chave): void {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            Database::pdo()->prepare("DELETE FROM rate_limits WHERE chave = ? AND ip = INET6_ATON(?)")
                ->execute([$chave, $ip]);
        } catch (\Throwable $e) {
            error_log('[RateLimit] ' . $e->getMessage());
        }
    }
}
