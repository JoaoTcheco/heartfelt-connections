<?php
use App\Helpers\Auth;
$user = Auth::user();
?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-500 mt-1">Visao geral — <?= $diaSemana ?>, <?= $dataHoje ?></p>
    </div>
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
        <i class="fa-solid fa-check-circle"></i> Bem-vindo!
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Funcionarios ativos</p>
                <p id="kpi-ativos" class="text-3xl font-bold text-gray-900"><?= $kpi['funcionarios_ativos'] ?></p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600 text-xl">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Presentes hoje</p>
                <p id="kpi-presentes" class="text-3xl font-bold text-gray-900"><?= $kpi['presentes_hoje'] ?></p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-600 text-xl">
                <i class="fa-solid fa-check"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Atrasos hoje</p>
                <p id="kpi-atrasos" class="text-3xl font-bold text-gray-900"><?= $kpi['atrasos_hoje'] ?></p>
            </div>
            <div class="w-12 h-12 bg-amber-50 rounded-full flex items-center justify-center text-amber-500 text-xl">
                <i class="fa-regular fa-clock"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1">Faltas hoje</p>
                <p id="kpi-faltas" class="text-3xl font-bold text-gray-900"><?= $kpi['faltas_hoje'] ?></p>
            </div>
            <div class="w-12 h-12 bg-red-50 rounded-full flex items-center justify-center text-red-500 text-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Atividade -->
    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Atividade de hoje</h3>
            <span class="text-xs text-gray-400 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Em tempo real
            </span>
        </div>
        <div class="p-5" id="box-atividade">
            <?php if (empty($atividade)): ?>
            <p class="text-gray-400 text-sm" id="atividade-vazio">Sem registos hoje.</p>
            <div class="space-y-3 hidden" id="lista-atividade"></div>
            <?php else: ?>
            <div class="space-y-3" id="lista-atividade">
                <?php foreach ($atividade as $a): ?>
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-2 h-2 rounded-full <?= $a['tipo'] === 'entrada' ? 'bg-emerald-500' : ($a['tipo'] === 'saida' ? 'bg-red-500' : 'bg-amber-500') ?>"></span>
                    <span class="text-gray-500 w-14"><?= substr($a['hora'], 0, 5) ?></span>
                    <span class="font-medium text-gray-900"><?= htmlspecialchars($a['nome']) ?></span>
                    <span class="text-gray-400 text-xs">· <?= htmlspecialchars($a['cargo']) ?></span>
                    <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-medium inline-flex items-center gap-1 <?= \App\Helpers\Metodo::classe($a['metodo'] ?? null) ?>"
                          title="<?= htmlspecialchars(\App\Helpers\Metodo::rotulo($a['metodo'] ?? null, !empty($a['selfie_id']), $a['metodo_detalhe'] ?? null)) ?>">
                        <i class="<?= \App\Helpers\Metodo::icone($a['metodo'] ?? null) ?>"></i>
                        <?= htmlspecialchars(\App\Helpers\Metodo::rotuloCurto($a['metodo'] ?? null)) ?>
                    </span>
                    <span class=" px-2 py-0.5 rounded-full text-xs font-medium <?= $a['tipo'] === 'entrada' ? 'bg-emerald-100 text-emerald-700' : ($a['tipo'] === 'saida' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
                        <?= ucfirst(str_replace('_', ' ', $a['tipo'])) ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Funcionarios -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
        <div class="p-5 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900">Funcionarios</h3>
        </div>
        <div class="p-5 space-y-4">
            <?php foreach ($funcionarios as $f): 
                $estaPresente = in_array($f['id'], $presentes);
                $iniciais = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), explode(' ', $f['nome'], 2)));
            ?>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-700 text-sm font-bold">
                    <?= htmlspecialchars($iniciais) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($f['nome']) ?></p>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($f['cargo']) ?></p>
                </div>
                <span data-presenca="<?= (int) $f['id'] ?>" class="px-2.5 py-1 rounded-full text-xs font-medium <?= $estaPresente ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' ?>">
                    <?= $estaPresente ? 'Presente' : 'Ausente' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>


<script>
// ====== Painel em tempo real ======
(function () {
    const BASE = (typeof BASE_PATH !== 'undefined') ? BASE_PATH : '';
    const esc = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };
    const cor = { entrada: ['bg-emerald-500', 'bg-emerald-100 text-emerald-700'], saida: ['bg-red-500', 'bg-red-100 text-red-700'] };
    // Origem da marcacao — espelha App\Helpers\Metodo (PHP) para o render em tempo real.
    const ORIGEM = {
        painel:  { rotulo: 'Painel',            icone: 'fa-solid fa-desktop',     cls: 'bg-sky-100 text-sky-700',       titulo: a => a.selfie_id ? 'Painel + foto' : 'Painel (sem foto)' },
        pin:     { rotulo: 'PIN',               icone: 'fa-solid fa-keyboard',    cls: 'bg-amber-100 text-amber-700',   titulo: a => a.selfie_id ? 'PIN + foto' : 'PIN (sem foto)' },
        digital: { rotulo: 'Impressão digital', icone: 'fa-solid fa-fingerprint', cls: 'bg-indigo-100 text-indigo-700', titulo: a => a.metodo_detalhe ? 'Impressão digital · ' + a.metodo_detalhe : 'Impressão digital' },
        sistema: { rotulo: 'Sistema',           icone: 'fa-solid fa-gear',        cls: 'bg-gray-100 text-gray-600',     titulo: () => 'Sistema (automático)' }
    };

    function setKpi(id, valor) {
        const el = document.getElementById(id);
        if (!el || valor === undefined || el.textContent.trim() === String(valor)) return;
        el.textContent = valor;
        el.classList.add('transition', 'scale-110');
        setTimeout(() => el.classList.remove('scale-110'), 400);
    }

    async function atualizar() {
        try {
            const r = await fetch(BASE + '/dashboard/resumo', { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;

            setKpi('kpi-ativos', d.kpi.funcionarios_ativos);
            setKpi('kpi-presentes', d.kpi.presentes_hoje);
            setKpi('kpi-atrasos', d.kpi.atrasos_hoje);
            setKpi('kpi-faltas', d.kpi.faltas_hoje);

            const lista = document.getElementById('lista-atividade');
            // Origem da marcacao (mesma semantica do helper PHP App\Helpers\Metodo)
            const vazio = document.getElementById('atividade-vazio');
            if (lista) {
                if (!d.atividade.length) {
                    lista.classList.add('hidden');
                    if (vazio) vazio.classList.remove('hidden');
                } else {
                    lista.classList.remove('hidden');
                    if (vazio) vazio.classList.add('hidden');
                    lista.innerHTML = d.atividade.map(a => {
                        const c = cor[a.tipo] || ['bg-amber-500', 'bg-amber-100 text-amber-700'];
                        const tipo = (a.tipo || '').replace(/_/g, ' ');
                        const org = ORIGEM[a.metodo] || ORIGEM.painel;
                        return `<div class="flex items-center gap-3 text-sm">
                            <span class="w-2 h-2 rounded-full ${c[0]}"></span>
                            <span class="text-gray-500 w-14">${esc((a.hora || '').substring(0, 5))}</span>
                            <span class="font-medium text-gray-900">${esc(a.nome)}</span>
                            <span class="text-gray-400 text-xs">· ${esc(a.cargo)}</span>
                            <span class="ml-auto px-2 py-0.5 rounded-full text-xs font-medium inline-flex items-center gap-1 ${org.cls}" title="${esc(org.titulo(a))}"><i class="${org.icone}"></i>${org.rotulo}</span>
                            <span class=" px-2 py-0.5 rounded-full text-xs font-medium ${c[1]}">${esc(tipo.charAt(0).toUpperCase() + tipo.slice(1))}</span>
                        </div>`;
                    }).join('');
                }
            }

            document.querySelectorAll('[data-presenca]').forEach(el => {
                const presente = d.presentes.includes(parseInt(el.dataset.presenca, 10));
                el.textContent = presente ? 'Presente' : 'Ausente';
                el.className = 'px-2.5 py-1 rounded-full text-xs font-medium ' + (presente ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500');
            });
        } catch (e) { /* rede indisponivel: mantem os valores atuais */ }
    }

    let timer = setInterval(atualizar, 15000);
    document.addEventListener('visibilitychange', () => {
        clearInterval(timer);
        if (!document.hidden) { atualizar(); timer = setInterval(atualizar, 15000); }
    });
})();
</script>
