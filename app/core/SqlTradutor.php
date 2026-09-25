<?php
/**
 * ============================================================
 * FarmaPonto - SqlTradutor
 * ============================================================
 * Responsabilidade: traduzir o SQL escrito para MySQL/MariaDB em
 * SQL valido para SQLite, sem tocar nos Models nem nos Controllers.
 * Comunica com: App\Core\PdoSqlite (unico consumidor em execucao) e
 * com o gerador de esquema (scripts/gerar-esquema-sqlite.php).
 * Portabilidade: PHP puro, sem dependencias externas.
 *
 * Contrato: traduzir() devolve SEMPRE uma lista de instrucoes, porque
 * uma instrucao MySQL (ex.: CREATE TABLE com KEY, ou ALTER TABLE com
 * varios ADD COLUMN) pode corresponder a varias instrucoes SQLite.
 */

namespace App\Core;

final class SqlTradutor {

    /**
     * @param callable|null $resolverUnico fn(string $tabela, array $colunas): array
     *        devolve as colunas do indice unico a usar no ON CONFLICT.
     * @return string[] lista de instrucoes SQLite
     */
    public static function traduzir(string $sql, ?callable $resolverUnico = null): array {
        $sql = trim($sql);
        if ($sql === '') { return []; }

        // 1. Identificadores: `x` -> "x"  (as strings usam apenas plicas)
        $sql = str_replace('`', '"', $sql);

        // 2. Instrucoes proprias do MySQL que tem equivalente directo
        if (preg_match('/^SHOW\s+TABLES/i', $sql)) {
            return ["SELECT name AS \"Tables_in_db\" FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"];
        }
        if (preg_match('/^SHOW\s+COLUMNS\s+FROM\s+"?([A-Za-z0-9_]+)"?(?:\s+LIKE\s+(\'[^\']*\'))?/i', $sql, $m)) {
            $t = $m[1];
            $filtro = isset($m[2]) ? " AND name LIKE {$m[2]}" : '';
            return ["SELECT name AS \"Field\", type AS \"Type\", CASE \"notnull\" WHEN 1 THEN 'NO' ELSE 'YES' END AS \"Null\", dflt_value AS \"Default\" FROM pragma_table_info('{$t}') WHERE 1=1{$filtro}"];
        }

        // 3. CREATE TABLE precisa de tratamento estrutural
        if (preg_match('/^CREATE\s+TABLE/i', $sql)) {
            return self::criarTabela($sql);
        }
        if (preg_match('/^ALTER\s+TABLE/i', $sql)) {
            return self::alterarTabela($sql);
        }

        return [self::expressoes($sql, $resolverUnico)];
    }

    /** Traducao de expressoes e sintaxe dentro de DML (SELECT/INSERT/UPDATE/DELETE). */
    private static function expressoes(string $sql, ?callable $resolverUnico = null): string {
        // NOW() - INTERVAL ? DAY  /  INTERVAL 7 DAY -> UDF MYSQL_INTERVAL
        $sql = preg_replace_callback(
            '/([A-Z_]+\([^()]*\)|"?[A-Za-z0-9_]+"?)\s*([+-])\s*INTERVAL\s+(\?|\d+)\s+(MICROSECOND|SECOND|MINUTE|HOUR|DAY|WEEK|MONTH|YEAR)/i',
            static function (array $m): string {
                $sinal = $m[2] === '-' ? '-1' : '1';
                return "MYSQL_INTERVAL({$m[1]}, ({$m[3]}) * {$sinal}, '" . strtoupper($m[4]) . "')";
            },
            $sql
        );
        // DATE_ADD/DATE_SUB(x, INTERVAL n UNIT)
        $sql = preg_replace_callback(
            '/DATE_(ADD|SUB)\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(\?|\d+)\s+([A-Z]+)\s*\)/i',
            static function (array $m): string {
                $sinal = strtoupper($m[1]) === 'SUB' ? '-1' : '1';
                return "MYSQL_INTERVAL({$m[2]}, ({$m[3]}) * {$sinal}, '" . strtoupper($m[4]) . "')";
            },
            $sql
        );

        // INSERT IGNORE / REPLACE INTO
        $sql = preg_replace('/^INSERT\s+IGNORE\s+INTO/i', 'INSERT OR IGNORE INTO', $sql);
        $sql = preg_replace('/^REPLACE\s+INTO/i', 'INSERT OR REPLACE INTO', $sql);

        // ON DUPLICATE KEY UPDATE -> ON CONFLICT(...) DO UPDATE SET
        if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql)) {
            $sql = self::upsert($sql, $resolverUnico);
        }

        // DELETE/UPDATE ... LIMIT n  (SQLite padrao nao suporta)
        if (preg_match('/^(DELETE|UPDATE)\b/i', $sql)) {
            $sql = preg_replace('/\s+LIMIT\s+\d+\s*$/i', '', $sql);
        }

        // VERSION() / DATABASE() ficam como UDF (registadas em PdoSqlite)
        // STRAIGHT_JOIN, SQL_CALC_FOUND_ROWS, FOR UPDATE: nao existem
        $sql = preg_replace('/\bSTRAIGHT_JOIN\b/i', 'JOIN', $sql);
        $sql = preg_replace('/\bSQL_CALC_FOUND_ROWS\b/i', '', $sql);
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);
        $sql = preg_replace('/\s+LOCK\s+IN\s+SHARE\s+MODE\b/i', '', $sql);
        // BINARY / COLLATE utf8mb4_*
        $sql = preg_replace('/\bCOLLATE\s+utf8[a-z0-9_]*/i', '', $sql);
        $sql = preg_replace('/\bBINARY\s+/i', '', $sql);

        return trim($sql);
    }

    /** Constroi o ON CONFLICT a partir dos indices unicos reais da tabela. */
    private static function upsert(string $sql, ?callable $resolverUnico): string {
        preg_match('/^INSERT(?:\s+OR\s+\w+)?\s+INTO\s+"?([A-Za-z0-9_]+)"?\s*\(([^)]*)\)/i', $sql, $m);
        $tabela  = $m[1] ?? '';
        $colunas = [];
        foreach (explode(',', $m[2] ?? '') as $c) {
            $c = trim(str_replace('"', '', $c));
            if ($c !== '') { $colunas[] = $c; }
        }

        $parts = preg_split('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql, 2);
        $head  = rtrim($parts[0]);
        $set   = trim($parts[1] ?? '');

        // VALUES(col) -> excluded."col"
        $set = preg_replace_callback(
            '/VALUES\s*\(\s*"?([A-Za-z0-9_]+)"?\s*\)/i',
            static fn(array $mm): string => 'excluded."' . $mm[1] . '"',
            $set
        );

        $alvo = $resolverUnico ? $resolverUnico($tabela, $colunas) : [];
        if (!$alvo) {
            // Sem indice unico conhecido: substituicao total e a semantica
            // mais proxima e nunca perde a escrita.
            return preg_replace('/^INSERT\s+INTO/i', 'INSERT OR REPLACE INTO', $head);
        }
        $destino = '"' . implode('", "', $alvo) . '"';

        return $head . " ON CONFLICT({$destino}) DO UPDATE SET " . $set;
    }

    /** CREATE TABLE MySQL -> CREATE TABLE + CREATE INDEX + TRIGGER de actualizacao. */
    private static function criarTabela(string $sql): array {
        preg_match('/^CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?"?([A-Za-z0-9_]+)"?\s*\((.*)\)[^)]*$/is', $sql, $m);
        if (!$m) { return [self::tipos($sql)]; }
        $seNao  = $m[1] ? 'IF NOT EXISTS ' : '';
        $tabela = $m[2];
        $corpo  = $m[3];

        $linhas = self::dividirTopo($corpo);
        $cols = [];
        $extra = [];
        $triggers = [];
        $pkExplicita = null;
        $temAutoIncremento = false;

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if ($linha === '') { continue; }

            if (preg_match('/^PRIMARY\s+KEY\s*\((.+)\)$/i', $linha, $mm)) {
                $pkExplicita = $mm[1];
                continue;
            }
            if (preg_match('/^(UNIQUE\s+)?(?:KEY|INDEX)\s+"?([A-Za-z0-9_]+)"?\s*\((.+)\)$/i', $linha, $mm)) {
                $unico = trim($mm[1] ?? '') !== '' ? 'UNIQUE ' : '';
                $nome  = $mm[2];
                $cols2 = preg_replace('/\(\d+\)/', '', $mm[3]); // prefixos de indice
                $extra[] = "CREATE {$unico}INDEX IF NOT EXISTS \"{$nome}\" ON \"{$tabela}\" ({$cols2})";
                continue;
            }
            if (preg_match('/^CONSTRAINT\s+"?[A-Za-z0-9_]+"?\s+FOREIGN\s+KEY/i', $linha)) {
                $cols[] = preg_replace('/^CONSTRAINT\s+"?[A-Za-z0-9_]+"?\s+/i', '', $linha);
                continue;
            }
            if (preg_match('/^(FOREIGN\s+KEY|FULLTEXT|SPATIAL)/i', $linha)) {
                if (stripos($linha, 'FOREIGN') === 0) { $cols[] = $linha; }
                continue;
            }

            // Coluna
            preg_match('/^"?([A-Za-z0-9_]+)"?\s+(.*)$/s', $linha, $mc);
            $nomeCol = $mc[1] ?? '';
            $def     = $mc[2] ?? '';

            if (preg_match('/\bAUTO_INCREMENT\b/i', $def)) {
                $temAutoIncremento = true;
                $cols[] = "\"{$nomeCol}\" INTEGER PRIMARY KEY AUTOINCREMENT";
                continue;
            }
            if (preg_match('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP\b/i', $def)) {
                $triggers[] = "CREATE TRIGGER IF NOT EXISTS \"trg_{$tabela}_{$nomeCol}\" AFTER UPDATE ON \"{$tabela}\" FOR EACH ROW BEGIN UPDATE \"{$tabela}\" SET \"{$nomeCol}\" = MYSQL_NOW() WHERE rowid = NEW.rowid; END";
            }
            $cols[] = "\"{$nomeCol}\" " . self::tipos($def);
        }

        if ($pkExplicita !== null && !$temAutoIncremento) {
            $cols[] = 'PRIMARY KEY (' . $pkExplicita . ')';
        }

        $out = ["CREATE TABLE {$seNao}\"{$tabela}\" (\n  " . implode(",\n  ", $cols) . "\n)"];
        return array_merge($out, $extra, $triggers);
    }

    /** ALTER TABLE MySQL (varias clausulas) -> varias instrucoes SQLite. */
    private static function alterarTabela(string $sql): array {
        preg_match('/^ALTER\s+TABLE\s+"?([A-Za-z0-9_]+)"?\s+(.*)$/is', $sql, $m);
        if (!$m) { return [$sql]; }
        $tabela = $m[1];
        $out = [];
        foreach (self::dividirTopo($m[2]) as $clausula) {
            $c = trim($clausula);
            if ($c === '') { continue; }
            $c = preg_replace('/\s+AFTER\s+"?[A-Za-z0-9_]+"?\s*$/i', '', $c);
            $c = preg_replace('/\s+FIRST\s*$/i', '', $c);

            if (preg_match('/^ADD\s+(UNIQUE\s+)?(?:KEY|INDEX)\s+"?([A-Za-z0-9_]+)"?\s*\((.+)\)$/i', $c, $mm)) {
                $unico = trim($mm[1] ?? '') !== '' ? 'UNIQUE ' : '';
                $out[] = "CREATE {$unico}INDEX IF NOT EXISTS \"{$mm[2]}\" ON \"{$tabela}\" ({$mm[3]})";
                continue;
            }
            if (preg_match('/^ADD\s+(?:COLUMN\s+)?"?([A-Za-z0-9_]+)"?\s+(.*)$/is', $c, $mm)) {
                $out[] = "ALTER TABLE \"{$tabela}\" ADD COLUMN \"{$mm[1]}\" " . self::tipos($mm[2]);
                continue;
            }
            if (preg_match('/^DROP\s+(?:COLUMN\s+)?"?([A-Za-z0-9_]+)"?$/i', $c, $mm)) {
                $out[] = "ALTER TABLE \"{$tabela}\" DROP COLUMN \"{$mm[1]}\"";
                continue;
            }
            if (preg_match('/^RENAME\s+(?:TO\s+)?"?([A-Za-z0-9_]+)"?$/i', $c, $mm)) {
                $out[] = "ALTER TABLE \"{$tabela}\" RENAME TO \"{$mm[1]}\"";
                continue;
            }
            // MODIFY/CHANGE/ENGINE/CONVERT: SQLite nao suporta e nao e necessario
        }
        return $out ?: [];
    }

    /** Normaliza tipos e clausulas de coluna MySQL para SQLite. */
    private static function tipos(string $def): string {
        $def = preg_replace('/\b(BIGINT|MEDIUMINT|SMALLINT|TINYINT|INTEGER|INT)\s*(\(\s*\d+\s*\))?(\s+UNSIGNED)?/i', 'INTEGER ', $def);
        $def = preg_replace('/\b(DECIMAL|NUMERIC)\s*(\(\s*\d+\s*(,\s*\d+\s*)?\))?(\s+UNSIGNED)?/i', 'NUMERIC ', $def);
        $def = preg_replace('/\b(DOUBLE|FLOAT|REAL)\s*(\(\s*\d+\s*,\s*\d+\s*\))?(\s+UNSIGNED)?/i', 'REAL ', $def);
        $def = preg_replace('/\b(VARCHAR|CHAR)\s*\(\s*\d+\s*\)/i', 'TEXT ', $def);
        $def = preg_replace('/\b(LONGTEXT|MEDIUMTEXT|TINYTEXT|JSON)\b/i', 'TEXT ', $def);
        $def = preg_replace('/\b(LONGBLOB|MEDIUMBLOB|TINYBLOB|BLOB)\b/i', 'BLOB ', $def);
        $def = preg_replace('/\b(VARBINARY|BINARY)\s*\(\s*\d+\s*\)/i', 'BLOB ', $def);
        $def = preg_replace('/\bENUM\s*\([^)]*\)/i', 'TEXT ', $def);
        $def = preg_replace('/\b(DATETIME|TIMESTAMP)\s*(\(\s*\d+\s*\))?/i', 'TEXT ', $def);
        $def = preg_replace('/\b(DATE|TIME|YEAR)(?=\s|$)/i', 'TEXT ', $def);
        $def = preg_replace('/\bUNSIGNED\b|\bZEROFILL\b/i', '', $def);
        // fuso: em SQLite CURRENT_TIMESTAMP e UTC; a aplicacao trabalha em hora local
        $def = preg_replace('/\bDEFAULT\s+CURRENT_TIMESTAMP(\(\))?/i', "DEFAULT (datetime('now','localtime'))", $def);
        $def = preg_replace('/\bON\s+UPDATE\s+CURRENT_TIMESTAMP(\(\))?/i', '', $def);
        $def = preg_replace('/\bCOMMENT\s+\'(?:[^\']|\'\')*\'/i', '', $def);
        $def = preg_replace('/\bCHARACTER\s+SET\s+[A-Za-z0-9_]+/i', '', $def);
        $def = preg_replace('/\bCOLLATE\s+[A-Za-z0-9_]+/i', '', $def);
        $def = preg_replace('/\bAUTO_INCREMENT\b/i', '', $def);
        $def = preg_replace('/\s+/', ' ', $def);
        return trim($def);
    }

    /** Divide por virgulas de topo (ignorando parenteses e plicas). */
    private static function dividirTopo(string $s): array {
        $partes = [];
        $nivel = 0;
        $buf = '';
        $emPlica = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = $s[$i];
            if ($emPlica) {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) { $buf .= $s[++$i]; continue; }
                if ($ch === "'") { $emPlica = false; }
                continue;
            }
            if ($ch === "'") { $emPlica = true; $buf .= $ch; continue; }
            if ($ch === '(') { $nivel++; }
            if ($ch === ')') { $nivel--; }
            if ($ch === ',' && $nivel === 0) { $partes[] = $buf; $buf = ''; continue; }
            $buf .= $ch;
        }
        if (trim($buf) !== '') { $partes[] = $buf; }
        return $partes;
    }
}
