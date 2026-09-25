<?php
/**
 * ============================================================
 * FarmaPonto - Auth Helper
 * ============================================================
 * Responsabilidade: Autenticacao, sessao, validacao de PIN.
 */

namespace App\Helpers;

use App\Models\Funcionario;

final class Auth {

    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'httponly' => true,
                'secure'   => false,
                'samesite' => 'Lax'
            ]);
            session_start();

            if (!empty($_SESSION['user']['ts']) && time() - $_SESSION['user']['ts'] > 3600) {
                session_destroy();
                $_SESSION = [];
            }

            if (!empty($_SESSION['user'])) {
                $_SESSION['user']['ts'] = time();
            }
        }
    }

    public static function login(string $email, string $pass): bool {
        self::start();
        $u = (new Funcionario())->porEmail($email);

        if (!$u || !$u['ativo'] || !password_verify($pass, $u['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        unset($u['password_hash'], $u['pin_hash']);
        $u['ts'] = time();
        $_SESSION['user'] = $u;
        return true;
    }

    public static function logout(): void {
        self::start();
        session_destroy();
        $_SESSION = [];
    }

    public static function user(): ?array {
        self::start();
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int {
        $id = self::user()['id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public static function validarPin(int $userId, string $pin): bool {
        $u = (new Funcionario())->porId($userId);
        return $u && password_verify($pin, $u['pin_hash']);
    }

    /**
     * O utilizador em sessao marca ponto (entra na assiduidade)?
     * Utilizadores administrativos (marca_ponto = 0) nao marcam ponto nem
     * geram faltas/atrasos.
     */
    public static function marcaPonto(): bool {
        $u = self::user();
        if (!$u) { return false; }
        if (array_key_exists('marca_ponto', $u)) {
            return (int) $u['marca_ponto'] === 1;
        }
        // Sessao antiga (sem o campo): consultar a base de dados.
        return (new Funcionario())->pontua((int) $u['id']);
    }

    public static function isAdmin(): bool {
        return (self::user()['perfil'] ?? '') === 'admin';
    }

    public static function isGestor(): bool {
        $p = self::user()['perfil'] ?? '';
        return $p === 'admin' || $p === 'gestor';
    }
}
