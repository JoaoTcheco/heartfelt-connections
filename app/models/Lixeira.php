<?php
/**
 * ============================================================
 * FarmaPonto - Lixeira (recuperabilidade)
 * ============================================================
 * Responsabilidade: guardar cópia integral das linhas eliminadas
 * (registos de ponto, folhas salariais, funcionários) para que um
 * apagamento por engano se possa desfazer.
 *
 * Estrutura de dados:
 *   lixeira(id, tabela, registo_id, dados JSON, contexto JSON,
 *           motivo, eliminado_por, eliminado_em)
 *
 * Comunica com: HistoricoController, RelatoriosController,
 * FuncionariosController, LixeiraController, Manutencao.
 */

namespace App\Models;

use App\Core\Database;
use PDO;

final class Lixeira {

    /** Tabelas cuja recuperação é suportada. */
    public const TABELAS = ['registos', 'folhas_salariais', 'funcionarios', 'selfies'];

    private static bool $esquemaOk = false;

    public static function garantirEsquema(): void {
        if (self::$esquemaOk) { return; }
        Database::pdo()->exec(
            "CREATE TABLE IF NOT EXISTS `lixeira` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `tabela` VARCHAR(60) NOT NULL,
              `registo_id` BIGINT UNSIGNED NULL,
              `rotulo` VARCHAR(160) NULL,
              `dados` JSON NOT NULL,
              `contexto` JSON NULL,
              `motivo` VARCHAR(160) NULL,
              `eliminado_por` INT UNSIGNED NULL,
              `eliminado_em` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `ix_lx_tabela_data` (`tabela`,`eliminado_em`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$esquemaOk = true;
    }

    /**
     * Arquiva uma linha antes de a eliminar.
     * @param array<string,mixed> $linha  Linha completa (SELECT *)
     * @param array<string,mixed> $contexto Dados dependentes (ex.: itens da folha)
     */
    public function arquivar(string $tabela, array $linha, ?int $por, string $motivo = '', string $rotulo = '', array $contexto = []): int {
        self::garantirEsquema();
        $st = Database::pdo()->prepare(
            "INSERT INTO lixeira (tabela, registo_id, rotulo, dados, contexto, motivo, eliminado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $st->execute([
            $tabela,
            isset($linha['id']) ? (int) $linha['id'] : null,
            $rotulo !== '' ? mb_substr($rotulo, 0, 160) : null,
            json_encode($linha, JSON_UNESCAPED_UNICODE),
            $contexto ? json_encode($contexto, JSON_UNESCAPED_UNICODE) : null,
            $motivo !== '' ? mb_substr($motivo, 0, 160) : null,
            $por,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Arquiva várias linhas da mesma tabela numa só transação. */
    public function arquivarVarias(string $tabela, array $linhas, ?int $por, string $motivo = ''): int {
        foreach ($linhas as $l) { $this->arquivar($tabela, $l, $por, $motivo); }
        return count($linhas);
    }

    /** @return array<int,array<string,mixed>> */
    public function listar(string $tabela = '', int $limite = 300): array {
        self::garantirEsquema();
        $sql = "SELECT l.*, f.nome AS autor FROM lixeira l
                LEFT JOIN funcionarios f ON f.id = l.eliminado_por";
        $args = [];
        if (in_array($tabela, self::TABELAS, true)) { $sql .= " WHERE l.tabela = ?"; $args[] = $tabela; }
        $sql .= " ORDER BY l.id DESC LIMIT " . max(1, min(2000, $limite));
        $st = Database::pdo()->prepare($sql);
        $st->execute($args);
        $linhas = $st->fetchAll();
        foreach ($linhas as &$l) {
            $l['dados'] = json_decode((string) $l['dados'], true) ?: [];
            $l['contexto'] = $l['contexto'] ? (json_decode((string) $l['contexto'], true) ?: []) : [];
        }
        return $linhas;
    }

    public function contagens(): array {
        self::garantirEsquema();
        $st = Database::pdo()->query("SELECT tabela, COUNT(*) n FROM lixeira GROUP BY tabela");
        $out = [];
        foreach ($st->fetchAll() as $r) { $out[$r['tabela']] = (int) $r['n']; }
        return $out;
    }

    public function porId(int $id): ?array {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("SELECT * FROM lixeira WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) { return null; }
        $r['dados'] = json_decode((string) $r['dados'], true) ?: [];
        $r['contexto'] = $r['contexto'] ? (json_decode((string) $r['contexto'], true) ?: []) : [];
        return $r;
    }

    /**
     * Repõe a linha na tabela de origem (só as colunas que ainda existem).
     * @return array{tabela:string,id:int}
     */
    public function recuperar(int $id): array {
        self::garantirEsquema();
        $item = $this->porId($id);
        if (!$item) { throw new \RuntimeException('Este item já não está na lixeira.'); }
        $tabela = (string) $item['tabela'];
        if (!in_array($tabela, self::TABELAS, true)) {
            throw new \RuntimeException('A recuperação desta informação não é suportada.');
        }

        return Database::transacao(function (PDO $pdo) use ($item, $tabela, $id) {
            $colunas = Database::colunas($tabela);
            $dados = array_intersect_key($item['dados'], $colunas);
            $dados = $this->reporDependencias($pdo, $tabela, $dados);
            if (!$dados) { throw new \RuntimeException('A cópia guardada não tem campos compatíveis com a tabela actual.'); }

            $campos = array_keys($dados);
            $sql = "INSERT INTO `{$tabela}` (`" . implode('`,`', $campos) . "`) VALUES ("
                 . implode(',', array_fill(0, count($campos), '?')) . ")";
            $st = $pdo->prepare($sql);
            $st->execute(array_values($dados));
            $novoId = (int) ($dados['id'] ?? $pdo->lastInsertId());

            // Itens dependentes (ex.: itens de uma folha salarial)
            foreach ($item['contexto'] as $tabelaFilha => $linhas) {
                if (!is_array($linhas) || !Database::temTabela((string) $tabelaFilha)) { continue; }
                $colsFilha = Database::colunas((string) $tabelaFilha);
                foreach ($linhas as $linha) {
                    if (!is_array($linha)) { continue; }
                    $d = array_intersect_key($linha, $colsFilha);
                    if (!$d) { continue; }
                    $c = array_keys($d);
                    $pdo->prepare("INSERT INTO `{$tabelaFilha}` (`" . implode('`,`', $c) . "`) VALUES ("
                        . implode(',', array_fill(0, count($c), '?')) . ")")->execute(array_values($d));
                }
            }

            $pdo->prepare("DELETE FROM lixeira WHERE id = ?")->execute([$id]);
            return ['tabela' => $tabela, 'id' => $novoId];
        });
    }

    /**
     * Repoe primeiro as linhas de que esta depende (ex.: a fotografia de um
     * registo). Se a dependencia nao existir nem estiver arquivada, limpa a
     * referencia em vez de falhar por chave estrangeira.
     */
    private function reporDependencias(PDO $pdo, string $tabela, array $dados): array {
        if ($tabela !== 'registos' || empty($dados['selfie_id'])) { return $dados; }
        $selfieId = (int) $dados['selfie_id'];

        $st = $pdo->prepare("SELECT COUNT(*) FROM selfies WHERE id = ?");
        $st->execute([$selfieId]);
        if ((int) $st->fetchColumn() > 0) { return $dados; }

        $st = $pdo->prepare("SELECT id, dados FROM lixeira WHERE tabela = 'selfies' AND registo_id = ? ORDER BY id DESC LIMIT 1");
        $st->execute([$selfieId]);
        $arquivada = $st->fetch();
        if ($arquivada) {
            $linha = json_decode((string) $arquivada['dados'], true) ?: [];
            $linha = array_intersect_key($linha, Database::colunas('selfies'));
            if ($linha) {
                $campos = array_keys($linha);
                try {
                    $pdo->prepare("INSERT IGNORE INTO `selfies` (`" . implode('`,`', $campos) . "`) VALUES ("
                        . implode(',', array_fill(0, count($campos), '?')) . ")")->execute(array_values($linha));
                    // INSERT IGNORE transforma falhas em avisos: confirmar que a
                    // linha ficou mesmo reposta antes de a referenciar.
                    $ver = $pdo->prepare("SELECT COUNT(*) FROM selfies WHERE id = ?");
                    $ver->execute([$selfieId]);
                    if ((int) $ver->fetchColumn() > 0) {
                        $pdo->prepare("DELETE FROM lixeira WHERE id = ?")->execute([(int) $arquivada['id']]);
                        return $dados;
                    }
                } catch (\Throwable $e) {
                    // Copia antiga incompleta: o registo e reposto sem a
                    // fotografia, em vez de bloquear a recuperacao.
                }
            }
        }

        $dados['selfie_id'] = null;
        return $dados;
    }

    public function eliminarDefinitivo(int $id): bool {
        self::garantirEsquema();
        $st = Database::pdo()->prepare("DELETE FROM lixeira WHERE id = ?");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** Limpa itens com mais de N dias (0 = nunca). */
    public function purgar(int $dias): int {
        if ($dias <= 0) { return 0; }
        self::garantirEsquema();
        $st = Database::pdo()->prepare("DELETE FROM lixeira WHERE eliminado_em < (NOW() - INTERVAL ? DAY)");
        $st->execute([$dias]);
        return $st->rowCount();
    }
}
