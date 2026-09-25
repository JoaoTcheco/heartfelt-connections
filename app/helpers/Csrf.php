<?php
/**
 * ============================================================
 * FarmaPonto - CSRF Helper
 * ============================================================
 */

namespace App\Helpers;

final class Csrf {

    public static function token(): string {
        Auth::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function valid(string $token): bool {
        Auth::start();
        return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }
}