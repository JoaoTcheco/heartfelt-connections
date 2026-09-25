<?php
/**
 * ============================================================
 * FarmaPonto - Database (Singleton)
 * ============================================================
 * Responsabilidade: Criar e fornecer uma única instância PDO segura,
 * ajudas de auto-migracao e transacoes.
 * Comunica com: TODOS os Models.
 * Portabilidade: host, porta, socket e fuso vêm de config/config.php.
 */

namespace App\Core;

use PDO;
use PDOException;

final class Database {
    private static ?PDO $pdo = null;
    private static array $cfg = [];

    public static function config(): array {
        if (!self::$cfg) { self::$cfg = require __DIR__ . '/../../config/config.php'; }
        return self::$cfg;
    }

    /** Motor em uso: 'mysql' (servidor) ou 'sqlite' (programa de secretaria). */
    public static function motor(): string {
        $d = strtolower((string) (self::config()['db_driver'] ?? 'mysql'));
        return $d === 'sqlite' ? 'sqlite' : 'mysql';
    }

    public static function pdo(): PDO {
        if (self::$pdo !== null) return self::$pdo;

        $cfg = self::config();
        try {
            self::$pdo = self::motor() === 'sqlite'
                ? PdoSqlite::abrir((string) ($cfg['db_file'] ?? ROOT_PATH . '/storage/farmaponto.db'), $cfg)
                : self::ligarMysql($cfg);
        } catch (PDOException $e) {
            $ref = PaginaErro::referencia();
            error_log('[FarmaPonto][' . $ref . '] ligacao BD falhou: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') {
                fwrite(STDERR, "Erro ao ligar a base de dados ({$ref}): " . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            PaginaErro::mostrar(
                'Não foi possível ligar à base de dados',
                self::motor() === 'sqlite'
                    ? 'O ficheiro de dados não pôde ser aberto. Confirme que tem permissões na pasta de dados e volte a tentar.'
                    : 'O serviço da base de dados parece estar parado. Confirme que o MySQL/MariaDB está a correr e volte a tentar dentro de alguns segundos.',
                $ref
            );
        }

        return self::$pdo;
    }

    private static function ligarMysql(array $cfg): PDO {
        $charset = $cfg['db_charset'] ?? 'utf8mb4';
        $fuso    = $cfg['timezone_sql'] ?? '+02:00';

        $dsn = !empty($cfg['db_socket'])
            ? "mysql:unix_socket={$cfg['db_socket']};dbname={$cfg['db_name']};charset={$charset}"
            : "mysql:host={$cfg['db_host']};port=" . (int) ($cfg['db_port'] ?? 3306)
              . ";dbname={$cfg['db_name']};charset={$charset}";

        return new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}, time_zone='{$fuso}'",
        ]);
    }

    /** @var array<string,array<string,bool>> cache de colunas por tabela */
    private static array $colunas = [];

    /** Lista (em cache) as colunas existentes de uma tabela. */
    public static function colunas(string $tabela): array {
        if (!isset(self::$colunas[$tabela])) {
            $map = [];
            if (self::motor() === 'sqlite') {
                $st = self::pdo()->prepare("SELECT name FROM pragma_table_info(?)");
                $st->execute([$tabela]);
            } else {
                $st = self::pdo()->prepare(
                    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
                );
                $st->execute([$tabela]);
            }
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) { $map[(string) $c] = true; }
            self::$colunas[$tabela] = $map;
        }
        return self::$colunas[$tabela];
    }

    /** Verifica se uma coluna existe na tabela. */
    public static function temColuna(string $tabela, string $coluna): bool {
        return isset(self::colunas($tabela)[$coluna]);
    }

    /** Verifica se uma tabela existe. */
    public static function temTabela(string $tabela): bool {
        static $cache = [];
        if (!isset($cache[$tabela])) {
            if (self::motor() === 'sqlite') {
                $st = self::pdo()->prepare(
                    "SELECT COUNT(*) FROM sqlite_master WHERE type IN ('table','view') AND name = ?"
                );
            } else {
                $st = self::pdo()->prepare(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
                );
            }
            $st->execute([$tabela]);
            $cache[$tabela] = ((int) $st->fetchColumn()) > 0;
        }
        return $cache[$tabela];
    }

    /**
     * Garante (idempotente) que uma coluna existe; cria-a se faltar.
     * Usado para auto-migrar instalações antigas sem reimportar a BD.
     */
    public static function garantirColuna(string $tabela, string $coluna, string $definicao): void {
        if (self::temColuna($tabela, $coluna)) { return; }
        try {
            self::pdo()->exec("ALTER TABLE `{$tabela}` ADD COLUMN `{$coluna}` {$definicao}");
            self::$colunas[$tabela][$coluna] = true;
        } catch (PDOException $e) {
            error_log("[FarmaPonto] auto-migracao {$tabela}.{$coluna}: " . $e->getMessage());
        }
    }

    /** Garante (idempotente) que um indice existe. */
    public static function garantirIndice(string $tabela, string $nome, string $definicao): void {
        try {
            if (self::motor() === 'sqlite') {
                $st = self::pdo()->prepare(
                    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = ?"
                );
                $st->execute([$nome]);
                if ((int) $st->fetchColumn() > 0) { return; }
                self::pdo()->exec("ALTER TABLE `{$tabela}` ADD {$definicao}");
                return;
            }
            $st = self::pdo()->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
            );
            $st->execute([$tabela, $nome]);
            if ((int) $st->fetchColumn() > 0) { return; }
            self::pdo()->exec("ALTER TABLE `{$tabela}` ADD {$definicao}");
        } catch (PDOException $e) {
            error_log("[FarmaPonto] auto-migracao indice {$tabela}.{$nome}: " . $e->getMessage());
        }
    }

    /**
     * Inventario das tabelas (nome, linhas, espaco em KB, motor).
     * Fonte unica usada pelo Diagnostico e pela Saude, em qualquer motor.
     * @return array<int,array<string,mixed>>
     */
    public static function inventarioTabelas(): array {
        try {
            if (self::motor() === 'sqlite') {
                $pdo = self::pdo();
                $pagina = (int) $pdo->query('PRAGMA page_size')->fetchColumn();
                $nomes = $pdo->query(
                    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
                )->fetchAll(PDO::FETCH_COLUMN);
                $out = [];
                foreach ($nomes as $n) {
                    $linhas = (int) $pdo->query('SELECT COUNT(*) FROM "' . $n . '"')->fetchColumn();
                    $kb = null;
                    try {
                        $paginas = (int) $pdo->query("SELECT SUM(pgsize) FROM dbstat WHERE name = '" . $n . "'")->fetchColumn();
                        $kb = (int) round($paginas / 1024);
                    } catch (\Throwable) {
                        $kb = (int) round(($linhas * $pagina) / 1024 / 8); // estimativa
                    }
                    $out[] = ['tabela' => $n, 'linhas' => $linhas, 'kb' => $kb, 'motor' => 'SQLite'];
                }
                usort($out, static fn($a, $b) => $b['kb'] <=> $a['kb']);
                return $out;
            }

            return self::pdo()->query(
                "SELECT TABLE_NAME AS tabela, TABLE_ROWS AS linhas,
                        ROUND((data_length + index_length)/1024) AS kb, ENGINE AS motor
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
                 ORDER BY (data_length + index_length) DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Numero de tabelas e tamanho total da base de dados em MB.
     * @return array{n:int,mb:float}
     */
    public static function dimensao(): array {
        if (self::motor() === 'sqlite') {
            $pdo = self::pdo();
            $n = (int) $pdo->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            )->fetchColumn();
            $ficheiro = (string) (self::config()['db_file'] ?? '');
            $bytes = is_file($ficheiro) ? (int) filesize($ficheiro) : 0;
            foreach (['-wal', '-shm'] as $suf) {
                if (is_file($ficheiro . $suf)) { $bytes += (int) filesize($ficheiro . $suf); }
            }
            return ['n' => $n, 'mb' => round($bytes / 1048576, 2)];
        }

        $r = self::pdo()->query(
            "SELECT COUNT(*) AS n, COALESCE(ROUND(SUM(data_length + index_length)/1048576, 2), 0) AS mb
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
        )->fetch();
        return ['n' => (int) ($r['n'] ?? 0), 'mb' => (float) ($r['mb'] ?? 0)];
    }


    /**
     * Executa uma função dentro de uma transação (reentrante).
     * Se já existir transação activa, apenas executa (o commit fica
     * a cargo de quem abriu), evitando commits parciais.
     */
    public static function transacao(callable $fn) {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) { return $fn($pdo); }
        $pdo->beginTransaction();
        try {
            $r = $fn($pdo);
            // Comandos DDL (CREATE TABLE/ALTER) fazem commit implicito no MySQL:
            // nesse caso a transacao ja fechou e commit() lancaria excecao.
            if ($pdo->inTransaction()) { $pdo->commit(); }
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    /** Alias historico de transacao(). */
    public static function tx(callable $fn) {
        return self::transacao($fn);
    }
}
