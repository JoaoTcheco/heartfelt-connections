<?php
/**
 * ============================================================
 * FarmaPonto - Cripto Helper
 * ============================================================
 * Cifra reversivel (AES-256-CBC) para credenciais operacionais que o
 * administrador precisa de consultar (PIN do terminal e password inicial).
 *
 * NAO substitui os hashes bcrypt: a autenticacao continua a usar
 * password_verify(). Esta cifra existe apenas para permitir a leitura
 * controlada (apenas perfil admin) na ficha do funcionario.
 *
 * Chave: guardada na tabela config (chave 'app_key'), gerada uma unica vez
 * com random_bytes(32). Sem dependencias externas (usa apenas OpenSSL do PHP).
 */

namespace App\Helpers;

use App\Models\Config;

final class Cripto {

    private const METODO = 'aes-256-cbc';
    private static ?string $chave = null;

    /** Devolve (e cria na primeira utilizacao) a chave binaria de 32 bytes. */
    private static function chave(): string {
        if (self::$chave !== null) { return self::$chave; }
        $cfg = new Config();
        $b64 = $cfg->get('app_key', '');
        if ($b64 === '' || strlen((string) base64_decode($b64, true)) !== 32) {
            $b64 = base64_encode(random_bytes(32));
            $cfg->set('app_key', $b64);
        }
        self::$chave = (string) base64_decode($b64, true);
        return self::$chave;
    }

    /** Cifra um texto simples. Devolve string base64 (iv + ciphertext) ou null. */
    public static function cifrar(?string $texto): ?string {
        if ($texto === null || $texto === '') { return null; }
        try {
            $iv = random_bytes(16);
            $enc = openssl_encrypt($texto, self::METODO, self::chave(), OPENSSL_RAW_DATA, $iv);
            if ($enc === false) { return null; }
            return base64_encode($iv . $enc);
        } catch (\Throwable $e) {
            error_log('[Cripto] cifrar: ' . $e->getMessage());
            return null;
        }
    }

    /** Decifra um valor produzido por cifrar(). Devolve null se invalido. */
    public static function decifrar(?string $valor): ?string {
        if ($valor === null || $valor === '') { return null; }
        try {
            $raw = base64_decode($valor, true);
            if ($raw === false || strlen($raw) <= 16) { return null; }
            $iv = substr($raw, 0, 16);
            $dec = openssl_decrypt(substr($raw, 16), self::METODO, self::chave(), OPENSSL_RAW_DATA, $iv);
            return $dec === false ? null : $dec;
        } catch (\Throwable $e) {
            error_log('[Cripto] decifrar: ' . $e->getMessage());
            return null;
        }
    }
}
