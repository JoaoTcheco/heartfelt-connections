<?php
/**
 * ============================================================
 * FarmaPonto - FuncionariosController
 * ============================================================
 * CRUD de funcionarios (admin/gestor).
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Auth;
use App\Models\Funcionario;
use App\Models\Registo;
use App\Models\Config;
use App\Models\Log;
use App\Models\DiaAjuste;

final class FuncionariosController extends Controller {

    public function lista(): void {
        $this->requireRole('admin', 'gestor');
        $filtro = $_GET['q'] ?? null;
        $incluirInativos = !empty($_GET['inativos']);
        $lista = (new Funcionario())->listar($filtro, $incluirInativos);
        $this->view('admin/funcionarios', [
            'titulo'          => 'Funcionarios',
            'lista'           => $lista,
            'q'               => $filtro,
            'incluirInativos' => $incluirInativos,
            'moeda'           => (new Config())->get('moeda', 'MZN'),
        ]);
    }

    /** GET /funcionarios/novo — pagina dedicada de criacao */
    public function novoForm(): void {
        $this->requireRole('admin');
        $this->view('admin/funcionario_novo', [
            'titulo'          => 'Novo funcionario',
            'moeda'           => (new Config())->get('moeda', 'MZN'),
            'codigoSugerido'  => $this->sugerirCodigo(),
        ]);
    }

    /** Proximo codigo livre no formato FUNCxxx, calculado a partir dos existentes. */
    private function sugerirCodigo(): string {
        try {
            $st = \App\Core\Database::pdo()->query("SELECT codigo FROM funcionarios");
            $max = 0;
            foreach ($st->fetchAll() as $r) {
                if (preg_match('/(\d+)\s*$/', (string) $r['codigo'], $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
            return 'FUNC' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function criar(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $d = $_POST;
        $diasTrabalhoMes = $d['dias_trabalho_mes'] ?? '';
        $diasTrabalhoDias = isset($d['dias_trabalho_dias']) ? json_decode($d['dias_trabalho_dias'], true) : null;
        unset($d['dias_trabalho_mes'], $d['dias_trabalho_dias']);

        if ((new Funcionario())->codigoExiste($d['codigo'] ?? '')) {
            $this->json(['ok' => false, 'erro' => 'Este código já está em uso por outro funcionário.'], 409);
        }

        if (!empty($d['pin']) && !preg_match('/^\d{4}$/', $d['pin'])) {
            $this->json(['ok' => false, 'erro' => 'O PIN deve ter exatamente 4 dígitos numéricos.'], 422);
        }

        if (!empty($d['pin'])) {
            $dono = (new Funcionario())->pinEmUso((string) $d['pin']);
            if ($dono) {
                $this->json(['ok' => false, 'erro' => "Este PIN já está a ser usado por {$dono['nome']} ({$dono['codigo']}). Escolha outro PIN."], 409);
            }
        }

        $entrada = $d['hora_entrada'] ?? '08:00';
        $saida = $d['hora_saida'] ?? '17:00';
        $carga = (float) ($d['carga_diaria'] ?? 8);
        $diff = (strtotime($saida) - strtotime($entrada)) / 3600;
        if ($diff <= 0) { $diff += 24; }
        if ($carga - $diff > 0.25) {
            $this->json(['ok' => false, 'erro' => "A carga horaria ({$carga}h) e maior que o intervalo entrada/saida ({$diff}h). Ajuste a carga ou os horarios."], 422);
        }

        $d = $this->calcularSalario($d, $diasTrabalhoDias);
        $id = (new Funcionario())->criar($d);
    
        // Guardar dias de trabalho do calendário, se enviados
        if ($diasTrabalhoMes && is_array($diasTrabalhoDias)) {
            $funcModel = new Funcionario();
            $funcModel->salvarDiasTrabalho($id, $diasTrabalhoMes, $diasTrabalhoDias);
        }

        Log::reg(Auth::id(), 'funcionario_criado', 'funcionarios', $id);

        $this->json(['ok' => true, 'id' => $id]);
    }

    public function dados(int $id): void {
        $this->requireRole('admin', 'gestor');
        $f = (new Funcionario())->porId($id);
        if (!$f) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado'], 404);
        }
        unset($f['password_hash'], $f['pin_hash']);
        $this->json(['ok' => true, 'dados' => $f]);
    }

    public function atualizar(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $atual = (new Funcionario())->porId($id);
        if (!$atual) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado.'], 404);
        }

        // Preservar os valores existentes para campos que o formulario nao enviou
        $d = $_POST;
        foreach (['codigo', 'nome', 'email', 'cargo', 'perfil', 'salario_base', 'dias_uteis_mes',
                  'dias_trabalho', 'carga_diaria', 'hora_entrada', 'hora_saida'] as $campo) {
            if (!isset($d[$campo]) || $d[$campo] === '') {
                if (isset($atual[$campo]) && $atual[$campo] !== null && $atual[$campo] !== '') {
                    $d[$campo] = $atual[$campo];
                }
            }
        }

        $diasTrabalhoMes = $d['dias_trabalho_mes'] ?? '';
        $diasTrabalhoDias = isset($d['dias_trabalho_dias']) ? json_decode($d['dias_trabalho_dias'], true) : null;
        unset($d['dias_trabalho_mes'], $d['dias_trabalho_dias']);

        if ((new Funcionario())->codigoExiste($d['codigo'] ?? '', $id)) {
            $this->json(['ok' => false, 'erro' => 'Este código já está em uso por outro funcionário.'], 409);
        }

        if (!empty($d['pin']) && !preg_match('/^\d{4}$/', $d['pin'])) {
            $this->json(['ok' => false, 'erro' => 'O PIN deve ter exatamente 4 dígitos numéricos.'], 422);
        }

        if (!empty($d['pin'])) {
            $dono = (new Funcionario())->pinEmUso((string) $d['pin'], $id);
            if ($dono) {
                $this->json(['ok' => false, 'erro' => "Este PIN já está a ser usado por {$dono['nome']} ({$dono['codigo']}). Escolha outro PIN."], 409);
            }
        }

        $entrada = $d['hora_entrada'] ?? '08:00';
        $saida = $d['hora_saida'] ?? '17:00';
        $carga = (float) ($d['carga_diaria'] ?? 8);
        $diff = (strtotime($saida) - strtotime($entrada)) / 3600;
        if ($diff <= 0) { $diff += 24; }
        if ($carga - $diff > 0.25) {
            $this->json(['ok' => false, 'erro' => "A carga horaria ({$carga}h) e maior que o intervalo entrada/saida ({$diff}h). Ajuste a carga ou os horarios."], 422);
        }

        $d = $this->calcularSalario($d, $diasTrabalhoDias);
        (new Funcionario())->atualizar($id, $d);

        // Guardar dias de trabalho do calendário, se enviados
        if ($diasTrabalhoMes && is_array($diasTrabalhoDias)) {
            $funcModel = new Funcionario();
            $funcModel->salvarDiasTrabalho($id, $diasTrabalhoMes, $diasTrabalhoDias);
        }

        Log::reg(Auth::id(), 'funcionario_atualizado', 'funcionarios', $id);

        $this->json(['ok' => true]);
    }

    /**
     * Credenciais actuais (PIN e password) de um funcionario.
     * Apenas admin; cada consulta fica registada na auditoria.
     */
    public function credenciais(int $id): void {
        $this->requireRole('admin');
        $func = (new Funcionario())->porId($id);
        if (!$func) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado'], 404);
        }
        $cred = (new Funcionario())->credenciais($id);
        Log::reg(Auth::id(), 'credenciais_consultadas', 'funcionarios', $id);
        $this->json([
            'ok'       => true,
            'pin'      => $cred['pin'],
            'password' => $cred['password'],
            'aviso'    => ($cred['pin'] === null || $cred['password'] === null)
                ? 'Credenciais definidas antes desta funcionalidade não podem ser lidas. Defina um novo valor para passar a ficar visível.'
                : null,
        ]);
    }

    public function toggleAtivo(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        if (!(new Funcionario())->porId($id)) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado'], 404);
        }
        (new Funcionario())->toggleAtivo($id);
        Log::reg(Auth::id(), 'toggle_ativo', 'funcionarios', $id);

        $this->json(['ok' => true]);
    }

    public function resetarPin(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        if (!(new Funcionario())->porId($id)) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado'], 404);
        }
        $pin = $_POST['pin'] ?? '';
        if (!preg_match('/^\d{4}$/', $pin)) {
            $this->json(['ok' => false, 'erro' => 'PIN deve ter exatamente 4 dígitos numéricos.'], 400);
        }

        $dono = (new Funcionario())->pinEmUso($pin, $id);
        if ($dono) {
            $this->json(['ok' => false, 'erro' => "Este PIN já está a ser usado por {$dono['nome']} ({$dono['codigo']}). Escolha outro PIN."], 409);
        }

        (new Funcionario())->resetarPin($id, $pin);
        Log::reg(Auth::id(), 'pin_resetado', 'funcionarios', $id);

        $this->json(['ok' => true]);
    }

    /**
     * Adicionar férias (admin) — cria 1 registo tipo='ferias' por dia útil
     * no intervalo [de, ate]. Ignora fins-de-semana salvo se incluir_fds=1.
     */
    public function adicionarFerias(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $de  = trim($_POST['de']  ?? '');
        $ate = trim($_POST['ate'] ?? '');
        $obs = trim($_POST['observacao'] ?? '') ?: null;
        $incluirFds = !empty($_POST['incluir_fds']);
        $forcar     = !empty($_POST['forcar']); // bypass de avisos não-bloqueantes

        // Validação básica YYYY-MM-DD
        $rx = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($rx, $de) || !preg_match($rx, $ate)) {
            $this->json(['ok' => false, 'erro' => 'Datas inválidas (use YYYY-MM-DD).'], 400);
        }
        if (strtotime($de) > strtotime($ate)) {
            $this->json(['ok' => false, 'erro' => 'Data inicial maior que a final.'], 400);
        }

        $func = (new Funcionario())->porId($id);
        if (!$func) {
            $this->json(['ok' => false, 'erro' => 'Funcionário não encontrado.'], 404);
        }

        // === VALIDAÇÃO DE LIMITES ===
        $cfg = new Config();
        $maxAno         = (int) $cfg->get('ferias_max_dias_ano', '22');
        $minAvisoDias   = (int) $cfg->get('ferias_min_aviso_dias', '0');
        $permitirPassado= (int) $cfg->get('ferias_permitir_passado', '1'); // 1=permitir

        $hoje = date('Y-m-d');
        if (!$permitirPassado && $de < $hoje) {
            $this->json(['ok' => false, 'erro' => 'Não é permitido lançar férias em datas passadas.'], 422);
        }
        if ($minAvisoDias > 0) {
            $diff = (strtotime($de) - strtotime($hoje)) / 86400;
            if ($diff < $minAvisoDias && $de >= $hoje) {
                $this->json([
                    'ok' => false,
                    'erro' => "Aviso prévio mínimo: $minAvisoDias dia(s). Início deve ser a partir de " . date('d/m/Y', strtotime("+$minAvisoDias days")) . '.',
                ], 422);
            }
        }

        // Limite anual: contar já existentes + novos do intervalo (por ano do início)
        $reg = new Registo();
        if ($maxAno > 0) {
            $ano = (int) date('Y', strtotime($de));
            $jaTem = $reg->contarFeriasAno($id, $ano);
            $novos = Registo::contarDiasNoIntervalo($de, $ate, $incluirFds);
            // Estimativa optimista: novos podem reduzir-se se houver duplicados;
            // o cálculo real será feito após inserção, mas validamos o tecto antes.
            if (($jaTem + $novos) > $maxAno) {
                $disp = max(0, $maxAno - $jaTem);
                $this->json([
                    'ok' => false,
                    'erro' => "Limite anual de férias excedido. Disponível em $ano: $disp dia(s) (já lançados: $jaTem; limite: $maxAno).",
                ], 422);
            }
        }

        // Conflitos com registos existentes (entrada/saída) — aviso não-bloqueante
        $conflitos = $reg->diasComRegistoNoIntervalo($id, $de, $ate);
        if (!empty($conflitos) && !$forcar) {
            $this->json([
                'ok' => false,
                'erro' => 'Existem dias com marcações (entrada/saída) no intervalo escolhido.',
                'aviso' => true,
                'conflitos' => $conflitos,
                'mensagem' => 'Confirme para lançar férias mesmo assim (os registos existentes mantêm-se).',
            ], 409);
        }

        $inseridos = $reg->inserirFerias($id, $de, $ate, $incluirFds, $obs);

        Log::reg(Auth::id(), 'ferias_adicionadas', 'funcionarios', $id, [
            'de' => $de, 'ate' => $ate, 'incluir_fds' => $incluirFds,
            'dias_inseridos' => $inseridos, 'observacao' => $obs,
            'forcado' => $forcar, 'conflitos' => $conflitos,
        ]);

        $this->json([
            'ok' => true,
            'dias_inseridos' => $inseridos,
            'mensagem' => $inseridos === 0
                ? 'Nenhum dia novo de férias foi inserido (já existem registos ou apenas fim-de-semana).'
                : "Inseridos $inseridos dia(s) de férias para " . $func['nome'] . '.',
        ]);
    }

    /**
     * Remover férias num intervalo (admin).
     */
    public function removerFerias(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $de  = trim($_POST['de']  ?? '');
        $ate = trim($_POST['ate'] ?? '');
        $rx = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($rx, $de) || !preg_match($rx, $ate)) {
            $this->json(['ok' => false, 'erro' => 'Datas inválidas.'], 400);
        }

        $n = (new Registo())->removerFerias($id, $de, $ate);
        Log::reg(Auth::id(), 'ferias_removidas', 'funcionarios', $id, [
            'de' => $de, 'ate' => $ate, 'dias_removidos' => $n,
        ]);

        $this->json(['ok' => true, 'dias_removidos' => $n]);
    }

    /**
     * Lista períodos de férias agrupados (admin/gestor).
     */
    public function listarFerias(int $id): void {
        $this->requireRole('admin', 'gestor');
        $func = (new Funcionario())->porId($id);
        if (!$func) $this->json(['ok' => false, 'erro' => 'Funcionário não encontrado.'], 404);

        $reg = new Registo();
        $periodos = $reg->listarPeriodosFerias($id);
        $anoAtual = (int) date('Y');
        $cfg = new Config();
        $maxAno = (int) $cfg->get('ferias_max_dias_ano', '22');
        $usados = $reg->contarFeriasAno($id, $anoAtual);

        $this->json([
            'ok' => true,
            'periodos' => $periodos,
            'limite' => [
                'ano' => $anoAtual,
                'usados' => $usados,
                'maximo' => $maxAno,
                'disponiveis' => max(0, $maxAno - $usados),
            ],
        ]);
    }

    /**
     * Editar um período de férias: remove o antigo (de_antigo..ate_antigo)
     * e insere o novo (de..ate). Transaccional.
     */
    public function editarFerias(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $deAnt  = trim($_POST['de_antigo']  ?? '');
        $ateAnt = trim($_POST['ate_antigo'] ?? '');
        $de     = trim($_POST['de']  ?? '');
        $ate    = trim($_POST['ate'] ?? '');
        $obs    = trim($_POST['observacao'] ?? '') ?: null;
        $incluirFds = !empty($_POST['incluir_fds']);
        $forcar = !empty($_POST['forcar']);

        $rx = '/^\d{4}-\d{2}-\d{2}$/';
        foreach (['de_antigo'=>$deAnt,'ate_antigo'=>$ateAnt,'de'=>$de,'ate'=>$ate] as $k=>$v) {
            if (!preg_match($rx, $v)) {
                $this->json(['ok' => false, 'erro' => "Data inválida: $k."], 400);
            }
        }
        if (strtotime($de) > strtotime($ate)) {
            $this->json(['ok' => false, 'erro' => 'Data inicial maior que a final.'], 400);
        }

        $func = (new Funcionario())->porId($id);
        if (!$func) $this->json(['ok' => false, 'erro' => 'Funcionário não encontrado.'], 404);

        $reg = new Registo();
        $cfg = new Config();
        $maxAno = (int) $cfg->get('ferias_max_dias_ano', '22');

        // Conflitos no novo intervalo (excluindo dias do intervalo antigo)
        $conflitos = $reg->diasComRegistoNoIntervalo($id, $de, $ate);
        if (!empty($conflitos) && !$forcar) {
            $this->json([
                'ok' => false,
                'erro' => 'Há dias com marcações no novo intervalo.',
                'aviso' => true, 'conflitos' => $conflitos,
                'mensagem' => 'Confirme para guardar mesmo assim.',
            ], 409);
        }

        // Limite anual (após remoção do antigo)
        if ($maxAno > 0) {
            $ano = (int) date('Y', strtotime($de));
            $atual  = $reg->contarFeriasAno($id, $ano);
            // Quantos do antigo seriam removidos (apenas no mesmo ano)
            $aRemover = 0;
            $d1 = max($deAnt, sprintf('%04d-01-01', $ano));
            $d2 = min($ateAnt, sprintf('%04d-12-31', $ano));
            if ($d1 <= $d2) {
                $st = \App\Core\Database::pdo()->prepare(
                    "SELECT COUNT(DISTINCT DATE(marcado_em)) FROM registos
                     WHERE funcionario_id = ? AND tipo='ferias' AND DATE(marcado_em) BETWEEN ? AND ?"
                );
                $st->execute([$id, $d1, $d2]);
                $aRemover = (int) $st->fetchColumn();
            }
            $novos = Registo::contarDiasNoIntervalo($de, $ate, $incluirFds);
            $total = $atual - $aRemover + $novos;
            if ($total > $maxAno) {
                $this->json([
                    'ok' => false,
                    'erro' => "Limite anual excedido. Total ficaria em $total/$maxAno em $ano.",
                ], 422);
            }
        }

        \App\Core\Database::tx(function () use ($reg, $id, $deAnt, $ateAnt, $de, $ate, $incluirFds, $obs, &$removidos, &$inseridos) {
            $removidos = $reg->removerFerias($id, $deAnt, $ateAnt);
            $inseridos = $reg->inserirFerias($id, $de, $ate, $incluirFds, $obs);
        });

        Log::reg(Auth::id(), 'ferias_editadas', 'funcionarios', $id, [
            'de_antigo' => $deAnt, 'ate_antigo' => $ateAnt,
            'de_novo' => $de, 'ate_novo' => $ate,
            'removidos' => $removidos ?? 0, 'inseridos' => $inseridos ?? 0,
            'observacao' => $obs, 'forcado' => $forcar,
        ]);

        $this->json([
            'ok' => true,
            'removidos' => $removidos ?? 0,
            'inseridos' => $inseridos ?? 0,
            'mensagem' => "Período actualizado: removidos " . ($removidos ?? 0) . ", inseridos " . ($inseridos ?? 0) . '.',
        ]);
    }

    /**
     * Deriva os valores unitarios (dia / hora / minuto) do salario base.
     *
     * Regra: o valor/dia usa os DIAS DE TRABALHO DEFINIDOS do funcionario no
     * mes corrente (escala semanal), nunca um divisor fixo de 30 dias.
     */
    private function calcularSalario(array $d, ?array $diasCalendario = null): array {
        $d['dias_trabalho'] = $d['dias_trabalho'] ?? '1,2,3,4,5';

        $cargaDiaria = max(1, (float) ($d['carga_diaria'] ?? 8));
        $salarioBase = (float) ($d['salario_base'] ?? 0);

        // Dias de trabalho programados no mes corrente segundo a escala semanal
        $escala = array_filter(array_map('intval', explode(',', (string) $d['dias_trabalho'])));
        $ultimoDia = (int) date('t');
        $diasUteis = 0;
        for ($i = 1; $i <= $ultimoDia; $i++) {
            $iso = (int) date('N', strtotime(date('Y-m-') . str_pad((string) $i, 2, '0', STR_PAD_LEFT)));
            if (in_array($iso, $escala, true)) { $diasUteis++; }
        }
        // Se o utilizador definiu um calendario de dias de trabalho, e esse que manda.
        if (is_array($diasCalendario) && count($diasCalendario) > 0) {
            $diasUteis = count($diasCalendario);
        }
        if ($diasUteis <= 0) { $diasUteis = 30; }
        $d['dias_uteis_mes'] = $diasUteis;

        $d['salario_diario'] = $diasUteis > 0 ? round($salarioBase / $diasUteis, 2) : 0;
        $d['salario_hora'] = $cargaDiaria > 0 ? round($d['salario_diario'] / $cargaDiaria, 2) : 0;
        $d['salario_minuto'] = $d['salario_hora'] > 0 ? round($d['salario_hora'] / 60, 4) : 0;
        $d['valor_hora_extra'] = round((float) ($d['valor_hora_extra'] ?? 0), 2);

        return $d;
    }


    /**
     * Computar estatísticas mensais de um funcionário.
     * @return array [stats, todosDias, diasStatus, diasPorData, diasTrabalhoMes, usrDiaSemana]
     */
    private function computarStats(int $id, int $ano, int $mes): array {
        $func = (new Funcionario())->porId($id);
        $regModel = new Registo();
        $config = new Config();

        $anoMes = sprintf('%04d-%02d', $ano, $mes);
        $diasTrabalhoMes = (new Funcionario())->getDiasTrabalho($id, $anoMes);
        $selectedDays = [];
        $usarDiaSemana = empty($diasTrabalhoMes);
        if ($usarDiaSemana) {
            $selectedDays = array_map('intval', explode(',', $func['dias_trabalho'] ?? '1,2,3,4,5'));
        }

        $diasArr = $regModel->agregarMensal($id, $ano, $mes);
        $feriasMap = $regModel->diasFeriasNoMes($id, $ano, $mes);
        $diasPorData = [];
        foreach ($diasArr as $d) {
            $diasPorData[$d['dia']] = $d;
        }

        $hoje = date('Y-m-d');
        $eMesCorrente = ($ano == date('Y') && $mes == date('m'));
        $eMesFuturo = ($ano > date('Y') || ($ano == date('Y') && $mes > date('m')));
        $todosDias = [];
        $dt = new \DateTime(sprintf('%04d-%02d-01', $ano, $mes));
        $fimMes = clone $dt;
        $fimMes->modify('last day of this month');
        while ($dt <= $fimMes) {
            $todosDias[] = $dt->format('Y-m-d');
            $dt->modify('+1 day');
        }

        $atrasos = 0; $faltas = 0; $horasAtraso = 0.0;
        $diasEsperados = 0; $presencas = 0; $diasFolga = 0; $diasTrabalhoTotal = 0;
        $toleranciaMin = (int) $config->get('tolerancia_atraso_min', '5');
        $horaPadrao = substr($func['hora_entrada'] ?? '08:00:00', 0, 5);

        $diasStatus = [];

        foreach ($todosDias as $diaStr) {
            $isFuturo = ($eMesCorrente && $diaStr > $hoje);

            // Ferias sobrepoe-se a qualquer outro estado (inclui fins-de-semana)
            if (isset($feriasMap[$diaStr])) {
                $diasStatus[$diaStr] = 'ferias';
                continue;
            }

            $isDiaTrabalho = false;
            if ($usarDiaSemana) {
                $diaSemana = (int) date('N', strtotime($diaStr));
                $isDiaTrabalho = in_array($diaSemana, $selectedDays);
            } else {
                $diaNum = (int) date('j', strtotime($diaStr));
                $isDiaTrabalho = in_array($diaNum, $diasTrabalhoMes);
            }

            if ($isDiaTrabalho) { $diasTrabalhoTotal++; }

            if ($isFuturo) {
                $diasStatus[$diaStr] = $isDiaTrabalho ? 'trabalho' : 'futuro';
                continue;
            }

            if ($eMesFuturo) {
                $diasStatus[$diaStr] = $isDiaTrabalho ? 'trabalho' : 'futuro';
                continue;
            }

            if (!$isDiaTrabalho) {
                $diasFolga++;
                $diasStatus[$diaStr] = 'folga';
                continue;
            }

            $diasEsperados++;

            if (!isset($diasPorData[$diaStr]) || !$diasPorData[$diaStr]['entrada']) {
                if ($diaStr === date('Y-m-d') && time() < strtotime(date('Y-m-d') . ' ' . ($func['hora_saida'] ?? '17:00:00'))) {
                    $diasStatus[$diaStr] = 'atraso';
                    continue;
                }
                $faltas++;
                $diasStatus[$diaStr] = 'falta';
                continue;
            }

            $presencas++;
            $d = $diasPorData[$diaStr];

            $he = strtotime($d['entrada']);
            $hp = strtotime(substr($d['entrada'], 0, 10) . ' ' . $horaPadrao);
            $diffLate = $he - $hp;
            if ($diffLate > ($toleranciaMin * 60)) {
                $atrasos++;
                $horasAtraso += $diffLate / 3600;
                $diasStatus[$diaStr] = 'presente_atraso';
            } else {
                $diasStatus[$diaStr] = 'presente';
            }
        }

        $cargaDiaria = max(1, (float) ($func['carga_diaria'] ?? 8));
        $valorDia = (float) ($func['salario_diario'] ?? 0);
        if ($valorDia <= 0) $valorDia = round($func['salario_base'] / 30, 2);
        $valorHora = (float) ($func['salario_hora'] ?? 0);
        if ($valorHora <= 0) $valorHora = $cargaDiaria > 0 ? round($valorDia / $cargaDiaria, 2) : 0;

        $corteFalta = round($faltas * $valorDia, 2);
        $corteAtraso = round($horasAtraso * $valorHora, 2);
        $totalCorte = $corteFalta + $corteAtraso;
        $liquido = max(0, $func['salario_base'] - $totalCorte);

        $moeda = $config->get('moeda', 'MZN');

        $stats = [
            'dias_esperados' => $diasEsperados,
            'dias_trabalho_mes' => $diasTrabalhoTotal,
            'presencas'      => $presencas,
            'faltas'         => $faltas,
            'atrasos'        => $atrasos,
            'horas_atraso'   => round($horasAtraso, 1),
            'dias_folga'     => $diasFolga,
            'dias_ferias'    => count($feriasMap),
            'valor_dia'      => $valorDia,
            'valor_hora'     => $valorHora,
            'corte_falta'    => $corteFalta,
            'corte_atraso'   => $corteAtraso,
            'total_corte'    => $totalCorte,
            'liquido'        => $liquido,
            'moeda'          => $moeda,
            'carga_diaria'   => $cargaDiaria,
            'mes'            => $anoMes,
        ];

        return [$stats, $todosDias, $diasStatus, $diasPorData, $diasTrabalhoMes, $func];
    }

    /**
     * Página de detalhe do funcionário (substitui o modal de edição).
     */
    public function detalhe(int $id): void {
        $this->requireRole('admin', 'gestor');
        $func = (new Funcionario())->porId($id);
        if (!$func) {
            http_response_code(404);
            echo 'Funcionário não encontrado';
            return;
        }
        unset($func['password_hash'], $func['pin_hash']);

        $anoMes = $_GET['mes'] ?? '';
        if (preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            [$ano, $mes] = array_map('intval', explode('-', $anoMes));
        } else {
            $ano = (int) ($_GET['ano'] ?? date('Y'));
            $mes = (int) ($_GET['mes'] ?? date('m'));
        }

        [$stats, $todosDias, $diasStatus, $diasPorData, $diasTrabalhoMes] = $this->computarStats($id, $ano, $mes);

        $this->view('admin/funcionario_detalhe', [
            'titulo'          => $func['nome'],
            'func'            => $func,
            'ano'             => $ano,
            'mes'             => $mes,
            'diasTrabalhoMes' => $diasTrabalhoMes,
            'todosDias'       => $todosDias,
            'diasStatus'      => $diasStatus,
            'diasPorData'     => $diasPorData,
            'stats'           => $stats,
        ]);
    }

    /**
     * JSON com estatísticas de um funcionário/mês (para refresh AJAX).
     */
    public function statsJson(int $id): void {
        $this->requireRole('admin', 'gestor');
        $anoMes = $_GET['mes'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            $this->json(['ok' => false, 'erro' => 'Mês inválido'], 400);
        }
        [$ano, $mes] = array_map('intval', explode('-', $anoMes));

        [$stats, $todosDias, $diasStatus, $diasPorData, $diasTrabalhoMes, $func] = $this->computarStats($id, $ano, $mes);

        $this->json([
            'ok'             => true,
            'stats'          => $stats,
            'diasStatus'     => $diasStatus,
            'diasPorData'    => $diasPorData,
            'todosDias'      => $todosDias,
            'diasTrabalhoMes' => $diasTrabalhoMes,
            'func'           => [
                'salario_base'  => $func['salario_base'],
                'carga_diaria'  => $func['carga_diaria'] ?? 8,
                'hora_entrada'  => $func['hora_entrada'] ?? '08:00:00',
                'hora_saida'    => $func['hora_saida'] ?? '17:00:00',
            ],
        ]);
    }

    /**
     * Eliminar funcionario e todos os dados em cascata (apenas admin).
     */
    public function eliminar(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $func = (new Funcionario())->porId($id);
        if (!$func) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado.'], 404);
        }

        $nome = $func['nome'];
        $codigo = $func['codigo'];

        (new Funcionario())->eliminar($id);

        Log::reg(Auth::id(), 'funcionario_eliminado', 'funcionarios', $id, [
            'nome' => $nome,
            'codigo' => $codigo,
        ]);

        $this->json(['ok' => true, 'mensagem' => "Funcionario \"$nome\" e todos os seus dados foram eliminados."]);
    }

    /**
     * Guardar dias de trabalho para um funcionário/mês específico.
     * POST: mes (YYYY-MM), dias (JSON array)
     */
    public function salvarDiasTrabalho(int $id): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $anoMes = trim($_POST['mes'] ?? '');
        $diasRaw = $_POST['dias'] ?? '[]';
        $dias = json_decode($diasRaw, true);

        if (!preg_match('/^\d{4}-\d{2}$/', $anoMes) || !is_array($dias)) {
            $this->json(['ok' => false, 'erro' => 'Dados inválidos.'], 400);
        }

        $func = (new Funcionario())->porId($id);
        if (!$func) {
            $this->json(['ok' => false, 'erro' => 'Funcionário não encontrado.'], 404);
        }

        (new Funcionario())->salvarDiasTrabalho($id, $anoMes, $dias);

        Log::reg(Auth::id(), 'dias_trabalho_atualizados', 'funcionarios', $id, [
            'ano_mes' => $anoMes,
            'dias' => $dias,
        ]);

        $this->json(['ok' => true, 'dias' => $dias]);
    }

    /**
     * Obter dias de trabalho de um funcionário para um mês.
     * GET: /funcionarios/dias-trabalho/{id}/{anoMes}
     */
    public function getDiasTrabalho(int $id, string $anoMes = ''): void {
        $this->requireRole('admin', 'gestor');

        if (!preg_match('/^\d{4}-\d{2}$/', $anoMes)) {
            $this->json(['ok' => false, 'erro' => 'Mês inválido. Use YYYY-MM.'], 400);
        }

        $funcModel = new Funcionario();
        $dias = $funcModel->getDiasTrabalho($id, $anoMes);

        // Fallback: se não há dados de calendário, usar dias da semana
        if (empty($dias)) {
            $func = $funcModel->porId($id);
            $raw = ($func['dias_trabalho'] ?? '') ?: '1,2,3,4,5';
            $weekdays = array_map('intval', explode(',', $raw));
            $ano = (int) substr($anoMes, 0, 4);
            $mes = (int) substr($anoMes, 5, 2);
            $ultimo = (int) date('t', strtotime($anoMes . '-01'));
            for ($d = 1; $d <= $ultimo; $d++) {
                $ds = (int) date('N', strtotime(sprintf('%04d-%02d-%02d', $ano, $mes, $d)));
                if (in_array($ds, $weekdays)) {
                    $dias[] = $d;
                }
            }
        }

        $this->json(['ok' => true, 'dias' => $dias]);
    }

    // ============================================================
    // Ajustes de dia: justificacoes, folgas/licencas e horas extras
    // ============================================================

    /**
     * Lista os ajustes de um mes.
     * GET /funcionarios/ajustes/{id}?mes=YYYY-MM
     */
    public function listarAjustes(int $id): void {
        $this->requireRole('admin', 'gestor');

        $mes = (string) ($_GET['mes'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $this->json(['ok' => false, 'erro' => 'Mes invalido. Use YYYY-MM.'], 400);
        }

        $this->json([
            'ok'      => true,
            'tipos'   => DiaAjuste::TIPOS,
            'ajustes' => (new DiaAjuste())->listarMes($id, $mes),
        ]);
    }

    /**
     * Cria/actualiza o ajuste de um dia.
     * POST /funcionarios/ajustes/{id}
     * Campos: dia (Y-m-d), tipo, com_vencimento (0|1), horas_extra, observacao
     */
    public function guardarAjuste(int $id): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $func = (new Funcionario())->porId($id);
        if (!$func) {
            $this->json(['ok' => false, 'erro' => 'Funcionario nao encontrado.'], 404);
        }

        $dia = trim((string) ($_POST['dia'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia) || !strtotime($dia)) {
            $this->json(['ok' => false, 'erro' => 'Data invalida. Use YYYY-MM-DD.'], 422);
        }

        $tipo = DiaAjuste::tipoValido($_POST['tipo'] ?? 'normal');
        $comVencimento = isset($_POST['com_vencimento'])
            ? ((string) $_POST['com_vencimento']) !== '0'
            : (bool) DiaAjuste::VENCIMENTO_PADRAO[$tipo];

        $horasExtra = (float) str_replace(',', '.', (string) ($_POST['horas_extra'] ?? 0));
        if ($horasExtra < 0 || $horasExtra > 24) {
            $this->json(['ok' => false, 'erro' => 'Horas extra devem estar entre 0 e 24.'], 422);
        }

        $obs = trim((string) ($_POST['observacao'] ?? ''));
        if ($tipo === 'normal' && $horasExtra <= 0) {
            $this->json(['ok' => false, 'erro' => 'Escolha um tipo de dia ou indique horas extra.'], 422);
        }

        $ajId = (new DiaAjuste())->guardar($id, $dia, $tipo, $comVencimento, $horasExtra, $obs ?: null, Auth::id());

        Log::reg(Auth::id(), 'dia_ajuste_guardado', 'dia_ajustes', (string) $ajId, [
            'funcionario_id' => $id, 'dia' => $dia, 'tipo' => $tipo,
            'com_vencimento' => $comVencimento, 'horas_extra' => $horasExtra,
        ]);

        $this->json([
            'ok'      => true,
            'id'      => $ajId,
            'ajustes' => (new DiaAjuste())->listarMes($id, substr($dia, 0, 7)),
        ]);
    }

    /**
     * Remove o ajuste de um dia.
     * POST /funcionarios/ajustes/remover/{id}   (campo: dia)
     */
    public function removerAjuste(int $id): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $dia = trim((string) ($_POST['dia'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) {
            $this->json(['ok' => false, 'erro' => 'Data invalida.'], 422);
        }

        $removidos = (new DiaAjuste())->remover($id, $dia);
        if ($removidos > 0) {
            Log::reg(Auth::id(), 'dia_ajuste_removido', 'dia_ajustes', null, [
                'funcionario_id' => $id, 'dia' => $dia,
            ]);
        }

        $this->json([
            'ok'        => true,
            'removidos' => $removidos,
            'ajustes'   => (new DiaAjuste())->listarMes($id, substr($dia, 0, 7)),
        ]);
    }

}
