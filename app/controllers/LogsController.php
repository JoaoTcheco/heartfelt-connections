<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Helpers\Auth;
use App\Helpers\Csrf;
use App\Models\Log;
use App\Models\Funcionario;

final class LogsController extends Controller {

    public function index(): void {
        $this->requireRole('admin');
        $de = $_GET['de'] ?? date('Y-m-01');
        $ate = $_GET['ate'] ?? date('Y-m-d');
        $funcId = !empty($_GET['funcionario']) ? (int) $_GET['funcionario'] : null;
        $acao = $_GET['acao'] ?? null;
        $q = $_GET['q'] ?? null;

        $logs = (new Log())->listarComFiltros($de, $ate, $funcId, $acao, $q, 500);
        $funcionarios = (new Funcionario())->listarAtivos();

        $st = Database::pdo()->query("SELECT DISTINCT acao FROM logs WHERE acao IS NOT NULL AND acao != '' ORDER BY acao");
        $acoes = $st->fetchAll(\PDO::FETCH_COLUMN);

        $this->view('admin/logs', [
            'titulo'       => 'Logs do Sistema',
            'logs'         => $logs,
            'de'           => $de,
            'ate'          => $ate,
            'funcionario'  => $funcId,
            'acao'         => $acao,
            'q'            => $q,
            'funcionarios' => $funcionarios,
            'acoes'        => $acoes,
        ]);
    }

    public function exportarPdf(): void {
        $this->requireRole('admin');
        $de = $_GET['de'] ?? date('Y-m-01');
        $ate = $_GET['ate'] ?? date('Y-m-d');
        $funcId = !empty($_GET['funcionario']) ? (int) $_GET['funcionario'] : null;
        $acao = $_GET['acao'] ?? null;
        $q = $_GET['q'] ?? null;

        $logs = (new Log())->listarComFiltros($de, $ate, $funcId, $acao, $q, 2000);
        require __DIR__ . '/../views/admin/logs_pdf.php';
    }

    public function eliminar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $de = $_POST['de'] ?? date('Y-m-01');
        $ate = $_POST['ate'] ?? date('Y-m-d');
        $funcId = !empty($_POST['funcionario']) ? (int) $_POST['funcionario'] : null;
        $acao = $_POST['acao'] ?? null;
        $q = $_POST['q'] ?? null;

        $where = [];
        $params = [];
        if ($de) { $where[] = 'DATE(criado_em) >= ?'; $params[] = $de; }
        if ($ate) { $where[] = 'DATE(criado_em) <= ?'; $params[] = $ate; }
        if ($funcId) { $where[] = 'funcionario_id = ?'; $params[] = $funcId; }
        if ($acao) { $where[] = 'acao = ?'; $params[] = $acao; }
        if ($q) {
            $where[] = '(acao LIKE ? OR entidade LIKE ? OR detalhes LIKE ?)';
            $w = "%$q%";
            $params = array_merge($params, [$w, $w, $w]);
        }

        $sql = 'SELECT COUNT(*) FROM logs';
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        if ($total > 0) {
            $sqlDel = 'DELETE FROM logs';
            if (!empty($where)) $sqlDel .= ' WHERE ' . implode(' AND ', $where);
            $st = Database::pdo()->prepare($sqlDel);
            $st->execute($params);

            Log::reg(Auth::id(), 'logs_eliminados', 'logs', null, [
                'de' => $de, 'ate' => $ate,
                'funcionario_id' => $funcId,
                'acao' => $acao,
                'q' => $q,
                'total' => $total,
            ]);
        }

        $this->json(['ok' => true, 'total' => $total]);
    }
}
