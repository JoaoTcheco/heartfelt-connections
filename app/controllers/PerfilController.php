<?php
/**
 * ============================================================
 * FarmaPonto - PerfilController
 * ============================================================
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Funcionario;
use App\Models\Log;
use App\Models\Registo;
use App\Models\Config;

final class PerfilController extends Controller {

    public function index(): void {
        $this->requireLogin();

        $id = Auth::id();
        $func = (new Funcionario())->porId($id);

        // Estatisticas do mes corrente
        $ano = (int) date('Y');
        $mes = (int) date('m');
        $regModel = new Registo();
        $dias = $regModel->agregarMensal($id, $ano, $mes);
        $feriasMap = $regModel->diasFeriasNoMes($id, $ano, $mes);

        $config = new Config();
        $moeda     = $config->get('moeda', 'MZN');

        $horaPadrao = substr($func['hora_entrada'] ?? '08:00:00', 0, 5);

        // Dias de trabalho: tentar da tabela específica, fallback para dias da semana
        $anoMes = sprintf('%04d-%02d', $ano, $mes);
        $diasTrabalhoMes = (new Funcionario())->getDiasTrabalho($id, $anoMes);
        $selectedDays = [];
        $usarDiaSemana = empty($diasTrabalhoMes);
        if ($usarDiaSemana) {
            $selectedDays = array_map('intval', explode(',', $func['dias_trabalho'] ?? '1,2,3,4,5'));
        }

        // Todos os dias do mês corrente, excluindo futuros
        $hoje = date('Y-m-d');
        $todosDias = [];
        $dt = new \DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $fimMes = clone $dt;
        $fimMes->modify('last day of this month');
        while ($dt <= $fimMes) {
            $diaStr = $dt->format('Y-m-d');
            if ($diaStr > $hoje) break;
            $todosDias[] = $diaStr;
            $dt->modify('+1 day');
        }

        // Indexar dias com registo por data
        $diasPorData = [];
        foreach ($dias as $d) {
            $diasPorData[$d['dia']] = $d;
        }

        $atrasos = 0; $faltas = 0; $horasAtraso = 0.0;
        $diasEsperados = 0;
        foreach ($todosDias as $diaStr) {
            // Ferias sobrepoe-se a qualquer outro estado
            if (isset($feriasMap[$diaStr])) { continue; }

            if ($usarDiaSemana) {
                $diaSemana = (int) date('N', strtotime($diaStr));
                if (!in_array($diaSemana, $selectedDays)) { continue; }
            } else {
                $diaNum = (int) date('j', strtotime($diaStr));
                if (!in_array($diaNum, $diasTrabalhoMes)) { continue; }
            }
            $diasEsperados++;

            if (!isset($diasPorData[$diaStr]) || !$diasPorData[$diaStr]['entrada']) {
                if ($diaStr === date('Y-m-d') && time() < strtotime(date('Y-m-d') . ' ' . ($func['hora_saida'] ?? '17:00:00'))) {
                    continue;
                }
                $faltas++;
                continue;
            }
            $d = $diasPorData[$diaStr];
            $horaEntrada = substr($d['entrada'], 11, 5);
            if ($horaEntrada > $horaPadrao) {
                $atrasos++;
                $he = strtotime($d['entrada']);
                $hp = strtotime($diaStr . ' ' . $horaPadrao);
                $horasAtraso += max(0, $he - $hp) / 3600;
            }
        }

        $salario = (float) ($func['salario_base'] ?? 0);
        $cargaDiaria = max(1, (float) ($func['carga_diaria'] ?? 8));

        $valorDia = (float) ($func['salario_diario'] ?? 0);
        if ($valorDia <= 0) {
            $valorDia = round($salario / 30, 2);
        }
        $valorHora = (float) ($func['salario_hora'] ?? 0);
        if ($valorHora <= 0) {
            $valorHora = $cargaDiaria > 0 ? round($valorDia / $cargaDiaria, 2) : 0;
        }

        $corteFalta = round($faltas * $valorDia, 2);
        $corteAtraso = round($horasAtraso * $valorHora, 2);
        $totalCorte  = $corteFalta + $corteAtraso;
        $liquido     = max(0, $salario - $totalCorte);

        $this->view('admin/perfil', [
            'titulo'      => 'Meu Perfil',
            'func'        => $func,
            'stats'       => [
                'salario'      => $salario,
                'faltas'       => $faltas,
                'atrasos'      => $atrasos,
                'horas_atraso' => round($horasAtraso, 1),
                'corte_total'  => $totalCorte,
                'liquido'      => $liquido,
                'moeda'        => $moeda,
                'mes'          => sprintf('%04d-%02d', $ano, $mes),
                'valor_dia'    => $valorDia,
                'valor_hora'   => $valorHora,
                'dias_uteis'   => $diasEsperados,
                'carga_diaria' => $cargaDiaria,
            ],
        ]);
    }

    public function atualizar(): void {
        $this->requireLogin();
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $id = Auth::id();
        (new Funcionario())->atualizarPerfil($id, [
            'nome'  => $_POST['nome'] ?? '',
            'email' => $_POST['email'] ?? '',
        ]);

        $_SESSION['user']['nome'] = $_POST['nome'] ?? '';
        $_SESSION['user']['email'] = $_POST['email'] ?? '';

        Log::reg($id, 'perfil_atualizado', 'funcionarios', $id);
        $this->json(['ok' => true]);
    }

    public function alterarPin(): void {
        $this->requireLogin();
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $pin = $_POST['pin'] ?? '';
        if (!preg_match('/^\d{4,8}$/', $pin)) {
            $this->json(['ok' => false, 'erro' => 'PIN deve ter 4 a 8 digitos numericos'], 400);
        }

        (new Funcionario())->resetarPin(Auth::id(), $pin);
        Log::reg(Auth::id(), 'pin_alterado', 'funcionarios', Auth::id());
        $this->json(['ok' => true]);
    }

    public function alterarPassword(): void {
        $this->requireLogin();
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $atual = $_POST['password_atual'] ?? '';
        $nova = $_POST['password_nova'] ?? '';

        if (strlen($nova) < 6) {
            $this->json(['ok' => false, 'erro' => 'A nova password deve ter pelo menos 6 caracteres'], 400);
        }

        $func = (new Funcionario())->porId(Auth::id());
        if (!password_verify($atual, $func['password_hash'])) {
            $this->json(['ok' => false, 'erro' => 'Password atual incorreta'], 401);
        }

        (new Funcionario())->alterarPassword(Auth::id(), $nova);
        Log::reg(Auth::id(), 'password_alterada', 'funcionarios', Auth::id());
        $this->json(['ok' => true]);
    }
}
