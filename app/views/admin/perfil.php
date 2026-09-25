<?php
use App\Helpers\Auth;
use App\Helpers\Csrf;
$user = Auth::user();
$iniciais = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), explode(' ', $user['nome'] ?? '', 2)));
$fmt = fn($v) => number_format((float)$v, 2, ',', '.');
?>
<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Meu Perfil</h1>
    <p class="text-gray-500 mt-1">Os seus dados e estatisticas.</p>
</div>

<!-- Cartao identidade -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 max-w-3xl mb-6">
    <div class="flex items-center gap-4 mb-6">
        <div class="w-14 h-14 bg-emerald-600 rounded-full flex items-center justify-center text-white text-xl font-bold">
            <?= htmlspecialchars($iniciais) ?>
        </div>
        <div>
            <p class="font-semibold text-gray-900 text-lg"><?= htmlspecialchars($user['nome']) ?></p>
            <p class="text-sm text-gray-500 capitalize"><?= htmlspecialchars($user['perfil']) ?></p>
        </div>
    </div>

<?php if ($user['perfil'] !== 'funcionario'): ?>
    <form id="form-perfil" class="space-y-4">
        <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($user['nome']) ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none bg-gray-50" readonly>
            </div>
        </div>
        <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700">Guardar</button>
    </form>
<?php else: ?>
    <div class="space-y-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <p class="px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"><?= htmlspecialchars($user['nome']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                <p class="px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"><?= htmlspecialchars($user['codigo'] ?? '—') ?></p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <p class="px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"><?= htmlspecialchars($user['email']) ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                <p class="px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-gray-700"><?= htmlspecialchars($user['cargo'] ?? '—') ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- Estatisticas do mes -->
<?php if (!empty($func)): ?>
<div class="mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-3">Estatisticas — <?= htmlspecialchars($stats['mes']) ?></h2>
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 max-w-4xl">
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Salario base</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?= $fmt($stats['salario']) ?> <span class="text-xs font-normal text-gray-500"><?= $stats['moeda'] ?></span></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Valor dia</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?= $fmt($stats['valor_dia'] ?? 0) ?> <span class="text-xs font-normal text-gray-500"><?= $stats['moeda'] ?></span></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Valor hora</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?= $fmt($stats['valor_hora'] ?? 0) ?> <span class="text-xs font-normal text-gray-500"><?= $stats['moeda'] ?></span></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Faltas (mes)</p>
            <p class="text-lg font-bold text-red-600 mt-1"><?= $stats['faltas'] ?></p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500">Atrasos</p>
            <p class="text-lg font-bold text-amber-600 mt-1"><?= $stats['atrasos'] ?> <span class="text-xs text-gray-500">(<?= $stats['horas_atraso'] ?>h)</span></p>
        </div>
        <?php if ($user['perfil'] !== 'funcionario'): ?>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-emerald-700">A receber</p>
            <p class="text-lg font-bold text-emerald-700 mt-1"><?= $fmt($stats['liquido']) ?> <span class="text-xs font-normal"><?= $stats['moeda'] ?></span></p>
        </div>
        <?php endif; ?>
    </div>
    <div class="mt-2 text-xs text-gray-400 flex flex-wrap gap-4">
        <?php
        $anoMesPerfil = $stats['mes'] ?? date('Y-m');
        $diasTrabPerfil = (new \App\Models\Funcionario())->getDiasTrabalho($func['id'], $anoMesPerfil);
        $diasLabel = !empty($diasTrabPerfil) ? implode(', ', $diasTrabPerfil) : 'Seg-Sex (padrão)';
        ?>
        <span>Dias trabalho: <?= count($diasTrabPerfil) ?: 'Seg-Sex (padrão)' ?></span>
        <span>Dias mês: <?= !empty($diasTrabPerfil) ? implode(', ', $diasTrabPerfil) : 'Seg-Sex' ?></span>
        <span>Carga diaria: <?= $stats['carga_diaria'] ?? 8 ?>h</span>
        <span>Corte total: <?= $fmt($stats['corte_total']) ?> <?= $stats['moeda'] ?></span>
    </div>
</div>
<?php endif; ?>

<?php if ($user['perfil'] !== 'funcionario'): ?>
<!-- Alterar password -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl">
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-solid fa-lock text-emerald-600"></i> Alterar password</h3>
        <form id="form-password" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password atual</label>
                <input type="password" name="password_atual" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nova password</label>
                <input type="password" name="password_nova" required minlength="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700">Alterar password</button>
        </form>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-solid fa-key text-emerald-600"></i> Alterar PIN</h3>
        <form id="form-pin" class="space-y-3">
            <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Novo PIN (4 a 8 digitos)</label>
                <input type="text" name="pin" required pattern="\d{4,8}" inputmode="numeric" maxlength="8" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none font-mono tracking-widest text-center">
            </div>
            <p class="text-xs text-gray-500">O PIN e usado nas marcacoes de presenca e na Marcacao Rapida.</p>
            <button type="submit" class="px-5 py-2 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700">Atualizar PIN</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
async function postForm(url, form) {
    const fd = new FormData(form);
    const r = await fetch(BASE_PATH + url, { method: 'POST', body: fd }).then(r => r.json());
    return r;
}

<?php if ($user['perfil'] !== 'funcionario'): ?>
document.getElementById('form-perfil').addEventListener('submit', async (e) => {
    e.preventDefault();
    const r = await postForm('/perfil/atualizar', e.target);
    r.ok ? showToast('Perfil atualizado!') : showToast(r.erro || 'Erro', 'error');
});
document.getElementById('form-password').addEventListener('submit', async (e) => {
    e.preventDefault();
    const r = await postForm('/perfil/password', e.target);
    if (r.ok) { showToast('Password alterada!'); e.target.reset(); }
    else showToast(r.erro || 'Erro', 'error');
});
document.getElementById('form-pin').addEventListener('submit', async (e) => {
    e.preventDefault();
    const r = await postForm('/perfil/pin', e.target);
    if (r.ok) { showToast('PIN atualizado!'); e.target.reset(); }
    else showToast(r.erro || 'Erro', 'error');
});
<?php endif; ?>
</script>
