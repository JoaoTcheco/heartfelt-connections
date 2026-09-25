<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
            <i class="fa-solid fa-shield-halved text-emerald-600"></i> Auditoria
        </h1>
        <p class="text-gray-500 mt-1">Trilha de eventos do sistema — quem fez o quê, quando e a partir de onde.</p>
    </div>
</div>

<!-- Estatísticas rápidas -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <?php foreach ([
        ['Eventos',       $stats['total'],           'fa-list-check',  'emerald'],
        ['Utilizadores',  $stats['utilizadores'],    'fa-users',       'blue'],
        ['Acções',        $stats['acoes_distintas'], 'fa-bolt',        'amber'],
        ['IPs distintos', $stats['ips'],             'fa-network-wired','purple'],
    ] as [$lbl,$val,$ico,$cor]): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm">
        <div class="w-10 h-10 rounded-full bg-<?= $cor ?>-100 text-<?= $cor ?>-700 flex items-center justify-center"><i class="fa-solid <?= $ico ?>"></i></div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide"><?= $lbl ?></p>
            <p class="text-xl font-bold text-gray-900"><?= (int)$val ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="get" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">De</label>
            <input type="date" name="de" value="<?= htmlspecialchars($de) ?>" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Até</label>
            <input type="date" name="ate" value="<?= htmlspecialchars($ate) ?>" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Funcionário</label>
            <select name="funcionario" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todos</option>
                <?php foreach ($funcionarios as $f): ?>
                <option value="<?= $f['id'] ?>" <?= $funcionario == $f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Acção</label>
            <select name="acao" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todas</option>
                <?php foreach ($acoes as $a): ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $acao === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Entidade</label>
            <select name="entidade" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todas</option>
                <?php foreach ($entidades as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $entidade === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">IP contém</label>
            <input type="text" name="ip" value="<?= htmlspecialchars($ip ?? '') ?>" placeholder="ex.: 192.168" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div class="md:col-span-2 lg:col-span-3">
            <label class="block text-xs font-medium text-gray-700 mb-1">Procurar (nome, acção, entidade, detalhes)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="ex.: ferias, login..." class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div class="flex gap-2 lg:col-span-3">
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Filtrar</button>
            <a href="<?= BASE_PATH ?>/auditoria" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Reset</a>
            <input type="text" data-filter-rows="#tab-audit" placeholder="Filtro rápido em tela..." class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
    </form>
</div>

<!-- Tabela -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm">
    <div class="overflow-x-auto rounded-xl">
        <table id="tab-audit" data-paginate="20" class="w-full text-sm min-w-[900px]">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Quando</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Utilizador</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Acção</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Origem</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Entidade</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">ID</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">IP</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Detalhes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100" data-live="tab-audit">
            <?php foreach ($eventos as $e): ?>
                <tr class="hover:bg-gray-50 align-top">
                    <td class="px-4 py-3 text-gray-700 font-mono text-xs whitespace-nowrap"><?= date('d/m/Y H:i:s', strtotime($e['criado_em'])) ?></td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($e['funcionario_nome'] ?? 'Sistema') ?></div>
                        <?php if (!empty($e['perfil'])): ?>
                            <div class="text-xs text-gray-400 capitalize"><?= htmlspecialchars($e['perfil']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"><?= htmlspecialchars($e['acao']) ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <?php $mtd = \App\Helpers\Metodo::daAcao($e['acao'] ?? null); ?>
                        <?php if ($mtd === null): ?>
                            <span class="text-gray-300 text-xs">—</span>
                        <?php else: ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium inline-flex items-center gap-1 <?= \App\Helpers\Metodo::classe($mtd) ?>">
                                <i class="<?= \App\Helpers\Metodo::icone($mtd) ?>"></i>
                                <?= htmlspecialchars(\App\Helpers\Metodo::rotuloCurto($mtd)) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($e['entidade'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= htmlspecialchars((string)($e['entidade_id'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= htmlspecialchars($e['ip_addr'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs">
                        <?php if (!empty($e['detalhes'])): ?>
                            <button onclick="verDetalhe(<?= (int)$e['id'] ?>)" class="text-emerald-700 hover:underline text-xs"><i class="fa-solid fa-magnifying-glass"></i> Ver JSON</button>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($eventos)): ?>
                <tr data-skip-paginate><td colspan="8" class="px-5 py-12 text-center text-gray-400">
                    <i class="fa-solid fa-folder-open text-3xl mb-2 block opacity-50"></i>
                    Nenhum evento corresponde aos filtros.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal detalhe -->
<div id="audit-modal" class="fixed inset-0 z-[120] hidden items-center justify-center bg-black/40 p-4 no-print">
    <div class="bg-white rounded-2xl shadow-xl max-w-2xl w-full p-6 animate-fade-in max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Detalhe do evento</h3>
            <button onclick="fecharDetalhe()" class="text-gray-400 hover:text-gray-700"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="audit-body" class="text-sm text-gray-700 space-y-2"></div>
    </div>
</div>

<script>
async function verDetalhe(id) {
    const r = await fetch(BASE_PATH + '/auditoria/detalhe/' + id).then(r=>r.json());
    if (!r.ok) { window.toast(r.erro || 'Erro', 'error'); return; }
    const e = r.evento;
    const det = e.detalhes_obj ? JSON.stringify(e.detalhes_obj, null, 2) : (e.detalhes || '—');
    document.getElementById('audit-body').innerHTML = `
        <div class="grid grid-cols-2 gap-2">
            <div><span class="text-gray-500">Quando:</span> <b>${new Date(e.criado_em).toLocaleString('pt-PT')}</b></div>
            <div><span class="text-gray-500">Utilizador:</span> <b>${e.funcionario_nome || 'Sistema'}</b></div>
            <div><span class="text-gray-500">Acção:</span> <b>${e.acao}</b></div>
            <div><span class="text-gray-500">Entidade:</span> <b>${e.entidade || '—'}</b> ${e.entidade_id?'#'+e.entidade_id:''}</div>
            <div><span class="text-gray-500">IP:</span> <code>${e.ip_addr || '—'}</code></div>
            <div><span class="text-gray-500">ID evento:</span> <code>${e.id}</code></div>
        </div>
        <div class="mt-3">
            <p class="text-gray-500 text-xs mb-1">Detalhes (JSON):</p>
            <pre class="bg-gray-900 text-emerald-300 p-3 rounded-lg text-xs overflow-auto max-h-80">${det.replace(/</g,'&lt;')}</pre>
        </div>`;
    const m = document.getElementById('audit-modal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function fecharDetalhe() {
    const m = document.getElementById('audit-modal');
    m.classList.add('hidden'); m.classList.remove('flex');
}
</script>
