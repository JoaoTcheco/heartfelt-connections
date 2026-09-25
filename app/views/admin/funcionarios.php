<?php
/**
 * Lista de funcionarios.
 * A criacao passou a ter pagina propria: /funcionarios/novo
 * A edicao continua na pagina de detalhe: /funcionario/{id}
 */
$moeda = $moeda ?? 'MZN';
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Funcionarios</h1>
        <p class="text-gray-500 mt-1">Gerir colaboradores da farmacia.</p>
    </div>
    <a href="<?= BASE_PATH ?>/funcionarios/novo" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Novo funcionario
    </a>
</div>

<!-- Filtro -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6">
    <form method="get" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-700 mb-1">Procurar (servidor)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="nome, email, cargo..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" name="inativos" value="1" onchange="this.form.submit()" <?= $incluirInativos ? 'checked' : '' ?> class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500">
                <span class="text-xs font-medium text-gray-700">Mostrar inactivos</span>
            </label>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Perfil</label>
            <select id="filtro-perfil" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                <option value="">Todos</option>
                <option value="admin">Admin</option>
                <option value="gestor">Gestor</option>
                <option value="funcionario">Funcionário</option>
            </select>
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs font-medium text-gray-700 mb-1">Filtro rápido (em tela)</label>
            <input type="text" data-filter-rows="#tab-func" placeholder="qualquer texto..." class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700">Procurar</button>
        <a href="<?= BASE_PATH ?>/funcionarios" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Reset</a>
    </form>
</div>

<!-- Tabela -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm">
    <div class="overflow-x-auto rounded-xl">
    <table id="tab-func" data-paginate="20" class="w-full text-sm min-w-[1180px]">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Nome</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Cargo</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Perfil</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Email</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Horario</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Salario</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">PIN</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500">Estado</th>
                <th class="text-left px-5 py-3 font-medium text-gray-500"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100" data-live="tab-func">
            <?php if (empty($lista)): ?>
            <tr data-skip-paginate>
                <td colspan="9" class="px-5 py-8 text-center text-gray-400">Nenhum funcionario encontrado.</td>
            </tr>
            <?php endif; ?>
            <?php foreach ($lista as $f): ?>
            <tr class="hover:bg-gray-50 <?= !$f['ativo'] ? 'opacity-50 bg-gray-50' : '' ?>" data-estado="<?= $f['ativo'] ? 'ativo' : 'inativo' ?>" data-perfil="<?= htmlspecialchars($f['perfil'] ?? 'funcionario') ?>">
                <td class="px-5 py-3 font-medium <?= $f['ativo'] ? 'text-gray-900' : 'text-gray-400 line-through' ?>"><?= htmlspecialchars($f['nome']) ?></td>
                <td class="px-5 py-3 <?= $f['ativo'] ? 'text-gray-600' : 'text-gray-400' ?>"><?= htmlspecialchars($f['cargo']) ?></td>
                <td class="px-5 py-3 <?= $f['ativo'] ? 'text-gray-600' : 'text-gray-400' ?> capitalize"><?= htmlspecialchars($f['perfil'] ?? 'funcionario') ?></td>
                <td class="px-5 py-3 <?= $f['ativo'] ? 'text-gray-600' : 'text-gray-400' ?>"><?= htmlspecialchars($f['email']) ?></td>
                <td class="px-5 py-3 <?= $f['ativo'] ? 'text-gray-600' : 'text-gray-400' ?>"><?= substr($f['hora_entrada'], 0, 5) ?>-<?= substr($f['hora_saida'], 0, 5) ?></td>
                <td class="px-5 py-3 <?= $f['ativo'] ? 'text-gray-900' : 'text-gray-400' ?> font-medium"><?= number_format($f['salario_base'], 0, ',', ' ') ?> <?= htmlspecialchars($moeda) ?></td>
                <td class="px-5 py-3 text-gray-400 font-mono">****</td>
                <td class="px-5 py-3">
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $f['ativo'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' ?>">
                        <?= $f['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td class="px-5 py-3">
                    <div class="flex gap-2">
                        <a href="<?= BASE_PATH ?>/funcionario/<?= $f['id'] ?>" class="text-gray-400 hover:text-emerald-600" title="Abrir ficha"><i class="fa-solid fa-pen"></i></a>
                        <button onclick="toggleAtivo(<?= $f['id'] ?>)" class="hover:text-emerald-600" title="<?= $f['ativo'] ? 'Desativar' : 'Reativar' ?> funcionario">
                            <i class="fa-solid <?= $f['ativo'] ? 'fa-toggle-on text-emerald-500' : 'fa-toggle-off text-gray-400' ?> text-xl"></i>
                        </button>
                        <?php if ($f['perfil'] !== 'admin'): ?>
                        <button onclick="eliminarFuncionario(<?= $f['id'] ?>, '<?= htmlspecialchars($f['nome'], ENT_QUOTES) ?>')" class="text-gray-400 hover:text-red-600" title="Eliminar funcionario">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<input type="hidden" id="csrf-func" value="<?= \App\Helpers\Csrf::token() ?>">

<script>
const csrf = document.getElementById('csrf-func').value;

async function toggleAtivo(id) {
    const r = await fetch(BASE_PATH + '/funcionarios/dados/' + id).then(r => r.json());
    if (!r.ok) return;
    const nome = r.dados.nome;
    const ativo = Number(r.dados.ativo);
    const ok = await window.confirmar({
        title: ativo ? 'Desativar funcionario' : 'Reativar funcionario',
        msg: ativo
            ? `Tem certeza que deseja desativar "${nome}"? Ele nao podera aceder ao sistema nem marcar presenca.`
            : `Tem certeza que deseja reativar "${nome}"? Ele voltara a ter acesso ao sistema.`,
        okText: ativo ? 'Sim, desativar' : 'Sim, reativar',
        danger: !!ativo
    });
    if (!ok) return;
    const fd = new FormData();
    fd.append('csrf', csrf);
    const res = await fetch(BASE_PATH + '/funcionarios/toggle/' + id, { method: 'POST', body: fd }).then(r => r.json());
    if (res.ok) { location.reload(); }
    else { showToast(res.erro || 'Erro ao alterar estado', 'error'); }
}

async function eliminarFuncionario(id, nome) {
    const ok = await window.confirmar({
        title: 'Eliminar funcionario',
        msg: `Tem certeza que deseja ELIMINAR PERMANENTEMENTE "${nome}"?\n\nTodos os registos, selfies, ferias e dados serao removidos. Esta acao nao pode ser desfeita.`,
        okText: 'Sim, eliminar',
        danger: true
    });
    if (!ok) return;
    const fd = new FormData();
    fd.append('csrf', csrf);
    const r = await fetch(BASE_PATH + '/funcionarios/eliminar/' + id, { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) {
        showToast(r.mensagem || 'Funcionario eliminado!');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(r.erro || 'Erro ao eliminar', 'error');
    }
}

// ===== Filtro local por perfil =====
(function(){
    const tab = document.getElementById('tab-func');
    const fp  = document.getElementById('filtro-perfil');
    function apply() {
        if (!tab || !tab.__paginator) return setTimeout(apply, 100);
        const p = fp.value;
        tab.__paginator.filter(row => {
            if (p && row.dataset.perfil !== p) return false;
            return true;
        });
    }
    fp && fp.addEventListener('change', apply);
})();
</script>
