<?php
/**
 * ============================================================
 * FarmaPonto - RelatoriosController
 * ============================================================
 * Relatorios mensais com calculo de descontos.
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\Invariantes;
use App\Services\CalculoSalarial;
use App\Services\RegrasAssiduidade;
use App\Helpers\Auth;
use App\Helpers\Export;
use App\Models\Funcionario;
use App\Models\Registo;
use App\Models\Config;
use App\Models\Log;
use App\Models\RelatorioModelo;
use App\Models\DiaAjuste;
use App\Models\FolhaSalarial;

final class RelatoriosController extends Controller {

    public function mensal(): void {
        $this->requireRole('admin', 'gestor');
        $data = $this->calcular();
        $data['modelos'] = (new RelatorioModelo())->listar();
        $this->view('admin/relatorios', $data);
    }

    /**
     * Colunas exportaveis (chave => rotulo). Fonte unica usada pelo CSV,
     * pelo PDF e pelo dialogo de exportacao da vista.
     *
     * @return array<string,string>
     */
    public static function colunasExportaveis(): array {
        return [
            'nome'                 => 'Funcionário',
            'cargo'                => 'Cargo',
            'salario'              => 'Salário do período',
            'salario_base'         => 'Salário base',
            'dias_programados'     => 'Dias programados',
            'dias_trabalhados'     => 'Dias trabalhados',
            'dias_pagos'           => 'Dias com vencimento',
            'faltas'               => 'Faltas',
            'faltas_justificadas'  => 'Faltas justificadas',
            'atrasos'              => 'Atrasos',
            'saidas_cedo'          => 'Saídas antecipadas',
            'ferias'               => 'Férias',
            'horas'                => 'Horas trabalhadas',
            'horas_atraso'         => 'H. atraso',
            'horas_deveria'        => 'H. previstas',
            'horas_extra'          => 'H. extra',
            'ganho_extra'          => 'Ganho h. extra',
            'corte_atraso'         => 'Corte atraso',
            'corte_falta'          => 'Corte faltas',
            'corte_sem_vencimento' => 'Corte dias s/ vencimento',
            'total_corte'          => 'Total corte',
            'liquido'              => 'Líquido',
        ];
    }

    /** Colunas monetarias (formatadas com 2 casas decimais). */
    public static function colunasMonetarias(): array {
        return ['salario', 'salario_base', 'ganho_extra', 'corte_atraso', 'corte_falta',
                'corte_sem_vencimento', 'total_corte', 'liquido'];
    }

    /** Colunas alinhadas a direita (monetarias + contadores). */
    public static function colunasNumericas(): array {
        return array_merge(self::colunasMonetarias(), [
            'dias_programados', 'dias_trabalhados', 'dias_pagos', 'faltas',
            'faltas_justificadas', 'atrasos', 'saidas_cedo', 'ferias', 'horas_extra',
        ]);
    }

    /** Rotulo legivel do modo de pagamento aplicado. */
    private function rotuloBase(string $base, int $diaCorte): string {
        return match ($base) {
            'proporcional' => 'Proporcional aos dias trabalhados',
            'ate_dia'      => 'Proporcional até ao dia ' . $diaCorte,
            default        => 'Mês completo',
        };
    }

    /** Descricao textual do periodo calculado. */
    private function descricaoPeriodo(array $d): string {
        if (($d['modo'] ?? 'mes') === 'intervalo' && !empty($d['de']) && !empty($d['ate'])) {
            return date('d/m/Y', strtotime($d['de'])) . ' a ' . date('d/m/Y', strtotime($d['ate']));
        }
        return $d['nomeMes'] . ' de ' . $d['ano'];
    }

    /**
     * Linhas de cabecalho com os filtros aplicados — partilhadas pelo CSV
     * e pelo rodape do PDF, para que os dois documentos sejam auditaveis.
     *
     * @return array<int,array{0:string,1:string}>
     */
    private function resumoFiltros(array $d): array {
        $sn = static fn(bool $b): string => $b ? 'Sim' : 'Não';
        $resumo = [
            ['Período', $this->descricaoPeriodo($d)],
            ['Modo de pagamento', $this->rotuloBase((string) $d['base'], (int) $d['diaCorte'])],
            ['Dias no período', (string) $d['diasNoPeriodo']],
            ['Descontar faltas', $sn((bool) $d['contarFaltas'])],
            ['Descontar atrasos', $sn((bool) $d['contarAtrasos'])],
            ['Descontar saídas antecipadas', $sn((bool) $d['contarSaidaCedo'])],
            ['Pagar horas extra', $sn((bool) $d['contarExtras'])],
            ['Pagar dias futuros', $sn((bool) $d['pagarFuturos'])],
            ['Tolerância de atraso (min)', (string) $d['tolerancia']],
        ];
        if (!empty($d['filtroDiasMes'])) {
            $resumo[] = ['Dias do mês seleccionados', implode(', ', $d['filtroDiasMes'])];
        }
        if (!empty($d['filtroDiasSem'])) {
            $nomes = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
            $resumo[] = ['Dias da semana', implode(', ', array_map(
                static fn(int $x): string => $nomes[$x] ?? (string) $x,
                $d['filtroDiasSem']
            ))];
        }
        return $resumo;
    }

    public function exportarCsv(): void {
        $this->requireRole('admin', 'gestor');

        // Recalcula sempre (sessão pode estar vazia em nova janela / após login)
        $data    = $this->calcular();
        $linhas  = $data['linhas'];
        $labels  = self::colunasExportaveis();
        $moeda   = $data['moeda'];
        $monet   = self::colunasMonetarias();

        // Cabecalho de auditoria: mesmo criterio do PDF
        $out = [['FarmaPonto — Relatório de salários e cortes']];
        foreach ($this->resumoFiltros($data) as [$rotulo, $valor]) {
            $out[] = [$rotulo, $valor];
        }
        $out[] = ['Emitido em', date('d/m/Y H:i')];
        $out[] = [''];

        $out[] = array_values($labels);
        foreach ($linhas as $l) {
            $linha = [];
            foreach ($labels as $chave => $_) {
                $valor = $l[$chave] ?? '';
                $linha[] = in_array($chave, $monet, true)
                    ? number_format((float) $valor, 2, ',', '.')
                    : (string) $valor;
            }
            $out[] = $linha;
        }

        // Totais gerais para conferencia rapida na folha de calculo
        $out[] = [''];
        $out[] = ['Total cortes (' . $moeda . ')', number_format(array_sum(array_column($linhas, 'total_corte')), 2, ',', '.')];
        $out[] = ['Total líquido (' . $moeda . ')', number_format(array_sum(array_column($linhas, 'liquido')), 2, ',', '.')];

        $sufixo = ($data['modo'] === 'intervalo' && $data['de'] && $data['ate'])
            ? $data['de'] . '_a_' . $data['ate']
            : sprintf('%04d-%02d', $data['ano'], $data['mes']);

        Export::csv($out, 'relatorio_salarios_' . $sufixo);
    }

    /**
     * Exporta PDF (HTML imprimivel A4 com window.print()).
     * Aceita opções personalizadas via GET (ExportDialog):
     * titulo_doc, subtitulo, autor, instituicao, nota_rodape, colunas (csv)
     */
    public function exportarPdf(): void {
        $this->requireRole('admin', 'gestor');
        $data = $this->calcular();
        $config = new \App\Models\Config();
        $padraoColunas = 'nome,cargo,salario,dias_trabalhados,faltas,faltas_justificadas,'
            . 'ferias,horas,horas_atraso,horas_extra,corte_atraso,corte_falta,total_corte,liquido';
        $colunas = array_values(array_filter(
            array_map('trim', explode(',', (string) ($_GET['colunas'] ?? $padraoColunas))),
            static fn(string $c): bool => isset(self::colunasExportaveis()[$c])
        ));
        if (!$colunas) { $colunas = explode(',', $padraoColunas); }

        $data['opcoes'] = [
            'titulo_doc'   => trim($_GET['titulo_doc'] ?? 'Relatório de Salários e Cortes'),
            'subtitulo'    => trim($_GET['subtitulo'] ?? ''),
            'autor'        => trim($_GET['autor'] ?? (\App\Helpers\Auth::user()['nome'] ?? '')),
            'instituicao'  => trim($_GET['instituicao'] ?? $config->get('instituicao', 'FarmaPonto Lda')),
            'nota_rodape'  => trim($_GET['nota_rodape'] ?? $config->get('relatorio_nota_rodape', '')),
            'colunas'      => $colunas,
        ];
        $data['periodoDesc']  = $this->descricaoPeriodo($data);
        $data['baseRotulo']   = $this->rotuloBase((string) $data['base'], (int) $data['diaCorte']);
        $data['resumoFiltros'] = $this->resumoFiltros($data);
        extract($data);
        require __DIR__ . '/../views/admin/relatorio_pdf.php';
    }



    /**
     * Motor de calculo com filtros.
     *
     * Filtros aceites por GET:
     *  - modo=mes|intervalo
     *  - mes=YYYY-MM            (modo mes)
     *  - de=Y-m-d & ate=Y-m-d   (modo intervalo)
     *  - func_id[]=1&func_id[]=2  (ou func_id=1) -> apenas estes funcionarios
     *  - dias_mes=4,5,6,...     -> apenas estes dias do mes entram no calculo
     *  - dias_semana=1,2,3,4,5  -> apenas estes dias da semana (1=Seg ... 7=Dom)
     *  - contar_faltas=0|1, contar_atrasos=0|1, contar_saida_cedo=0|1
     *  - base=mes_completo|proporcional|ate_dia
     *      mes_completo -> paga o salario base cheio (mesmo com o mes a decorrer),
     *                      aplicando apenas os cortes activados
     *      proporcional -> paga apenas os dias efectivamente pagos (valor dia x dias)
     *      ate_dia      -> paga os dias programados do dia 1 ate dia_corte
     *      (aliases legados: integral = mes_completo)
     *  - dia_corte=1..31        (base=ate_dia)
     *  - pagar_futuros=0|1      -> dias programados ainda por decorrer contam como pagos
     *  - contar_extras=0|1      -> somar horas extras lancadas pelo gestor
     *
     * O valor/dia e calculado com os DIAS DE TRABALHO DEFINIDOS do funcionario
     * (calendario do mes ou escala semanal), nunca com um divisor fixo.
     * Os cortes nunca podem ultrapassar o salario disponivel: os dias
     * efectivamente trabalhados (e os dias pagos por justificacao) sao sempre
     * garantidos ao funcionario.
     */
    private function calcular(): array {
        $ano = (int) ($_GET['ano'] ?? date('Y'));
        $mes = (int) ($_GET['mes'] ?? date('m'));

        // Suporte ao input type="month" (formato YYYY-MM)
        if (!empty($_GET['mes']) && strpos((string) $_GET['mes'], '-') !== false) {
            [$ano, $mes] = array_map('intval', explode('-', $_GET['mes']));
        }

        $modo = ($_GET['modo'] ?? 'mes') === 'intervalo' ? 'intervalo' : 'mes';

        // --------- Periodo ---------
        if ($modo === 'intervalo') {
            $de  = trim((string) ($_GET['de'] ?? date('Y-m-01')));
            $ate = trim((string) ($_GET['ate'] ?? date('Y-m-d')));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de))  { $de  = date('Y-m-01'); }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $ate = date('Y-m-d'); }
            if ($ate < $de) { [$de, $ate] = [$ate, $de]; }
            $ano = (int) substr($de, 0, 4);
            $mes = (int) substr($de, 5, 2);
        } else {
            $de  = sprintf('%04d-%02d-01', $ano, $mes);
            $ate = date('Y-m-t', strtotime($de));
        }

        // --------- Filtros ---------
        $csvInts = static function ($v): array {
            if (is_array($v)) { $arr = $v; } else { $arr = explode(',', (string) $v); }
            $out = [];
            foreach ($arr as $x) {
                $x = (int) trim((string) $x);
                if ($x > 0) { $out[] = $x; }
            }
            return array_values(array_unique($out));
        };

        $filtroFuncs   = $csvInts($_GET['func_id'] ?? []);
        $filtroDiasMes = $csvInts($_GET['dias_mes'] ?? []);
        $filtroDiasSem = $csvInts($_GET['dias_semana'] ?? []);

        $contarFaltas    = ($_GET['contar_faltas'] ?? '1') !== '0';
        $contarAtrasos   = ($_GET['contar_atrasos'] ?? '1') !== '0';
        $contarSaidaCedo = ($_GET['contar_saida_cedo'] ?? '1') !== '0';
        $contarExtras    = ($_GET['contar_extras'] ?? '1') !== '0';
        $pagarFuturos    = ($_GET['pagar_futuros'] ?? '0') === '1';

        // Base de pagamento (aceita os nomes legados)
        $base = (string) ($_GET['base'] ?? 'mes_completo');
        if ($base === 'integral') { $base = 'mes_completo'; }
        if (!in_array($base, ['mes_completo', 'proporcional', 'ate_dia'], true)) {
            $base = 'mes_completo';
        }
        $diaCorte = (int) ($_GET['dia_corte'] ?? 0);
        if ($diaCorte < 1 || $diaCorte > 31) { $diaCorte = (int) date('j'); }
        if ($base === 'mes_completo') { $pagarFuturos = true; }

        $config = new Config();
        $toleranciaMin = (int) $config->get('tolerancia_atraso_min', '5');
        $moeda = $config->get('moeda', 'MZN');

        $funcModel = new Funcionario();
        $regModel = new Registo();
        $ajusteModel = new DiaAjuste();

        $funcionarios = $funcModel->listarPontuaveis();
        $linhas = [];

        // Dias do periodo. Dias futuros so entram quando o gestor decide
        // paga-los (ou quando a base e o mes completo).
        $hoje = date('Y-m-d');
        $todosDias = [];
        $dt = new \DateTime($de);
        $fim = new \DateTime($ate);
        while ($dt <= $fim) {
            $diaStr = $dt->format('Y-m-d');
            $diaNumMes = (int) $dt->format('j');
            $diaSem = (int) $dt->format('N');

            $incluir = true;
            if ($diaStr > $hoje && !$pagarFuturos)                              { $incluir = false; }
            if ($base === 'ate_dia' && $diaNumMes > $diaCorte)                  { $incluir = false; }
            if ($filtroDiasMes && !in_array($diaNumMes, $filtroDiasMes, true))  { $incluir = false; }
            if ($filtroDiasSem && !in_array($diaSem, $filtroDiasSem, true))     { $incluir = false; }

            if ($incluir) { $todosDias[] = $diaStr; }
            $dt->modify('+1 day');
        }

        $anoMes = sprintf('%04d-%02d', $ano, $mes);
        $ultimoDiaMes = (int) date('t', strtotime($anoMes . '-01'));

        foreach ($funcionarios as $f) {
            if (($f['perfil'] ?? '') === 'admin') continue;
            if ($filtroFuncs && !in_array((int) $f['id'], $filtroFuncs, true)) continue;

            // Dias de trabalho: tentar da tabela especifica, fallback para dias da semana
            $diasTrabalhoMes = $funcModel->getDiasTrabalho((int) $f['id'], $anoMes);
            $selectedDays = [];
            $usarDiaSemana = empty($diasTrabalhoMes);
            if ($usarDiaSemana) {
                $selectedDays = array_map('intval', explode(',', $f['dias_trabalho'] ?? '1,2,3,4,5'));
            }

            // Dias programados do mes de referencia (divisor do valor/dia)
            $diasProgramadosMes = [];
            for ($d = 1; $d <= $ultimoDiaMes; $d++) {
                $diaStr = sprintf('%s-%02d', $anoMes, $d);
                $trabalha = $usarDiaSemana
                    ? in_array((int) date('N', strtotime($diaStr)), $selectedDays, true)
                    : in_array($d, $diasTrabalhoMes, true);
                if ($trabalha) { $diasProgramadosMes[] = $d; }
            }
            $nDiasProgramados = count($diasProgramadosMes);

            $dias = $regModel->agregarIntervalo((int) $f['id'], $de, $ate);
            $feriasMap = $regModel->diasFeriasIntervalo((int) $f['id'], $de, $ate);
            $ajustes = $ajusteModel->mapaIntervalo((int) $f['id'], $de, $ate);

            // Indexar dias com registo por data
            $diasPorData = [];
            foreach ($dias as $d) {
                $diasPorData[$d['dia']] = $d;
            }

            $atrasos = 0;
            $faltas = 0;
            $faltasJustificadas = 0;
            $horasAtraso = 0;
            $saidasCedo = 0;
            $horasCedo = 0;
            $horasTrab = 0;
            $horasExtra = 0.0;
            $diasEsperados = 0;   // dias avaliados (presenca esperada)
            $diasTrabalhados = 0; // dias efectivamente presentes
            $diasPagos = 0;       // dias com vencimento no periodo
            $diasFerias = 0;
            $diasSemVencimento = 0;

            // Detalhe dia-a-dia (usado pelo endpoint /relatorios/detalhe)
            $detalhe = [];

            foreach ($todosDias as $diaStr) {
                $aj = $ajustes[$diaStr] ?? null;
                $linhaDia = [
                    'dia'           => $diaStr,
                    'dia_semana'    => (int) date('N', strtotime($diaStr)),
                    'estado'        => 'nao_trabalha',
                    'entrada'       => null,
                    'saida'         => null,
                    'horas'         => 0.0,
                    'horas_atraso'  => 0.0,
                    'horas_cedo'    => 0.0,
                    'horas_extra'   => $aj && $contarExtras ? (float) $aj['horas_extra'] : 0.0,
                    'atrasado'      => false,
                    'saiu_cedo'     => false,
                    'pago'          => false,
                    'observacao'    => '',
                ];
                $horasExtra += $linhaDia['horas_extra'];

                // 1) Ajuste manual do gestor sobrepoe-se a tudo (excepto o dia normal)
                if ($aj && $aj['tipo'] !== 'normal') {
                    $pago = $aj['com_vencimento'];
                    $linhaDia['pago'] = $pago;
                    $linhaDia['observacao'] = $aj['observacao'] !== ''
                        ? $aj['observacao']
                        : (DiaAjuste::TIPOS[$aj['tipo']] ?? '') . ($pago ? ' (com vencimento)' : ' (sem vencimento)');

                    if ($aj['tipo'] === 'falta_justificada') {
                        $linhaDia['estado'] = 'falta_justificada';
                        $faltasJustificadas++;
                        $diasEsperados++;
                    } elseif ($aj['tipo'] === 'ferias') {
                        $linhaDia['estado'] = 'ferias';
                        $diasFerias++;
                    } elseif ($aj['tipo'] === 'folga') {
                        $linhaDia['estado'] = 'folga';
                    } else {
                        $linhaDia['estado'] = 'licenca';
                    }

                    if ($pago) { $diasPagos++; } else { $diasSemVencimento++; }
                    $detalhe[] = $linhaDia;
                    continue;
                }

                // 2) Ferias registadas no livro de ponto
                if (isset($feriasMap[$diaStr])) {
                    $diasFerias++;
                    $diasPagos++;
                    $linhaDia['estado'] = 'ferias';
                    $linhaDia['pago'] = true;
                    $linhaDia['observacao'] = 'Ferias';
                    $detalhe[] = $linhaDia;
                    continue;
                }

                // 3) Escala de trabalho
                $trabalhaNesteDia = $usarDiaSemana
                    ? in_array((int) date('N', strtotime($diaStr)), $selectedDays, true)
                    : in_array((int) date('j', strtotime($diaStr)), $diasTrabalhoMes, true);

                if (!$trabalhaNesteDia) {
                    $linhaDia['observacao'] = $usarDiaSemana ? 'Fora da escala semanal' : 'Fora da escala do mes';
                    $detalhe[] = $linhaDia;
                    continue;
                }

                // 4) Dia programado ainda por decorrer
                if ($diaStr > $hoje) {
                    $linhaDia['estado'] = 'futuro';
                    $linhaDia['observacao'] = 'Dia por decorrer';
                    if ($pagarFuturos) { $diasPagos++; $linhaDia['pago'] = true; }
                    $detalhe[] = $linhaDia;
                    continue;
                }

                if (!isset($diasPorData[$diaStr]) || !$diasPorData[$diaStr]['entrada']) {
                    if ($diaStr === $hoje && time() < strtotime($hoje . ' ' . ($f['hora_saida'] ?? '17:00:00'))) {
                        $linhaDia['estado'] = 'em_curso';
                        $linhaDia['observacao'] = 'Dia ainda a decorrer';
                        if ($pagarFuturos) { $diasPagos++; $linhaDia['pago'] = true; }
                        $detalhe[] = $linhaDia;
                        continue;
                    }
                    $faltas++;
                    $diasEsperados++;
                    $linhaDia['estado'] = 'falta';
                    $linhaDia['observacao'] = 'Sem marcacao de entrada';
                    $detalhe[] = $linhaDia;
                    continue;
                }

                $diasEsperados++;
                $diasTrabalhados++;
                $diasPagos++;
                $d = $diasPorData[$diaStr];
                $linhaDia['estado']  = 'presente';
                $linhaDia['pago']    = true;
                $linhaDia['entrada'] = $d['entrada'];
                $linhaDia['saida']   = $d['saida'] ?? null;
                $horaPadrao = substr($f['hora_entrada'] ?? '08:00:00', 0, 5);
                $he = strtotime($d['entrada']);
                $hp = strtotime(substr($d['entrada'], 0, 10) . ' ' . $horaPadrao);
                $diffLate = RegrasAssiduidade::segundosAtraso($d['entrada'], $horaPadrao);
                if (RegrasAssiduidade::atrasado($diffLate, $toleranciaMin)) {
                    $atrasos++;
                    $linhaDia['atrasado'] = true;
                }
                if ($diffLate > 0) {
                    $horasAtraso += $diffLate / 3600;
                    $linhaDia['horas_atraso'] = round($diffLate / 3600, 4);
                }

                if (!empty($d['saida'])) {
                    $horaSaidaPadrao = substr($f['hora_saida'] ?? '17:00:00', 0, 5);
                    $hs = strtotime($d['saida']);
                    $hsp = strtotime(substr($d['saida'], 0, 10) . ' ' . $horaSaidaPadrao);
                    $diffEarly = RegrasAssiduidade::segundosSaidaAntecipada($d['saida'], $horaSaidaPadrao);
                    if (RegrasAssiduidade::saiuCedo($diffEarly, $toleranciaMin)) {
                        $saidasCedo++;
                        $linhaDia['saiu_cedo'] = true;
                    }
                    if ($diffEarly > 0) {
                        $horasCedo += $diffEarly / 3600;
                        $linhaDia['horas_cedo'] = round($diffEarly / 3600, 4);
                    }
                    $horasTrab += ($hs - $he) / 3600;
                    $linhaDia['horas'] = round(($hs - $he) / 3600, 4);
                }

                if ($linhaDia['observacao'] === '') {
                    $obs = [];
                    if ($linhaDia['atrasado'])  { $obs[] = 'Atraso'; }
                    if ($linhaDia['saiu_cedo']) { $obs[] = 'Saida antecipada'; }
                    if (empty($linhaDia['saida'])) { $obs[] = 'Sem marcacao de saida'; }
                    if ($linhaDia['horas_extra'] > 0) { $obs[] = 'Horas extra'; }
                    $linhaDia['observacao'] = $obs ? implode(' + ', $obs) : 'Normal';
                }

                $detalhe[] = $linhaDia;
            }

            $cargaDiaria = max(1, (float) ($f['carga_diaria'] ?? 8));
            $horasDeveria = round($diasEsperados * $cargaDiaria, 1);

            $horasPenalizadas = RegrasAssiduidade::horasPenalizadas(
                $horasAtraso, $horasCedo, $contarAtrasos, $contarSaidaCedo
            );
            $horasAtrasoTotal = round($horasPenalizadas, 1);

            // ----- Bases de calculo -----
            // Valor/dia = salario base / dias de trabalho definidos no mes.
            $salarioBase = (float) ($f['salario_base'] ?? 0);
            $valorDia = CalculoSalarial::valorDia(
                $salarioBase, $nDiasProgramados, (float) ($f['salario_diario'] ?? 0)
            );
            $valorHora = CalculoSalarial::valorHora($valorDia, $cargaDiaria);
            $valorHoraExtra = (float) ($f['valor_hora_extra'] ?? 0);
            if ($valorHoraExtra <= 0) { $valorHoraExtra = $valorHora; }

            // ----- Salario do periodo conforme a base escolhida -----
            $programadosAteCorte = count(array_filter(
                $diasProgramadosMes,
                static fn(int $d): bool => $d <= $diaCorte
            ));
            $salarioPeriodo = CalculoSalarial::salarioPeriodo(
                $base, $salarioBase, $diasPagos, $programadosAteCorte, $valorDia
            );

            // ----- Cortes (regras isoladas em app/services/CalculoSalarial.php) -----
            $c = CalculoSalarial::cortes(
                $salarioPeriodo, $valorDia, $valorHora,
                $faltas, $horasPenalizadas, $diasSemVencimento,
                $diasTrabalhados, $faltasJustificadas, $diasFerias,
                $contarFaltas, $base
            );
            $corteFalta             = $c['corte_falta'];
            $corteAtraso            = $c['corte_atraso'];
            $corteDiasSemVencimento = $c['corte_sem_vencimento'];
            $totalCorteBruto        = $c['corte_bruto'];
            $totalCorte             = $c['total_corte'];
            $corteLimitado          = $c['corte_limitado'];
            $fatorCorte             = $c['fator'];

            // Corte imputado a cada dia (respeita as opcoes activas e o tecto)
            foreach ($detalhe as $i => $ld) {
                $detalhe[$i]['corte'] = CalculoSalarial::corteDoDia(
                    $ld, $valorDia, $valorHora,
                    $contarFaltas, $contarAtrasos, $contarSaidaCedo,
                    $base, $corteLimitado ? $fatorCorte : 1.0
                );
                $detalhe[$i]['ganho_extra'] = round($ld['horas_extra'] * $valorHoraExtra, 2);
            }

            $ganhoExtra = $contarExtras ? round($horasExtra * $valorHoraExtra, 2) : 0.0;
            $liquido = CalculoSalarial::liquido($salarioPeriodo, $totalCorte, $ganhoExtra);

            // Converte horas decimais em HH:MM:SS sem produzir 60 minutos/segundos
            $fmtHms = static fn($dec): string => RegrasAssiduidade::hms((float) $dec);

            $linhas[] = [
                'id'                 => $f['id'],
                'nome'               => $f['nome'],
                'cargo'              => $f['cargo'],
                'salario'            => $salarioPeriodo,
                'salario_base'       => $salarioBase,
                'dias_pagos'         => $diasPagos,
                'dias_programados'   => $nDiasProgramados,
                'dias_trabalhados'   => $diasTrabalhados,
                'dias_avaliados'     => $diasEsperados,
                'dias_sem_vencimento'=> $diasSemVencimento,
                'faltas'             => $faltas,
                'faltas_justificadas'=> $faltasJustificadas,
                'atrasos'            => $atrasos,
                'saidas_cedo'        => $saidasCedo,
                'ferias'             => $diasFerias,
                'horas'              => $fmtHms($horasTrab),
                'horas_atraso'       => $fmtHms($horasAtrasoTotal),
                'horas_deveria'      => $fmtHms($horasDeveria),
                'horas_extra'        => round($horasExtra, 2),
                'ganho_extra'        => $ganhoExtra,
                'corte_atraso'       => $corteAtraso,
                'corte_falta'        => $corteFalta,
                'corte_sem_vencimento' => $corteDiasSemVencimento,
                'corte_bruto'        => $totalCorteBruto,
                'corte_limitado'     => $corteLimitado,
                'total_corte'        => $totalCorte,
                'liquido'            => $liquido,
                'valor_dia'          => $valorDia,
                'valor_hora'         => $valorHora,
                'valor_hora_extra'   => $valorHoraExtra,
                'detalhe'            => $detalhe,
            ];
        }

        // A sessao guarda apenas o resumo (o detalhe diario e servido a pedido)
        $_SESSION['ultimo_relatorio'] = array_map(
            static fn(array $l): array => array_diff_key($l, ['detalhe' => null]),
            $linhas
        );

        // Rede de seguranca: confirma que os numeros respeitam as regras que
        // nunca podem ser violadas. Um desvio fica registado em /diagnostico.
        $problemas = Invariantes::verificarCalculo([
            'linhas' => $linhas, 'de' => $de, 'ate' => $ate,
        ]);

        return [
            'titulo'      => 'Relatorios & Salarios',
            'linhas'      => $linhas,
            'avisosCalculo' => $problemas,
            'ano'         => $ano,
            'mes'         => $mes,
            'moeda'       => $moeda,
            'tolerancia'  => $toleranciaMin,
            'nomeMes'     => $this->nomeMes($mes),
            'modo'        => $modo,
            'de'          => $de,
            'ate'         => $ate,
            'diasNoPeriodo'     => count($todosDias),
            'filtroFuncs'       => $filtroFuncs,
            'filtroDiasMes'     => $filtroDiasMes,
            'filtroDiasSem'     => $filtroDiasSem,
            'contarFaltas'      => $contarFaltas,
            'contarAtrasos'     => $contarAtrasos,
            'contarSaidaCedo'   => $contarSaidaCedo,
            'contarExtras'      => $contarExtras,
            'pagarFuturos'      => $pagarFuturos,
            'base'              => $base,
            'diaCorte'          => $diaCorte,
            'funcionarios'      => array_values(array_filter($funcionarios, fn($x) => ($x['perfil'] ?? '') !== 'admin')),
            'queryFiltros'      => $_GET,
        ];
    }


    /**
     * Processar faltas automaticamente: insere registos 'falta' na BD para
     * dias de trabalho sem marcacao de entrada num periodo.
     * POST: mes (YYYY-MM) ou de + ate (Y-m-d)
     */
    public function processarFaltas(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $mes = $_POST['mes'] ?? '';
        if ($mes && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $de = $mes . '-01';
            $ate = date('Y-m-t', strtotime($de));
        } else {
            $de = trim($_POST['de'] ?? date('Y-m-01'));
            $ate = trim($_POST['ate'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $de) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
                $this->json(['ok' => false, 'erro' => 'Datas invalidas. Use YYYY-MM-DD.'], 400);
            }
        }

        $resultado = (new Registo())->processarFaltas($de, $ate);

        Log::reg(Auth::id(), 'faltas_processadas', 'registos', null, [
            'de' => $de, 'ate' => $ate,
            'processados' => $resultado['processados'],
            'faltas_inseridas' => $resultado['faltas_inseridas'],
        ]);

        $this->json([
            'ok' => true,
            'processados' => $resultado['processados'],
            'faltas_inseridas' => $resultado['faltas_inseridas'],
            'mensagem' => "Processados {$resultado['processados']} funcionarios. Inseridas {$resultado['faltas_inseridas']} falta(s).",
        ]);
    }

    /**
     * Modelos de calculo (presets de filtros).
     * GET  /relatorios/modelos            -> lista JSON
     * POST /relatorios/modelos/guardar    -> cria/atualiza (nome + filtros)
     * POST /relatorios/modelos/eliminar   -> remove por id
     */
    /**
     * Detalhe dia-a-dia de um funcionario, com os mesmos filtros do relatorio.
     * GET /relatorios/detalhe?func_id=X&modo=...&mes=...&dias_mes[]=...
     */
    public function detalhe(): void {
        $this->requireRole('admin', 'gestor');

        $funcId = (int) ($_GET['func_id'] ?? 0);
        if ($funcId <= 0) {
            $this->json(['ok' => false, 'erro' => 'Indique o funcionario.'], 422);
        }

        // Reaproveita o motor de calculo, restringindo a um unico funcionario
        $_GET['func_id'] = [$funcId];
        $data = $this->calcular();

        $linha = null;
        foreach ($data['linhas'] as $l) {
            if ((int) $l['id'] === $funcId) { $linha = $l; break; }
        }
        if (!$linha) {
            $alvo = (new Funcionario())->porId($funcId);
            $msg = 'Funcionario nao encontrado no periodo filtrado.';
            if ($alvo && ($alvo['perfil'] ?? '') === 'admin') {
                $msg = 'Perfis de administrador nao entram no calculo de salarios.';
            } elseif ($alvo && (int) ($alvo['ativo'] ?? 1) === 0) {
                $msg = 'Este funcionario esta inativo e nao entra no calculo.';
            }
            $this->json(['ok' => false, 'erro' => $msg], 404);
        }

        $detalhe = $linha['detalhe'] ?? [];
        unset($linha['detalhe']);

        $this->json([
            'ok'       => true,
            'periodo'  => ['de' => $data['de'], 'ate' => $data['ate'], 'dias' => $data['diasNoPeriodo']],
            'moeda'    => $data['moeda'],
            'resumo'   => $linha,
            'detalhe'  => $detalhe,
        ]);
    }

    /**
     * Minha Assiduidade: progresso e cortes do PROPRIO utilizador em sessao.
     * GET /minha-assiduidade?mes=YYYY-MM
     *
     * Reaproveita integralmente o motor de calculo do relatorio (mesma fonte
     * de verdade que o gestor ve), mas forca o filtro ao id da sessao: um
     * funcionario nunca consegue observar dados de colegas, mesmo alterando
     * parametros no endereco.
     */
    public function minhaAssiduidade(): void {
        $this->requireLogin();

        if (!Auth::marcaPonto()) {
            http_response_code(403);
            $this->view('public/erro', [
                'titulo'   => 'Minha Assiduidade',
                'mensagem' => 'A sua conta e administrativa e nao entra no controlo de assiduidade.',
            ]);
            return;
        }

        $id = (int) Auth::id();
        $mesRef = (string) ($_GET['mes'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mesRef) || $mesRef > date('Y-m')) {
            $mesRef = date('Y-m');
        }

        // Contexto de calculo controlado pelo servidor (ignora tudo o resto do GET)
        $getOriginal = $_GET;
        $sessaoOriginal = $_SESSION['ultimo_relatorio'] ?? null;
        $_GET = ['modo' => 'mes', 'mes' => $mesRef, 'func_id' => [$id], 'base' => 'mes_completo'];

        $data = $this->calcular();

        $_GET = $getOriginal;
        if ($sessaoOriginal === null) { unset($_SESSION['ultimo_relatorio']); }
        else { $_SESSION['ultimo_relatorio'] = $sessaoOriginal; }

        $resumo = null;
        foreach ($data['linhas'] as $l) {
            if ((int) $l['id'] === $id) { $resumo = $l; break; }
        }

        $detalhe = [];
        if ($resumo) {
            $detalhe = array_values(array_filter(
                $resumo['detalhe'] ?? [],
                static fn(array $d): bool => $d['estado'] !== 'nao_trabalha'
            ));
            unset($resumo['detalhe']);
        }

        $avaliados = $resumo ? (int) $resumo['dias_avaliados'] : 0;
        $percentual = $avaliados > 0
            ? round(((int) $resumo['dias_trabalhados'] / $avaliados) * 100, 1)
            : 0.0;

        $this->view('admin/minha_assiduidade', [
            'titulo'     => 'Minha Assiduidade',
            'meta_desc'  => 'Progresso de assiduidade e cortes do proprio funcionario.',
            'resumo'     => $resumo,
            'detalhe'    => $detalhe,
            'percentual' => $percentual,
            'mesRef'     => $mesRef,
            'ano'        => $data['ano'],
            'mes'        => $data['mes'],
            'nomeMes'    => $data['nomeMes'],
            'moeda'      => $data['moeda'],
            'tolerancia' => $data['tolerancia'],
        ]);
    }



    /**
     * Recibo de salario individual (A4 imprimivel), com o detalhe dia-a-dia
     * e os mesmos filtros aplicados no relatorio.
     * GET /relatorios/recibo?func_id=X&...filtros
     */
    public function recibo(): void {
        $this->requireRole('admin', 'gestor');

        $funcId = (int) ($_GET['func_id'] ?? 0);
        if ($funcId <= 0) {
            $this->view('public/erro', ['titulo' => 'Recibo', 'mensagem' => 'Indique o funcionario.']);
            return;
        }

        $func = (new Funcionario())->porId($funcId);
        if (!$func) {
            $this->view('public/erro', ['titulo' => 'Recibo', 'mensagem' => 'Funcionario nao encontrado.']);
            return;
        }
        unset($func['password_hash'], $func['pin_hash']);

        $_GET['func_id'] = [$funcId];
        $data = $this->calcular();

        $resumo = null;
        foreach ($data['linhas'] as $l) {
            if ((int) $l['id'] === $funcId) { $resumo = $l; break; }
        }
        if (!$resumo) {
            $this->view('public/erro', ['titulo' => 'Recibo', 'mensagem' => 'Sem dados para o periodo filtrado.']);
            return;
        }

        $detalhe = $resumo['detalhe'] ?? [];
        unset($resumo['detalhe']);

        $opcoes = $this->opcoesRecibo();

        Log::reg(Auth::id(), 'recibo_emitido', 'funcionarios', (string) $funcId, [
            'de' => $data['de'], 'ate' => $data['ate'], 'liquido' => $resumo['liquido'],
        ]);

        $ano = $data['ano'];
        $nomeMes = $data['nomeMes'];
        $moeda = $data['moeda'];
        $tolerancia = $data['tolerancia'];
        $de = $data['de'];
        $ate = $data['ate'];

        require __DIR__ . '/../views/admin/recibo_pdf.php';
    }

    public function modelos(): void {
        $this->requireRole('admin', 'gestor');
        $this->json(['ok' => true, 'modelos' => (new RelatorioModelo())->listar()]);
    }

    public function guardarModelo(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $nome = trim((string) ($_POST['nome'] ?? ''));
        if ($nome === '' || mb_strlen($nome) > 80) {
            $this->json(['ok' => false, 'erro' => 'Indique um nome com ate 80 caracteres.'], 422);
        }

        $filtros = RelatorioModelo::normalizar($_POST);
        if (!$filtros) {
            $this->json(['ok' => false, 'erro' => 'Nenhum filtro para guardar.'], 422);
        }

        $modelo = new RelatorioModelo();
        $id = $modelo->guardar($nome, $filtros, Auth::id());
        Log::reg(Auth::id(), 'modelo_calculo_guardado', 'relatorio_modelos', (string) $id, [
            'nome' => $nome, 'filtros' => $filtros,
        ]);

        $this->json(['ok' => true, 'id' => $id, 'modelos' => $modelo->listar()]);
    }

    public function eliminarModelo(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $modelo = new RelatorioModelo();
        $alvo = $modelo->porId($id);
        if (!$alvo) {
            $this->json(['ok' => false, 'erro' => 'Modelo nao encontrado.'], 404);
        }

        $modelo->eliminar($id);
        Log::reg(Auth::id(), 'modelo_calculo_eliminado', 'relatorio_modelos', (string) $id, [
            'nome' => $alvo['nome'],
        ]);

        $this->json(['ok' => true, 'modelos' => $modelo->listar()]);
    }

    /**
     * Opcoes de apresentacao dos recibos (partilhadas pelo individual e pelo lote).
     *
     * @return array<string,string>
     */
    private function opcoesRecibo(): array {
        $config = new Config();
        return [
            'titulo_doc'      => trim($_GET['titulo_doc'] ?? 'Recibo de Salário'),
            'instituicao'     => trim($_GET['instituicao'] ?? $config->get('instituicao', 'FarmaPonto Lda')),
            'autor'           => trim($_GET['autor'] ?? (Auth::user()['nome'] ?? '')),
            'nota_rodape'     => trim($_GET['nota_rodape'] ?? $config->get('relatorio_nota_rodape', '')),
            'mostrar_detalhe' => ($_GET['mostrar_detalhe'] ?? '1') === '0' ? '0' : '1',
        ];
    }

    /**
     * Recibos em lote: um unico documento A4 com o recibo de cada
     * funcionario do filtro actual (1 recibo por pagina) + indice.
     * GET /relatorios/recibos?<mesmos filtros de /relatorios>
     */
    public function recibosLote(): void {
        $this->requireRole('admin', 'gestor');

        $data = $this->calcular();
        $modelFunc = new Funcionario();

        $recibos = [];
        foreach ($data['linhas'] as $linha) {
            $func = $modelFunc->porId((int) $linha['id']);
            if (!$func) { continue; }
            unset($func['password_hash'], $func['pin_hash']);
            $detalhe = $linha['detalhe'] ?? [];
            unset($linha['detalhe']);
            $recibos[] = ['func' => $func, 'resumo' => $linha, 'detalhe' => $detalhe];
        }

        if (!$recibos) {
            $this->view('public/erro', [
                'titulo'   => 'Recibos em lote',
                'mensagem' => 'Nao ha funcionarios no filtro seleccionado.',
            ]);
            return;
        }

        Log::reg(Auth::id(), 'recibos_lote_emitidos', 'funcionarios', null, [
            'de' => $data['de'], 'ate' => $data['ate'], 'recibos' => count($recibos),
        ]);

        $opcoes     = $this->opcoesRecibo();
        $ano        = $data['ano'];
        $nomeMes    = $data['nomeMes'];
        $moeda      = $data['moeda'];
        $tolerancia = $data['tolerancia'];
        $de         = $data['de'];
        $ate        = $data['ate'];

        require __DIR__ . '/../views/admin/recibos_lote.php';
    }

    /* ==========================================================
       FOLHAS SALARIAIS (fecho / historico)
       O fecho e apenas um snapshot auditavel: o utilizador pode
       recalcular o relatorio quando quiser, sem restricoes.
       ========================================================== */

    /** GET /folhas — historico de folhas fechadas. */
    public function folhas(): void {
        $this->requireRole('admin', 'gestor');
        $modelo = new FolhaSalarial();
        $this->view('admin/folhas', [
            'titulo' => 'Folhas Salariais',
            'folhas' => $modelo->listar(),
        ]);
    }

    /** GET /folhas/{id} — detalhe de uma folha fechada. */
    public function verFolha(string $id): void {
        $this->requireRole('admin', 'gestor');
        $modelo = new FolhaSalarial();
        $folha  = $modelo->porId((int) $id);
        if (!$folha) {
            $this->view('public/erro', ['titulo' => 'Folha', 'mensagem' => 'Folha nao encontrada.']);
            return;
        }
        $this->view('admin/folhas', [
            'titulo' => 'Folha Salarial #' . (int) $id,
            'folhas' => $modelo->listar(),
            'folha'  => $folha,
            'itens'  => $modelo->itens((int) $id),
        ]);
    }

    /** POST /folhas/fechar — guarda o calculo actual como folha. */
    public function fecharFolha(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        // Os filtros chegam por POST; o motor le de $_GET
        foreach ($_POST as $k => $v) {
            if ($k !== 'csrf' && $k !== 'observacao') { $_GET[$k] = $v; }
        }

        $calculo = $this->calcular();
        if (!$calculo['linhas']) {
            $this->json(['ok' => false, 'erro' => 'Nao ha funcionarios no filtro seleccionado.'], 422);
        }

        $observacao = mb_substr(trim((string) ($_POST['observacao'] ?? '')), 0, 255);
        $modelo = new FolhaSalarial();
        $id = $modelo->fechar($calculo, $observacao, Auth::id());

        Log::reg(Auth::id(), 'folha_fechada', 'folhas_salariais', (string) $id, [
            'de' => $calculo['de'], 'ate' => $calculo['ate'],
            'funcionarios' => count($calculo['linhas']),
        ]);

        $this->json(['ok' => true, 'id' => $id, 'url' => BASE_PATH . '/folhas/' . $id]);
    }

    /** POST /folhas/reabrir — marca a folha como reaberta. */
    public function reabrirFolha(): void {
        $this->requireRole('admin', 'gestor');
        $this->checkCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $modelo = new FolhaSalarial();
        if (!$modelo->porId($id)) {
            $this->json(['ok' => false, 'erro' => 'Folha nao encontrada.'], 404);
        }
        $modelo->reabrir($id);
        Log::reg(Auth::id(), 'folha_reaberta', 'folhas_salariais', (string) $id, []);
        $this->json(['ok' => true]);
    }

    /** POST /folhas/eliminar — remove a folha e os seus itens. */
    public function eliminarFolha(): void {
        $this->requireRole('admin');
        $this->checkCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $modelo = new FolhaSalarial();
        $folha = $modelo->porId($id);
        if (!$folha) {
            $this->json(['ok' => false, 'erro' => 'Esta folha já não existe. Actualize a página para ver a lista actual.'], 404);
        }
        // A folha vai para a lixeira (com os seus itens) antes de sair da lista.
        $itens = $modelo->itens($id);
        foreach ($itens as &$it) { $it['dados'] = json_encode($it['dados'], JSON_UNESCAPED_UNICODE); }
        unset($it);
        $bruta = $folha;
        $bruta['filtros'] = json_encode($folha['filtros'] ?? [], JSON_UNESCAPED_UNICODE);
        $bruta['totais']  = json_encode($folha['totais'] ?? [], JSON_UNESCAPED_UNICODE);
        unset($bruta['autor'], $bruta['n_itens']);
        (new \App\Models\Lixeira())->arquivar(
            'folhas_salariais', $bruta, Auth::id(),
            'Folha salarial eliminada',
            'Folha ' . ($folha['referencia'] ?? '') . ' (' . $folha['periodo_de'] . ' a ' . $folha['periodo_ate'] . ')',
            ['folha_salarial_itens' => $itens]
        );
        $modelo->eliminar($id);
        Log::reg(Auth::id(), 'folha_eliminada', 'folhas_salariais', (string) $id, []);
        $this->json(['ok' => true]);
    }

    private function nomeMes(int $mes): string {
        $meses = ['', 'Janeiro', 'Fevereiro', 'Marco', 'Abril', 'Maio', 'Junho',
                  'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        return $meses[$mes] ?? '';
    }
}
