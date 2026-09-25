<?php
/**
 * ============================================================
 * FarmaPonto - Recibos em Lote (varios recibos, 1 por pagina A4)
 * ============================================================
 * Shell do documento. Reutiliza exactamente o mesmo bloco do recibo
 * individual (_recibo_bloco.php), garantindo layout identico.
 *
 * Variaveis esperadas:
 *   $recibos (array<int, array{func:array, resumo:array, detalhe:array}>)
 *   $de, $ate, $nomeMes, $ano, $moeda, $tolerancia
 *   $opcoes (array): titulo_doc, instituicao, autor, nota_rodape, mostrar_detalhe
 */

$opcoes      = $opcoes ?? [];
$tituloDoc   = $opcoes['titulo_doc']   ?? 'Recibo de Salário';
$instituicao = $opcoes['instituicao']  ?? 'FarmaPonto';
$autor       = $opcoes['autor']        ?? '';
$notaExtra   = $opcoes['nota_rodape']  ?? '';
$mostrarDet  = ($opcoes['mostrar_detalhe'] ?? '1') !== '0';

$cfgLogo = new \App\Models\Config();
$logoRel = $cfgLogo->get('instituicao_logo', '');
$logoAbs = $logoRel ? (__DIR__ . '/../../../' . $logoRel) : '';

$fmtL       = static fn($v): string => number_format((float) $v, 2, ',', '.');
$totLiquido = array_sum(array_map(static fn(array $r): float => (float) $r['resumo']['liquido'], $recibos));
$totCorte   = array_sum(array_map(static fn(array $r): float => (float) $r['resumo']['total_corte'], $recibos));
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Recibos em lote (<?= count($recibos) ?>) · FarmaPonto</title>
<?php require __DIR__ . '/_recibo_estilos.php'; ?>
</head>
<body onload="setTimeout(()=>window.print(), 400)">

<div class="actions">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
    <a class="sec" href="<?= BASE_PATH ?>/relatorios">Voltar</a>
</div>

<div class="indice">
    <h3>Folha de recibos — <?= date('d/m/Y', strtotime($de)) ?> a <?= date('d/m/Y', strtotime($ate)) ?></h3>
    <div><b><?= count($recibos) ?></b> recibo(s) · Total líquido <b><?= $fmtL($totLiquido) ?> <?= $moeda ?></b> · Total de cortes <b class="corte"><?= $fmtL($totCorte) ?> <?= $moeda ?></b></div>
    <table style="margin-top:8px;">
        <thead>
            <tr><th>#</th><th>Funcionário</th><th>Cargo</th><th class="num">Cortes</th><th class="num">Líquido</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recibos as $i => $r): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($r['func']['nome']) ?></td>
                <td><?= htmlspecialchars($r['func']['cargo'] ?? '—') ?></td>
                <td class="num corte"><?= $fmtL($r['resumo']['total_corte']) ?></td>
                <td class="num liquido"><?= $fmtL($r['resumo']['liquido']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$recibos): ?>
            <tr><td colspan="5" style="text-align:center;color:#999;">Sem funcionários no filtro actual.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php foreach ($recibos as $r):
    $func    = $r['func'];
    $resumo  = $r['resumo'];
    $detalhe = $r['detalhe'];
    require __DIR__ . '/_recibo_bloco.php';
endforeach; ?>

</body>
</html>
