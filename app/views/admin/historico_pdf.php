<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Historico de Registos · FarmaPonto</title>
<style>
    @page { size: A4 landscape; margin: 12mm; }
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 10pt; color: #000; margin: 0; padding: 12mm; }
    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #047857; padding-bottom: 6px; margin-bottom: 12px; }
    .brand { color: #047857; font-weight: bold; font-size: 16pt; }
    .meta { font-size: 9pt; color: #555; text-align: right; }
    h1 { text-align: center; font-size: 14pt; margin: 12px 0 2px; }
    .sub { text-align: center; color: #555; font-size: 10pt; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
    th { background: #ecfdf5; color: #064e3b; border-bottom: 2px solid #047857; }
    .falta { color: #b91c1c; font-weight: bold; }
    .atraso { color: #d97706; font-weight: bold; }
    .footer { margin-top: 14px; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 6px; }
    .actions { margin-bottom: 8px; }
    .actions button { background: #047857; color: #fff; border: 0; padding: 6px 14px; border-radius: 4px; font-size: 10pt; cursor: pointer; font-family: inherit; }
    @media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body onload="setTimeout(()=>window.print(), 300)">

<div class="actions">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
    <a class="sec" href="<?= BASE_PATH ?>/historico?de=<?= $de ?>&ate=<?= $ate ?><?= $funcId ? '&funcionario='.$funcId : '' ?><?= $tipo ? '&tipo='.$tipo : '' ?>" style="margin-left:8px;background:#6b7280;color:#fff;border:0;padding:6px 14px;border-radius:4px;font-size:10pt;cursor:pointer;text-decoration:none;font-family:inherit">Voltar</a>
</div>

<div class="header">
    <div class="brand">FarmaPonto</div>
    <div class="meta">Historico de Registos<br>Emitido em <?= date('d/m/Y H:i') ?></div>
</div>

<h1>Historico de Registos</h1>
<div class="sub">Periodo: <?= date('d/m/Y', strtotime($de)) ?> a <?= date('d/m/Y', strtotime($ate)) ?> · Total: <?= $total ?> registos</div>

<table>
    <thead>
        <tr>
            <th>Data</th>
            <th>Hora</th>
            <th>Funcionario</th>
            <th>Tipo</th>
            <th>Falta</th>
            <th>Atraso</th>
            <th>Origem</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $tol = (int) ($tolerancia ?? 5);
    foreach ($regs as $r):
        $atrasoTexto = '';
        $atrasoClasse = '';
        if ($r['tipo'] === 'entrada' && !empty($r['hora_entrada'])) {
            $real = strtotime($r['marcado_em']);
            $padrao = strtotime(date('Y-m-d', $real) . ' ' . $r['hora_entrada']);
            $diffSeg = $real - $padrao;
            if ($diffSeg > ($tol * 60)) {
                $h = floor($diffSeg / 3600); $m = floor(($diffSeg % 3600) / 60); $s = $diffSeg % 60;
                $atrasoTexto = sprintf('+%02d:%02d:%02d', $h, $m, $s);
                $atrasoClasse = 'atraso';
            } elseif ($diffSeg >= 0) {
                $atrasoTexto = sprintf('+%02d:%02d', 0, round($diffSeg / 60));
            } else {
                $atrasoTexto = 'OK';
            }
        } elseif ($r['tipo'] === 'saida' && !empty($r['hora_saida'])) {
            $real = strtotime($r['marcado_em']);
            $padrao = strtotime(date('Y-m-d', $real) . ' ' . $r['hora_saida']);
            $diffSeg = $padrao - $real;
            if ($diffSeg > 0) {
                $h = floor($diffSeg / 3600); $m = floor(($diffSeg % 3600) / 60); $s = $diffSeg % 60;
                $atrasoTexto = sprintf('-%02d:%02d:%02d', $h, $m, $s);
            } elseif ($diffSeg < (-$tol * 60)) {
                $h = floor(abs($diffSeg) / 3600); $m = floor((abs($diffSeg) % 3600) / 60); $s = abs($diffSeg) % 60;
                $atrasoTexto = sprintf('+%02d:%02d:%02d', $h, $m, $s);
            } else {
                $atrasoTexto = 'OK';
            }
        } elseif ($r['tipo'] === 'atraso') {
            $atrasoTexto = 'Sim';
            $atrasoClasse = 'atraso';
        }
    ?>
        <tr>
            <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($r['marcado_em'])) ?></td>
            <td style="white-space:nowrap"><?= empty($r['id']) ? '—' : date('H:i', strtotime($r['marcado_em'])) ?></td>
            <td><?= htmlspecialchars($r['funcionario_nome']) ?></td>
            <td><?= ucfirst(str_replace('_', ' ', $r['tipo'])) ?></td>
            <td class="<?= $r['tipo'] === 'falta' ? 'falta' : '' ?>"><?= $r['tipo'] === 'falta' ? 'Sim' : '—' ?></td>
            <td class="<?= $atrasoClasse ?>"><?= $atrasoTexto ?: '—' ?></td>
            <td><?= empty($r['id']) ? '—' : htmlspecialchars(\App\Helpers\Metodo::rotulo($r['metodo'] ?? null, !empty($r['selfie_existe']), $r['metodo_detalhe'] ?? null)) ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($regs)): ?>
        <tr><td colspan="7" style="text-align:center;color:#999;padding:18px;">Sem registos para o periodo.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    Documento gerado automaticamente pelo FarmaPonto.
</div>

</body>
</html>
