<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-500 mt-1"><?= $diaSemana ?>, <?= $dataHoje ?></p>
    </div>
    <div class="flex items-center gap-3">
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
            <i class="fa-solid fa-user"></i> <?= htmlspecialchars($func['nome']) ?>
        </div>
    </div>
</div>

<!-- Info do Funcionario -->
<div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm mb-8">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-700 text-xl font-bold">
            <?= strtoupper(substr($func['nome'], 0, 1)) ?>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($func['nome']) ?></h2>
            <p class="text-gray-500"><?= htmlspecialchars($func['cargo']) ?> · Código <?= htmlspecialchars($func['codigo']) ?></p>
        </div>
    </div>
</div>

<!-- Meus KPIs -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Dias c/ registo</p>
                <p class="text-3xl font-bold text-gray-900"><?= $diasComRegisto ?></p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600 text-xl">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Atrasos</p>
                <p class="text-3xl font-bold text-gray-900"><?= $atrasos ?></p>
            </div>
            <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 text-xl">
                <i class="fa-regular fa-clock"></i>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Faltas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $faltas ?></p>
            </div>
            <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center text-red-500 text-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Proximo</p>
                <p class="text-3xl font-bold <?= $proximo === 'entrada' ? 'text-emerald-600' : ($proximo === 'atrasado' ? 'text-amber-500' : 'text-red-600') ?>">
                    <?= ucfirst(str_replace('_', ' ', $proximo)) ?>
                </p>
            </div>
            <div class="w-12 h-12 <?= $proximo === 'atrasado' ? 'bg-amber-50 text-amber-500' : 'bg-emerald-50 text-emerald-600' ?> rounded-full flex items-center justify-center text-xl">
                <i class="fa-solid <?= $proximo === 'atrasado' ? 'fa-clock' : 'fa-arrow-right' ?>"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Registos de Hoje -->
    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Registos de hoje</h3>
            <a href="<?= BASE_PATH ?>/marcar" class="text-sm text-emerald-600 hover:text-emerald-800 font-medium">
                <i class="fa-solid fa-plus"></i> Novo registo
            </a>
        </div>
        <div class="p-5" id="registos-hoje-container">
            <?php if (empty($hoje)): ?>
            <p class="text-gray-400 text-sm">Nenhum registo hoje.</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($hoje as $r): ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-2 h-2 rounded-full <?= $r['tipo'] === 'entrada' ? 'bg-emerald-500' : ($r['tipo'] === 'saida' ? 'bg-red-500' : 'bg-amber-500') ?>"></span>
                    <span class="text-gray-500 w-14"><?= date('H:i', strtotime($r['marcado_em'])) ?></span>
                    <span class="font-medium text-gray-900"><?= ucfirst(str_replace('_', ' ', $r['tipo'])) ?></span>
                    <?php if ($r['observacao']): ?>
                    <span class="text-gray-400">· <?= htmlspecialchars($r['observacao']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Acao Rapida -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">Acao rapida</h3>
        </div>
        <div class="p-5 flex flex-col gap-3">
            <a href="<?= BASE_PATH ?>/marcar"
               class="w-full text-center px-4 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 font-medium transition-colors">
                <i class="fa-solid fa-clock"></i> Marcar <?= ucfirst(str_replace('_', ' ', $proximo)) ?>
            </a>
            <a href="<?= BASE_PATH ?>/minha-assiduidade"
               class="w-full text-center px-4 py-3 border border-emerald-200 text-emerald-700 rounded-lg hover:bg-emerald-50 font-medium transition-colors">
                <i class="fa-solid fa-chart-line"></i> Minha assiduidade e cortes
            </a>
            <a href="<?= BASE_PATH ?>/perfil"
               class="w-full text-center px-4 py-3 border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition-colors">
                <i class="fa-solid fa-user"></i> Meu perfil
            </a>

        </div>
    </div>
</div>

<script>
function carregarRegistosHoje() {
    fetch(BASE_PATH + '/dashboard/registos-hoje')
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            const container = document.getElementById('registos-hoje-container');
            if (!container) return;
            if (data.registos.length === 0) {
                container.innerHTML = '<p class="text-gray-400 text-sm">Nenhum registo hoje.</p>';
            } else {
                container.innerHTML = '<div class="space-y-3">' +
                    data.registos.map(r => {
                        var dot = r.tipo === 'entrada' ? 'bg-emerald-500' : (r.tipo === 'saida' ? 'bg-red-500' : 'bg-amber-500');
                        return '<div class="flex items-center gap-3 text-sm">' +
                            '<span class="w-2 h-2 rounded-full ' + dot + '"></span>' +
                            '<span class="text-gray-500 w-14">' + r.marcado_em.slice(11, 16) + '</span>' +
                            '<span class="font-medium text-gray-900">' + r.tipo.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); }) + '</span>' +
                            (r.observacao ? '<span class="text-gray-400">\u00b7 ' + r.observacao + '</span>' : '') +
                        '</div>';
                    }).join('') + '</div>';
            }
        });
}
setInterval(carregarRegistosHoje, 15000);
carregarRegistosHoje();
</script>
