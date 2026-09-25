<?php
/**
 * ============================================================
 * FarmaPonto - PasswordReset Model
 * ============================================================
 * Tokens de recuperação de palavra-passe.
 */

namespace App\Models;

use App\Core\Database;

final class PasswordReset {

    public function criar(int $funcionarioId, int $minutosValidade = 60): string {
        $token = bin2hex(random_bytes(32));
        $exp = (new \DateTime("+{$minutosValidade} minutes"))->format('Y-m-d H:i:s');
        $st = Database::pdo()->prepare(
            "INSERT INTO password_resets (funcionario_id, token, expira_em) VALUES (?, ?, ?)"
        );
        $st->execute([$funcionarioId, $token, $exp]);
        return $token;
    }

    public function valido(string $token): ?array {
        $st = Database::pdo()->prepare(
            "SELECT * FROM password_resets
             WHERE token = ? AND usado = 0 AND expira_em > NOW() LIMIT 1"
        );
        $st->execute([$token]);
        return $st->fetch() ?: null;
    }

    public function marcarUsado(int $id): void {
        Database::pdo()->prepare("UPDATE password_resets SET usado = 1 WHERE id = ?")->execute([$id]);
    }

    public function limparExpirados(): int {
        return Database::pdo()->exec("DELETE FROM password_resets WHERE expira_em < NOW() OR usado = 1");
    }
}
