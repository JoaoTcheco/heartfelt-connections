<?php
/**
 * ============================================================
 * FarmaPonto - Config Model
 * ============================================================
 * Responsabilidade: Gerenciar configuracoes do sistema.
 */

namespace App\Models;

use App\Core\Database;

final class Config {

    public function get(string $chave, string $padrao = ''): string {
        $st = Database::pdo()->prepare("SELECT valor FROM config WHERE chave = ?");
        $st->execute([$chave]);
        $r = $st->fetch();
        return $r['valor'] ?? $padrao;
    }

    public function set(string $chave, string $valor): void {
        $st = Database::pdo()->prepare("INSERT INTO config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $st->execute([$chave, $valor, $valor]);
    }

    public function todas(): array {
        $st = Database::pdo()->query("SELECT chave, valor FROM config");
        $configs = [];
        foreach ($st->fetchAll() as $row) {
            $configs[$row['chave']] = $row['valor'];
        }
        return $configs;
    }
}
