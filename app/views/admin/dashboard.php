<?php
use App\Helpers\Auth;
$user = Auth::user();
$totalAtivos = max(0, (int) ($kpi['funcionarios_ativos'] ?? 0));
$presentesHoje = max(0, (int) ($kpi['presentes_hoje'] ?? 0));
$atrasosHoje = max(0, (int) ($kpi['atrasos_hoje'] ?? 0));
$faltasHoje = max(0, (int) ($kpi['faltas_hoje'] ?? 0));
$taxaPresenca = $totalAtivos > 0 ? min(100, round(($presentesHoje / $totalAtivos) * 100)) : 0;
$taxaRestante = max(0, $totalAtivos - $presentesHoje);
?>
<div class="space-y-6">
    <!-- Cabeçalho -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Painel operacional
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">Dashboard</h1>
            <p class="text-slate-500 mt-1"><?= htmlspecialchars($diaSemana) ?>, <?= htmlspecialchars($dataHoje) ?></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= BASE_PATH ?>/marcar" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold shadow-sm hover:bg-emerald-700 transition-colors">
                <i class="fa-solid fa-circle-dot"></i> Marcar presença
            </a>
            <a href="<?= BASE_PATH ?>/relatorios" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-chart-column"></i> Relatórios
            </a>
        </div>
    </div>

    <!-- KPIs -->
    <section aria-label="Indicadores de hoje" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Funcionários ativos</p>
                    <p id="kpi-ativos" class="mt-2 text-3xl font-bold text-slate-900"><?= $totalAtivos ?></p>
                    <p class="mt-1 text-xs text-slate-400">Elegíveis para assiduidade</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>

        <div class="bg-white border border-emerald-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Presentes hoje</p>
                    <p id="kpi-presentes" class="mt-2 text-3xl font-bold text-emerald-700"><?= $presentesHoje ?></p>
                    <p class="mt-1 text-xs text-emerald-600"><span id="taxa-presenca"><?= $taxaPresenca ?></span>% dos ativos</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fa-solid fa-user-check"></i></div>
            </div>
        </div>

        <div class="bg-white border border-amber-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Atrasos hoje</p>
                    <p id="kpi-atrasos" class="mt-2 text-3xl font-bold text-amber-600"><?= $atrasosHoje ?></p>
                    <p class="mt-1 text-xs text-slate-400">Entradas após tolerância</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-regular fa-clock"></i></div>
            </div>
        </div>

        <div class="bg-white border border-red-200 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Faltas hoje</p>
                    <p id="kpi-faltas" class="mt-2 text-3xl font-bold text-red-600"><?= $faltasHoje ?></p>
                    <p class="mt-1 text-xs text-slate-400">Ausências processadas</p>
                </div>
                <div class="w-11 h-11 rounded-xl bg-red-50 text-red-600 flex items-center justify-center"><i class="fa-solid fa-user-xmark"></i></div>
            </div>
        </div>
    </section>

    <!-- Resumo visual -->
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm p-5 sm:p-6">
            <div class="flex items-center justify-between gap-4 mb-5">
                <div>
                    <h2 class="font-bold text-slate-900">Resumo da presença</h2>
                    <p class="text-sm text-slate-500 mt-1">Estado dos funcionários ativos no dia de hoje.</p>
                </div>
                <span id="dashboard-atualizado" class="hidden sm:inline-flex items-center gap-1.5 text-xs text-slate-400">
                    <i class="fa-solid fa-rotate"></i> Atualização automática
                </span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-6">
                <div class="relative w-32 h-32 shrink-0 mx-auto sm:mx-0">
                    <svg class="w-32 h-32 -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="50" fill="none" stroke="currentColor" stroke-width="12" class="text-slate-100"></circle>
                        <circle id="presenca-ring" cx="60" cy="60" r="50" fill="none" stroke="currentColor" stroke-width="12" stroke-linecap="round" class="text-emerald-500" stroke-dasharray="314.16" stroke-dashoffset="<?= 314.16 - (314.16 * $taxaPresenca / 100) ?>"></circle>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                        <strong id="presenca-percent" class="text-2xl font-bold text-slate-900"><?= $taxaPresenca ?>%</strong>
                        <span class="text-[11px] text-slate-400">presença</span>
                    </div>
                </div>

                <div class="flex-1 w-full">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-xl bg-emerald-50 p-4">
                            <p class="text-xs font-medium text-emerald-700">Presentes</p>
                            <p id="resumo-presentes" class="text-xl font-bold text-emerald-800 mt-1"><?= $presentesHoje ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs font-medium text-slate-500">Por verificar</p>
                            <p id="resumo-restantes" class="text-xl font-bold text-slate-800 mt-1"><?= $taxaRestante ?></p>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-4">
                            <p class="text-xs font-medium text-amber-700">Com atraso</p>
                            <p id="resumo-atrasos" class="text-xl font-bold text-amber-800 mt-1"><?= $atrasosHoje ?></p>
                        </div>
                    </div>
                    <div class="mt-5">
                        <div class="flex justify-between text-xs mb-2">
                            <span class="text-slate-500">Presença registada</span>
                            <span id="barra-label" class="font-semibold text-slate-700"><?= $taxaPresenca ?>%</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                            <div id="barra-presenca" class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: <?= $taxaPresenca ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Atalhos -->
        <div class="bg-slate-900 rounded-2xl shadow-sm p-5 sm:p-6 text-white">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-bold">Acessos rápidos</h2>
                    <p class="text-sm text-slate-400 mt-1">Operações frequentes</p>
                </div>
                <i class="fa-solid fa-bolt text-amber-300"></i>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a href="<?= BASE_PATH ?>/funcionarios/novo" class="rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 p-3 transition-colors">
                    <i class="fa-solid fa-user-plus text-emerald-300"></i>
                    <span class="block text-sm font-medium mt-2">Novo funcionário</span>
                </a>
                <a href="<?= BASE_PATH ?>/relatorios" class="rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 p-3 transition-colors">
                    <i class="fa-solid fa-file-lines text-sky-300"></i>
                    <span class="block text-sm font-medium mt-2">Relatórios</span>
                </a>
                <a href="<?= BASE_PATH ?>/folhas" class="rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 p-3 transition-colors">
                    <i class="fa-solid fa-file-invoice-dollar text-amber-300"></i>
                    <span class="block text-sm font-medium mt-2">Folhas salariais</span>
                </a>
                <a href="<?= BASE_PATH ?>/auditoria" class="rounded-xl bg-white/10 hover:bg-white/15 border border-white/10 p-3 transition-colors">
                    <i class="fa-solid fa-shield-halved text-violet-300"></i>
                    <span class="block text-sm font-medium mt-2">Auditoria</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Atividade + funcionários -->
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Atividade de hoje</h2>
                    <p class="text-xs text-slate-400 mt-1">Últimas marcações registadas</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-xs text-emerald-600 font-medium">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Ao vivo
                </span>
            </div>
            <div class="p-5" id="box-atividade">
                <?php if (empty($atividade)): ?>
                    <div id="atividade-vazio" class="py-8 text-center">
                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto"><i class="fa-regular fa-clock"></i></div>
                        <p class="text-sm font-medium text-slate-600 mt-3">Sem marcações hoje</p>
                        <p class="text-xs text-slate-400 mt-1">As novas marcações aparecerão aqui automaticamente.</p>
                    </div>
                    <div class="space-y-2 hidden" id="lista-atividade"></div>
                <?php else: ?>
                    <div class="space-y-2" id="lista-atividade">
                        <?php foreach ($atividade as $a): ?>
                            <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">
                                <span class="w-2.5 h-2.5 rounded-full shrink-0 <?= $a['tipo'] === 'entrada' ? 'bg-emerald-500' : ($a['tipo'] === 'saida' ? 'bg-red-500' : 'bg-amber-500') ?>"></span>
                                <span class="text-xs text-slate-400 w-11 shrink-0"><?= htmlspecialchars(substr($a['hora'], 0, 5)) ?></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($a['nome']) ?></p>
                                    <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($a['cargo']) ?></p>
                                </div>
                                <span class="hidden sm:inline-flex px-2 py-1 rounded-lg text-[11px] font-semibold <?= \App\Helpers\Metodo::classe($a['metodo'] ?? null) ?>">
                                    <i class="<?= \App\Helpers\Metodo::icone($a['metodo'] ?? null) ?> mr-1"></i><?= htmlspecialchars(\App\Helpers\Metodo::rotuloCurto($a['metodo'] ?? null)) ?>
                                </span>
                                <span class="px-2 py-1 rounded-lg text-[11px] font-semibold <?= $a['tipo'] === 'entrada' ? 'bg-emerald-50 text-emerald-700' : ($a['tipo'] === 'saida' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $a['tipo']))) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Funcionários</h2>
                    <p class="text-xs text-slate-400 mt-1">Estado atual</p>
                </div>
                <a href="<?= BASE_PATH ?>/funcionarios" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800">Ver todos</a>
            </div>
            <div class="p-5 space-y-3 max-h-[430px] overflow-y-auto">
                <?php if (empty($funcionarios)): ?>
                    <p class="text-sm text-slate-400 text-center py-8">Nenhum funcionário ativo.</p>
                <?php else: ?>
                    <?php foreach ($funcionarios as $f):
                        $estaPresente = in_array($f['id'], $presentes);
                        $partesNome = preg_split('/\s+/', trim($f['nome']));
                        $iniciais = strtoupper(substr($partesNome[0] ?? 'F', 0, 1) . substr($partesNome[count($partesNome)-1] ?? '', 0, 1));
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl <?= $estaPresente ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?> flex items-center justify-center text-xs font-bold shrink-0">
                            <?= htmlspecialchars($iniciais) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($f['nome']) ?></p>
                            <p class="text-xs text-slate-400 truncate"><?= htmlspecialchars($f['cargo']) ?></p>
                        </div>
                        <span data-presenca="<?= (int) $f['id'] ?>" class="px-2 py-1 rounded-lg text-[11px] font-semibold <?= $estaPresente ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                            <?= $estaPresente ? 'Presente' : 'Ausente' ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    const BASE = (typeof BASE_PATH !== 'undefined') ? BASE_PATH : '';
    const esc = (t) => { const d = document.createElement('div'); d.textContent = t ?? ''; return d.innerHTML; };
    const cor = {
        entrada: ['bg-emerald-500', 'bg-emerald-50 text-emerald-700'],
        saida: ['bg-red-500', 'bg-red-50 text-red-700']
    };
    const ORIGEM = {
        painel:  { rotulo: 'Painel', icone: 'fa-solid fa-desktop', cls: 'bg-sky-100 text-sky-700', titulo: a => a.selfie_id ? 'Painel + foto' : 'Painel (sem foto)' },
        pin:     { rotulo: 'PIN', icone: 'fa-solid fa-keyboard', cls: 'bg-amber-100 text-amber-700', titulo: a => a.selfie_id ? 'PIN + foto' : 'PIN (sem foto)' },
        digital: { rotulo: 'Impressão digital', icone: 'fa-solid fa-fingerprint', cls: 'bg-indigo-100 text-indigo-700', titulo: a => a.metodo_detalhe ? 'Impressão digital · ' + a.metodo_detalhe : 'Impressão digital' },
        sistema: { rotulo: 'Sistema', icone: 'fa-solid fa-gear', cls: 'bg-gray-100 text-gray-600', titulo: () => 'Sistema (automático)' }
    };

    function setKpi(id, valor) {
        const el = document.getElementById(id);
        if (!el || valor === undefined) return;
        if (el.textContent.trim() === String(valor)) return;
        el.textContent = valor;
        el.classList.add('transition', 'scale-110');
        setTimeout(() => el.classList.remove('scale-110'), 400);
    }

    function atualizarResumoVisual(kpi) {
        const ativos = Math.max(0, parseInt(kpi.funcionarios_ativos, 10) || 0);
        const presentes = Math.max(0, parseInt(kpi.presentes_hoje, 10) || 0);
        const atrasos = Math.max(0, parseInt(kpi.atrasos_hoje, 10) || 0);
        const taxa = ativos > 0 ? Math.min(100, Math.round((presentes / ativos) * 100)) : 0;
        const restantes = Math.max(0, ativos - presentes);

        ['taxa-presenca', 'presenca-percent', 'barra-label'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = taxa + (id === 'taxa-presenca' ? '' : '%');
        });
        setKpi('resumo-presentes', presentes);
        setKpi('resumo-restantes', restantes);
        setKpi('resumo-atrasos', atrasos);

        const barra = document.getElementById('barra-presenca');
        if (barra) barra.style.width = taxa + '%';
        const ring = document.getElementById('presenca-ring');
        if (ring) ring.style.strokeDashoffset = String(314.16 - (314.16 * taxa / 100));
    }

    function renderAtividade(atividade) {
        const lista = document.getElementById('lista-atividade');
        const vazio = document.getElementById('atividade-vazio');
        if (!lista) return;

        if (!atividade.length) {
            lista.classList.add('hidden');
            if (vazio) vazio.classList.remove('hidden');
            return;
        }

        lista.classList.remove('hidden');
        if (vazio) vazio.classList.add('hidden');
        lista.innerHTML = atividade.map(a => {
            const c = cor[a.tipo] || ['bg-amber-500', 'bg-amber-50 text-amber-700'];
            const org = ORIGEM[a.metodo] || ORIGEM.painel;
            const tipo = (a.tipo || '').replace(/_/g, ' ');
            return '<div class="flex items-center gap-3 p-3 rounded-xl hover:bg-slate-50 transition-colors">' +
                '<span class="w-2.5 h-2.5 rounded-full shrink-0 ' + c[0] + '"></span>' +
                '<span class="text-xs text-slate-400 w-11 shrink-0">' + esc((a.hora || '').substring(0, 5)) + '</span>' +
                '<div class="min-w-0 flex-1"><p class="text-sm font-semibold text-slate-800 truncate">' + esc(a.nome) + '</p><p class="text-xs text-slate-400 truncate">' + esc(a.cargo) + '</p></div>' +
                '<span class="hidden sm:inline-flex px-2 py-1 rounded-lg text-[11px] font-semibold ' + org.cls + '" title="' + esc(org.titulo(a)) + '"><i class="' + org.icone + ' mr-1"></i>' + esc(org.rotulo) + '</span>' +
                '<span class="px-2 py-1 rounded-lg text-[11px] font-semibold ' + c[1] + '">' + esc(tipo.charAt(0).toUpperCase() + tipo.slice(1)) + '</span>' +
            '</div>';
        }).join('');
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
            atualizarResumoVisual(d.kpi);
            renderAtividade(d.atividade || []);

            document.querySelectorAll('[data-presenca]').forEach(el => {
                const presente = (d.presentes || []).includes(parseInt(el.dataset.presenca, 10));
                el.textContent = presente ? 'Presente' : 'Ausente';
                el.className = 'px-2 py-1 rounded-lg text-[11px] font-semibold ' + (presente ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500');
            });

            const atualizado = document.getElementById('dashboard-atualizado');
            if (atualizado) atualizado.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i> Atualizado agora';
        } catch (e) {
            // Falha de rede: manter os dados já apresentados.
        }
    }

    let timer = setInterval(atualizar, 15000);
    document.addEventListener('visibilitychange', () => {
        clearInterval(timer);
        if (!document.hidden) {
            atualizar();
            timer = setInterval(atualizar, 15000);
        }
    });
})();
</script>
