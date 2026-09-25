<?php
/** Filtros ativos (com defaults vindos do controller) */
$modo            = $modo ?? 'mes';
$de              = $de ?? date('Y-m-01');
$ate             = $ate ?? date('Y-m-d');
$filtroFuncs     = $filtroFuncs ?? [];
$filtroDiasMes   = $filtroDiasMes ?? [];
$filtroDiasSem   = $filtroDiasSem ?? [];
$contarFaltas    = $contarFaltas ?? true;
$contarAtrasos   = $contarAtrasos ?? true;
$contarSaidaCedo = $contarSaidaCedo ?? true;
$contarExtras    = $contarExtras ?? true;
$pagarFuturos    = $pagarFuturos ?? false;
$diaCorte        = $diaCorte ?? (int) date('j');
$base            = $base ?? 'mes_completo';
if ($base === 'integral') { $base = 'mes_completo'; }

$funcionarios    = $funcionarios ?? [];
$qs              = http_build_query($queryFiltros ?? []);
$nomesSemana     = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];
$modelos         = $modelos ?? [];
?>
<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Relatórios &amp; Salários</h1>
        <p class="text-gray-500 mt-1">Cálculo de salários com filtros personalizados.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <input type="text" data-filter-rows="#tab-rel" placeholder="Filtrar por nome/cargo..."
               class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        <a href="<?= BASE_PATH ?>/relatorios/csv<?= $qs ? '?' . htmlspecialchars($qs) : '' ?>" class="px-4 py-2 bg-emerald-100 text-emerald-700 rounded-lg text-sm font-medium hover:bg-emerald-200 flex items-center gap-2">
            <i class="fa-solid fa-download"></i> CSV
        </a>
        <button type="button" onclick="abrirExportDialog()"
           class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2">
            <i class="fa-solid fa-file-pdf"></i> PDF
        </button>
        <button type="button" onclick="abrirRecibosLote()"
           class="px-4 py-2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-medium hover:bg-emerald-100 flex items-center gap-2">
            <i class="fa-solid fa-receipt"></i> Recibos em lote
        </button>
        <button type="button" onclick="abrirFecharFolha()"
           class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-medium hover:bg-gray-900 flex items-center gap-2">
            <i class="fa-solid fa-file-signature"></i> Fechar folha
        </button>
        <button type="button" onclick="processarFaltas()" id="btn-processar"
           class="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i> Processar Faltas
        </button>
    </div>
</div>

<!-- ========== PAINEL DE FILTROS ========== -->
<form method="get" id="form-filtros" class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-5 space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-emerald-600"></i> Filtros de cálculo
        </h2>
        <a href="<?= BASE_PATH ?>/relatorios" class="text-xs text-gray-500 hover:text-gray-800">
            <i class="fa-solid fa-rotate-left"></i> Limpar filtros
        </a>
    </div>

    <!-- Modelos de cálculo guardados -->
    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
        <div class="flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs font-medium text-gray-700 mb-1">Modelo de cálculo</label>
                <select id="sel-modelo" onchange="aplicarModelo()" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="">— Selecionar modelo guardado —</option>
                    <?php foreach ($modelos as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" data-query="<?= htmlspecialchars($m['query']) ?>">
                            <?= htmlspecialchars($m['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" onclick="guardarModelo()"
                class="px-3 py-2 bg-white border border-emerald-300 text-emerald-700 rounded-lg text-xs font-medium hover:bg-emerald-50 flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Guardar filtros atuais
            </button>
            <button type="button" onclick="eliminarModelo()"
                class="px-3 py-2 bg-white border border-red-200 text-red-600 rounded-lg text-xs font-medium hover:bg-red-50 flex items-center gap-2">
                <i class="fa-solid fa-trash"></i> Eliminar
            </button>
        </div>
        <p class="text-[11px] text-gray-400 mt-2">Guarde combinações de filtros (ex.: "Quinzena 1 — dias 1 a 15") e reutilize-as em qualquer mês.</p>
    </div>

    <!-- Período -->
    <div class="grid md:grid-cols-4 gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Tipo de período</label>
            <select name="modo" id="sel-modo" onchange="togglePeriodo()" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="mes" <?= $modo === 'mes' ? 'selected' : '' ?>>Mês completo</option>
                <option value="intervalo" <?= $modo === 'intervalo' ? 'selected' : '' ?>>Intervalo de datas</option>
            </select>
        </div>
        <div id="campo-mes" class="<?= $modo === 'intervalo' ? 'hidden' : '' ?>">
            <label class="block text-xs font-medium text-gray-700 mb-1">Mês</label>
            <input type="month" name="mes" value="<?= sprintf('%04d-%02d', $ano, $mes) ?>"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div id="campo-de" class="<?= $modo === 'intervalo' ? '' : 'hidden' ?>">
            <label class="block text-xs font-medium text-gray-700 mb-1">De</label>
            <input type="date" name="de" value="<?= htmlspecialchars($de) ?>"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div id="campo-ate" class="<?= $modo === 'intervalo' ? '' : 'hidden' ?>">
            <label class="block text-xs font-medium text-gray-700 mb-1">Até</label>
            <input type="date" name="ate" value="<?= htmlspecialchars($ate) ?>"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Base de pagamento</label>
            <select name="base" id="sel-base" onchange="atualizarBase()" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="mes_completo" <?= $base === 'mes_completo' ? 'selected' : '' ?>>Mês completo (salário cheio − cortes)</option>
                <option value="proporcional" <?= $base === 'proporcional' ? 'selected' : '' ?>>Proporcional aos dias pagos</option>
                <option value="ate_dia" <?= $base === 'ate_dia' ? 'selected' : '' ?>>Proporcional até ao dia X</option>
            </select>
        </div>
        <div id="campo-dia-corte" class="<?= $base === 'ate_dia' ? '' : 'hidden' ?>">
            <label class="block text-xs font-medium text-gray-700 mb-1">Pagar até ao dia</label>
            <input type="number" name="dia_corte" min="1" max="31" value="<?= (int) $diaCorte ?>"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
    </div>


    <!-- Funcionários -->
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Funcionários (vazio = todos)</label>
        <select name="func_id[]" multiple size="4" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            <?php foreach ($funcionarios as $fx): ?>
                <option value="<?= (int) $fx['id'] ?>" <?= in_array((int) $fx['id'], $filtroFuncs, true) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($fx['nome']) ?><?= $fx['cargo'] ? ' — ' . htmlspecialchars($fx['cargo']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-gray-400 mt-1">Ctrl/Cmd + clique para selecionar vários.</p>
    </div>

    <!-- Dias do mês -->
    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-medium text-gray-700">Dias do mês a calcular (vazio = todos)</label>
            <div class="flex gap-2 text-[11px]">
                <button type="button" onclick="marcarDias(true)" class="text-emerald-700 hover:underline">Todos</button>
                <button type="button" onclick="marcarDias(false)" class="text-gray-500 hover:underline">Nenhum</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-1">
            <?php for ($d = 1; $d <= 31; $d++): ?>
            <label class="cursor-pointer">
                <input type="checkbox" class="peer sr-only chk-dia" name="dias_mes[]" value="<?= $d ?>"
                       <?= in_array($d, $filtroDiasMes, true) ? 'checked' : '' ?>>
                <span class="block w-9 text-center text-xs py-1.5 rounded-md border border-gray-200 text-gray-600 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600"><?= $d ?></span>
            </label>
            <?php endfor; ?>
        </div>
        <p class="text-[11px] text-gray-400 mt-1">Ex.: selecione 4 a 10 para calcular só esse intervalo — cortes só se houver falta/atraso nesses dias.</p>
    </div>

    <!-- Dias da semana + opções de corte -->
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Dias da semana (vazio = todos)</label>
            <div class="flex flex-wrap gap-1">
                <?php foreach ($nomesSemana as $n => $lbl): ?>
                <label class="cursor-pointer">
                    <input type="checkbox" class="peer sr-only" name="dias_semana[]" value="<?= $n ?>"
                           <?= in_array($n, $filtroDiasSem, true) ? 'checked' : '' ?>>
                    <span class="block px-3 py-1.5 text-xs rounded-md border border-gray-200 text-gray-600 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600"><?= $lbl ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">O que descontar</label>
            <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                <input type="hidden" name="contar_faltas" value="0">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="contar_faltas" value="1" <?= $contarFaltas ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500"> Faltas
                </label>
                <input type="hidden" name="contar_atrasos" value="0">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="contar_atrasos" value="1" <?= $contarAtrasos ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500"> Atrasos
                </label>
                <input type="hidden" name="contar_saida_cedo" value="0">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="contar_saida_cedo" value="1" <?= $contarSaidaCedo ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500"> Saída antecipada
                </label>
            </div>
            <label class="block text-xs font-medium text-gray-700 mt-3 mb-1">Outras opções</label>
            <div class="flex flex-wrap gap-4 text-sm text-gray-700">
                <input type="hidden" name="pagar_futuros" value="0">
                <label class="flex items-center gap-2 cursor-pointer" title="Dias programados ainda por decorrer contam como pagos">
                    <input type="checkbox" name="pagar_futuros" value="1" <?= $pagarFuturos ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500"> Pagar dias futuros
                </label>
                <input type="hidden" name="contar_extras" value="0">
                <label class="flex items-center gap-2 cursor-pointer" title="Somar as horas extras lançadas na página do funcionário">
                    <input type="checkbox" name="contar_extras" value="1" <?= $contarExtras ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500"> Pagar horas extra
                </label>
            </div>
        </div>

    </div>

    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
        <p class="text-xs text-gray-500"><?= (int) ($diasNoPeriodo ?? 0) ?> dia(s) no período filtrado.</p>
        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium flex items-center gap-2">
            <i class="fa-solid fa-calculator"></i> Calcular
        </button>
    </div>
</form>

<script>
function togglePeriodo() {
    const m = document.getElementById('sel-modo').value === 'intervalo';
    document.getElementById('campo-mes').classList.toggle('hidden', m);
    document.getElementById('campo-de').classList.toggle('hidden', !m);
    document.getElementById('campo-ate').classList.toggle('hidden', !m);
}
function marcarDias(v) {
    document.querySelectorAll('.chk-dia').forEach(c => c.checked = v);
}

/* ===== Modelos de cálculo (presets de filtros) ===== */
function csrfToken() {
    const el = document.getElementById('csrf-proc');
    return el ? el.value : '';
}

function aplicarModelo() {
    const opt = document.getElementById('sel-modelo').selectedOptions[0];
    const q = opt ? (opt.dataset.query || '') : '';
    if (!q) { return; }
    window.location.href = '<?= BASE_PATH ?>/relatorios?' + q;
}

async function guardarModelo() {
    const nome = prompt('Nome do modelo de cálculo:');
    if (!nome) { return; }
    const fd = new FormData(document.getElementById('form-filtros'));
    fd.set('nome', nome);
    fd.set('csrf', csrfToken());
    try {
        const r = await fetch('<?= BASE_PATH ?>/relatorios/modelos/guardar', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) { alert(j.erro || 'Não foi possível guardar.'); return; }
        alert('Modelo "' + nome + '" guardado.');
        window.location.reload();
    } catch (e) {
        alert('Erro de rede ao guardar o modelo.');
    }
}

async function eliminarModelo() {
    const sel = document.getElementById('sel-modelo');
    const id = sel.value;
    if (!id) { alert('Selecione primeiro um modelo.'); return; }
    if (!confirm('Eliminar o modelo "' + sel.selectedOptions[0].text.trim() + '"?')) { return; }
    const fd = new FormData();
    fd.set('id', id);
    fd.set('csrf', csrfToken());
    try {
        const r = await fetch('<?= BASE_PATH ?>/relatorios/modelos/eliminar', { method: 'POST', body: fd });
        const j = await r.json();
        if (!j.ok) { alert(j.erro || 'Não foi possível eliminar.'); return; }
        window.location.href = '<?= BASE_PATH ?>/relatorios';
    } catch (e) {
        alert('Erro de rede ao eliminar o modelo.');
    }
}
</script>


<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto [scrollbar-gutter:stable]">
    <table id="tab-rel" data-paginate="20" class="w-full text-sm min-w-[1180px] [&_th]:px-3 [&_td]:px-3 xl:[&_th]:px-2 xl:[&_td]:px-2">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="w-10 px-3 py-3"><span class="sr-only">Detalhe</span></th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Funcionario</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Salario</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Dias pagos</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Faltas</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Faltas just.</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">H. extra</th>

                <th class="text-left px-5 py-3 font-medium text-gray-500">Férias</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Horas</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">H. atraso</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">H. deveria</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Corte atraso</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Corte faltas</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Total corte</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Liquido</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100" data-live="tab-rel">
            <?php foreach ($linhas as $l): ?>
            <tr class="hover:bg-gray-50" data-func-id="<?= (int) $l['id'] ?>">
                <td class="px-3 py-3 align-top">
                    <button type="button"
                            onclick="alternarDetalhe(<?= (int) $l['id'] ?>, this)"
                            title="Ver detalhe dia-a-dia"
                            aria-expanded="false"
                            class="w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 transition">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button type="button"
                            onclick="abrirRecibo(<?= (int) $l['id'] ?>)"
                            title="Recibo de salário (PDF)"
                            class="mt-1 w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:bg-emerald-50 hover:text-emerald-700 transition">
                        <i class="fa-solid fa-file-invoice-dollar text-xs"></i>
                    </button>
                </td>
                <td class="px-5 py-3">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($l['nome']) ?></div>
                    <div class="text-xs text-gray-500"><?= htmlspecialchars($l['cargo']) ?></div>
                </td>
                <td class="px-5 py-3 text-gray-900"><?= number_format($l['salario'], 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-gray-700"><?= $l['dias_pagos'] ?? 0 ?></td>
                <td class="px-5 py-3 text-gray-900"><?= $l['faltas'] ?></td>
                <td class="px-5 py-3 text-indigo-700"><?= $l['faltas_justificadas'] ?? 0 ?></td>
                <td class="px-5 py-3 text-emerald-700"><?= number_format((float) ($l['horas_extra'] ?? 0), 2, ',', '.') ?>h
                    <?php if (!empty($l['ganho_extra'])): ?>
                        <span class="block text-[11px] text-gray-500">+<?= number_format($l['ganho_extra'], 2, ',', '.') ?></span>
                    <?php endif; ?>
                </td>

                <td class="px-5 py-3 text-blue-700"><?= $l['ferias'] ?? 0 ?></td>
                <td class="px-5 py-3 font-mono text-xs text-gray-900"><?= $l['horas'] ?></td>
                <td class="px-5 py-3 font-mono text-xs <?= $l['horas_atraso'] !== '00:00:00' ? 'text-amber-600' : 'text-gray-400' ?>"><?= $l['horas_atraso'] ?></td>
                <td class="px-5 py-3 font-mono text-xs text-gray-500"><?= $l['horas_deveria'] ?></td>
                <td class="px-5 py-3 text-red-600"><?= number_format($l['corte_atraso'], 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-red-600"><?= number_format($l['corte_falta'], 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-red-600 font-bold"><?= number_format($l['total_corte'], 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-emerald-700 font-bold"><?= number_format($l['liquido'], 2, ',', '.') ?></td>
            </tr>
            <tr id="detalhe-<?= (int) $l['id'] ?>" data-skip-paginate class="hidden bg-gray-50/70">
                <td colspan="15" class="px-5 py-4">
                    <div id="detalhe-conteudo-<?= (int) $l['id'] ?>" class="text-sm text-gray-500">
                        <i class="fa-solid fa-spinner fa-spin"></i> A carregar detalhe...
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($linhas)): ?>
            <tr data-skip-paginate><td colspan="15" class="px-5 py-12 text-center text-gray-400">
                <i class="fa-solid fa-folder-open text-3xl mb-2 block opacity-50"></i>
                Sem funcionarios ativos no periodo.
            </td></tr>
            <?php endif; ?>
        </tbody>
        <?php if (!empty($linhas)):
            $tSalario = array_sum(array_column($linhas, 'salario'));
            $tCorte   = array_sum(array_column($linhas, 'total_corte'));
            $tLiquido = array_sum(array_column($linhas, 'liquido'));
            $tFaltas  = array_sum(array_column($linhas, 'faltas'));
            $tJust    = array_sum(array_map(fn($l) => (int) ($l['faltas_justificadas'] ?? 0), $linhas));
            $tExtra   = array_sum(array_map(fn($l) => (float) ($l['horas_extra'] ?? 0), $linhas));
            $tDias    = array_sum(array_map(fn($l) => (int) ($l['dias_pagos'] ?? 0), $linhas));
            $tFerias  = array_sum(array_map(fn($l) => (int) ($l['ferias'] ?? 0), $linhas));
            $tCorteA  = array_sum(array_column($linhas, 'corte_atraso'));
            $tCorteF  = array_sum(array_column($linhas, 'corte_falta'));
        ?>
        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
            <tr class="font-semibold text-gray-900">
                <td class="px-3 py-3"></td>
                <td class="px-5 py-3">Totais (<?= count($linhas) ?>)</td>
                <td class="px-5 py-3"><?= number_format($tSalario, 2, ',', '.') ?></td>
                <td class="px-5 py-3"><?= $tDias ?></td>
                <td class="px-5 py-3"><?= $tFaltas ?></td>
                <td class="px-5 py-3 text-indigo-700"><?= $tJust ?></td>
                <td class="px-5 py-3 text-emerald-700"><?= number_format($tExtra, 2, ',', '.') ?>h</td>
                <td class="px-5 py-3 text-blue-700"><?= $tFerias ?></td>
                <td class="px-5 py-3"></td>
                <td class="px-5 py-3"></td>
                <td class="px-5 py-3"></td>
                <td class="px-5 py-3 text-red-600"><?= number_format($tCorteA, 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-red-600"><?= number_format($tCorteF, 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-red-600"><?= number_format($tCorte, 2, ',', '.') ?></td>
                <td class="px-5 py-3 text-emerald-700"><?= number_format($tLiquido, 2, ',', '.') ?></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
</div>

<?php if (!empty($linhas)): ?>
<!-- Resumo do periodo (calculado a partir das linhas acima) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <p class="text-sm text-gray-500 mb-1">Funcionarios no calculo</p>
        <p class="text-2xl font-bold text-gray-900"><?= count($linhas) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= $tDias ?> dias pagos no total</p>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <p class="text-sm text-gray-500 mb-1">Massa salarial base</p>
        <p class="text-2xl font-bold text-gray-900"><?= number_format($tSalario, 2, ',', '.') ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($moeda ?? 'MZN') ?></p>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <p class="text-sm text-gray-500 mb-1">Total de cortes</p>
        <p class="text-2xl font-bold text-red-600"><?= number_format($tCorte, 2, ',', '.') ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= $tFaltas ?> falta(s) &middot; <?= $tJust ?> justificada(s)</p>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <p class="text-sm text-gray-500 mb-1">Total liquido a pagar</p>
        <p class="text-2xl font-bold text-emerald-700"><?= number_format($tLiquido, 2, ',', '.') ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= number_format($tExtra, 2, ',', '.') ?>h extra incluidas</p>
    </div>
</div>
<?php endif; ?>

<p class="text-xs text-gray-500 mt-4 mb-2">
    <i class="fa-solid fa-circle-info"></i>
    <input type="hidden" id="csrf-proc" value="<?= \App\Helpers\Csrf::token() ?>">
    Cortes calculados com base no salário individual de cada funcionário
    (valor dia = salário base ÷ dias de trabalho definidos no mês, valor hora = valor dia ÷ carga horária).
    Os cortes nunca ultrapassam o salário: os dias efetivamente trabalhados e os dias justificados são sempre pagos.
    Tolerância de atraso: <?= $tolerancia ?? 5 ?> min.
</p>


<!-- ========== FECHAR FOLHA DIALOG ========== -->
<div id="folha-dialog" class="fixed inset-0 z-[105] hidden items-center justify-center bg-black/40 p-4 no-print">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 animate-fade-in">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-file-signature text-gray-700"></i> Fechar folha salarial
            </h2>
            <button onclick="fecharFolhaDialog()" class="text-gray-400 hover:text-gray-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="text-sm text-gray-600 mb-3">
            Guarda o cálculo actual como <b>folha fechada</b> (histórico auditável).
            Isto <b>não bloqueia</b> nada: pode continuar a recalcular o relatório sempre que quiser.
        </p>
        <label class="block text-xs font-medium text-gray-700 mb-1">Observação (opcional)</label>
        <textarea id="folha-obs" rows="2" maxlength="255" placeholder="Ex.: Folha aprovada pela direcção"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"></textarea>
        <div class="flex justify-end gap-2 mt-4">
            <button type="button" onclick="fecharFolhaDialog()" class="px-4 py-2 text-sm rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">Cancelar</button>
            <button type="button" id="btn-folha-confirmar" onclick="confirmarFecharFolha()" class="px-4 py-2 text-sm rounded-lg bg-gray-800 text-white hover:bg-gray-900">Fechar folha</button>
        </div>
    </div>
</div>

<!-- ========== EXPORT DIALOG ========== -->
<div id="export-dialog" class="fixed inset-0 z-[105] hidden items-center justify-center bg-black/40 p-4 no-print">
    <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full p-6 animate-fade-in max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-file-pdf text-emerald-600"></i> Exportar para PDF
            </h2>
            <button onclick="fecharExportDialog()" class="text-gray-400 hover:text-gray-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="export-form" class="space-y-3" onsubmit="exportar(event)">
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Título do documento</label>
                <input name="titulo_doc" value="Relatório de Salários e Cortes" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Subtítulo</label>
                <input name="subtitulo" placeholder="Ex.: Departamento de RH" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Instituição</label>
                    <input name="instituicao" value="FarmaPonto Lda" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Emitido por</label>
                    <input name="autor" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Nota de rodapé</label>
                <textarea name="nota_rodape" rows="2" placeholder="Documento confidencial..." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-2">Colunas a incluir</label>
                <?php
                  // Fonte unica de colunas (partilhada com o CSV e o PDF)
                  $colunasExport = \App\Controllers\RelatoriosController::colunasExportaveis();
                  $colunasPadrao = ['nome','cargo','salario','dias_trabalhados','faltas','faltas_justificadas',
                                    'ferias','horas','horas_atraso','horas_extra','corte_atraso','corte_falta',
                                    'total_corte','liquido'];
                ?>
                <div class="grid grid-cols-2 gap-2 text-sm max-h-60 overflow-y-auto pr-1">
                    <?php foreach ($colunasExport as $k => $lbl): ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="col_<?= $k ?>" value="<?= $k ?>" <?= in_array($k, $colunasPadrao, true) ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500">
                        <span><?= $lbl ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="fecharExportDialog()" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium flex items-center gap-2">
                    <i class="fa-solid fa-file-pdf"></i> Gerar PDF
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const MES_PARAM = '<?= sprintf('%04d-%02d', $ano, $mes) ?>';

function processarFaltas() {
    if (!confirm('Processar faltas automaticamente para ' + MES_PARAM + '?\nSerao inseridos registos de falta para dias de trabalho sem marcacao.')) return;
    const btn = document.getElementById('btn-processar');
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processando...';
    const fd = new FormData();
    fd.set('csrf', document.getElementById('csrf-proc').value);
    fd.set('mes', MES_PARAM);
    fetch(BASE_PATH + '/relatorios/processar-faltas', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                window.toast ? window.toast(d.mensagem, 'success') : alert(d.mensagem);
                location.reload();
            } else {
                window.toast ? window.toast(d.erro || 'Erro ao processar', 'error') : alert(d.erro || 'Erro');
            }
        })
        .catch(() => { alert('Erro de rede.'); })
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Processar Faltas'; });
}

function abrirExportDialog() {
    const d = document.getElementById('export-dialog');
    d.classList.remove('hidden'); d.classList.add('flex');
}
function fecharExportDialog() {
    const d = document.getElementById('export-dialog');
    d.classList.add('hidden'); d.classList.remove('flex');
}
function exportar(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const colunas = [];
    for (const [k, v] of fd.entries()) { if (k.startsWith('col_')) colunas.push(v); }
    const params = new URLSearchParams(new FormData(document.getElementById('form-filtros')));
    Object.entries({
        titulo_doc: fd.get('titulo_doc') || '',
        subtitulo:  fd.get('subtitulo')  || '',
        instituicao: fd.get('instituicao') || '',
        autor: fd.get('autor') || '',
        nota_rodape: fd.get('nota_rodape') || '',
        colunas: colunas.join(','),
    }).forEach(([k, v]) => params.set(k, v));
    window.open(BASE_PATH + '/relatorios/pdf?' + params.toString(), '_blank');
    fecharExportDialog();
    window.toast && window.toast('PDF aberto em nova aba.', 'success');
}
</script>

<script>
/* ==========================================================
   Detalhe dia-a-dia por funcionario (carregado a pedido)
   ========================================================== */
const DETALHE_CACHE = {};
const DIAS_SEMANA = ['', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'];
const ESTADO_BADGE = {
    presente:     ['Presente', 'bg-emerald-100 text-emerald-700'],
    falta:        ['Falta', 'bg-red-100 text-red-700'],
    ferias:       ['Ferias', 'bg-blue-100 text-blue-700'],
    nao_trabalha: ['Nao trabalha', 'bg-gray-100 text-gray-500'],
    falta_justificada: ['Falta justificada', 'bg-indigo-100 text-indigo-700'],
    folga:        ['Folga', 'bg-purple-100 text-purple-700'],
    licenca:      ['Licenca', 'bg-orange-100 text-orange-700'],
    futuro:       ['Por decorrer', 'bg-gray-100 text-gray-500'],
    em_curso:     ['Em curso', 'bg-amber-100 text-amber-700'],
};

function abrirRecibo(funcId) {
    const params = filtrosAtuais();
    params.set('func_id', funcId);
    window.open(BASE_PATH + '/relatorios/recibo?' + params.toString(), '_blank');
}

function atualizarBase() {
    const base = document.getElementById('sel-base').value;
    document.getElementById('campo-dia-corte').classList.toggle('hidden', base !== 'ate_dia');
}

function filtrosAtuais() {
    return new URLSearchParams(new FormData(document.getElementById('form-filtros')));
}

/* ==========================================================
   Recibos em lote (1 PDF com todos os funcionarios filtrados)
   ========================================================== */
function abrirRecibosLote() {
    const params = filtrosAtuais();
    params.set('mostrar_detalhe', '1');
    window.open(BASE_PATH + '/relatorios/recibos?' + params.toString(), '_blank');
}

/* ==========================================================
   Fecho de folha salarial (snapshot; o recalculo continua livre)
   ========================================================== */
function abrirFecharFolha() {
    const d = document.getElementById('folha-dialog');
    d.classList.remove('hidden'); d.classList.add('flex');
}
function fecharFolhaDialog() {
    const d = document.getElementById('folha-dialog');
    d.classList.add('hidden'); d.classList.remove('flex');
}
function confirmarFecharFolha() {
    const btn = document.getElementById('btn-folha-confirmar');
    btn.disabled = true; btn.textContent = 'A fechar...';
    const fd = new FormData(document.getElementById('form-filtros'));
    fd.set('csrf', document.getElementById('csrf-proc').value);
    fd.set('observacao', document.getElementById('folha-obs').value || '');
    fetch(BASE_PATH + '/folhas/fechar', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) { window.toast && window.toast(j.erro || 'Erro ao fechar folha.', 'error'); return; }
            window.toast && window.toast('Folha fechada com sucesso.', 'success');
            window.location.href = j.url;
        })
        .catch(() => window.toast && window.toast('Erro de comunicacao.', 'error'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Fechar folha'; fecharFolhaDialog(); });
}

function fmtNum(n) {
    return Number(n || 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtHoras(dec) {
    const total = Math.round(Number(dec || 0) * 3600);
    const h = Math.floor(total / 3600), m = Math.floor((total % 3600) / 60), s = total % 60;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}
function apenasHora(ts) {
    return ts ? String(ts).substring(11, 16) : '--:--';
}

async function alternarDetalhe(funcId, btn) {
    const linha = document.getElementById('detalhe-' + funcId);
    const icone = btn.querySelector('i');
    const aberto = !linha.classList.contains('hidden');

    if (aberto) {
        linha.classList.add('hidden');
        btn.setAttribute('aria-expanded', 'false');
        icone.className = 'fa-solid fa-chevron-right text-xs';
        return;
    }

    linha.classList.remove('hidden');
    btn.setAttribute('aria-expanded', 'true');
    icone.className = 'fa-solid fa-chevron-down text-xs';

    if (DETALHE_CACHE[funcId]) { return; }

    const alvo = document.getElementById('detalhe-conteudo-' + funcId);
    const params = filtrosAtuais();
    params.delete('func_id');
    params.set('func_id', funcId);

    try {
        const r = await fetch(BASE_PATH + '/relatorios/detalhe?' + params.toString());
        const j = await r.json();
        if (!j.ok) { alvo.innerHTML = '<span class="text-red-600">' + (j.erro || 'Erro ao carregar.') + '</span>'; return; }
        alvo.innerHTML = renderDetalhe(j);
        DETALHE_CACHE[funcId] = true;
    } catch (e) {
        alvo.innerHTML = '<span class="text-red-600">Erro de rede ao carregar o detalhe.</span>';
    }
}

function renderDetalhe(j) {
    const moeda = j.moeda || 'MZN';
    const linhas = j.detalhe.map(d => {
        const [rotulo, cor] = ESTADO_BADGE[d.estado] || ['-', 'bg-gray-100 text-gray-500'];
        const corCorte = Number(d.corte) > 0 ? 'text-red-600 font-semibold' : 'text-gray-400';
        return `<tr class="border-t border-gray-100">
            <td class="px-3 py-2 font-mono text-xs text-gray-700">${d.dia}</td>
            <td class="px-3 py-2 text-xs text-gray-500">${DIAS_SEMANA[d.dia_semana] || ''}</td>
            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-medium ${cor}">${rotulo}</span></td>
            <td class="px-3 py-2 font-mono text-xs">${apenasHora(d.entrada)}</td>
            <td class="px-3 py-2 font-mono text-xs">${apenasHora(d.saida)}</td>
            <td class="px-3 py-2 font-mono text-xs text-gray-700">${fmtHoras(d.horas)}</td>
            <td class="px-3 py-2 font-mono text-xs ${d.atrasado ? 'text-amber-600' : 'text-gray-400'}">${fmtHoras(d.horas_atraso)}</td>
            <td class="px-3 py-2 font-mono text-xs ${d.saiu_cedo ? 'text-amber-600' : 'text-gray-400'}">${fmtHoras(d.horas_cedo)}</td>
            <td class="px-3 py-2 text-xs ${corCorte}">${fmtNum(d.corte)}</td>
            <td class="px-3 py-2 text-xs text-gray-500">${d.observacao || ''}</td>
        </tr>`;
    }).join('');

    const r = j.resumo;
    return `
    <div class="mb-3 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-gray-600">
        <span><strong class="text-gray-900">${r.nome}</strong> &middot; ${r.cargo || ''}</span>
        <span>Periodo: <strong>${j.periodo.de}</strong> a <strong>${j.periodo.ate}</strong></span>
        <span>Valor dia: <strong>${fmtNum(r.valor_dia)} ${moeda}</strong></span>
        <span>Valor hora: <strong>${fmtNum(r.valor_hora)} ${moeda}</strong></span>
        <span>Total corte: <strong class="text-red-600">${fmtNum(r.total_corte)} ${moeda}</strong></span>
        <span>Liquido: <strong class="text-emerald-700">${fmtNum(r.liquido)} ${moeda}</strong></span>
    </div>
    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-sm min-w-[820px]">
            <thead class="bg-gray-50">
                <tr class="text-left text-xs font-medium text-gray-500">
                    <th class="px-3 py-2">Dia</th>
                    <th class="px-3 py-2">Sem.</th>
                    <th class="px-3 py-2">Estado</th>
                    <th class="px-3 py-2">Entrada</th>
                    <th class="px-3 py-2">Saida</th>
                    <th class="px-3 py-2">Horas</th>
                    <th class="px-3 py-2">Atraso</th>
                    <th class="px-3 py-2">Saida cedo</th>
                    <th class="px-3 py-2">Corte (${moeda})</th>
                    <th class="px-3 py-2">Observacao</th>
                </tr>
            </thead>
            <tbody>${linhas || '<tr><td colspan="10" class="px-3 py-6 text-center text-gray-400">Sem dias no periodo filtrado.</td></tr>'}</tbody>
        </table>
    </div>`;
}
</script>
