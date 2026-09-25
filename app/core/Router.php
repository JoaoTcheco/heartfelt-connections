<?php
/**
 * ============================================================
 * FarmaPonto - Router (Front Controller Dispatcher)
 * ============================================================
 */

namespace App\Core;

final class Router {
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $handler): void {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Rotas exatas
        if (isset($this->routes[$method][$path])) {
            [$ctrl, $action] = $this->routes[$method][$path];
            (new $ctrl())->$action();
            return;
        }

        // Rotas com parâmetros {id}
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $temp = preg_replace('#\{[^/]+\}#', '___P___', $route);
            $quoted = preg_quote($temp, '#');
            $pattern = '#^' . str_replace('___P___', '([^/]+)', $quoted) . '$#';
            if (preg_match($pattern, $path, $m)) {
                array_shift($m);
                [$ctrl, $action] = $handler;
                (new $ctrl())->$action(...$m);
                return;
            }
        }

        // 404 estilizado
        http_response_code(404);
        $titulo = 'Página não encontrada (404)';
        $erro404 = true;
        $erro404Path = htmlspecialchars($path);
        require __DIR__ . '/../views/layout/header.php';
        ?>
        <div class="min-h-[70vh] flex items-center justify-center p-8">
            <div class="text-center max-w-md">
                <div class="text-7xl font-extrabold text-emerald-600">404</div>
                <h1 class="text-2xl font-bold text-gray-900 mt-3">Página não encontrada</h1>
                <p class="text-gray-500 mt-2 text-sm">O endereço <code class="bg-gray-100 px-1.5 py-0.5 rounded"><?= $erro404Path ?></code> não existe ou foi removido.</p>
                <div class="mt-6 flex items-center justify-center gap-2">
                    <a href="<?= BASE_PATH ?>/" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium">
                        <i class="fa-solid fa-house mr-1"></i> Início
                    </a>
                    <a href="javascript:history.back()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">Voltar</a>
                </div>
            </div>
        </div>
        <?php
        require __DIR__ . '/../views/layout/footer.php';
    }
}
