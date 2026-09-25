<?php
/**
 * ============================================================
 * FarmaPonto - FolhaSalarial Model
 * ============================================================
 * Responsabilidade: guardar o "fecho" (snapshot imutavel) de um
 * calculo de salarios, para historico e auditoria.
 *
 * IMPORTANTE: fechar uma folha NAO bloqueia o motor de calculo.
 * O ecra /relatorios continua a recalcular sempre que o utilizador
 * quiser; a folha e apenas uma fotografia do que foi aprovado.
 *
 * Estrutura de dados:
 *   folhas_salariais(id, referencia, periodo_de, periodo_ate, base,
 *                    filtros JSON, totais JSON, estado, observacao,
 *                    criado_por, criado_em)
 *   folha_salarial_itens(id, folha_id, funcionario_id, nome, cargo,
 *                        salario, total_corte, liquido, dados JSON)
 *
 * Comunica com: RelatoriosController (fecho/consulta), Log (auditoria).
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class FolhaSalarial {

    /** Campos do resumo guardados como colunas indexaveis. */
    private const COLUNAS_ITEM = ['salario', 'total_corte', 'liquido'];

    /**
     * Fecha (persiste) uma folha a partir do resultado do motor de calculo.
     *
     * @param array  $calculo  Resultado de RelatoriosController::calcular()
     * @param string $observacao
     * @param int|null $userId
     * @return int Id da folha criada
     */
    /**
     * Auto-migracao idempotente: garante que as tabelas existem em
     * instalacoes criadas antes desta funcionalidade (o DDL e o mesmo
     * de database.sql, que continua a ser a fonte unica de verdade).
     */
    private static function garantirEsquema(): void {
        static $feito = false;
        if ($feito) { return; }
        $pdo = Database::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `folhas_salariais` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `referencia` VARCHAR(20) NOT NULL,
              `periodo_de` DATE NOT NULL,
              `periodo_ate` DATE NOT NULL,
              `base` VARCHAR(20) NOT NULL DEFAULT 'mes_completo',
              `filtros` JSON NOT NULL,
              `totais` JSON NOT NULL,
              `estado` ENUM('fechada','reaberta') NOT NULL DEFAULT 'fechada',
              `observacao` VARCHAR(255) NULL,
              `criado_por` INT UNSIGNED NULL,
              `criado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_fs_ref` (`referencia`),
              KEY `ix_fs_periodo` (`periodo_de`,`periodo_ate`),
              KEY `ix_fs_autor` (`criado_por`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `folha_salarial_itens` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `folha_id` INT UNSIGNED NOT NULL,
              `funcionario_id` INT UNSIGNED NOT NULL,
              `nome` VARCHAR(120) NOT NULL,
              `cargo` VARCHAR(80) NULL,
              `salario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `total_corte` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `liquido` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `dados` JSON NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_fsi_folha_func` (`folha_id`,`funcionario_id`),
              KEY `ix_fsi_func` (`funcionario_id`),
              CONSTRAINT `fk_fsi_folha` FOREIGN KEY (`folha_id`)
                REFERENCES `folhas_salariais` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $feito = true;
    }

    public function fechar(array $calculo, string $observacao, ?int $userId): int {
        self::garantirEsquema();
        $linhas = $calculo['linhas'] ?? [];

        $totais = [
            'funcionarios'  => count($linhas),
            'salario'       => round(array_sum(array_column($linhas, 'salario')), 2),
            'total_corte'   => round(array_sum(array_column($linhas, 'total_corte')), 2),
            'ganho_extra'   => round(array_sum(array_column($linhas, 'ganho_extra')), 2),
            'liquido'       => round(array_sum(array_column($linhas, 'liquido')), 2),
            'moeda'         => $calculo['moeda'] ?? 'MZN',
        ];

        $filtros = [
            'modo'              => $calculo['modo'] ?? 'mes',
            'base'              => $calculo['base'] ?? 'mes_completo',
            'dia_corte'         => $calculo['diaCorte'] ?? 0,
            'dias_mes'          => $calculo['filtroDiasMes'] ?? [],
            'dias_semana'       => $calculo['filtroDiasSem'] ?? [],
            'func_id'           => $calculo['filtroFuncs'] ?? [],
            'contar_faltas'     => (bool) ($calculo['contarFaltas'] ?? true),
            'contar_atrasos'    => (bool) ($calculo['contarAtrasos'] ?? true),
            'contar_saida_cedo' => (bool) ($calculo['contarSaidaCedo'] ?? false),
            'contar_extras'     => (bool) ($calculo['contarExtras'] ?? true),
            'pagar_futuros'     => (bool) ($calculo['pagarFuturos'] ?? false),
            'tolerancia'        => $calculo['tolerancia'] ?? 0,
        ];

        return (int) Database::tx(function (PDO $pdo) use ($calculo, $linhas, $totais, $filtros, $observacao, $userId) {
            $st = $pdo->prepare(
                "INSERT INTO folhas_salariais
                   (referencia, periodo_de, periodo_ate, base, filtros, totais, estado, observacao, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, 'fechada', ?, ?)"
            );
            $referencia = strtoupper(substr(md5($calculo['de'] . $calculo['ate'] . microtime(true)), 0, 10));
            $st->execute([
                $referencia,
                $calculo['de'],
                $calculo['ate'],
                (string) ($calculo['base'] ?? 'mes_completo'),
                json_encode($filtros, JSON_UNESCAPED_UNICODE),
                json_encode($totais, JSON_UNESCAPED_UNICODE),
                $observacao !== '' ? $observacao : null,
                $userId ?: null,
            ]);
            $folhaId = (int) $pdo->lastInsertId();

            $stItem = $pdo->prepare(
                "INSERT INTO folha_salarial_itens
                   (folha_id, funcionario_id, nome, cargo, salario, total_corte, liquido, dados)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($linhas as $l) {
                $dados = $l;
                unset($dados['detalhe']); // o detalhe diario e recalculavel a pedido
                $stItem->execute([
                    $folhaId,
                    (int) $l['id'],
                    (string) $l['nome'],
                    (string) ($l['cargo'] ?? ''),
                    (float) $l['salario'],
                    (float) $l['total_corte'],
                    (float) $l['liquido'],
                    json_encode($dados, JSON_UNESCAPED_UNICODE),
                ]);
            }

            return $folhaId;
        });
    }

    /** @return array<int,array<string,mixed>> Folhas mais recentes primeiro. */
    public function listar(int $limite = 100): array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare(
            "SELECT f.*, u.nome AS autor,
                    (SELECT COUNT(*) FROM folha_salarial_itens i WHERE i.folha_id = f.id) AS n_itens
             FROM folhas_salariais f
             LEFT JOIN funcionarios u ON u.id = f.criado_por
             ORDER BY f.criado_em DESC
             LIMIT " . max(1, $limite)
        );
        $st->execute();
        return array_map([$this, 'hidratar'], $st->fetchAll());
    }

    /** @return array<string,mixed>|null */
    public function porId(int $id): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare(
            "SELECT f.*, u.nome AS autor
             FROM folhas_salariais f
             LEFT JOIN funcionarios u ON u.id = f.criado_por
             WHERE f.id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? $this->hidratar($row) : null;
    }

    /** @return array<int,array<string,mixed>> Itens (funcionarios) da folha. */
    public function itens(int $folhaId): array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare(
            "SELECT * FROM folha_salarial_itens WHERE folha_id = ? ORDER BY nome ASC"
        );
        $st->execute([$folhaId]);
        $itens = $st->fetchAll();
        foreach ($itens as &$i) {
            $i['dados'] = json_decode((string) $i['dados'], true) ?: [];
        }
        return $itens;
    }

    /** Reabre a folha (permite novo fecho para o mesmo periodo). */
    public function reabrir(int $id): void {
        $st = Database::pdo()->prepare("UPDATE folhas_salariais SET estado = 'reaberta' WHERE id = ?");
        $st->execute([$id]);
    }

    /** Elimina uma folha (os itens caem por ON DELETE CASCADE). */
    public function eliminar(int $id): void {
        $st = Database::pdo()->prepare("DELETE FROM folhas_salariais WHERE id = ?");
        $st->execute([$id]);
    }

    /** Converte a query string dos filtros guardados para reaplicar em /relatorios. */
    public static function paraQueryString(array $folha): string {
        $f = $folha['filtros'] ?? [];
        $qs = [
            'modo' => 'intervalo',
            'de'   => $folha['periodo_de'],
            'ate'  => $folha['periodo_ate'],
            'base' => $f['base'] ?? 'mes_completo',
            'contar_faltas'     => !empty($f['contar_faltas']) ? '1' : '0',
            'contar_atrasos'    => !empty($f['contar_atrasos']) ? '1' : '0',
            'contar_saida_cedo' => !empty($f['contar_saida_cedo']) ? '1' : '0',
            'contar_extras'     => !empty($f['contar_extras']) ? '1' : '0',
            'pagar_futuros'     => !empty($f['pagar_futuros']) ? '1' : '0',
        ];
        if (!empty($f['dia_corte']))   { $qs['dia_corte']   = $f['dia_corte']; }
        if (!empty($f['dias_mes']))    { $qs['dias_mes']    = implode(',', $f['dias_mes']); }
        if (!empty($f['dias_semana'])) { $qs['dias_semana'] = implode(',', $f['dias_semana']); }
        if (!empty($f['func_id']))     { $qs['func_id']     = implode(',', $f['func_id']); }
        return http_build_query($qs);
    }

    /** Descodifica os campos JSON de uma linha. */
    private function hidratar(array $row): array {
        $row['filtros'] = json_decode((string) $row['filtros'], true) ?: [];
        $row['totais']  = json_decode((string) $row['totais'], true) ?: [];
        return $row;
    }
}
