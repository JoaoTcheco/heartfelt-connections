<?php
/**
 * ============================================================
 * FarmaPonto - DiagnosticoController
 * ============================================================
 * Responsabilidade: mostrar o estado tecnico do sistema (saude,
 * eventos, desempenho por rota, estrutura de dados e fluxos) e
 * expor /saude em JSON para monitorizacao local.
 * Comunica com: Saude, Registador, Database, Log.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Registador;
use App\Helpers\Auth;
use App\Helpers\Saude;
use App\Models\Log;
use PDO;
use Throwable;

final class DiagnosticoController extends Controller {

    public function index(): void {
        $this->requireRole('admin');
        $nivel = (string) ($_GET['nivel'] ?? '');

        $this->view('admin/diagnostico', [
            'titulo'      => 'Diagnóstico do Sistema',
            'meta_desc'   => 'Saúde, desempenho, eventos técnicos e estrutura de dados do FarmaPonto.',
            'saude'       => Saude::estado(),
            'eventos'     => Registador::eventos($nivel, 200),
            'contagens'   => Registador::contagens(24),
            'desempenho'  => Registador::desempenho(7, 15),
            'nivel'       => $nivel,
            'tabelas'     => $this->tabelas(),
            'ambiente'    => $this->ambiente(),
        ]);
    }

    /** Estado do sistema em JSON (para monitorizacao local/cron). */
    public function saude(): void {
        $estado = Saude::estado();
        $this->json($estado, $estado['estado'] === 'falha' ? 503 : 200);
    }

    public function purgar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();
        $dias = max(1, (int) ($_POST['dias'] ?? 30));
        $n = Registador::purgar($dias);
        Log::reg(Auth::id(), 'diagnostico_purgado', 'sistema_eventos', null, ['dias' => $dias, 'removidos' => $n]);
        $this->json(['ok' => true, 'removidos' => $n]);
    }

    /** Inventario das tabelas: linhas e espaco ocupado. */
    private function tabelas(): array {
        return Database::inventarioTabelas();
    }

    private function ambiente(): array {
        $cfg = Database::config();
        return [
            'PHP'                => PHP_VERSION,
            'Servidor'           => $_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI,
            'Modo'               => (string) ($cfg['modo'] ?? 'desenvolvimento'),
            'Base de dados'       => Database::motor() === 'sqlite'
                ? 'SQLite (ficheiro unico): ' . basename((string) ($cfg['db_file'] ?? 'farmaponto.db'))
                : $cfg['db_name'] . ' @ ' . $cfg['db_host'] . ':' . ($cfg['db_port'] ?? 3306),
            'Fuso (PHP)'         => date_default_timezone_get(),
            'Fuso (SQL)'         => (string) ($cfg['timezone_sql'] ?? ''),
            'Memória máxima'     => ini_get('memory_limit'),
            'Envio máximo'       => ini_get('upload_max_filesize'),
            'ZipArchive'         => class_exists(\ZipArchive::class) ? 'sim' : 'não',
            'GD (imagens)'       => extension_loaded('gd') ? 'sim' : 'não',
        ];
    }
}
