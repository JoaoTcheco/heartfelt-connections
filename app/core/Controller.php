<?php
/**
 * ============================================================
 * FarmaPonto - Controller Base
 * ============================================================
 * Responsabilidade: Render de views, JSON, validação RBAC e CSRF.
 */

namespace App\Core;

use App\Helpers\Auth;
use App\Helpers\Csrf;

abstract class Controller {

    protected function view(string $view, array $data = []): void {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        require __DIR__ . '/../views/layout/header.php';
        require $viewFile;
        require __DIR__ . '/../views/layout/footer.php';
    }

    protected function viewPartial(string $view, array $data = []): void {
        extract($data, EXTR_SKIP);
        require __DIR__ . '/../views/' . $view . '.php';
    }

    protected function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireLogin(): void {
        if (!Auth::user()) {
            $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== ''
                || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || $_SERVER['REQUEST_METHOD'] !== 'GET';
            if ($ajax) {
                $this->json(['ok' => false, 'erro' => 'Sessao expirada. Inicie sessao novamente.', 'login' => BASE_PATH . '/login'], 401);
            }
            $destino = $_SERVER['REQUEST_URI'] ?? (BASE_PATH . '/');
            header('Location: ' . BASE_PATH . '/login?redir=' . rawurlencode($destino));
            exit;
        }
    }

    protected function requireRole(string ...$roles): void {
        $u = Auth::user();
        if (!$u) {
            // Sessao expirada ou inexistente: pedidos AJAX recebem 401, paginas vao ao login
            $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== ''
                || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
            if ($ajax) {
                $this->json(['ok' => false, 'erro' => 'Sessao expirada. Inicie sessao novamente.', 'login' => BASE_PATH . '/login'], 401);
            }
            $destino = $_SERVER['REQUEST_URI'] ?? (BASE_PATH . '/');
            header('Location: ' . BASE_PATH . '/login?redir=' . rawurlencode($destino));
            exit;
        }
        if (!in_array($u['perfil'], $roles, true)) {
            http_response_code(403);
            $this->view('public/erro', ['titulo' => 'Acesso Negado', 'mensagem' => 'Nao tem permissao para aceder a esta pagina.']);
            exit;
        }
    }

    protected function checkCsrf(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !Csrf::valid($_POST['csrf'] ?? '')) {
            $this->json(['ok' => false, 'erro' => 'Token CSRF invalido. Recarregue a pagina.'], 419);
        }
    }

    protected function redirect(string $url): void {
        header("Location: $url");
        exit;
    }
}
