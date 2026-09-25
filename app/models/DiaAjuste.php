<?php
/**
 * ============================================================
 * FarmaPonto - DiaAjuste Model
 * ============================================================
 * Responsabilidade: excepcoes lancadas pelo gestor sobre um dia
 * concreto de um funcionario:
 *   - justificar uma falta (conta como presenca e tem vencimento)
 *   - marcar folga / licenca / ferias (com ou sem vencimento)
 *   - lancar horas extras nesse dia
 *
 * Comunica com: FuncionariosController (CRUD) e
 *               RelatoriosController (motor de calculo).
 *
 * Tabela: dia_ajustes (1 linha por funcionario+dia).
 */

namespace App\Models;

use App\Core\Database;

final class DiaAjuste {

    /** Tipos suportados => rotulo legivel. */
    public const TIPOS = [
        'falta_justificada' => 'Falta justificada',
        'folga'             => 'Folga',
        'licenca'           => 'Licenca',
        'ferias'            => 'Ferias',
        'normal'            => 'Dia normal',
    ];

    /** Vencimento por defeito de cada tipo (usado quando o gestor nao escolhe). */
    public const VENCIMENTO_PADRAO = [
        'falta_justificada' => 1,
        'folga'             => 0,
        'licenca'           => 0,
        'ferias'            => 1,
        'normal'            => 1,
    ];

    private static bool $verificado = false;

    /** Cria a tabela se ainda nao existir (auto-migracao idempotente). */
    public static function garantirTabela(): void {
        if (self::$verificado) { return; }
        self::$verificado = true;
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `dia_ajustes` (
               `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
               `funcionario_id` INT UNSIGNED NOT NULL,
               `dia`            DATE NOT NULL,
               `tipo`           ENUM('falta_justificada','folga','licenca','ferias','normal')
                                NOT NULL DEFAULT 'normal',
               `com_vencimento` TINYINT(1) NOT NULL DEFAULT 1,
               `horas_extra`    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
               `observacao`     VARCHAR(255) NULL,
               `criado_por`     INT UNSIGNED NULL,
               `criado_em`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
               `atualizado_em`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_dia_ajustes` (`funcionario_id`, `dia`),
               KEY `ix_dia_ajustes_dia` (`dia`),
               CONSTRAINT `fk_dia_ajustes_func` FOREIGN KEY (`funcionario_id`)
                 REFERENCES `funcionarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** Normaliza um tipo recebido do exterior. */
    public static function tipoValido(?string $tipo): string {
        $tipo = (string) $tipo;
        return isset(self::TIPOS[$tipo]) ? $tipo : 'normal';
    }

    /**
     * Cria ou actualiza o ajuste de um dia.
     * @return int id do ajuste
     */
    public function guardar(
        int $funcId,
        string $dia,
        string $tipo,
        bool $comVencimento,
        float $horasExtra = 0.0,
        ?string $obs = null,
        ?int $autorId = null
    ): int {
        self::garantirTabela();
        $tipo = self::tipoValido($tipo);
        $horasExtra = max(0.0, min(24.0, $horasExtra));

        $st = Database::pdo()->prepare(
            "INSERT INTO dia_ajustes (funcionario_id, dia, tipo, com_vencimento, horas_extra, observacao, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               tipo = VALUES(tipo),
               com_vencimento = VALUES(com_vencimento),
               horas_extra = VALUES(horas_extra),
               observacao = VALUES(observacao)"
        );
        $st->execute([
            $funcId, $dia, $tipo, $comVencimento ? 1 : 0,
            round($horasExtra, 2), $obs !== null ? mb_substr($obs, 0, 255) : null, $autorId,
        ]);

        $id = (int) Database::pdo()->lastInsertId();
        if ($id > 0) { return $id; }

        $q = Database::pdo()->prepare("SELECT id FROM dia_ajustes WHERE funcionario_id = ? AND dia = ? LIMIT 1");
        $q->execute([$funcId, $dia]);
        return (int) $q->fetchColumn();
    }

    /** Remove o ajuste de um dia. */
    public function remover(int $funcId, string $dia): int {
        self::garantirTabela();
        $st = Database::pdo()->prepare("DELETE FROM dia_ajustes WHERE funcionario_id = ? AND dia = ?");
        $st->execute([$funcId, $dia]);
        return $st->rowCount();
    }

    /**
     * Mapa [Y-m-d => ajuste] de um funcionario num intervalo.
     * Estrutura usada directamente pelo motor de calculo.
     */
    public function mapaIntervalo(int $funcId, string $de, string $ate): array {
        self::garantirTabela();
        $st = Database::pdo()->prepare(
            "SELECT dia, tipo, com_vencimento, horas_extra, observacao
             FROM dia_ajustes
             WHERE funcionario_id = ? AND dia BETWEEN ? AND ?"
        );
        $st->execute([$funcId, $de, $ate]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[$r['dia']] = [
                'tipo'           => (string) $r['tipo'],
                'com_vencimento' => (int) $r['com_vencimento'] === 1,
                'horas_extra'    => (float) $r['horas_extra'],
                'observacao'     => $r['observacao'] !== null ? (string) $r['observacao'] : '',
            ];
        }
        return $out;
    }

    /** Lista os ajustes de um mes (YYYY-MM) para apresentacao ao gestor. */
    public function listarMes(int $funcId, string $anoMes): array {
        self::garantirTabela();
        $de = $anoMes . '-01';
        $ate = date('Y-m-t', strtotime($de));
        $st = Database::pdo()->prepare(
            "SELECT id, dia, tipo, com_vencimento, horas_extra, observacao
             FROM dia_ajustes
             WHERE funcionario_id = ? AND dia BETWEEN ? AND ?
             ORDER BY dia"
        );
        $st->execute([$funcId, $de, $ate]);
        return array_map(static function (array $r): array {
            return [
                'id'             => (int) $r['id'],
                'dia'            => $r['dia'],
                'tipo'           => $r['tipo'],
                'tipo_label'     => self::TIPOS[$r['tipo']] ?? $r['tipo'],
                'com_vencimento' => (int) $r['com_vencimento'] === 1,
                'horas_extra'    => (float) $r['horas_extra'],
                'observacao'     => (string) ($r['observacao'] ?? ''),
            ];
        }, $st->fetchAll());
    }
}
