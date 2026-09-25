<?php
/**
 * ============================================================
 * FarmaPonto - PdoSqlite
 * ============================================================
 * Responsabilidade: ligacao PDO a um ficheiro SQLite unico que se
 * comporta como MySQL aos olhos dos Models: traduz o SQL (SqlTradutor)
 * e registra em PHP as funcoes que o MySQL oferece e o SQLite nao tem.
 * Comunica com: App\Core\Database (unico criador) e SqlTradutor.
 * Portabilidade: PHP puro + extensao pdo_sqlite (padrao no PHP oficial).
 */

namespace App\Core;

use PDO;
use PDOStatement;

final class PdoSqlite extends PDO {

    private string $nomeBd = 'farmaponto';
    /** @var array<string,string> cache de traducoes (SQL original -> SQL SQLite) */
    private array $cache = [];
    /** @var array<string,array<int,array<int,string>>> cache de indices unicos por tabela */
    private array $unicos = [];

    public static function abrir(string $ficheiro, array $cfg = []): self {
        $pasta = dirname($ficheiro);
        if (!is_dir($pasta)) { @mkdir($pasta, 0775, true); }

        $pdo = new self('sqlite:' . $ficheiro, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->nomeBd = (string) ($cfg['db_name'] ?? pathinfo($ficheiro, PATHINFO_FILENAME));

        // Durabilidade + concorrencia num posto unico
        foreach ([
            'PRAGMA journal_mode = WAL',
            'PRAGMA synchronous = NORMAL',
            'PRAGMA foreign_keys = ON',
            'PRAGMA busy_timeout = 8000',
            'PRAGMA temp_store = MEMORY',
            'PRAGMA cache_size = -16000',
        ] as $p) { $pdo->exec($p); }

        $pdo->registarFuncoes();
        return $pdo;
    }

    // ------------------------------------------------------------------
    // Traducao transparente
    // ------------------------------------------------------------------

    private function traduzir(string $sql): array {
        if (isset($this->cache[$sql])) { return [$this->cache[$sql]]; }
        $lista = SqlTradutor::traduzir($sql, fn(string $t, array $c): array => $this->indiceUnico($t, $c));
        if (count($lista) === 1) { $this->cache[$sql] = $lista[0]; }
        return $lista;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $lista = $this->traduzir($query);
        return parent::prepare($lista[0] ?? $query, $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $lista = $this->traduzir($query);
        $sql = $lista[0] ?? $query;
        // instrucoes extra (indices, triggers) sao aplicadas antes
        for ($i = 1; $i < count($lista); $i++) { parent::exec($lista[$i]); }
        return $fetchMode === null
            ? parent::query($sql)
            : parent::query($sql, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false {
        $total = 0;
        foreach ($this->traduzir($statement) as $sql) {
            if (trim($sql) === '') { continue; }
            $n = parent::exec($sql);
            if ($n === false) { return false; }
            $total += $n;
        }
        return $total;
    }

    /** Colunas do indice unico adequado a um upsert nesta tabela. */
    private function indiceUnico(string $tabela, array $colunas): array {
        if (!isset($this->unicos[$tabela])) {
            $lista = [];
            try {
                $idx = parent::query('PRAGMA index_list("' . $tabela . '")')->fetchAll(PDO::FETCH_ASSOC);
                foreach ($idx as $i) {
                    if ((int) ($i['unique'] ?? 0) !== 1) { continue; }
                    $info = parent::query('PRAGMA index_info("' . $i['name'] . '")')->fetchAll(PDO::FETCH_ASSOC);
                    $cols = [];
                    foreach ($info as $c) { if ($c['name'] !== null) { $cols[] = (string) $c['name']; } }
                    if ($cols) { $lista[] = $cols; }
                }
            } catch (\Throwable) {
                $lista = [];
            }
            $this->unicos[$tabela] = $lista;
        }

        $melhor = [];
        foreach ($this->unicos[$tabela] as $cols) {
            if (array_diff($cols, $colunas)) { continue; }      // exige todas presentes no INSERT
            if (count($cols) > count($melhor)) { $melhor = $cols; }
        }
        return $melhor;
    }

    // ------------------------------------------------------------------
    // Funcoes MySQL implementadas em PHP (UDF)
    // ------------------------------------------------------------------

    private function registarFuncoes(): void {
        $ts = static function (?string $v): int {
            if ($v === null || $v === '') { return time(); }
            $t = strtotime($v);
            return $t === false ? time() : $t;
        };

        $f = [
            'MYSQL_NOW' => static fn(): string => date('Y-m-d H:i:s'),
            'NOW'       => static fn(): string => date('Y-m-d H:i:s'),
            'SYSDATE'   => static fn(): string => date('Y-m-d H:i:s'),
            'CURDATE'   => static fn(): string => date('Y-m-d'),
            'CURTIME'   => static fn(): string => date('H:i:s'),
            'UTC_TIMESTAMP' => static fn(): string => gmdate('Y-m-d H:i:s'),
            'VERSION'   => static fn(): string => 'SQLite ' . (\SQLite3::version()['versionString'] ?? '3'),
            'DATABASE'  => fn(): string => $this->nomeBd,
            'UUID'      => static fn(): string => bin2hex(random_bytes(16)),
            'RAND'      => static fn(): float => mt_rand() / mt_getrandmax(),
        ];
        foreach ($f as $nome => $fn) { $this->sqliteCreateFunction($nome, $fn, 0); }

        $this->sqliteCreateFunction('MYSQL_INTERVAL', static function (?string $base, $n, string $unidade) use ($ts): string {
            $n = (int) $n;
            $mapa = [
                'MICROSECOND' => 'seconds', 'SECOND' => 'seconds', 'MINUTE' => 'minutes',
                'HOUR' => 'hours', 'DAY' => 'days', 'WEEK' => 'weeks',
                'MONTH' => 'months', 'YEAR' => 'years',
            ];
            $u = $mapa[strtoupper($unidade)] ?? 'days';
            $temHora = $base === null || strlen((string) $base) > 10;
            $t = strtotime(($n >= 0 ? '+' : '') . $n . ' ' . $u, $ts($base));
            return date($temHora ? 'Y-m-d H:i:s' : 'Y-m-d', $t === false ? $ts($base) : $t);
        }, 3);

        $this->sqliteCreateFunction('YEAR', static fn(?string $d): ?int => $d === null ? null : (int) date('Y', $ts($d)), 1);
        $this->sqliteCreateFunction('MONTH', static fn(?string $d): ?int => $d === null ? null : (int) date('n', $ts($d)), 1);
        $this->sqliteCreateFunction('DAY', static fn(?string $d): ?int => $d === null ? null : (int) date('j', $ts($d)), 1);
        $this->sqliteCreateFunction('DAYOFMONTH', static fn(?string $d): ?int => $d === null ? null : (int) date('j', $ts($d)), 1);
        $this->sqliteCreateFunction('HOUR', static fn(?string $d): ?int => $d === null ? null : (int) date('G', $ts($d)), 1);
        $this->sqliteCreateFunction('MINUTE', static fn(?string $d): ?int => $d === null ? null : (int) date('i', $ts($d)), 1);
        // MySQL: 0=Segunda ... 6=Domingo
        $this->sqliteCreateFunction('WEEKDAY', static fn(?string $d): ?int => $d === null ? null : ((int) date('N', $ts($d))) - 1, 1);
        // MySQL: 1=Domingo ... 7=Sabado
        $this->sqliteCreateFunction('DAYOFWEEK', static fn(?string $d): ?int => $d === null ? null : ((int) date('w', $ts($d))) + 1, 1);
        $this->sqliteCreateFunction('WEEK', static fn(?string $d): ?int => $d === null ? null : (int) date('W', $ts($d)), 1);
        $this->sqliteCreateFunction('LAST_DAY', static fn(?string $d): ?string => $d === null ? null : date('Y-m-t', $ts($d)), 1);
        $this->sqliteCreateFunction('UNIX_TIMESTAMP', static fn(?string $d = null): int => $ts($d), -1);
        $this->sqliteCreateFunction('FROM_UNIXTIME', static fn($s): string => date('Y-m-d H:i:s', (int) $s), 1);

        $this->sqliteCreateFunction('DATE_FORMAT', static function (?string $d, string $fmt) use ($ts): ?string {
            if ($d === null) { return null; }
            $mapa = [
                '%Y' => 'Y', '%y' => 'y', '%m' => 'm', '%c' => 'n', '%d' => 'd', '%e' => 'j',
                '%H' => 'H', '%k' => 'G', '%i' => 'i', '%s' => 's', '%S' => 's',
                '%p' => 'A', '%h' => 'h', '%W' => 'l', '%a' => 'D', '%M' => 'F', '%b' => 'M',
                '%j' => 'z', '%U' => 'W', '%u' => 'W', '%%' => '%',
            ];
            return date(strtr($fmt, $mapa), $ts($d));
        }, 2);

        $seg = static function (?string $t): int {
            if ($t === null || $t === '') { return 0; }
            $neg = str_starts_with($t, '-');
            $p = array_map('intval', explode(':', ltrim($t, '-')));
            $s = ($p[0] ?? 0) * 3600 + ($p[1] ?? 0) * 60 + ($p[2] ?? 0);
            return $neg ? -$s : $s;
        };
        $hms = static function (int $s): string {
            $sinal = $s < 0 ? '-' : '';
            $s = abs($s);
            return sprintf('%s%02d:%02d:%02d', $sinal, intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
        };
        $this->sqliteCreateFunction('TIME_TO_SEC', static fn(?string $t): int => $seg($t), 1);
        $this->sqliteCreateFunction('SEC_TO_TIME', static fn($s): string => $hms((int) $s), 1);
        $this->sqliteCreateFunction('ADDTIME', static function (?string $a, ?string $b) use ($seg, $hms, $ts): ?string {
            if ($a === null) { return null; }
            if (strlen($a) > 8 && str_contains($a, '-')) {           // DATETIME + tempo
                return date('Y-m-d H:i:s', $ts($a) + $seg($b));
            }
            return $hms($seg($a) + $seg($b));
        }, 2);
        $this->sqliteCreateFunction('SUBTIME', static fn(?string $a, ?string $b): ?string => $a === null ? null : $hms($seg($a) - $seg($b)), 2);
        $this->sqliteCreateFunction('TIMEDIFF', static function (?string $a, ?string $b) use ($ts, $hms): ?string {
            if ($a === null || $b === null) { return null; }
            return $hms($ts($a) - $ts($b));
        }, 2);
        $this->sqliteCreateFunction('DATEDIFF', static function (?string $a, ?string $b) use ($ts): ?int {
            if ($a === null || $b === null) { return null; }
            return (int) floor((strtotime(date('Y-m-d', $ts($a))) - strtotime(date('Y-m-d', $ts($b)))) / 86400);
        }, 2);
        $this->sqliteCreateFunction('TIMESTAMPDIFF', static function (string $u, ?string $a, ?string $b) use ($ts): ?int {
            if ($a === null || $b === null) { return null; }
            $d = $ts($b) - $ts($a);
            return match (strtoupper($u)) {
                'SECOND' => $d, 'MINUTE' => intdiv($d, 60), 'HOUR' => intdiv($d, 3600),
                'DAY' => intdiv($d, 86400), 'WEEK' => intdiv($d, 604800),
                'MONTH' => (int) floor($d / 2629800), 'YEAR' => (int) floor($d / 31557600),
                default => $d,
            };
        }, 3);

        $this->sqliteCreateFunction('CONCAT', static function (...$a): string {
            return implode('', array_map(static fn($v): string => (string) $v, $a));
        }, -1);
        $this->sqliteCreateFunction('CONCAT_WS', static function (...$a): string {
            $sep = (string) array_shift($a);
            $vals = array_filter($a, static fn($v): bool => $v !== null);
            return implode($sep, array_map(static fn($v): string => (string) $v, $vals));
        }, -1);
        $this->sqliteCreateFunction('LOCATE', static fn(string $ag, ?string $palha, $pos = 1): int => $palha === null ? 0 : (int) (($p = strpos($palha, $ag, max(0, (int) $pos - 1))) === false ? 0 : $p + 1), -1);
        $this->sqliteCreateFunction('LPAD', static fn($v, $n, $p = ' '): string => str_pad((string) $v, (int) $n, (string) $p, STR_PAD_LEFT), -1);
        $this->sqliteCreateFunction('RPAD', static fn($v, $n, $p = ' '): string => str_pad((string) $v, (int) $n, (string) $p), -1);
        $this->sqliteCreateFunction('LEAST', static fn(...$a) => min($a), -1);
        $this->sqliteCreateFunction('GREATEST', static fn(...$a) => max($a), -1);
        $this->sqliteCreateFunction('MD5', static fn(?string $v): ?string => $v === null ? null : md5($v), 1);
        $this->sqliteCreateFunction('SHA1', static fn(?string $v): ?string => $v === null ? null : sha1($v), 1);
        $this->sqliteCreateFunction('SUBSTRING_INDEX', static function (?string $v, string $d, $n): ?string {
            if ($v === null) { return null; }
            $n = (int) $n;
            $p = explode($d, $v);
            return $n >= 0 ? implode($d, array_slice($p, 0, $n)) : implode($d, array_slice($p, $n));
        }, 3);

        // Auditoria de IP: guardar em binario como o MySQL faz
        $this->sqliteCreateFunction('INET6_ATON', static function (?string $ip) {
            if ($ip === null || $ip === '') { return null; }
            $b = @inet_pton($ip);
            return $b === false ? null : $b;
        }, 1);
        $this->sqliteCreateFunction('INET6_NTOA', static function ($bin): ?string {
            if ($bin === null || $bin === '') { return null; }
            $ip = @inet_ntop(is_string($bin) ? $bin : (string) $bin);
            return $ip === false ? null : $ip;
        }, 1);
        $this->sqliteCreateFunction('INET_ATON', static fn(?string $ip) => $ip === null ? null : (int) sprintf('%u', ip2long($ip)), 1);
        $this->sqliteCreateFunction('INET_NTOA', static fn($n): ?string => $n === null ? null : long2ip((int) $n), 1);
    }
}
