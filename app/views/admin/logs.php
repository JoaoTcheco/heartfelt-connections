<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Logs do Sistema</h1>
        <p class="text-gray-500 mt-1">Auditoria de acoes no sistema.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <a href="<?= BASE_PATH ?>/logs/pdf?de=<?= $de ?>&ate=<?= $ate ?><?= $funcionario ? '&funcionario='.$funcionario : '' ?><?= $acao ? '&acao='.urlencode($acao) : '' ?><?= $q ? '&q='.urlencode($q) : '' ?>"
           class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2" target="_blank">
            <i class="fa-solid fa-file-pdf"></i> Exportar PDF
        </a>
        <button onclick="eliminarLogs()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 flex items-center gap-2">
            <i class="fa-solid fa-trash-can"></i> Eliminar registos filtrados
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">De</label>
            <input type="date" name="de" value="<?= $de ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ate</label>
            <input type="date" name="ate" value="<?= $ate ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Funcionario</label>
            <select name="funcionario" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todos</option>
                <?php foreach ($funcionarios as $f): ?>
                <option value="<?= $f['id'] ?>" <?= $funcionario == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Acao</label>
            <select name="acao" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todas</option>
                <?php foreach ($acoes as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $acao === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Procurar</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Texto..."
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Filtrar</button>
        <a href="<?= BASE_PATH ?>/logs" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Reset</a>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Filtro rápido</label>
            <input type="text" data-filter-rows="#tab-logs" placeholder="Filtrar em tela..." class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
    </form>
</div>

<div class="bg-white border border-gray-200 rounded-xl shadow-sm">
    <div class="overflow-x-auto rounded-xl">
    <table id="tab-logs" data-paginate="20" class="w-full text-sm min-w-[860px]">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Timestamp</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Utilizador</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Acao</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Detalhe</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100" data-live="tab-logs">
            <?php foreach ($logs as $l): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-gray-600 font-mono text-xs"><?= date('d/m/Y, H:i:s', strtotime($l['criado_em'])) ?></td>
                <td class="px-5 py-3 text-gray-900"><?= htmlspecialchars($l['funcionario_nome'] ?? 'Sistema') ?></td>
                <td class="px-5 py-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                        <?= htmlspecialchars($l['acao']) ?>
                    </span>
                </td>
                <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($l['detalhes'] ? (json_decode($l['detalhes'])->tipo ?? $l['detalhes']) : '-') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="4" class="px-5 py-8 text-center text-gray-400">Nenhum log encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<input type="hidden" id="csrf" value="<?= \App\Helpers\Csrf::token() ?>">

<script>
async function eliminarLogs() {
    const params = new URLSearchParams(window.location.search);
    const de = params.get('de') || '<?= date('Y-m-01') ?>';
    const ate = params.get('ate') || '<?= date('Y-m-d') ?>';
    const funcId = params.get('funcionario') || '';
    const acao = params.get('acao') || '';
    const q = params.get('q') || '';

    const confirmar = await window.confirmar({
        title: 'Eliminar logs?',
        msg: `Deseja eliminar todos os logs de ${de} a ${ate}${acao ? ' (acao: ' + acao + ')' : ''}${funcId ? ' (funcionario)' : ''}? Esta acao nao pode ser desfeita.`,
        okText: 'Eliminar',
        danger: true
    });
    if (!confirmar) return;

    const fd = new FormData();
    fd.append('csrf', document.getElementById('csrf').value);
    fd.append('de', de);
    fd.append('ate', ate);
    if (funcId) fd.append('funcionario', funcId);
    if (acao) fd.append('acao', acao);
    if (q) fd.append('q', q);

    const r = await fetch(BASE_PATH + '/logs/eliminar', { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) {
        window.toast(`${r.total} registo(s) eliminado(s) com sucesso!`, 'success');
        setTimeout(() => location.reload(), 1000);
    } else {
        window.toast(r.erro || 'Erro ao eliminar', 'error');
    }
}
</script>
