<?php
/**
 * ============================================================
 * FarmaPonto - Armazenamento (ficheiros locais)
 * ============================================================
 * Responsabilidade: unica porta de entrada para ler/gravar ficheiros
 * (fotografias, logotipos, copias de seguranca). Isola o resto do
 * sistema do caminho fisico, o que torna a mudanca de maquina ou de
 * pasta trivial e mantem tudo offline (sem armazenamento na nuvem).
 *
 * Comunica com: Selfie, ConfigController (logo), Backup.
 */

namespace App\Core;

final class Armazenamento {

    private static function raiz(): string {
        $cfg = Database::config();
        return rtrim((string) ($cfg['storage_dir'] ?? ROOT_PATH . '/storage'), '/');
    }

    /** Caminho absoluto de um ficheiro dentro do armazenamento. */
    public static function caminho(string $relativo): string {
        $relativo = ltrim(str_replace(['..', '\\'], ['', '/'], $relativo), '/');
        return self::raiz() . '/' . $relativo;
    }

    public static function garantirPasta(string $relativo): string {
        $dir = self::caminho($relativo);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return $dir;
    }

    public static function guardar(string $relativo, string $conteudo): bool {
        $caminho = self::caminho($relativo);
        $dir = dirname($caminho);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        return file_put_contents($caminho, $conteudo) !== false;
    }

    public static function ler(string $relativo): ?string {
        $caminho = self::caminho($relativo);
        if (!is_file($caminho)) { return null; }
        $c = file_get_contents($caminho);
        return $c === false ? null : $c;
    }

    public static function existe(string $relativo): bool {
        return is_file(self::caminho($relativo));
    }

    public static function apagar(string $relativo): bool {
        $caminho = self::caminho($relativo);
        return is_file($caminho) ? @unlink($caminho) : false;
    }

    /** Espaco ocupado (bytes) por uma pasta do armazenamento. */
    public static function tamanhoPasta(string $relativo): int {
        $dir = self::caminho($relativo);
        if (!is_dir($dir)) { return 0; }
        $total = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if ($f->isFile()) { $total += $f->getSize(); } }
        return $total;
    }

    /** Espaco livre em disco (bytes). */
    public static function espacoLivre(): int {
        $b = @disk_free_space(self::raiz());
        return $b === false ? 0 : (int) $b;
    }
}
