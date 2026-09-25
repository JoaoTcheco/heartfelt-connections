<?php
/**
 * ============================================================
 * FarmaPonto - Carregador do cofre de codigo
 * ============================================================
 * Responsabilidade: abrir o cofre cifrado (cofre.dat), verificar a sua
 * integridade e disponibilizar o codigo da aplicacao ao PHP atraves de um
 * protocolo interno em memoria (cofre://...). Nenhum ficheiro de logica de
 * negocio e escrito em disco em texto legivel.
 *
 * A chave nao esta em lado nenhum: e derivada da impressao digital do
 * executavel do programa (FP_HOST_BIN). Copiar o cofre para outro
 * computador ou alterar o executavel torna o conteudo ilegivel.
 *
 * 100% offline: usa apenas openssl e hash, ambos do proprio PHP.
 */

declare(strict_types=1);

final class Cofre {

    private const MAGIA = 'FPCOFRE1';

    private static string $bruto = '';
    /** @var array<string,array{pos:int,tam:int,iv:string,selo:string}> */
    private static array $indice = [];
    private static int $base = 0;
    private static string $chave = '';
    private static string $chaveSelo = '';
    /** @var array<string,string> cache do codigo ja decifrado */
    private static array $cache = [];

    // ---------- Arranque ----------

    public static function arrancar(string $pastaSite): void {
        if (!defined('FP_SITE')) { define('FP_SITE', $pastaSite); }

        $ficheiro = $pastaSite . '/cofre.dat';
        $exe = getenv('FP_HOST_BIN') ?: '';
        if (!is_file($ficheiro) || !is_file($exe)) { self::morrer(); }

        self::$bruto = (string) file_get_contents($ficheiro);
        if (strncmp(self::$bruto, self::MAGIA, 8) !== 0 || strlen(self::$bruto) < 100) { self::morrer(); }

        $impressao = hash_file('sha256', $exe, true);
        $salt = substr(self::$bruto, 8, 16);
        self::$chave     = hash_hkdf('sha256', $impressao, 32, 'FarmaPonto/cofre/v1', $salt);
        self::$chaveSelo = hash_hkdf('sha256', $impressao, 32, 'FarmaPonto/selo/v1', $salt);

        // Selo global: garante que ninguem mexeu no cofre
        $corpo = substr(self::$bruto, 0, -32);
        $selo  = substr(self::$bruto, -32);
        if (!hash_equals(hash_hmac('sha256', $corpo, self::$chaveSelo, true), $selo)) { self::morrer(); }

        $ivIdx = substr(self::$bruto, 24, 16);
        $tamIdx = unpack('N', substr(self::$bruto, 40, 4))[1];
        $indiceCif = substr(self::$bruto, 44, $tamIdx);
        $json = openssl_decrypt($indiceCif, 'aes-256-cbc', self::$chave, OPENSSL_RAW_DATA, $ivIdx);
        $indice = $json === false ? null : json_decode($json, true);
        if (!is_array($indice)) { self::morrer(); }

        self::$indice = $indice;
        self::$base = 44 + $tamIdx;

        stream_wrapper_register('cofre', self::class . 'Stream');

        // Autoloader: App\Core\Database -> cofre://app/core/Database.php
        spl_autoload_register(static function (string $classe): void {
            if (strncmp($classe, 'App\\', 4) !== 0) { return; }
            $rel = str_replace('\\', '/', substr($classe, 4));
            $partes = explode('/', $rel);
            $nome = array_pop($partes);
            $pasta = strtolower(implode('/', $partes));
            $alvo = 'app/' . ($pasta !== '' ? $pasta . '/' : '') . $nome . '.php';
            if (self::existe($alvo)) { require 'cofre://' . $alvo; }
        });
    }

    // ---------- Acesso ao conteudo ----------

    public static function normalizar(string $caminho): string {
        $caminho = preg_replace('#^cofre://#', '', $caminho) ?? $caminho;
        $caminho = str_replace('\\', '/', $caminho);
        $saida = [];
        foreach (explode('/', $caminho) as $parte) {
            if ($parte === '' || $parte === '.') { continue; }
            if ($parte === '..') { array_pop($saida); continue; }
            $saida[] = $parte;
        }
        return implode('/', $saida);
    }

    public static function existe(string $caminho): bool {
        return isset(self::$indice[self::normalizar($caminho)]);
    }

    public static function ler(string $caminho): ?string {
        $rel = self::normalizar($caminho);
        if (isset(self::$cache[$rel])) { return self::$cache[$rel]; }
        $e = self::$indice[$rel] ?? null;
        if ($e === null) { return null; }

        $cif = substr(self::$bruto, self::$base + $e['pos'], $e['tam']);
        if (!hash_equals(hash_hmac('sha256', $cif, self::$chaveSelo), (string) $e['selo'])) { self::morrer(); }
        $claro = openssl_decrypt($cif, 'aes-256-cbc', self::$chave, OPENSSL_RAW_DATA, hex2bin($e['iv']));
        if ($claro === false) { self::morrer(); }

        return self::$cache[$rel] = $claro;
    }

    public static function tamanho(string $caminho): int {
        $c = self::ler($caminho);
        return $c === null ? 0 : strlen($c);
    }

    /** Mensagem unica e neutra: nunca revela detalhes tecnicos. */
    private static function morrer(): never {
        http_response_code(500);
        $html = '<!doctype html><meta charset="utf-8"><title>FarmaPonto</title>'
            . '<div style="font:16px/1.6 system-ui;padding:40px;max-width:640px;margin:auto">'
            . '<h1 style="font-size:20px">Instalação inválida</h1>'
            . '<p>Este programa não pôde ser iniciado porque os seus ficheiros foram alterados '
            . 'ou copiados para outro local. Volte a instalar o FarmaPonto a partir do instalador original.</p></div>';
        if (PHP_SAPI === 'cli') { fwrite(STDERR, "Instalacao invalida.\n"); } else { echo $html; }
        exit(1);
    }
}

/**
 * Protocolo cofre:// — apenas leitura, apenas em memoria.
 */
final class CofreStream {

    /** @var resource|null contexto do fluxo (exigido pelo PHP) */
    public $context;

    private string $dados = '';
    private int $pos = 0;

    public function stream_open(string $caminho, string $modo, int $opcoes, ?string &$aberto): bool {
        if (!str_contains($modo, 'r')) { return false; }
        $c = Cofre::ler($caminho);
        if ($c === null) { return false; }
        $this->dados = $c;
        $this->pos = 0;
        $aberto = $caminho;
        return true;
    }

    public function stream_read(int $quantidade): string {
        $t = substr($this->dados, $this->pos, $quantidade);
        $this->pos += strlen($t);
        return $t;
    }

    public function stream_write(string $dados): int { return 0; }
    public function stream_tell(): int { return $this->pos; }
    public function stream_eof(): bool { return $this->pos >= strlen($this->dados); }

    public function stream_seek(int $desvio, int $modo = SEEK_SET): bool {
        $tam = strlen($this->dados);
        $novo = match ($modo) {
            SEEK_CUR => $this->pos + $desvio,
            SEEK_END => $tam + $desvio,
            default  => $desvio,
        };
        if ($novo < 0 || $novo > $tam) { return false; }
        $this->pos = $novo;
        return true;
    }

    public function stream_stat(): array {
        return ['size' => strlen($this->dados), 'mode' => 0100444];
    }

    public function stream_set_option(int $opcao, int $a, int $b): bool { return true; }

    public function url_stat(string $caminho, int $bandeiras): array|false {
        if (!Cofre::existe($caminho)) { return false; }
        return ['size' => Cofre::tamanho($caminho), 'mode' => 0100444];
    }
}
