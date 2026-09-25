<?php
/**
 * ============================================================
 * FarmaPonto - Backup (copias de seguranca)
 * ============================================================
 * Responsabilidade: exportar toda a base de dados para um ficheiro
 * .sql reimportavel, empacotar BD + fotografias + logotipo num .zip,
 * restaurar a partir de um .sql e manter a rotacao das copias.
 *
 * 100% offline: usa apenas PDO e a extensao ZipArchive do PHP.
 * Nao depende de mysqldump nem de qualquer servico externo.
 *
 * Comunica com: BackupController, cli/backup.php, Saude.
 */

namespace App\Helpers;

use App\Core\Armazenamento;
use App\Core\Database;
use PDO;
use Throwable;

final class Backup {

    private const LOTE = 500; // linhas por INSERT

    /** Pasta (absoluta) onde as copias sao guardadas. */
    public static function pasta(): string {
        return Armazenamento::garantirPasta('backups');
    }

    /**
     * Exporta a base de dados completa para SQL.
     * @param resource|null $destino Se dado, escreve nele (poupa memoria).
     * @return string SQL completo quando $destino e null; '' caso contrario.
     */
    public static function exportarSql($destino = null): string {
        $pdo = Database::pdo();
        $cfg = Database::config();
        $buffer = '';
        $escrever = static function (string $txt) use ($destino, &$buffer): void {
            if ($destino) { fwrite($destino, $txt); } else { $buffer .= $txt; }
        };

        $sqlite = Database::motor() === 'sqlite';

        $escrever("-- FarmaPonto :: copia de seguranca\n");
        $escrever('-- Base de dados: ' . ($sqlite ? basename((string) ($cfg['db_file'] ?? 'farmaponto.db')) : $cfg['db_name']) . "\n");
        $escrever('-- Motor: ' . ($sqlite ? 'SQLite' : 'MySQL') . "\n");
        $escrever('-- Gerada em: ' . date('Y-m-d H:i:s') . "\n");
        $escrever($sqlite
            ? "PRAGMA foreign_keys=OFF;\nBEGIN;\n\n"
            : "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

        $tabelas = $sqlite
            ? $pdo->query("SELECT name, 'BASE TABLE' FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_NUM)
            : $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($tabelas as [$tabela, $tipo]) {
            if (strtoupper((string) $tipo) === 'VIEW') { continue; }
            if ($sqlite) {
                $st0 = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = ?");
                $st0->execute([$tabela]);
                $criar = [0 => $tabela, 1 => (string) $st0->fetchColumn()];
            } else {
                $criar = $pdo->query("SHOW CREATE TABLE `{$tabela}`")->fetch(PDO::FETCH_NUM);
            }
            $escrever("-- ----------------------------\n-- Tabela: {$tabela}\n-- ----------------------------\n");
            $escrever("DROP TABLE IF EXISTS `{$tabela}`;\n" . $criar[1] . ";\n\n");

            $st = $pdo->query("SELECT * FROM `{$tabela}`");
            $linhas = [];
            $colunas = null;
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                if ($colunas === null) {
                    $colunas = '`' . implode('`,`', array_keys($row)) . '`';
                }
                $vals = array_map(static function ($v) use ($pdo) {
                    if ($v === null) { return 'NULL'; }
                    if (is_int($v) || is_float($v)) { return (string) $v; }
                    $s = (string) $v;
                    // Valores binarios (ex.: IP guardado em bruto) em hexadecimal,
                    // sintaxe aceita pelo SQLite e pelo MySQL.
                    if (!mb_check_encoding($s, 'UTF-8')) { return "X'" . bin2hex($s) . "'"; }
                    return $pdo->quote($s);
                }, $row);
                $linhas[] = '(' . implode(',', $vals) . ')';
                if (count($linhas) >= self::LOTE) {
                    $escrever("INSERT INTO `{$tabela}` ({$colunas}) VALUES\n" . implode(",\n", $linhas) . ";\n");
                    $linhas = [];
                }
            }
            if ($linhas) {
                $escrever("INSERT INTO `{$tabela}` ({$colunas}) VALUES\n" . implode(",\n", $linhas) . ";\n");
            }
            $escrever("\n");
        }

        if ($sqlite) {
            // Indices e gatilhos (o CREATE TABLE do SQLite nao os inclui)
            $extras = $pdo->query(
                "SELECT sql FROM sqlite_master WHERE type IN ('index','trigger')
                   AND sql IS NOT NULL AND name NOT LIKE 'sqlite_%'"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($extras as $sqlExtra) { $escrever($sqlExtra . ";\n"); }
            $escrever("\nCOMMIT;\nPRAGMA foreign_keys=ON;\n-- fim da copia\n");
            return $buffer;
        }

        // Vistas depois das tabelas (podem depender delas)
        foreach ($tabelas as [$nome, $tipo]) {
            if (strtoupper((string) $tipo) !== 'VIEW') { continue; }
            $criar = $pdo->query("SHOW CREATE VIEW `{$nome}`")->fetch(PDO::FETCH_NUM);
            $escrever("DROP VIEW IF EXISTS `{$nome}`;\n" . $criar[1] . ";\n\n");
        }

        $escrever("SET FOREIGN_KEY_CHECKS=1;\n-- fim da copia\n");
        return $buffer;
    }

    /**
     * Grava uma copia .sql na pasta de backups e roda as antigas.
     * @return array{ficheiro:string,caminho:string,bytes:int}
     */
    public static function criarFicheiroSql(): array {
        $nome = 'farmaponto-' . date('Y-m-d_His') . '.sql';
        $caminho = self::pasta() . '/' . $nome;
        $fh = fopen($caminho, 'wb');
        if (!$fh) { throw new \RuntimeException('Não foi possível escrever em ' . $caminho); }
        try {
            self::exportarSql($fh);
        } finally {
            fclose($fh);
        }
        self::rodar();
        return ['ficheiro' => $nome, 'caminho' => $caminho, 'bytes' => (int) filesize($caminho)];
    }

    /**
     * Copia completa: base de dados + fotografias + logotipo num .zip.
     * @return array{ficheiro:string,caminho:string,bytes:int}
     */
    public static function criarZipCompleto(): array {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('A extensão ZipArchive do PHP não está activa; use a cópia apenas da base de dados.');
        }
        $nome = 'farmaponto-completo-' . date('Y-m-d_His') . '.zip';
        $caminho = self::pasta() . '/' . $nome;
        $zip = new \ZipArchive();
        if ($zip->open($caminho, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível criar o ficheiro .zip.');
        }
        $zip->addFromString('base-de-dados.sql', self::exportarSql());
        $zip->addFromString('LEIA-ME.txt',
            "Copia completa do FarmaPonto\n"
            . 'Gerada em: ' . date('d/m/Y H:i:s') . "\n\n"
            . "Como restaurar:\n"
            . "1. Criar a base de dados vazia (ex.: CREATE DATABASE farmaponto;).\n"
            . "2. Importar base-de-dados.sql: na versao de secretaria, use Configuracoes > Copias de seguranca > Restaurar; no servidor web, mysql -u root farmaponto < base-de-dados.sql.\n"
            . "3. Copiar a pasta storage/ deste zip para a pasta storage/ da instalacao.\n"
        );
        foreach (['selfies', 'uploads'] as $sub) {
            $dir = Armazenamento::caminho($sub);
            if (!is_dir($dir)) { continue; }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $zip->addFile($f->getPathname(), 'storage/' . $sub . '/' . $f->getFilename());
                }
            }
        }
        $zip->close();
        self::rodar();
        return ['ficheiro' => $nome, 'caminho' => $caminho, 'bytes' => (int) filesize($caminho)];
    }

    /** Mantem apenas as N copias mais recentes (config: backup_manter). */
    public static function rodar(?int $manter = null): int {
        $cfg = Database::config();
        $manter = $manter ?? (int) ($cfg['backup_manter'] ?? 14);
        if ($manter <= 0) { return 0; }
        $ficheiros = self::listar();
        $removidos = 0;
        foreach (array_slice($ficheiros, $manter) as $f) {
            if (@unlink($f['caminho'])) { $removidos++; }
        }
        return $removidos;
    }

    /**
     * Copias existentes, mais recentes primeiro.
     * @return array<int,array{ficheiro:string,caminho:string,bytes:int,em:int,tipo:string}>
     */
    public static function listar(): array {
        $pasta = self::pasta();
        $out = [];
        foreach (glob($pasta . '/farmaponto-*') ?: [] as $caminho) {
            if (!is_file($caminho)) { continue; }
            $out[] = [
                'ficheiro' => basename($caminho),
                'caminho'  => $caminho,
                'bytes'    => (int) filesize($caminho),
                'em'       => (int) filemtime($caminho),
                'tipo'     => str_ends_with($caminho, '.zip') ? 'completa' : 'base de dados',
            ];
        }
        usort($out, static fn($a, $b) => $b['em'] <=> $a['em']);
        return $out;
    }

    /** Data (timestamp) da copia mais recente, ou null. */
    public static function ultima(): ?array {
        $l = self::listar();
        return $l[0] ?? null;
    }

    public static function apagar(string $ficheiro): bool {
        $caminho = self::pasta() . '/' . basename($ficheiro);
        return is_file($caminho) && @unlink($caminho);
    }

    /**
     * Restaura a base de dados a partir de SQL.
     * Antes de tocar em nada, guarda uma copia de seguranca do estado actual.
     * @return array{comandos:int,antes:string}
     */
    public static function restaurarSql(string $sql): array {
        $antes = self::criarFicheiroSql();
        $pdo = Database::pdo();
        $sqlite = Database::motor() === 'sqlite';
        $desligar = $sqlite ? 'PRAGMA foreign_keys=OFF' : 'SET FOREIGN_KEY_CHECKS=0';
        $ligar    = $sqlite ? 'PRAGMA foreign_keys=ON'  : 'SET FOREIGN_KEY_CHECKS=1';
        $pdo->exec($desligar);
        $comandos = 0;
        try {
            foreach (self::dividirSql($sql) as $cmd) {
                if ($sqlite && preg_match('/^(SET\s|BEGIN|COMMIT|PRAGMA\s)/i', $cmd)) { continue; }
                $pdo->exec($cmd);
                $comandos++;
            }
        } catch (Throwable $e) {
            $pdo->exec($ligar);
            throw new \RuntimeException(
                'A importação parou no comando ' . ($comandos + 1) . ': ' . $e->getMessage()
                . ' — a cópia do estado anterior ficou em ' . $antes['ficheiro'] . '.', 0, $e
            );
        }
        $pdo->exec($ligar);
        return ['comandos' => $comandos, 'antes' => $antes['ficheiro']];
    }

    /**
     * Parte um dump em comandos, respeitando strings e comentarios.
     * @return array<int,string>
     */
    public static function dividirSql(string $sql): array {
        $comandos = [];
        $actual = '';
        $tamanho = strlen($sql);
        $emString = null;
        for ($i = 0; $i < $tamanho; $i++) {
            $c = $sql[$i];
            if ($emString !== null) {
                $actual .= $c;
                if ($c === '\\' && $i + 1 < $tamanho) { $actual .= $sql[++$i]; continue; }
                if ($c === $emString) { $emString = null; }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') { $emString = $c; $actual .= $c; continue; }
            if ($c === '-' && substr($sql, $i, 2) === '--' && trim($actual) === '') {
                $fim = strpos($sql, "\n", $i);
                if ($fim === false) { break; }
                $i = $fim; continue;
            }
            if ($c === ';') {
                // Gatilhos SQLite tem ';' interno entre BEGIN e END: nao dividir
                $t = trim($actual);
                if (preg_match('/^CREATE\s+TRIGGER/i', $t) && !preg_match('/\bEND$/i', $t)) {
                    $actual .= $c;
                    continue;
                }
                if ($t !== '') { $comandos[] = $t; }
                $actual = '';
                continue;
            }
            $actual .= $c;
        }
        if (trim($actual) !== '') { $comandos[] = trim($actual); }
        return $comandos;
    }

    /** Formata bytes de forma legivel. */
    public static function tamanho(int $bytes): string {
        if ($bytes >= 1073741824) { return round($bytes / 1073741824, 2) . ' GB'; }
        if ($bytes >= 1048576)    { return round($bytes / 1048576, 1) . ' MB'; }
        if ($bytes >= 1024)       { return round($bytes / 1024) . ' KB'; }
        return $bytes . ' B';
    }
}
