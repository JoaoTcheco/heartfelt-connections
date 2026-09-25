<?php
/**
 * ============================================================
 * FarmaPonto - DashboardController
 * ============================================================
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Funcionario;
use App\Models\Registo;

final class DashboardController extends Controller {

    public function index(): void {
        $this->requireLogin();
        $user = \App\Helpers\Auth::user();
        $funcModel = new Funcionario();
        $regModel = new Registo();

        // Processar faltas automaticamente: ontem e hoje (se after hora saida)
        $ontem = date('Y-m-d', strtotime('-1 day'));
        $hoje = date('Y-m-d');
        $regModel->processarFaltas($ontem, $hoje);
        // Manutencao diaria: apagar selfies fora do periodo de retencao configurado.
        (new \App\Models\Selfie())->purgaAutomatica();
        $toleranciaSeg = max(0, (int) (new \App\Models\Config())->get('tolerancia_atraso_min', '5')) * 60;

        if (\App\Helpers\Auth::isGestor()) {
            $kpi = [
                'funcionarios_ativos' => $funcModel->contarPontuaveis(),
                'presentes_hoje'      => $regModel->presentesHoje(),
                'atrasos_hoje'        => $regModel->atrasosHoje(),
                'faltas_hoje'         => $regModel->faltasHoje(),
            ];

            $atividade = $regModel->atividadeHoje();
            $funcionarios = $funcModel->listarPontuaveis();

            $st = \App\Core\Database::pdo()->query(
                "SELECT DISTINCT funcionario_id FROM registos WHERE tipo = 'entrada' AND DATE(marcado_em) = CURDATE()"
            );
            $presentes = array_column($st->fetchAll(), 'funcionario_id');

            $this->view('admin/dashboard', [
                'titulo'       => 'Dashboard',
                'kpi'          => $kpi,
                'atividade'    => $atividade,
                'funcionarios' => $funcionarios,
                'presentes'    => $presentes,
                'dataHoje'     => date('d/m/Y'),
                'diaSemana'    => $this->diaSemana(),
            ]);
        } else {
            $id = (int) $user['id'];
            $func = $funcModel->porId($id) ?? $user;
            $hoje = $regModel->listarPorDia($id, date('Y-m-d'));
            $mes = (int) date('m');
            $ano = (int) date('Y');

            $diasComRegisto = 0;
            $atrasos = 0;
            $faltas = 0;
            $hojeAtrasado = false;

            // Dias de trabalho do funcionário (calendário ou dias da semana)
            $diasTrabalho = (new Funcionario())->getDiasTrabalho($id, sprintf('%04d-%02d', $ano, $mes));
            $usarDiaSemana = empty($diasTrabalho);
            if ($usarDiaSemana) {
                $diasTrabalho = array_map('intval', explode(',', $func['dias_trabalho'] ?? '1,2,3,4,5'));
            }

            $dados = $regModel->agregarMensal($id, $ano, $mes);
            $feriasMap = $regModel->diasFeriasNoMes($id, $ano, $mes);
            $diasPorData = [];
            foreach ($dados as $d) {
                $diasPorData[$d['dia']] = $d;
            }

            $dt = new \DateTime(sprintf('%04d-%02d-01', $ano, $mes));
            $fimMes = clone $dt;
            $fimMes->modify('last day of this month');
            $hojeDt = new \DateTime('today');

            while ($dt <= $fimMes && $dt <= $hojeDt) {
                $diaStr = $dt->format('Y-m-d');
                $diaNum = (int) $dt->format('j');
                $diaSemana = (int) $dt->format('N');

                // Ferias sobrepoe-se a qualquer outro estado
                if (isset($feriasMap[$diaStr])) {
                    $dt->modify('+1 day');
                    continue;
                }

                $isDiaTrabalho = $usarDiaSemana
                    ? in_array($diaSemana, $diasTrabalho)
                    : in_array($diaNum, $diasTrabalho);

                if (!$isDiaTrabalho) {
                    $dt->modify('+1 day');
                    continue;
                }

                if (isset($diasPorData[$diaStr]) && $diasPorData[$diaStr]['entrada']) {
                    $diasComRegisto++;
                    $hp = strtotime($diaStr . ' ' . ($func['hora_entrada'] ?? '08:00:00'));
                    if (strtotime($diasPorData[$diaStr]['entrada']) > $hp + $toleranciaSeg) {
                        $atrasos++;
                    }
                } elseif ($diaStr === date('Y-m-d') && time() < strtotime(date('Y-m-d') . ' ' . ($func['hora_saida'] ?? '17:00:00'))) {
                    $hojeAtrasado = true;
                } else {
                    $faltas++;
                }

                $dt->modify('+1 day');
            }

            $proximo = Registo::proximoTipo($id);

            $this->view('admin/dashboard_funcionario', [
                'titulo'          => 'Dashboard',
                'func'            => $func,
                'hoje'            => $hoje,
                'diasComRegisto'  => $diasComRegisto,
                'atrasos'         => $atrasos,
                'faltas'          => $faltas,
                'diasFerias'      => count($feriasMap),
                'proximo'         => $hojeAtrasado ? 'atrasado' : $proximo,
                'dataHoje'        => date('d/m/Y'),
                'diaSemana'       => $this->diaSemana(),
            ]);
        }
    }

    /** Resumo do painel (KPIs + atividade + presencas) para atualizacao em tempo real. */
    public function resumo(): void {
        $this->requireLogin();
        if (!Auth::isGestor()) { $this->json(['ok' => false], 403); return; }
        $funcModel = new Funcionario();
        $regModel = new Registo();
        $presentes = array_map('intval', array_column(
            \App\Core\Database::pdo()->query(
                "SELECT DISTINCT funcionario_id FROM registos WHERE tipo = 'entrada' AND DATE(marcado_em) = CURDATE()"
            )->fetchAll(),
            'funcionario_id'
        ));
        $this->json([
            'ok' => true,
            'kpi' => [
                'funcionarios_ativos' => $funcModel->contarPontuaveis(),
                'presentes_hoje'      => $regModel->presentesHoje(),
                'atrasos_hoje'        => $regModel->atrasosHoje(),
                'faltas_hoje'         => $regModel->faltasHoje(),
            ],
            'atividade' => $regModel->atividadeHoje(),
            'presentes' => $presentes,
        ]);
    }

    public function registosHoje(): void {
        $this->requireLogin();
        $id = Auth::id();
        $regs = (new Registo())->listarPorDia($id, date('Y-m-d'));
        $proximo = Registo::proximoTipo($id);
        $this->json([
            'ok' => true,
            'registos' => $regs,
            'proximo' => $proximo,
        ]);
    }

    private function diaSemana(): string {
        $dias = ['Domingo', 'Segunda-feira', 'Terca-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sabado'];
        return $dias[date('w')];
    }
}
