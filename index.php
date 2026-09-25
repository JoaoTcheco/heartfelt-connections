<?php
/**
 * ============================================================
 * FarmaPonto - Front Controller
 * ============================================================
 */

date_default_timezone_set('Africa/Maputo');

define('ROOT_PATH', __DIR__);
define('INICIO_PEDIDO', microtime(true));
// Identificador curto do pedido: aparece nos registos tecnicos e na pagina de
// erro, permitindo ligar o que o utilizador viu ao que o sistema registou.
define('PEDIDO_ID', substr(bin2hex(random_bytes(6)), 0, 10));

// Autoloader PSR-4 simples.
// Tolerante a maiusculas/minusculas: as pastas do projecto estao em minusculas
// (app/core, app/models, ...) enquanto os namespaces usam CamelCase
// (App\\Core, App\\Models, ...). Em Linux o sistema de ficheiros e
// case-sensitive, por isso tentamos tambem a variante em minusculas.
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) { return; }

    $relative = str_replace('\\', '/', substr($class, $len));
    $segments = explode('/', $relative);
    $file = array_pop($segments);

    $candidatos = [
        $baseDir . implode('/', $segments) . ($segments ? '/' : '') . $file . '.php',
        $baseDir . strtolower(implode('/', $segments)) . ($segments ? '/' : '') . $file . '.php',
    ];

    foreach ($candidatos as $caminho) {
        if (is_file($caminho)) { require $caminho; return; }
    }
});

// ===== Configuracao por modo (producao esconde detalhes tecnicos) =====
$__cfg = require __DIR__ . '/config/config.php';
$__producao = ($__cfg['modo'] ?? 'desenvolvimento') === 'producao';
ini_set('display_errors', $__producao ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ===== Rede de seguranca: nada sai para o utilizador sem ser tratado =====
set_exception_handler(function (Throwable $e) use ($__producao) {
    $ref = App\Core\PaginaErro::referencia();
    App\Core\Registador::excecao($e, $ref);
    if (!$__producao && PHP_SAPI === 'cli') { throw $e; }
    App\Core\PaginaErro::mostrar(
        'Não foi possível concluir esta operação',
        $__producao
            ? 'O sistema encontrou um problema inesperado e nada foi gravado a meio. Tente novamente; se repetir, mostre o código abaixo a quem administra o sistema.'
            : 'Detalhe técnico: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
        $ref
    );
});
set_error_handler(function (int $no, string $msg, string $ficheiro = '', int $linha = 0) {
    if (!(error_reporting() & $no)) { return false; }
    App\Core\Registador::aviso($msg, ['ficheiro' => basename($ficheiro) . ':' . $linha], 'php');
    return false; // deixa o PHP seguir o seu curso normal
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        App\Core\Registador::erro($e['message'], ['ficheiro' => basename($e['file']) . ':' . $e['line']], 'fatal');
        return;
    }
    // Amostragem de desempenho: 1 em cada 20 pedidos, para nao pesar.
    if (random_int(1, 20) === 1) {
        $rota = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $rota = preg_replace('#/\d+(?=/|$)#', '/{id}', $rota) ?? $rota;
        App\Core\Registador::metrica($rota, (microtime(true) - INICIO_PEDIDO) * 1000, http_response_code() ?: 200);
    }
});

session_start();

// ===== Cópia de segurança diária automática (sem depender de cron) =====
App\Helpers\Manutencao::diaria();

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
define('BASE_PATH', $basePath);

use App\Core\Router;
$router = new Router();

// ===== Rota raiz =====
$router->get(BASE_PATH . '/', [App\Controllers\AuthController::class, 'root']);

// ===== Rotas Publicas =====
$router->get(BASE_PATH . '/login', [App\Controllers\AuthController::class, 'loginForm']);
$router->post(BASE_PATH . '/login', [App\Controllers\AuthController::class, 'login']);
$router->get(BASE_PATH . '/logout', [App\Controllers\AuthController::class, 'logout']);

// Auto-registo
$router->get(BASE_PATH . '/register', [App\Controllers\AuthController::class, 'registerForm']);
$router->post(BASE_PATH . '/register', [App\Controllers\AuthController::class, 'register']);

// Recuperação de password
$router->get(BASE_PATH . '/forgot-password', [App\Controllers\AuthController::class, 'forgotForm']);
$router->post(BASE_PATH . '/forgot-password', [App\Controllers\AuthController::class, 'forgot']);
$router->get(BASE_PATH . '/reset-password', [App\Controllers\AuthController::class, 'resetForm']);
$router->post(BASE_PATH . '/reset-password', [App\Controllers\AuthController::class, 'reset']);

// QuickPunch
$router->get(BASE_PATH . '/quickpunch', [App\Controllers\QuickPunchController::class, 'index']);
$router->post(BASE_PATH . '/quickpunch/verificar-pin', [App\Controllers\QuickPunchController::class, 'verificarPin']);
$router->post(BASE_PATH . '/quickpunch/punch', [App\Controllers\QuickPunchController::class, 'punch']);
$router->post(BASE_PATH . '/quickpunch/digital', [App\Controllers\DigitaisController::class, 'punch']);

// ===== Rotas Autenticadas =====
$router->get(BASE_PATH . '/dashboard', [App\Controllers\DashboardController::class, 'index']);
$router->get(BASE_PATH . '/dashboard/resumo', [App\Controllers\DashboardController::class, 'resumo']);
$router->get(BASE_PATH . '/dashboard/registos-hoje', [App\Controllers\DashboardController::class, 'registosHoje']);
$router->get(BASE_PATH . '/marcar', [App\Controllers\MarcarController::class, 'index']);
$router->post(BASE_PATH . '/marcar/registar', [App\Controllers\MarcarController::class, 'registar']);
$router->get(BASE_PATH . '/historico', [App\Controllers\HistoricoController::class, 'index']);
$router->get(BASE_PATH . '/perfil', [App\Controllers\PerfilController::class, 'index']);
$router->post(BASE_PATH . '/perfil/atualizar', [App\Controllers\PerfilController::class, 'atualizar']);
$router->post(BASE_PATH . '/perfil/pin', [App\Controllers\PerfilController::class, 'alterarPin']);
$router->post(BASE_PATH . '/perfil/password', [App\Controllers\PerfilController::class, 'alterarPassword']);

// ===== Rotas Admin/Gestor =====
$router->get(BASE_PATH . '/funcionarios', [App\Controllers\FuncionariosController::class, 'lista']);
$router->get(BASE_PATH . '/funcionarios/novo', [App\Controllers\FuncionariosController::class, 'novoForm']);
$router->get(BASE_PATH . '/funcionario/{id}', [App\Controllers\FuncionariosController::class, 'detalhe']);
$router->get(BASE_PATH . '/funcionarios/dados/{id}', [App\Controllers\FuncionariosController::class, 'dados']);
$router->post(BASE_PATH . '/funcionarios/criar', [App\Controllers\FuncionariosController::class, 'criar']);
$router->post(BASE_PATH . '/funcionarios/atualizar/{id}', [App\Controllers\FuncionariosController::class, 'atualizar']);
$router->post(BASE_PATH . '/funcionarios/toggle/{id}', [App\Controllers\FuncionariosController::class, 'toggleAtivo']);
$router->post(BASE_PATH . '/funcionarios/eliminar/{id}', [App\Controllers\FuncionariosController::class, 'eliminar']);
$router->get (BASE_PATH . '/funcionarios/credenciais/{id}', [App\Controllers\FuncionariosController::class, 'credenciais']);
$router->post(BASE_PATH . '/funcionarios/pin/{id}', [App\Controllers\FuncionariosController::class, 'resetarPin']);
$router->post(BASE_PATH . '/funcionarios/ferias/{id}', [App\Controllers\FuncionariosController::class, 'adicionarFerias']);
$router->post(BASE_PATH . '/funcionarios/ferias/remover/{id}', [App\Controllers\FuncionariosController::class, 'removerFerias']);
$router->get (BASE_PATH . '/funcionarios/ferias/listar/{id}',  [App\Controllers\FuncionariosController::class, 'listarFerias']);
$router->post(BASE_PATH . '/funcionarios/ferias/editar/{id}',  [App\Controllers\FuncionariosController::class, 'editarFerias']);
$router->post(BASE_PATH . '/funcionarios/dias-trabalho/{id}', [App\Controllers\FuncionariosController::class, 'salvarDiasTrabalho']);
$router->get (BASE_PATH . '/funcionarios/dias-trabalho/{id}/{anoMes}', [App\Controllers\FuncionariosController::class, 'getDiasTrabalho']);
$router->get (BASE_PATH . '/funcionarios/digitais/{id}', [App\Controllers\DigitaisController::class, 'listar']);
$router->post(BASE_PATH . '/funcionarios/digitais/{id}', [App\Controllers\DigitaisController::class, 'guardar']);
$router->post(BASE_PATH . '/funcionarios/digitais/remover/{id}', [App\Controllers\DigitaisController::class, 'remover']);
$router->get (BASE_PATH . '/funcionarios/stats/{id}', [App\Controllers\FuncionariosController::class, 'statsJson']);
$router->get (BASE_PATH . '/funcionarios/ajustes/{id}', [App\Controllers\FuncionariosController::class, 'listarAjustes']);
$router->post(BASE_PATH . '/funcionarios/ajustes/{id}', [App\Controllers\FuncionariosController::class, 'guardarAjuste']);
$router->post(BASE_PATH . '/funcionarios/ajustes/remover/{id}', [App\Controllers\FuncionariosController::class, 'removerAjuste']);


$router->get(BASE_PATH . '/minha-assiduidade', [App\Controllers\RelatoriosController::class, 'minhaAssiduidade']);
$router->get(BASE_PATH . '/relatorios', [App\Controllers\RelatoriosController::class, 'mensal']);
$router->get(BASE_PATH . '/relatorios/csv', [App\Controllers\RelatoriosController::class, 'exportarCsv']);
$router->get(BASE_PATH . '/relatorios/pdf', [App\Controllers\RelatoriosController::class, 'exportarPdf']);
$router->get (BASE_PATH . '/relatorios/detalhe', [App\Controllers\RelatoriosController::class, 'detalhe']);
$router->get (BASE_PATH . '/relatorios/recibo',  [App\Controllers\RelatoriosController::class, 'recibo']);
$router->get (BASE_PATH . '/relatorios/recibos', [App\Controllers\RelatoriosController::class, 'recibosLote']);

// Folhas salariais (fecho / historico auditavel; o recalculo continua livre)
$router->get (BASE_PATH . '/folhas',           [App\Controllers\RelatoriosController::class, 'folhas']);
$router->get (BASE_PATH . '/folhas/{id}',      [App\Controllers\RelatoriosController::class, 'verFolha']);
$router->post(BASE_PATH . '/folhas/fechar',    [App\Controllers\RelatoriosController::class, 'fecharFolha']);
$router->post(BASE_PATH . '/folhas/reabrir',   [App\Controllers\RelatoriosController::class, 'reabrirFolha']);
$router->post(BASE_PATH . '/folhas/eliminar',  [App\Controllers\RelatoriosController::class, 'eliminarFolha']);
$router->get (BASE_PATH . '/relatorios/modelos', [App\Controllers\RelatoriosController::class, 'modelos']);
$router->post(BASE_PATH . '/relatorios/modelos/guardar', [App\Controllers\RelatoriosController::class, 'guardarModelo']);
$router->post(BASE_PATH . '/relatorios/modelos/eliminar', [App\Controllers\RelatoriosController::class, 'eliminarModelo']);
$router->post(BASE_PATH . '/relatorios/processar-faltas', [App\Controllers\RelatoriosController::class, 'processarFaltas']);

$router->get(BASE_PATH . '/selfie/{id}', [App\Controllers\SelfieController::class, 'ver']);

$router->get(BASE_PATH . '/logs', [App\Controllers\LogsController::class, 'index']);
$router->get(BASE_PATH . '/logs/pdf', [App\Controllers\LogsController::class, 'exportarPdf']);
$router->post(BASE_PATH . '/logs/eliminar', [App\Controllers\LogsController::class, 'eliminar']);

// Auditoria (vista enriquecida sobre logs)
$router->get(BASE_PATH . '/auditoria', [App\Controllers\AuditoriaController::class, 'index']);
$router->get(BASE_PATH . '/auditoria/detalhe/{id}', [App\Controllers\AuditoriaController::class, 'detalhe']);

$router->get(BASE_PATH . '/historico/pdf', [App\Controllers\HistoricoController::class, 'exportarPdf']);
$router->post(BASE_PATH . '/historico/eliminar', [App\Controllers\HistoricoController::class, 'eliminar']);

$router->get(BASE_PATH . '/configuracoes', [App\Controllers\ConfigController::class, 'index']);
$router->post(BASE_PATH . '/configuracoes/salvar', [App\Controllers\ConfigController::class, 'salvar']);
$router->post(BASE_PATH . '/configuracoes/limpar-selfies', [App\Controllers\ConfigController::class, 'limparSelfies']);
$router->post(BASE_PATH . '/configuracoes/logo', [App\Controllers\ConfigController::class, 'uploadLogo']);
$router->post(BASE_PATH . '/configuracoes/logo/remover', [App\Controllers\ConfigController::class, 'removerLogo']);
$router->get (BASE_PATH . '/logo',                       [App\Controllers\ConfigController::class, 'serveLogo']);

// ===== Copias de seguranca (admin) =====
$router->get (BASE_PATH . '/backup',            [App\Controllers\BackupController::class, 'index']);
$router->get (BASE_PATH . '/backup/download',   [App\Controllers\BackupController::class, 'download']);
$router->post(BASE_PATH . '/backup/criar',      [App\Controllers\BackupController::class, 'criar']);
$router->post(BASE_PATH . '/backup/completo',   [App\Controllers\BackupController::class, 'criarCompleto']);
$router->post(BASE_PATH . '/backup/apagar',     [App\Controllers\BackupController::class, 'apagar']);
$router->post(BASE_PATH . '/backup/restaurar',  [App\Controllers\BackupController::class, 'restaurar']);

// ===== Diagnostico e saude =====
$router->get (BASE_PATH . '/diagnostico',         [App\Controllers\DiagnosticoController::class, 'index']);
$router->post(BASE_PATH . '/diagnostico/purgar',  [App\Controllers\DiagnosticoController::class, 'purgar']);
$router->get (BASE_PATH . '/saude',               [App\Controllers\DiagnosticoController::class, 'saude']);
$router->get (BASE_PATH . '/api/v1/saude',        [App\Controllers\DiagnosticoController::class, 'saude']);

// ===== Lixeira (recuperacao de registos eliminados) =====
$router->get (BASE_PATH . '/lixeira',              [App\Controllers\LixeiraController::class, 'index']);
$router->post(BASE_PATH . '/lixeira/recuperar',    [App\Controllers\LixeiraController::class, 'recuperar']);
$router->post(BASE_PATH . '/lixeira/eliminar',     [App\Controllers\LixeiraController::class, 'eliminarDefinitivo']);

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$router->dispatch($method, $uri);
