<?php
/**
 * ============================================================
 * FarmaPonto - Gerador do cofre de codigo (protecao do fonte)
 * ============================================================
 * Responsabilidade: transformar todo o codigo PHP da aplicacao num unico
 * ficheiro cifrado (cofre.dat) e escrever o pequeno carregador que o le em
 * memoria. Depois de instalado, nao existe nenhum ficheiro .php legivel com
 * logica de negocio no computador do cliente.
 *
 * Como usar (na maquina de quem constroi o instalador):
 *   php scripts/proteger.php --destino=/caminho/site --exe=/caminho/FarmaPonto.exe
 *
 * Camadas de protecao aplicadas:
 *   1. comentarios e espacos removidos (php_strip_whitespace);
 *   2. cada ficheiro cifrado com AES-256-CBC e IV proprio;
 *   3. o indice do cofre tambem vai cifrado (nao se ve a lista de ficheiros);
 *   4. a chave nunca e guardada: e derivada da impressao digital do
 *      executavel do programa, por isso o cofre copiado para outro sitio
 *      (ou com o executavel alterado) deixa de abrir;
 *   5. cada ficheiro tem selo de integridade (HMAC-SHA256): qualquer
 *      alteracao ao cofre e detectada e o arranque e recusado.
 *
 * 100% offline: usa apenas a extensao openssl do proprio PHP.
 */

declare(strict_types=1);

const ORIGEM = __DIR__ . '/..';

// ---------- Argumentos ----------

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) { $args[$m[1]] = $m[2]; }
}
$destino = $args['destino'] ?? null;
$exe     = $args['exe'] ?? null;
if (!$destino || !$exe) {
    fwrite(STDERR, "Uso: php scripts/proteger.php --destino=PASTA --exe=EXECUTAVEL\n");
    exit(1);
}
if (!is_file($exe)) { fwrite(STDERR, "Executavel nao encontrado: {$exe}\n"); exit(1); }

@mkdir($destino, 0775, true);
$destino = realpath($destino) ?: $destino;

// ---------- Ficheiros que entram no cofre ----------

/** @return array<int,string> caminhos relativos */
function recolher(string $base, array $pastas, array $ficheiros): array {
    $out = $ficheiros;
    foreach ($pastas as $pasta) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $base . '/' . $pasta, FilesystemIterator::SKIP_DOTS
        ));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            $rel = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($base))), '/');
            if (str_ends_with($rel, '.php')) { $out[] = $rel; }
        }
    }
    sort($out);
    return array_values(array_unique($out));
}

$lista = recolher(ORIGEM, ['app', 'config'], [
    'index.php',
    'scripts/preparar-dados.php',
]);

// Nunca entram: configuracao local da maquina de desenvolvimento e testes.
$lista = array_values(array_filter($lista, static fn($r) => !str_contains($r, 'config.local')));

// Ficheiros de dados (nao PHP) que tambem devem ficar escondidos
$dados = ['database.sqlite.sql'];

// ---------- Preparacao do conteudo ----------

/**
 * Reescreve __DIR__ para o caminho equivalente dentro do cofre e liga
 * ROOT_PATH a pasta real de instalacao (onde vivem os recursos publicos).
 */
function adaptar(string $codigo, string $rel): string {
    $dir = dirname($rel);
    $dirCofre = 'cofre://' . ($dir === '.' ? '.' : $dir);

    // ROOT_PATH continua a ser uma pasta real (assets, storage, ...)
    $codigo = str_replace("define('ROOT_PATH', __DIR__)", "define('ROOT_PATH', FP_SITE)", $codigo);
    $codigo = str_replace("define('ROOT_PATH', dirname(__DIR__))", "define('ROOT_PATH', FP_SITE)", $codigo);
    $codigo = str_replace("or define('ROOT_PATH', dirname(__DIR__))", "or define('ROOT_PATH', FP_SITE)", $codigo);

    // Codigo e esquema vivem dentro do cofre, nao no disco
    $codigo = str_replace(
        ["ROOT_PATH . '/app/", "ROOT_PATH . '/config/", "ROOT_PATH . '/database.sqlite.sql'"],
        ["'cofre://app/", "'cofre://config/", "'cofre://database.sqlite.sql'"],
        $codigo
    );

    // Os restantes __DIR__ apontam para dentro do cofre (vistas, includes)
    return str_replace('__DIR__', var_export($dirCofre, true), $codigo);
}

$entradas = [];   // rel => ['pos'=>int,'tam'=>int,'iv'=>hex,'selo'=>hex]
$blobs = '';

$salt = random_bytes(16);
$impressao = hash_file('sha256', $exe, true);
$chave = hash_hkdf('sha256', $impressao, 32, 'FarmaPonto/cofre/v1', $salt);
$chaveSelo = hash_hkdf('sha256', $impressao, 32, 'FarmaPonto/selo/v1', $salt);

function guardar(string $rel, string $conteudo, string $chave, string $chaveSelo, array &$entradas, string &$blobs): void {
    $iv = random_bytes(16);
    $cif = openssl_encrypt($conteudo, 'aes-256-cbc', $chave, OPENSSL_RAW_DATA, $iv);
    if ($cif === false) { throw new RuntimeException('Falha ao cifrar ' . $rel); }
    $entradas[$rel] = [
        'pos'  => strlen($blobs),
        'tam'  => strlen($cif),
        'iv'   => bin2hex($iv),
        'selo' => hash_hmac('sha256', $cif, $chaveSelo),
    ];
    $blobs .= $cif;
}

foreach ($lista as $rel) {
    $codigo = php_strip_whitespace(ORIGEM . '/' . $rel); // remove comentarios e espacos
    if ($codigo === '') { $codigo = (string) file_get_contents(ORIGEM . '/' . $rel); }
    guardar($rel, adaptar($codigo, $rel), $chave, $chaveSelo, $entradas, $blobs);
}
foreach ($dados as $rel) {
    if (!is_file(ORIGEM . '/' . $rel)) { continue; }
    guardar($rel, (string) file_get_contents(ORIGEM . '/' . $rel), $chave, $chaveSelo, $entradas, $blobs);
}

// ---------- Escrita do cofre ----------

$indice = json_encode($entradas, JSON_UNESCAPED_SLASHES);
$ivIdx = random_bytes(16);
$indiceCif = openssl_encrypt($indice, 'aes-256-cbc', $chave, OPENSSL_RAW_DATA, $ivIdx);

$cabecalho = "FPCOFRE1" . $salt . $ivIdx . pack('N', strlen($indiceCif));
$cofre = $cabecalho . $indiceCif . $blobs;
$cofre .= hash_hmac('sha256', $cofre, $chaveSelo, true); // selo global (32 bytes)

file_put_contents($destino . '/cofre.dat', $cofre);

printf(
    "Cofre criado: %s\n  ficheiros: %d\n  tamanho: %.1f KB\n  impressao do executavel: %s...\n",
    $destino . '/cofre.dat',
    count($entradas),
    strlen($cofre) / 1024,
    substr(bin2hex($impressao), 0, 12)
);
