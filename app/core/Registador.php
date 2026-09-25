<?php
/**
 * ============================================================
 * FarmaPonto - Registador (observabilidade)
 * ============================================================
 * Responsabilidade: registar eventos tecnicos (info/aviso/erro) e
 * metricas de desempenho numa tabela propria, para a pagina
 * /diagnostico. Cada linha traz o PEDIDO_ID, o que permite ligar
 * um erro visto pelo utilizador ao pedido que o causou.
 *
 * Comunica com: index.php (handlers globais), DiagnosticoController,
 * qualquer controlador que queira deixar rasto.
 * Nunca lanca excecoes: falhar a registar nunca pode derrubar o pedido.
 */

namespace App\Core;

use PDO;
use Throwable;

final class Registador {

    public const NIVEIS = ['info', 'aviso', 'erro'];

    private static bool $esquemaOk = false;

    private static function garantirEsquema(): bool {
        if (self::$esquemaOk) { return true; }
        try {
            $pdo = Database::pdo();
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `sistema_eventos` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `pedido_id` VARCHAR(16) NULL,
                  `nivel` ENUM('info','aviso','erro') NOT NULL DEFAULT 'info',
                  `canal` VARCHAR(40) NOT NULL DEFAULT 'app',
                  `mensagem` VARCHAR(500) NOT NULL,
                  `contexto` JSON NULL,
                  `metodo` VARCHAR(10) NULL,
                  `url` VARCHAR(255) NULL,
                  `utilizador_id` INT UNSIGNED NULL,
                  `ip` VARCHAR(45) NULL,
                  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `ix_se_nivel_data` (`nivel`,`criado_em`),
                  KEY `ix_se_pedido` (`pedido_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS `sistema_metricas` (
                  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  `rota` VARCHAR(120) NOT NULL,
                  `metodo` VARCHAR(10) NOT NULL DEFAULT 'GET',
                  `duracao_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                  `memoria_kb` INT UNSIGNED NOT NULL DEFAULT 0,
                  `codigo` SMALLINT UNSIGNED NOT NULL DEFAULT 200,
                  `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `ix_sm_rota_data` (`rota`,`criado_em`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            self::$esquemaOk = true;
        } catch (Throwable $e) {
            error_log('[FarmaPonto] Registador esquema: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    public static function info(string $mensagem, array $contexto = [], string $canal = 'app'): void {
        self::registar('info', $mensagem, $contexto, $canal);
    }

    public static function aviso(string $mensagem, array $contexto = [], string $canal = 'app'): void {
        self::registar('aviso', $mensagem, $contexto, $canal);
    }

    public static function erro(string $mensagem, array $contexto = [], string $canal = 'app'): void {
        self::registar('erro', $mensagem, $contexto, $canal);
    }

    /** Registo estruturado de uma excecao/erro fatal. */
    public static function excecao(Throwable $e, string $ref = '', string $canal = 'excecao'): void {
        self::registar('erro', substr($e->getMessage(), 0, 500), [
            'ref'      => $ref,
            'tipo'     => get_class($e),
            'ficheiro' => str_replace(ROOT_PATH, '', $e->getFile()) . ':' . $e->getLine(),
            'pilha'    => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
        ], $canal);
    }

    private static function registar(string $nivel, string $mensagem, array $contexto, string $canal): void {
        $pedido = defined('PEDIDO_ID') ? PEDIDO_ID : null;
        error_log(sprintf('[FarmaPonto][%s][%s][%s] %s %s',
            $pedido ?? '-', strtoupper($nivel), $canal, $mensagem,
            $contexto ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : ''
        ));
        if (!self::garantirEsquema()) { return; }
        try {
            $st = Database::pdo()->prepare(
                "INSERT INTO sistema_eventos
                   (pedido_id, nivel, canal, mensagem, contexto, metodo, url, utilizador_id, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $st->execute([
                $pedido,
                in_array($nivel, self::NIVEIS, true) ? $nivel : 'info',
                substr($canal, 0, 40),
                substr($mensagem, 0, 500),
                $contexto ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : null,
                substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI'), 0, 10),
                substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
                isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]);
        } catch (Throwable $e) {
            error_log('[FarmaPonto] Registador insert: ' . $e->getMessage());
        }
    }

    /** Guarda uma amostra de desempenho do pedido actual. */
    public static function metrica(string $rota, float $duracaoMs, int $codigo = 200): void {
        if (!self::garantirEsquema()) { return; }
        try {
            $st = Database::pdo()->prepare(
                "INSERT INTO sistema_metricas (rota, metodo, duracao_ms, memoria_kb, codigo)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $st->execute([
                substr($rota, 0, 120),
                substr((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 0, 10),
                (int) round($duracaoMs),
                (int) round(memory_get_peak_usage(true) / 1024),
                $codigo,
            ]);
        } catch (Throwable $e) {
            error_log('[FarmaPonto] Registador metrica: ' . $e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>> Eventos mais recentes primeiro. */
    public static function eventos(string $nivel = '', int $limite = 200): array {
        if (!self::garantirEsquema()) { return []; }
        $sql = "SELECT e.*, f.nome AS utilizador FROM sistema_eventos e
                LEFT JOIN funcionarios f ON f.id = e.utilizador_id";
        $args = [];
        if (in_array($nivel, self::NIVEIS, true)) { $sql .= " WHERE e.nivel = ?"; $args[] = $nivel; }
        $sql .= " ORDER BY e.id DESC LIMIT " . max(1, min(1000, $limite));
        $st = Database::pdo()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** Contagem de eventos por nivel nas ultimas N horas. */
    public static function contagens(int $horas = 24): array {
        if (!self::garantirEsquema()) { return []; }
        $st = Database::pdo()->prepare(
            "SELECT nivel, COUNT(*) AS n FROM sistema_eventos
             WHERE criado_em >= (NOW() - INTERVAL ? HOUR) GROUP BY nivel"
        );
        $st->execute([max(1, $horas)]);
        $out = ['info' => 0, 'aviso' => 0, 'erro' => 0];
        foreach ($st->fetchAll() as $r) { $out[$r['nivel']] = (int) $r['n']; }
        return $out;
    }

    /** Desempenho agregado por rota (para os graficos do Diagnostico). */
    public static function desempenho(int $dias = 7, int $limite = 15): array {
        if (!self::garantirEsquema()) { return []; }
        $st = Database::pdo()->prepare(
            "SELECT rota, COUNT(*) AS pedidos,
                    ROUND(AVG(duracao_ms),1) AS media_ms,
                    MAX(duracao_ms) AS max_ms,
                    ROUND(AVG(memoria_kb)) AS memoria_kb
             FROM sistema_metricas
             WHERE criado_em >= (NOW() - INTERVAL ? DAY)
             GROUP BY rota ORDER BY media_ms DESC LIMIT " . max(1, $limite)
        );
        $st->execute([max(1, $dias)]);
        return $st->fetchAll();
    }

    /** Apaga eventos/metricas mais antigos do que N dias. */
    public static function purgar(int $dias): int {
        if ($dias <= 0 || !self::garantirEsquema()) { return 0; }
        $pdo = Database::pdo();
        $n = 0;
        foreach (['sistema_eventos', 'sistema_metricas'] as $t) {
            $st = $pdo->prepare("DELETE FROM `{$t}` WHERE criado_em < (NOW() - INTERVAL ? DAY)");
            $st->execute([$dias]);
            $n += $st->rowCount();
        }
        return $n;
    }
}
