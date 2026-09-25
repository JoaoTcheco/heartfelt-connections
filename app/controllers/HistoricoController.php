<?php
/**
 * ============================================================
 * FarmaPonto - HistoricoController
 * ============================================================
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Core\Database;
use App\Models\Registo;
use App\Models\Funcionario;
use App\Models\Log;

final class HistoricoController extends Controller {

    public function index(): void {
        $this->requireLogin();

        $mes = $_GET['mes'] ?? '';
        if ($mes && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            [$ano, $m] = explode('-', $mes);
            $de  = sprintf('%04d-%02d-01', $ano, $m);
            $ate = date('Y-m-t', strtotime($de));
        } else {
            $de  = $_GET['de'] ?? date('Y-m-01');
            $ate = $_GET['ate'] ?? date('Y-m-d');
            $ano = (int) date('Y', strtotime($de));
            $m   = (int) date('m', strtotime($de));
            $mes = sprintf('%04d-%02d', $ano, $m);
        }

        $funcId = $_GET['funcionario'] ?? null;
        $funcId = $funcId ? (int) $funcId : null;
        $tipo = $_GET['tipo'] ?? null;

        if (!Auth::isGestor()) {
            $funcId = Auth::id();
        }

        $regs = (new Registo())->listarPorPeriodo($funcId, $de, $ate, $tipo);

        if ($tipo === null) {
            $regs = $this->preencherFaltas($regs, $funcId, $ano, $m, $de, $ate);
        }

        $funcionarios = Auth::isGestor() ? (new Funcionario())->listarPontuaveis() : [];
        $tolerancia = (int) (new \App\Models\Config())->get('tolerancia_atraso_min', '5');

        $this->view('admin/historico', [
            'titulo'       => 'Historico',
            'regs'         => $regs,
            'de'           => $de,
            'ate'          => $ate,
            'funcionario'  => $funcId,
            'funcionarios' => $funcionarios,
            'tipo'         => $tipo,
            'mes'          => $mes,
            'tolerancia'   => $tolerancia,
        ]);
    }

    private function preencherFaltas(array $regs, ?int $funcId, int $ano, int $mes, string $de, string $ate): array {
        $registoPorFuncDia = [];
        foreach ($regs as $r) {
            $data = date('Y-m-d', strtotime($r['marcado_em']));
            $fid = $r['funcionario_id'];
            $registoPorFuncDia[$fid][$data] = true;
        }

        $funcModel = new Funcionario();
        $funcs = $funcId ? [$funcModel->porId($funcId)] : $funcModel->listarPontuaveis();
        $funcs = array_filter($funcs);

        if (empty($funcs)) return $regs;

        $hoje = date('Y-m-d');
        $novos = [];
        $dt = new \DateTime($de);
        $fim = new \DateTime($ate);
        $anoMes = sprintf('%04d-%02d', $ano, $mes);
        $diasTrabalhoCache = [];

        while ($dt <= $fim) {
            $diaStr = $dt->format('Y-m-d');
            $diaNum = (int) $dt->format('j');
            $diaSemana = (int) $dt->format('N');

            if ($diaStr > $hoje) {
                $dt->modify('+1 day');
                continue;
            }

            foreach ($funcs as $func) {
                $fid = (int) $func['id'];
                if (isset($registoPorFuncDia[$fid][$diaStr])) continue;

                if (!isset($diasTrabalhoCache[$fid])) {
                    $diasTrabalhoCache[$fid] = $funcModel->getDiasTrabalho($fid, $anoMes);
                }
                $diasTrabalho = $diasTrabalhoCache[$fid];

                if (!empty($diasTrabalho)) {
                    $isDiaTrabalho = in_array($diaNum, $diasTrabalho);
                } else {
                    $selectedDays = array_map('intval', explode(',', $func['dias_trabalho'] ?? '1,2,3,4,5'));
                    $isDiaTrabalho = in_array($diaSemana, $selectedDays);
                }

                if (!$isDiaTrabalho) continue;

                $novos[] = [
                    'id' => null,
                    'funcionario_id' => $fid,
                    'tipo' => 'falta',
                    'marcado_em' => $diaStr . ' 09:00:00',
                    'observacao' => null,
                    'selfie_id' => null,
                    'metodo' => \App\Helpers\Metodo::SISTEMA,
                    'metodo_detalhe' => null,
                    'funcionario_nome' => $func['nome'],
                    'selfie_existe' => null,
                    'hora_entrada' => $func['hora_entrada'] ?? '08:00:00',
                    'hora_saida' => $func['hora_saida'] ?? '17:00:00',
                    'is_synthetic' => true,
                ];
            }

            $dt->modify('+1 day');
        }

        if (empty($novos)) return $regs;

        $todos = array_merge($regs, $novos);
        usort($todos, fn($a, $b) => strtotime($b['marcado_em']) - strtotime($a['marcado_em']));
        return $todos;
    }

    public function exportarPdf(): void {
        $this->requireRole('admin', 'gestor');

        $de = $_GET['de'] ?? date('Y-m-01');
        $ate = $_GET['ate'] ?? date('Y-m-d');
        $funcId = $_GET['funcionario'] ?? null;
        $funcId = $funcId ? (int) $funcId : null;
        $tipo = $_GET['tipo'] ?? null;

        $regs = (new Registo())->listarPorPeriodo($funcId, $de, $ate, $tipo);

        if ($tipo === null) {
            $ano = (int) date('Y', strtotime($de));
            $m   = (int) date('m', strtotime($de));
            $regs = $this->preencherFaltas($regs, $funcId, $ano, $m, $de, $ate);
        }

        $total = count($regs);
        $tolerancia = (int) (new \App\Models\Config())->get('tolerancia_atraso_min', '5');

        require __DIR__ . '/../views/admin/historico_pdf.php';
    }

    public function eliminar(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $de = $_POST['de'] ?? date('Y-m-01');
        $ate = $_POST['ate'] ?? date('Y-m-d');
        $funcId = $_POST['funcionario'] ?? null;
        $funcId = $funcId ? (int) $funcId : null;
        $tipo = $_POST['tipo'] ?? null;

        if (!Auth::isGestor()) {
            $funcId = Auth::id();
        }

        $where = "WHERE DATE(r.marcado_em) BETWEEN ? AND ?";
        $params = [$de, $ate];
        if ($funcId) { $where .= " AND r.funcionario_id = ?"; $params[] = $funcId; }
        if ($tipo && in_array($tipo, ['entrada','saida','ferias','falta','atraso','atestado'], true)) {
            $where .= " AND r.tipo = ?"; $params[] = $tipo;
        }

        $total = 0;
        $autor = Auth::id();
        Database::transacao(function ($pdo) use ($where, $params, &$total, $autor, $de, $ate) {
            // Guardar a linha completa na lixeira antes de apagar: um
            // apagamento por engano passa a ser reversível (60 dias).
            $st = $pdo->prepare("SELECT r.* FROM registos r $where");
            $st->execute($params);
            $registos = $st->fetchAll();
            $total = count($registos);
            $lixeira = new \App\Models\Lixeira();
            foreach ($registos as $reg) {
                $lixeira->arquivar('registos', $reg, $autor,
                    'Eliminação em lote (' . $de . ' a ' . $ate . ')',
                    ($reg['tipo'] ?? 'registo') . ' de ' . ($reg['marcado_em'] ?? ''));
            }

            $selfieIds = array_filter(array_column($registos, 'selfie_id'));
            if (!empty($selfieIds)) {
                $st = $pdo->prepare("SELECT * FROM selfies WHERE id IN (" . implode(',', array_fill(0, count($selfieIds), '?')) . ")");
                $st->execute(array_values($selfieIds));
                foreach ($st->fetchAll() as $s) {
                    $lixeira->arquivar('selfies', $s, $autor, 'Fotografia do registo eliminado');
                }
                // A fotografia em disco fica; se o registo for reposto, volta a
                // aparecer. A purga por retenção limpa-a mais tarde.
                $st = $pdo->prepare("DELETE FROM selfies WHERE id IN (" . implode(',', array_fill(0, count($selfieIds), '?')) . ")");
                $st->execute(array_values($selfieIds));
            }

            $regIds = array_column($registos, 'id');
            if (!empty($regIds)) {
                $st = $pdo->prepare("DELETE FROM registos WHERE id IN (" . implode(',', array_fill(0, count($regIds), '?')) . ")");
                $st->execute(array_values($regIds));
            }
        });

        Log::reg(Auth::id(), 'historico_eliminado', 'registos', null, [
            'de' => $de, 'ate' => $ate,
            'funcionario_id' => $funcId,
            'tipo' => $tipo,
            'total' => $total,
        ]);

        $this->json(['ok' => true, 'total' => $total]);
    }
}
