<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Historico</h1>
        <p class="text-gray-500 mt-1">Consulte os registos de assiduidade.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <?php if (\App\Helpers\Auth::isGestor()): ?>
        <a href="<?= BASE_PATH ?>/historico/pdf?de=<?= $de ?>&ate=<?= $ate ?><?= $funcionario ? '&funcionario='.$funcionario : '' ?><?= $tipo ? '&tipo='.$tipo : '' ?>"
           class="px-4 py-2 bg-emerald-100 text-emerald-700 rounded-lg text-sm font-medium hover:bg-emerald-200 flex items-center gap-2" target="_blank">
            <i class="fa-solid fa-file-pdf"></i> PDF
        </a>
        <button onclick="eliminarRegistos()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 flex items-center gap-2">
            <i class="fa-solid fa-trash-can"></i> Eliminar registos filtrados
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Mes</label>
            <input type="month" name="mes" value="<?= $mes ?? '' ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none"
                   onchange="this.form.submit()">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">De</label>
            <input type="date" name="de" value="<?= $de ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ate</label>
            <input type="date" name="ate" value="<?= $ate ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <?php if (\App\Helpers\Auth::isGestor()): ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Funcionario</label>
            <select name="funcionario" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todos</option>
                <?php foreach ($funcionarios as $f): ?>
                <option value="<?= $f['id'] ?>" <?= $funcionario == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
            <select name="tipo" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todos</option>
                <?php foreach (['entrada'=>'Entrada','saida'=>'Saida','falta'=>'Falta','atraso'=>'Atraso','ferias'=>'Ferias','atestado'=>'Atestado'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($tipo ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Filtrar</button>
        <a href="<?= BASE_PATH ?>/historico" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Reset</a>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Filtro rápido</label>
            <input type="text" data-filter-rows="#tab-hist" placeholder="Procurar em tela..." class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
    </form>
</div>

<!-- Tabela -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto [scrollbar-gutter:stable]">
    <table id="tab-hist" data-paginate="20" class="w-full text-sm min-w-[860px]">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Data</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Horario</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Funcionario</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Tipo</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Falta</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Atraso</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Origem</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Selfie</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100" data-live="tab-hist">
            <?php
            $tol = ($tolerancia ?? 5);
            foreach ($regs as $r):
                $tipoClass = match($r['tipo']) {
                    'entrada' => 'bg-emerald-100 text-emerald-700',
                    'saida' => 'bg-gray-100 text-gray-700',
                    'atraso' => 'bg-red-100 text-red-700',
                    'falta' => 'bg-red-100 text-red-700',
                    'ferias' => 'bg-blue-100 text-blue-700',
                    default => 'bg-gray-100 text-gray-700'
                };
                $isFalta = $r['tipo'] === 'falta';

                $atrasoTexto = '';
                $atrasoClasse = '';
                $esperado = '';
                if ($r['tipo'] === 'entrada' && !empty($r['hora_entrada'])) {
                    $esperado = substr($r['hora_entrada'], 0, 5);
                    $real = strtotime($r['marcado_em']);
                    $padrao = strtotime(date('Y-m-d', $real) . ' ' . $r['hora_entrada']);
                    $diffSeg = $real - $padrao;
                    if ($diffSeg > ($tol * 60)) {
                        $h = floor($diffSeg / 3600); $m = floor(($diffSeg % 3600) / 60); $s = $diffSeg % 60;
                        $atrasoTexto = sprintf('+%02d:%02d:%02d', $h, $m, $s);
                        $atrasoClasse = 'text-red-600';
                    } elseif ($diffSeg >= 0) {
                        $atrasoTexto = sprintf('+%02d:%02d', 0, round($diffSeg / 60));
                        $atrasoClasse = 'text-amber-500';
                    } else {
                        $atrasoTexto = 'OK';
                        $atrasoClasse = 'text-emerald-600';
                    }
                } elseif ($r['tipo'] === 'saida' && !empty($r['hora_saida'])) {
                    $esperado = substr($r['hora_saida'], 0, 5);
                    $real = strtotime($r['marcado_em']);
                    $padrao = strtotime(date('Y-m-d', $real) . ' ' . $r['hora_saida']);
                    $diffSeg = $padrao - $real;
                    if ($diffSeg > 0) {
                        $h = floor($diffSeg / 3600); $m = floor(($diffSeg % 3600) / 60); $s = $diffSeg % 60;
                        $atrasoTexto = sprintf('-%02d:%02d:%02d', $h, $m, $s);
                        $atrasoClasse = 'text-amber-600';
                    } elseif ($diffSeg < (-$tol * 60)) {
                        $h = floor(abs($diffSeg) / 3600); $m = floor((abs($diffSeg) % 3600) / 60); $s = abs($diffSeg) % 60;
                        $atrasoTexto = sprintf('+%02d:%02d:%02d', $h, $m, $s);
                        $atrasoClasse = 'text-emerald-600';
                    } else {
                        $atrasoTexto = 'OK';
                        $atrasoClasse = 'text-emerald-600';
                    }
                } elseif ($r['tipo'] === 'atraso') {
                    $atrasoTexto = 'Sim';
                    $atrasoClasse = 'text-amber-600';
                }
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-gray-900 font-mono text-xs"><?= date('Y-m-d', strtotime($r['marcado_em'])) ?></td>
                <td class="px-5 py-3 text-gray-900 font-mono text-xs">
                    <?php if (empty($r['id'])): ?>
                        <span class="text-gray-300">—</span>
                    <?php else: ?>
                        <?= date('H:i', strtotime($r['marcado_em'])) ?>
                        <?php if ($esperado): ?>
                            <span class="text-gray-400 text-xs block">(prev: <?= $esperado ?>)</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3 text-gray-900"><?= htmlspecialchars($r['funcionario_nome']) ?></td>
                <td class="px-5 py-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $tipoClass ?>">
                        <?= ucfirst(str_replace('_', ' ', $r['tipo'])) ?>
                    </span>
                </td>
                <td class="px-5 py-3">
                    <?php if ($isFalta): ?>
                        <span class="text-red-600 font-medium"><i class="fa-solid fa-xmark"></i> Falta</span>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3 font-mono text-xs font-medium <?= $atrasoClasse ?>">
                    <?php if ($atrasoTexto): ?>
                        <?php if ($atrasoTexto === 'Sim'): ?>
                            <span class="text-amber-600 font-medium flex items-center gap-1"><i class="fa-regular fa-clock"></i> Sim</span>
                        <?php else: ?>
                            <?= $atrasoTexto ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3">
                    <?php if (empty($r['id'])): ?>
                        <span class="text-gray-300 text-xs">—</span>
                    <?php else: $mtd = $r['metodo'] ?? 'painel'; ?>
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1.5 <?= \App\Helpers\Metodo::classe($mtd) ?>"
                              title="<?= htmlspecialchars(\App\Helpers\Metodo::rotulo($mtd, !empty($r['selfie_existe']), $r['metodo_detalhe'] ?? null)) ?>">
                            <i class="<?= \App\Helpers\Metodo::icone($mtd) ?>"></i>
                            <?= htmlspecialchars(\App\Helpers\Metodo::rotulo($mtd, !empty($r['selfie_existe']), $r['metodo_detalhe'] ?? null)) ?>
                        </span>
                    <?php endif; ?>
                </td>
                <td class="px-5 py-3">
                    <?php if (!empty($r['selfie_existe'])): ?>
                        <img src="<?= BASE_PATH ?>/selfie/<?= (int)$r['selfie_id'] ?>" alt="selfie"
                             class="w-10 h-10 rounded object-cover cursor-pointer border border-gray-200 hover:ring-2 hover:ring-emerald-500"
                             onclick="abrirSelfie(this.src)" loading="lazy">
                    <?php else: ?>
                        <span class="text-gray-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($regs)): ?>
            <tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">Nenhum registo encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Lightbox selfie -->
<div id="selfie-modal" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4" onclick="this.classList.add('hidden'); this.classList.remove('flex');">
    <img id="selfie-img" src="" alt="" class="max-w-full max-h-[90vh] rounded-lg shadow-2xl">
</div>

<input type="hidden" id="csrf" value="<?= \App\Helpers\Csrf::token() ?>">

<script>
function abrirSelfie(src) {
    const m = document.getElementById('selfie-modal');
    document.getElementById('selfie-img').src = src;
    m.classList.remove('hidden'); m.classList.add('flex');
}

async function eliminarRegistos() {
    const params = new URLSearchParams(window.location.search);
    const de = params.get('de') || '<?= date('Y-m-01') ?>';
    const ate = params.get('ate') || '<?= date('Y-m-d') ?>';
    const funcId = params.get('funcionario') || '';
    const tipo = params.get('tipo') || '';

    const confirmar = await window.confirmar({
        title: 'Eliminar registos?',
        msg: `Deseja eliminar todos os registos de ${de} a ${ate}${tipo ? ' (tipo: ' + tipo + ')' : ''}${funcId ? ' (funcionario)' : ''}? Esta acao nao pode ser desfeita. As selfies associadas tambem serao apagadas.`,
        okText: 'Eliminar',
        danger: true
    });
    if (!confirmar) return;

    const fd = new FormData();
    fd.append('csrf', document.getElementById('csrf').value);
    fd.append('de', de);
    fd.append('ate', ate);
    if (funcId) fd.append('funcionario', funcId);
    if (tipo) fd.append('tipo', tipo);

    const r = await fetch(BASE_PATH + '/historico/eliminar', { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) {
        window.toast(`${r.total} registo(s) eliminado(s) com sucesso!`, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        window.toast(r.erro || 'Erro ao eliminar', 'error');
    }
}
</script>
