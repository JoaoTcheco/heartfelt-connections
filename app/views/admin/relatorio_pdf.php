<?php
/** Vista imprimível A4 — guardar como PDF via diálogo de impressão. */
use App\Controllers\RelatoriosController;

$totalGeral  = array_sum(array_column($linhas, 'liquido'));
$totalCortes = array_sum(array_column($linhas, 'total_corte'));
$totalExtras = array_sum(array_column($linhas, 'ganho_extra'));

$opcoes = $opcoes ?? [];
$tituloDoc  = $opcoes['titulo_doc']  ?? 'Relatório de Salários e Cortes';
$subtitulo  = $opcoes['subtitulo']   ?? '';
$autor      = $opcoes['autor']       ?? '';
$instituicao= $opcoes['instituicao'] ?? 'FarmaPonto';
$notaExtra  = $opcoes['nota_rodape'] ?? '';

$labels    = RelatoriosController::colunasExportaveis();
$numericas = RelatoriosController::colunasNumericas();
$monetarias= RelatoriosController::colunasMonetarias();
$colunas   = $opcoes['colunas'] ?? array_keys($labels);

$periodoDesc   = $periodoDesc   ?? ($nomeMes . ' de ' . $ano);
$baseRotulo    = $baseRotulo    ?? 'Mês completo';
$resumoFiltros = $resumoFiltros ?? [];
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($tituloDoc) ?> — <?= $nomeMes ?> <?= $ano ?> · FarmaPonto</title>
<style>
    @page { size: A4 portrait; margin: 18mm; }
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #000; margin: 0; padding: 18mm; }
    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 16px; }
    .brand { color: #047857; font-weight: bold; font-size: 18pt; }
    .meta { font-size: 10pt; color: #555; text-align: right; }
    h1 { text-align: center; font-size: 16pt; margin: 18px 0 4px; }
    .sub { text-align: center; color: #555; font-size: 11pt; margin-bottom: 6px; }
    .autor { text-align: center; color: #777; font-size: 10pt; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 10pt; }
    th, td { border-bottom: 1px solid #ccc; padding: 6px 4px; text-align: left; }
    th { background: #ecfdf5; color: #064e3b; border-bottom: 2px solid #047857; }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .corte { color: #b91c1c; }
    .liquido { color: #047857; font-weight: bold; }
    .footer { margin-top: 20px; font-size: 9pt; color: #555; border-top: 1px solid #ccc; padding-top: 8px; }
    .totais { margin-top: 14px; display: flex; justify-content: flex-end; gap: 32px; font-size: 11pt; }
    .totais b { color: #047857; }
    .actions { margin-bottom: 12px; }
    .actions button, .actions a { background: #047857; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 11pt; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 6px; font-family: inherit; }
    .actions a.sec { background: #6b7280; }
    @media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body onload="setTimeout(()=>window.print(), 300)">

<div class="actions">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
    <a class="sec" href="<?= BASE_PATH ?>/relatorios?mes=<?= sprintf('%04d-%02d', $ano, $mes) ?>">Voltar</a>
</div>

<div class="header">
    <?php
      $__cfg2 = new \App\Models\Config();
      $__logoRel = $__cfg2->get('instituicao_logo', '');
      $__logoAbs = $__logoRel ? (__DIR__ . '/../../../' . $__logoRel) : '';
    ?>
    <div class="brand" style="display:flex;align-items:center;gap:10px;">
        <?php if ($__logoAbs && is_file($__logoAbs)): ?>
          <img src="<?= BASE_PATH ?>/logo?v=<?= @filemtime($__logoAbs) ?>" alt="logo" style="height:42px;width:auto;border-radius:6px;">
        <?php endif; ?>
        <span><?= htmlspecialchars($instituicao) ?></span>
    </div>
    <div class="meta">
        <?= htmlspecialchars($tituloDoc) ?><br>
        Emitido em <?= date('d/m/Y H:i') ?>
    </div>
</div>

<h1><?= htmlspecialchars($tituloDoc) ?></h1>
<div class="sub">Período de referência: <?= htmlspecialchars($periodoDesc) ?><?= $subtitulo ? ' — ' . htmlspecialchars($subtitulo) : '' ?></div>
<div class="sub">Modo de pagamento: <?= htmlspecialchars($baseRotulo) ?></div>
<?php if ($autor): ?><div class="autor">Emitido por: <?= htmlspecialchars($autor) ?></div><?php endif; ?>

<table>
    <thead>
        <tr>
        <?php foreach ($colunas as $c): $c = trim($c); if (!isset($labels[$c])) continue; ?>
            <th class="<?= in_array($c, $numericas, true) ? 'num' : '' ?>"><?= $labels[$c] ?></th>
        <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($linhas as $l): ?>
        <tr>
        <?php foreach ($colunas as $c): $c = trim($c); if (!isset($labels[$c])) continue;
            $valor = $l[$c] ?? '';
            $isNum = in_array($c, $numericas, true);
            $classe = '';
            if (str_starts_with($c, 'corte_') || $c === 'total_corte') $classe = 'corte';
            elseif ($c === 'liquido') $classe = 'liquido';
            $fmt = is_numeric($valor) && in_array($c, $monetarias, true)
                ? number_format((float) $valor, 2, ',', '.')
                : htmlspecialchars((string) $valor);
        ?>
            <td class="<?= $isNum ? 'num' : '' ?> <?= $classe ?>"><?= $fmt ?></td>
        <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($linhas)): ?>
        <tr><td colspan="<?= count($colunas) ?>" style="text-align:center;color:#999;padding:18px;">Sem dados para o período.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="totais">
    <?php if ($totalExtras > 0): ?>
    <div>Total horas extra: <b><?= number_format($totalExtras, 2, ',', '.') ?> <?= $moeda ?></b></div>
    <?php endif; ?>
    <div>Total cortes: <b class="corte"><?= number_format($totalCortes, 2, ',', '.') ?> <?= $moeda ?></b></div>
    <div>Total líquido: <b><?= number_format($totalGeral, 2, ',', '.') ?> <?= $moeda ?></b></div>
</div>

<?php if ($resumoFiltros): ?>
<div class="footer">
    <b>Filtros aplicados.</b>
    <?= htmlspecialchars(implode(' · ', array_map(
        static fn(array $r): string => $r[0] . ': ' . $r[1],
        $resumoFiltros
    ))) ?>
</div>
<?php endif; ?>

<div class="footer">
    <b>Nota metodológica.</b> O valor/dia de cada funcionário resulta do salário base dividido pelos
    <i>dias de trabalho definidos</i> no período (calendário mensal ou escala semanal), nunca por um divisor fixo.
    O valor/hora é o valor/dia ÷ carga horária diária. As horas extra são pagas ao valor definido em cada
    funcionário (por omissão, o valor/hora normal). Os cortes nunca ultrapassam o salário disponível acima do
    piso dos dias efectivamente trabalhados ou justificados. Valores em <?= $moeda ?>.
    Documento gerado automaticamente pelo FarmaPonto.
    <?php if ($notaExtra): ?><br><?= htmlspecialchars($notaExtra) ?><?php endif; ?>
</div>


</body>
</html>
