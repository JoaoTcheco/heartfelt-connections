<?php
/**
 * ============================================================
 * FarmaPonto - RelatorioModelo Model
 * ============================================================
 * Responsabilidade: Persistir "modelos de calculo" (presets de
 * filtros do ecra Relatorios & Salarios) para reutilizacao.
 *
 * Estrutura de dados:
 *   relatorio_modelos(id, nome, filtros JSON, criado_por, criado_em)
 *
 * O campo `filtros` guarda apenas chaves da whitelist (ver
 * RelatorioModelo::CHAVES) — nunca a query string crua — para que
 * o modelo continue valido mesmo que a UI evolua.
 */

namespace App\Models;

use App\Core\Database;

final class RelatorioModelo {

    /** Chaves de filtro permitidas num modelo (whitelist). */
    public const CHAVES = [
        'modo', 'mes', 'de', 'ate', 'base',
        'func_id', 'dias_mes', 'dias_semana',
        'contar_faltas', 'contar_atrasos', 'contar_saida_cedo',
    ];

    /** Normaliza um array de filtros (GET/POST) para persistencia. */
    public static function normalizar(array $input): array {
        $out = [];
        foreach (self::CHAVES as $k) {
            if (!isset($input[$k])) { continue; }
            $v = $input[$k];
            if (is_array($v)) {
                $v = array_values(array_filter(array_map(
                    static fn($x) => trim((string) $x),
                    $v
                ), static fn($x) => $x !== ''));
                if ($v) { $out[$k] = $v; }
            } else {
                $v = trim((string) $v);
                if ($v !== '') { $out[$k] = $v; }
            }
        }
        return $out;
    }

    /** Converte filtros guardados numa query string aplicavel a /relatorios. */
    public static function paraQueryString(array $filtros): string {
        return http_build_query(self::normalizar($filtros));
    }

    /**
     * Garante que o esquema da tabela está alinhado com o database.sql
     * (auto-migração idempotente para instalações criadas antes desta coluna).
     */
    private static function garantirEsquema(): void {
        static $feito = false;
        if ($feito) { return; }
        Database::garantirColuna(
            'relatorio_modelos',
            'atualizado_em',
            'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        );
        $feito = true;
    }

    /** @return array<int,array<string,mixed>> Todos os modelos, mais recentes primeiro. */
    public function listar(): array {
        self::garantirEsquema();
        $st = Database::pdo()->query(
            "SELECT m.id, m.nome, m.filtros, m.criado_por, m.criado_em, m.atualizado_em,
                    f.nome AS autor
             FROM relatorio_modelos m
             LEFT JOIN funcionarios f ON f.id = m.criado_por
             ORDER BY m.nome ASC"
        );
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['filtros_array'] = json_decode((string) $r['filtros'], true) ?: [];
            $r['query'] = self::paraQueryString($r['filtros_array']);
        }
        return $rows;
    }

    public function porId(int $id): ?array {
        $st = Database::pdo()->prepare("SELECT * FROM relatorio_modelos WHERE id = ?");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) { return null; }
        $r['filtros_array'] = json_decode((string) $r['filtros'], true) ?: [];
        $r['query'] = self::paraQueryString($r['filtros_array']);
        return $r;
    }

    /**
     * Cria ou atualiza (pelo nome) um modelo. Devolve o id.
     */
    public function guardar(string $nome, array $filtros, ?int $criadoPor): int {
        $nome = trim($nome);
        $json = json_encode(self::normalizar($filtros), JSON_UNESCAPED_UNICODE);

        $pdo = Database::pdo();
        $st = $pdo->prepare("SELECT id FROM relatorio_modelos WHERE nome = ?");
        $st->execute([$nome]);
        $existente = $st->fetch();

        if ($existente) {
            $up = $pdo->prepare("UPDATE relatorio_modelos SET filtros = ?, criado_por = ? WHERE id = ?");
            $up->execute([$json, $criadoPor, (int) $existente['id']]);
            return (int) $existente['id'];
        }

        $in = $pdo->prepare("INSERT INTO relatorio_modelos (nome, filtros, criado_por) VALUES (?, ?, ?)");
        $in->execute([$nome, $json, $criadoPor]);
        return (int) $pdo->lastInsertId();
    }

    public function eliminar(int $id): bool {
        $st = Database::pdo()->prepare("DELETE FROM relatorio_modelos WHERE id = ?");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }
}
