<?php
/**
 * ============================================================
 * FarmaPonto - Log Model (Auditoria)
 * ============================================================
 * Responsabilidade: Registro append-only de acoes do sistema.
 */

namespace App\Models;

use App\Core\Database;

final class Log {

    public static function reg(?int $funcId, string $acao, ?string $entidade = null, $entId = null, array $det = []): void {
        try {
            $st = Database::pdo()->prepare(
                "INSERT INTO logs (funcionario_id, acao, entidade, entidade_id, detalhes, ip)
                 VALUES (?, ?, ?, ?, ?, INET6_ATON(?))"
            );
            $st->execute([
                $funcId,
                $acao,
                $entidade,
                $entId !== null ? (string) $entId : null,
                $det ? json_encode($det, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    public function listar(int $lim = 500, ?string $filtro = null): array {
        if ($filtro) {
            $st = Database::pdo()->prepare(
                "SELECT l.*, f.nome as funcionario_nome
                 FROM logs l
                 LEFT JOIN funcionarios f ON f.id = l.funcionario_id
                 WHERE f.nome LIKE ? OR l.acao LIKE ? OR l.entidade LIKE ?
                 ORDER BY l.id DESC LIMIT ?"
            );
            $f = "%$filtro%";
            $st->execute([$f, $f, $f, $lim]);
        } else {
            $st = Database::pdo()->prepare(
                "SELECT l.*, f.nome as funcionario_nome
                 FROM logs l
                 LEFT JOIN funcionarios f ON f.id = l.funcionario_id
                 ORDER BY l.id DESC LIMIT ?"
            );
            $st->execute([$lim]);
        }
        return $st->fetchAll();
    }

    public function listarComFiltros(?string $de = null, ?string $ate = null, ?int $funcId = null, ?string $acao = null, ?string $q = null, int $lim = 500): array {
        $where = [];
        $params = [];
        if ($de) { $where[] = 'DATE(l.criado_em) >= ?'; $params[] = $de; }
        if ($ate) { $where[] = 'DATE(l.criado_em) <= ?'; $params[] = $ate; }
        if ($funcId) { $where[] = 'l.funcionario_id = ?'; $params[] = $funcId; }
        if ($acao) { $where[] = 'l.acao = ?'; $params[] = $acao; }
        if ($q) {
            $where[] = '(f.nome LIKE ? OR l.acao LIKE ? OR l.entidade LIKE ? OR l.detalhes LIKE ?)';
            $w = "%$q%";
            $params = array_merge($params, [$w, $w, $w, $w]);
        }
        $sql = "SELECT l.*, f.nome as funcionario_nome FROM logs l LEFT JOIN funcionarios f ON f.id = l.funcionario_id";
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY l.id DESC LIMIT ?';
        $params[] = $lim;
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /**
     * Resume e purga o histórico de actividade antigo.
     *
     * Antes de apagar, guarda em `logs_resumo` quantas acções de cada tipo
     * houve em cada dia, para que o histórico longo continue a existir em
     * forma agregada (o disco não cresce para sempre e nada se perde de vista).
     *
     * @param int $dias Retenção detalhada (0 = guardar tudo para sempre).
     * @return int Linhas detalhadas removidas.
     */
    public static function resumirEPurgar(int $dias): int {
        if ($dias <= 0) { return 0; }
        $pdo = Database::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `logs_resumo` (
              `dia` DATE NOT NULL,
              `acao` VARCHAR(60) NOT NULL,
              `total` INT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (`dia`,`acao`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        return (int) Database::transacao(function ($pdo) use ($dias) {
            $st = $pdo->prepare(
                "INSERT INTO logs_resumo (dia, acao, total)
                 SELECT DATE(criado_em), acao, COUNT(*) FROM logs
                 WHERE criado_em < (NOW() - INTERVAL ? DAY)
                 GROUP BY DATE(criado_em), acao
                 ON DUPLICATE KEY UPDATE total = VALUES(total)"
            );
            $st->execute([$dias]);

            $del = $pdo->prepare("DELETE FROM logs WHERE criado_em < (NOW() - INTERVAL ? DAY) LIMIT 20000");
            $del->execute([$dias]);
            return $del->rowCount();
        });
    }

    /** @return array<int,array{dia:string,acao:string,total:int}> Resumo agregado. */
    public function resumo(int $limite = 500): array {
        if (!Database::temTabela('logs_resumo')) { return []; }
        $st = Database::pdo()->query(
            "SELECT dia, acao, total FROM logs_resumo ORDER BY dia DESC, total DESC LIMIT " . max(1, $limite)
        );
        return $st->fetchAll();
    }
}
