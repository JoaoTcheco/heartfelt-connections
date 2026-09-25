<?php
/**
 * ============================================================
 * FarmaPonto - Bloco de um recibo de salario (parcial reutilizavel)
 * ============================================================
 * Renderiza UM recibo. E incluido por:
 *   - admin/recibo_pdf.php    (um funcionario)
 *   - admin/recibos_lote.php  (varios funcionarios, 1 por pagina)
 *
 * Variaveis esperadas (definidas pelo ficheiro que inclui):
 *   $func (array)     Dados do funcionario
 *   $resumo (array)   Linha do motor de calculo (RelatoriosController::calcular)
 *   $detalhe (array)  Dias do periodo (pode ser [])
 *   $de, $ate, $nomeMes, $ano, $moeda, $tolerancia
 *   $instituicao, $tituloDoc, $autor, $notaExtra, $mostrarDet (bool), $logoAbs
 *
 * Nao imprime <html>/<style>: o layout e a folha de estilo pertencem ao shell.
 */

$fmtR = static fn($v): string => number_format((float) $v, 2, ',', '.');

$estadosR = [
    'presente'          => ['Presente', '#065f46'],
    'falta'             => ['Falta', '#b91c1c'],
    'ferias'            => ['Férias', '#1d4ed8'],
    'nao_trabalha'      => ['Não trabalha', '#6b7280'],
    'em_curso'          => ['Em curso', '#b45309'],
    'falta_justificada' => ['Falta justificada', '#4338ca'],
    'folga'             => ['Folga', '#7e22ce'],
    'licenca'           => ['Licença', '#c2410c'],
    'futuro'            => ['Por decorrer', '#6b7280'],
];
$diasSemanaR = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
$refDoc = strtoupper(substr(md5($func['id'] . $de . $ate), 0, 8));
?>
<section class="recibo">

<div class="header">
    <div class="brand">
        <?php if (!empty($logoAbs) && is_file($logoAbs)): ?>
          <img src="<?= BASE_PATH ?>/logo?v=<?= @filemtime($logoAbs) ?>" alt="logo" style="height:42px;width:auto;border-radius:6px;">
        <?php endif; ?>
        <span><?= htmlspecialchars($instituicao) ?></span>
    </div>
    <div class="meta">
        <?= htmlspecialchars($tituloDoc) ?><br>
        Ref. <?= $refDoc ?> · Emitido em <?= date('d/m/Y H:i') ?>
    </div>
</div>

<h1><?= htmlspecialchars($tituloDoc) ?></h1>
<div class="sub">Período de referência: <?= date('d/m/Y', strtotime($de)) ?> a <?= date('d/m/Y', strtotime($ate)) ?> (<?= $nomeMes ?> de <?= $ano ?>)</div>

<div class="grid">
    <div class="card">
        <h3>Funcionário</h3>
        <div><b>Nome:</b> <?= htmlspecialchars($func['nome']) ?></div>
        <div><b>Cargo:</b> <?= htmlspecialchars($func['cargo'] ?? '—') ?></div>
        <div><b>Email:</b> <?= htmlspecialchars($func['email'] ?? '—') ?></div>
        <div><b>Horário:</b> <?= substr($func['hora_entrada'] ?? '08:00:00', 0, 5) ?> – <?= substr($func['hora_saida'] ?? '17:00:00', 0, 5) ?> (<?= (float) ($func['carga_diaria'] ?? 8) ?>h/dia)</div>
    </div>
    <div class="card">
        <h3>Bases de cálculo</h3>
        <div><b>Salário base:</b> <?= $fmtR($resumo['salario_base'] ?? $resumo['salario']) ?> <?= $moeda ?></div>
        <div><b>Valor dia:</b> <?= $fmtR($resumo['valor_dia']) ?> <?= $moeda ?></div>
        <div><b>Valor hora:</b> <?= $fmtR($resumo['valor_hora']) ?> <?= $moeda ?></div>
        <div><b>Valor hora extra:</b> <?= $fmtR($resumo['valor_hora_extra'] ?? $resumo['valor_hora']) ?> <?= $moeda ?></div>
        <div><b>Dias de trabalho no mês:</b> <?= (int) ($resumo['dias_programados'] ?? 0) ?></div>
        <div><b>Tolerância de atraso:</b> <?= (int) $tolerancia ?> min</div>
    </div>
</div>

<div class="grid">
    <div class="card">
        <h3>Assiduidade no período</h3>
        <div><b>Dias com vencimento:</b> <?= (int) ($resumo['dias_pagos'] ?? 0) ?> &nbsp;·&nbsp; <b>Dias trabalhados:</b> <?= (int) ($resumo['dias_trabalhados'] ?? 0) ?></div>
        <div><b>Faltas:</b> <?= (int) $resumo['faltas'] ?> &nbsp;·&nbsp; <b>Faltas justificadas:</b> <?= (int) ($resumo['faltas_justificadas'] ?? 0) ?></div>
        <div><b>Atrasos:</b> <?= (int) ($resumo['atrasos'] ?? 0) ?> &nbsp;·&nbsp; <b>Saídas antecipadas:</b> <?= (int) ($resumo['saidas_cedo'] ?? 0) ?></div>
        <div><b>Férias:</b> <?= (int) ($resumo['ferias'] ?? 0) ?> dia(s) &nbsp;·&nbsp; <b>Dias sem vencimento:</b> <?= (int) ($resumo['dias_sem_vencimento'] ?? 0) ?></div>
        <div><b>Horas extra:</b> <?= number_format((float) ($resumo['horas_extra'] ?? 0), 2, ',', '.') ?> h</div>
        <div><b>Horas trabalhadas:</b> <?= $resumo['horas'] ?> (previstas <?= $resumo['horas_deveria'] ?>)</div>
    </div>
    <div class="card">
        <h3>Resumo financeiro</h3>
        <div><b>Salário do período:</b> <?= $fmtR($resumo['salario']) ?> <?= $moeda ?></div>
        <div class="corte"><b>Corte por faltas:</b> −<?= $fmtR($resumo['corte_falta']) ?> <?= $moeda ?></div>
        <div class="corte"><b>Corte por atrasos/saídas:</b> −<?= $fmtR($resumo['corte_atraso']) ?> <?= $moeda ?></div>
        <?php if (($resumo['corte_sem_vencimento'] ?? 0) > 0): ?>
        <div class="corte"><b>Corte por dias sem vencimento:</b> −<?= $fmtR($resumo['corte_sem_vencimento']) ?> <?= $moeda ?></div>
        <?php endif; ?>
        <?php if (($resumo['ganho_extra'] ?? 0) > 0): ?>
        <div><b>(+) Horas extra:</b> <?= $fmtR($resumo['ganho_extra']) ?> <?= $moeda ?></div>
        <?php endif; ?>
        <?php if (!empty($resumo['corte_limitado'])): ?>
        <div style="color:#b45309;font-size:8pt;">Cortes limitados: garantidos os dias efetivamente trabalhados.</div>
        <?php endif; ?>
        <div class="liquido"><b>Líquido a receber:</b> <?= $fmtR($resumo['liquido']) ?> <?= $moeda ?></div>
    </div>
</div>

<?php if (!empty($mostrarDet)): ?>
<h3 style="font-size:11pt;margin:18px 0 6px;color:#064e3b;">Detalhe dia-a-dia</h3>
<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Dia</th>
            <th>Estado</th>
            <th>Entrada</th>
            <th>Saída</th>
            <th class="num">Atraso (h)</th>
            <th class="num">Saída cedo (h)</th>
            <th class="num">Extra (h)</th>
            <th>Observação</th>
            <th class="num">Corte</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($detalhe as $d):
        [$estLabel, $estCor] = $estadosR[$d['estado']] ?? [$d['estado'], '#000'];
    ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($d['dia'])) ?></td>
            <td><?= $diasSemanaR[$d['dia_semana']] ?? '' ?></td>
            <td style="color:<?= $estCor ?>"><?= $estLabel ?></td>
            <td><?= $d['entrada'] ? substr($d['entrada'], 11, 5) : '—' ?></td>
            <td><?= $d['saida'] ? substr($d['saida'], 11, 5) : '—' ?></td>
            <td class="num"><?= $d['horas_atraso'] > 0 ? number_format($d['horas_atraso'], 2, ',', '.') : '—' ?></td>
            <td class="num"><?= $d['horas_cedo'] > 0 ? number_format($d['horas_cedo'], 2, ',', '.') : '—' ?></td>
            <td class="num"><?= ($d['horas_extra'] ?? 0) > 0 ? number_format($d['horas_extra'], 2, ',', '.') : '—' ?></td>
            <td><?= htmlspecialchars($d['observacao']) ?></td>
            <td class="num corte"><?= ($d['corte'] ?? 0) > 0 ? $fmtR($d['corte']) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($detalhe)): ?>
        <tr><td colspan="10" style="text-align:center;color:#999;padding:16px;">Sem dias no período filtrado.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php endif; ?>

<table class="resumo">
    <tr><td>Salário do período</td><td class="num"><?= $fmtR($resumo['salario']) ?> <?= $moeda ?></td></tr>
    <tr><td>(−) Corte por faltas</td><td class="num corte"><?= $fmtR($resumo['corte_falta']) ?></td></tr>
    <tr><td>(−) Corte por atrasos / saídas antecipadas</td><td class="num corte"><?= $fmtR($resumo['corte_atraso']) ?></td></tr>
    <?php if (($resumo['corte_sem_vencimento'] ?? 0) > 0): ?>
    <tr><td>(−) Corte por dias sem vencimento</td><td class="num corte"><?= $fmtR($resumo['corte_sem_vencimento']) ?></td></tr>
    <?php endif; ?>
    <?php if (($resumo['ganho_extra'] ?? 0) > 0): ?>
    <tr><td>(+) Horas extra (<?= number_format((float) $resumo['horas_extra'], 2, ',', '.') ?> h)</td><td class="num"><?= $fmtR($resumo['ganho_extra']) ?></td></tr>
    <?php endif; ?>
    <tr><td>Total de cortes</td><td class="num corte"><?= $fmtR($resumo['total_corte']) ?></td></tr>
    <tr class="total"><td><b>Líquido a receber</b></td><td class="num liquido"><?= $fmtR($resumo['liquido']) ?> <?= $moeda ?></td></tr>
</table>

<div class="assinaturas">
    <div>O funcionário<br><?= htmlspecialchars($func['nome']) ?></div>
    <div>Pela entidade<br><?= htmlspecialchars($autor ?: $instituicao) ?></div>
</div>

<div class="footer">
    <b>Nota.</b> Valor dia = salário base ÷ dias de trabalho definidos; valor hora = valor dia ÷ carga horária diária.
    Cortes aplicados apenas aos dias incluídos nos filtros do período e limitados aos dias efetivamente trabalhados.
    Tolerância de atraso: <?= (int) $tolerancia ?> min. Valores em <?= $moeda ?>.
    Documento gerado automaticamente pelo FarmaPonto.
    <?php if (!empty($notaExtra)): ?><br><?= htmlspecialchars($notaExtra) ?><?php endif; ?>
</div>

</section>
