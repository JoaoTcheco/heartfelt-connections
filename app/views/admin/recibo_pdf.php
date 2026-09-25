<?php
/**
 * ============================================================
 * FarmaPonto - Recibo de Salario Individual (A4 imprimivel)
 * ============================================================
 * Shell do documento. O conteudo do recibo vive em _recibo_bloco.php
 * (partilhado com o lote) e os estilos em _recibo_estilos.php.
 *
 * Variaveis esperadas:
 *   $func, $resumo, $detalhe, $de, $ate, $nomeMes, $ano, $moeda, $tolerancia
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
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($tituloDoc) ?> — <?= htmlspecialchars($func['nome']) ?> · FarmaPonto</title>
<?php require __DIR__ . '/_recibo_estilos.php'; ?>
</head>
<body onload="setTimeout(()=>window.print(), 300)">

<div class="actions">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
    <a class="sec" href="<?= BASE_PATH ?>/relatorios">Voltar</a>
</div>

<?php require __DIR__ . '/_recibo_bloco.php'; ?>

</body>
</html>
