<?php
/**
 * ============================================================
 * FarmaPonto - Gerador do esquema SQLite
 * ============================================================
 * Converte database.sql (MySQL) em database.sqlite.sql, usando o mesmo
 * tradutor que corre em producao. Evita ter dois esquemas escritos a mao
 * (e, portanto, divergentes).
 *
 * Uso: php scripts/gerar-esquema-sqlite.php [entrada.sql] [saida.sql]
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/core/SqlTradutor.php';

use App\Core\SqlTradutor;

$entrada = $argv[1] ?? ROOT_PATH . '/database.sql';
$saida   = $argv[2] ?? ROOT_PATH . '/database.sqlite.sql';

$bruto = file_get_contents($entrada);
if ($bruto === false) {
    fwrite(STDERR, "Nao foi possivel ler {$entrada}\n");
    exit(1);
}

// Remover comentarios e directivas proprias do MySQL
$bruto = preg_replace('/^\s*--.*$/m', '', $bruto);
$bruto = preg_replace('/^\s*#.*$/m', '', $bruto);
$bruto = preg_replace('~/\*!.*?\*/;?~s', '', $bruto);
$bruto = preg_replace('~/\*.*?\*/~s', '', $bruto);

$instrucoes = dividirInstrucoes($bruto);

$out = [
    '-- FarmaPonto - esquema SQLite (GERADO por scripts/gerar-esquema-sqlite.php)',
    '-- Nao editar a mao: editar database.sql e voltar a gerar.',
    'PRAGMA foreign_keys = OFF;',
    'BEGIN;',
];

$ignorar = '/^(SET|START\s+TRANSACTION|COMMIT|USE|CREATE\s+DATABASE|DROP\s+DATABASE|LOCK\s+TABLES|UNLOCK\s+TABLES|ALTER\s+DATABASE|DELIMITER)/i';
$tabelas = 0;
$indices = 0;
$dados   = 0;

foreach ($instrucoes as $sql) {
    $sql = trim($sql);
    if ($sql === '' || preg_match($ignorar, $sql)) { continue; }

    foreach (SqlTradutor::traduzir($sql) as $t) {
        $t = trim($t);
        if ($t === '') { continue; }
        if (stripos($t, 'CREATE TABLE') === 0) { $tabelas++; }
        elseif (stripos($t, 'CREATE UNIQUE INDEX') === 0 || stripos($t, 'CREATE INDEX') === 0) { $indices++; }
        elseif (stripos($t, 'INSERT') === 0) { $dados++; }
        $out[] = $t . ';';
    }
}

$out[] = 'COMMIT;';
$out[] = 'PRAGMA foreign_keys = ON;';

file_put_contents($saida, implode("\n", $out) . "\n");
printf("Gerado %s\n  tabelas: %d\n  indices: %d\n  blocos de dados: %d\n", $saida, $tabelas, $indices, $dados);

/** Divide um ficheiro .sql em instrucoes, respeitando strings e comentarios. */
function dividirInstrucoes(string $s): array {
    $inst = [];
    $buf  = '';
    $len  = strlen($s);
    $plica = false;
    $dupla = false;
    $crase = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $s[$i];
        if ($ch === '\\' && ($plica || $dupla)) { $buf .= $ch . ($s[$i + 1] ?? ''); $i++; continue; }
        if ($ch === "'" && !$dupla && !$crase) { $plica = !$plica; }
        elseif ($ch === '"' && !$plica && !$crase) { $dupla = !$dupla; }
        elseif ($ch === '`' && !$plica && !$dupla) { $crase = !$crase; }
        elseif ($ch === ';' && !$plica && !$dupla && !$crase) { $inst[] = $buf; $buf = ''; continue; }
        $buf .= $ch;
    }
    if (trim($buf) !== '') { $inst[] = $buf; }
    return $inst;
}
