<?php
$total = count($logs);
$de = $_GET['de'] ?? date('Y-m-01');
$ate = $_GET['ate'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Logs do Sistema · FarmaPonto</title>
<style>
    @page { size: A4 portrait; margin: 15mm; }
    * { box-sizing: border-box; }
    body { font-family: "Times New Roman", Times, serif; font-size: 10pt; color: #000; margin: 0; padding: 15mm; }
    .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #047857; padding-bottom: 8px; margin-bottom: 16px; }
    .brand { color: #047857; font-weight: bold; font-size: 16pt; }
    .meta { font-size: 9pt; color: #555; text-align: right; }
    h1 { text-align: center; font-size: 14pt; margin: 12px 0 4px; }
    .sub { text-align: center; color: #555; font-size: 9pt; margin-bottom: 14px; }
    table { width: 100%; border-collapse: collapse; font-size: 8pt; }
    th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
    th { background: #ecfdf5; color: #064e3b; }
    .footer { margin-top: 16px; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 6px; }
    .actions { margin-bottom: 12px; }
    .actions button { background: #047857; color: #fff; border: 0; padding: 8px 16px; border-radius: 6px; font-size: 10pt; cursor: pointer; }
    @media print { .actions { display: none; } body { padding: 0; } }
</style>
</head>
<body onload="setTimeout(()=>window.print(), 300)">

<div class="actions">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="header">
    <div class="brand">FarmaPonto</div>
    <div class="meta">Logs do Sistema<br>Emitido em <?= date('d/m/Y H:i') ?></div>
</div>

<h1>Logs do Sistema</h1>
<div class="sub">Período: <?= date('d/m/Y', strtotime($de)) ?> a <?= date('d/m/Y', strtotime($ate)) ?> · Total: <?= $total ?> registos</div>

<table>
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>Utilizador</th>
            <th>Acao</th>
            <th>Detalhe</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
        <tr>
            <td style="white-space:nowrap"><?= date('d/m/Y H:i:s', strtotime($l['criado_em'])) ?></td>
            <td><?= htmlspecialchars($l['funcionario_nome'] ?? 'Sistema') ?></td>
            <td><?= htmlspecialchars($l['acao']) ?></td>
            <td><?php
                $det = $l['detalhes'] ? json_decode($l['detalhes'], true) : null;
                echo $det ? htmlspecialchars(json_encode($det, JSON_UNESCAPED_UNICODE)) : '-';
            ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?>
        <tr><td colspan="4" style="text-align:center;color:#999;padding:12px;">Nenhum registo encontrado.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    Documento gerado automaticamente pelo FarmaPonto. Ficheiro de auditoria.
</div>

</body>
</html>
