<?php
/**
 * Pagina dedicada de criacao de funcionario (substitui o antigo modal).
 */
$moeda = $moeda ?? 'MZN';
?>
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <nav class="text-sm text-gray-500 mb-1">
            <a href="<?= BASE_PATH ?>/funcionarios" class="hover:text-emerald-700">Funcionarios</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700">Novo</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-900">Novo funcionario</h1>
        <p class="text-gray-500 mt-1">Preencha os dados do colaborador. Os valores de salario dia/hora/minuto sao calculados automaticamente.</p>
    </div>
    <a href="<?= BASE_PATH ?>/funcionarios" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-2">
        <i class="fa-solid fa-arrow-left"></i> Voltar a lista
    </a>
</div>

<form id="form-novo" class="space-y-6 pb-4">
    <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">

    <!-- Identificacao -->
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-solid fa-id-card text-emerald-600"></i> Identificacao</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo *</label>
                <input type="text" name="nome" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email (login) *</label>
                <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                <input type="text" name="cargo" placeholder="Ex.: Balconista" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Codigo interno *</label>
                <input type="text" name="codigo" id="novo-codigo" required placeholder="FUNC001" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <p class="text-xs text-gray-500 mt-1">Sugerido automaticamente; pode alterar.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Perfil de acesso</label>
                <select name="perfil" id="novo-perfil" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"
                        onchange="sugerirMarcaPonto(this.value)">
                    <option value="funcionario">Funcionario</option>
                    <option value="gestor">Gestor</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Marca ponto</label>
                <select name="marca_ponto" id="novo-marca-ponto" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                    <option value="1">Sim — entra na assiduidade</option>
                    <option value="0">Nao — utilizador administrativo</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Quem nao marca ponto fica fora de faltas, atrasos, relatorios e folhas salariais.</p>
            </div>
        </div>
    </section>

    <!-- Horario -->
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-regular fa-clock text-emerald-600"></i> Horario</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Carga diaria (h)</label>
                <input type="number" name="carga_diaria" id="novo-carga" value="8" step="0.5" min="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hora de entrada</label>
                <input type="text" name="hora_entrada" id="novo-hora-entrada" value="08:00" pattern="\d{2}:\d{2}" placeholder="HH:MM" inputmode="numeric" maxlength="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hora de saida</label>
                <input type="text" name="hora_saida" id="novo-hora-saida" value="17:00" pattern="\d{2}:\d{2}" placeholder="HH:MM" inputmode="numeric" maxlength="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">A hora de saida acompanha a carga diaria automaticamente.</p>
    </section>

    <!-- Dias de trabalho -->
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-regular fa-calendar-days text-emerald-600"></i> Dias de trabalho</h2>
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <input type="month" id="novo-cal-mes" class="px-2 py-1 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
            <button type="button" onclick="calendarioSelecionarTodos('novo')" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Todos</button>
            <button type="button" onclick="calendarioDiasUteis('novo')" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Seg a Sex</button>
            <button type="button" onclick="calendarioLimparTodos('novo')" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Limpar</button>
            <span class="text-xs text-gray-500 ml-auto"><span id="novo-dias-count">0</span> dias selecionados</span>
        </div>
        <div id="novo-cal-grid" class="cal-grid"></div>
        <input type="hidden" name="dias_trabalho_mes" id="novo-dias-mes" value="">
        <input type="hidden" name="dias_trabalho_dias" id="novo-dias-dias" value="">
        <p class="text-xs text-gray-500 mt-2">Estes dias definem o valor-dia usado nos calculos de salario e nos cortes por falta.</p>
    </section>

    <!-- Remuneracao -->
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-solid fa-money-bill-wave text-emerald-600"></i> Remuneracao</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Salario base (<?= htmlspecialchars($moeda) ?>)</label>
                <input type="number" name="salario_base" id="novo-salario" value="18000" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor hora extra (<?= htmlspecialchars($moeda) ?>)</label>
                <input type="number" name="valor_hora_extra" id="novo-hora-extra" value="0" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                <p class="text-xs text-gray-500 mt-1">0 = usa o valor/hora normal do funcionario.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Salario dia</label>
                <input type="number" name="salario_diario" id="novo-salario-dia" readonly step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Salario hora</label>
                <input type="number" name="salario_hora" id="novo-salario-hora" readonly step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Salario minuto</label>
                <input type="number" name="salario_minuto" id="novo-salario-minuto" readonly step="0.0001" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-600">
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2" id="novo-formula"></p>
    </section>

    <!-- Acesso -->
    <section class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
        <h2 class="font-semibold text-gray-900 mb-4 flex items-center gap-2"><i class="fa-solid fa-lock text-emerald-600"></i> Acesso</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password (minimo 6 caracteres) *</label>
                <input type="password" name="password" required minlength="6" autocomplete="new-password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PIN do terminal (4 digitos) *</label>
                <input type="text" name="pin" id="novo-pin" maxlength="4" inputmode="numeric" pattern="\d{4}" required autocomplete="off" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none font-mono tracking-widest">
            </div>
        </div>
    </section>

    <div class="flex flex-wrap items-center justify-end gap-3">
        <a href="<?= BASE_PATH ?>/funcionarios" class="px-5 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</a>
        <button type="submit" id="btn-guardar" class="px-6 py-2.5 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 disabled:opacity-50">
            <i class="fa-solid fa-check mr-1"></i> Criar funcionario
        </button>
    </div>
</form>

<style>
.cal-grid { max-width: 380px; }
.cal-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; margin-bottom: 2px; }
.cal-header-cell { text-align: center; font-size: 11px; font-weight: 600; color: #6b7280; padding: 4px 0; }
.cal-body { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.cal-cell { text-align: center; padding: 8px 0; font-size: 13px; border-radius: 6px; cursor: pointer; color: #374151; transition: all .15s; }
.cal-cell:hover { background: #d1fae5; }
.cal-cell.cal-selected { background: #059669; color: #fff; font-weight: 600; }
.cal-cell.cal-selected:hover { background: #047857; }
.cal-cell.cal-today { border: 1px solid #d1d5db; }
.cal-cell.cal-empty { cursor: default; }
.cal-cell.cal-empty:hover { background: transparent; }
</style>

<script>
const MOEDA = <?= json_encode($moeda) ?>;
const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'];

/* ===== Calendario de dias de trabalho ===== */
function calendarioRender(p, ano, mes, dias) {
    const grid = document.getElementById(p + '-cal-grid');
    if (!grid) return;
    const set = new Set(dias.map(Number));
    const primeiro = new Date(ano, mes - 1, 1);
    const diasNoMes = new Date(ano, mes, 0).getDate();
    const inicio = primeiro.getDay();

    let html = '<div class="cal-header">';
    for (let i = 0; i < 7; i++) html += '<div class="cal-header-cell">' + DIAS_SEMANA[i] + '</div>';
    html += '</div><div class="cal-body">';
    for (let i = 0; i < inicio; i++) html += '<div class="cal-cell cal-empty"></div>';
    const hoje = new Date();
    for (let d = 1; d <= diasNoMes; d++) {
        const sel = set.has(d) ? ' cal-selected' : '';
        const isHoje = (ano === hoje.getFullYear() && mes === hoje.getMonth() + 1 && d === hoje.getDate());
        html += `<div class="cal-cell${sel}${isHoje ? ' cal-today' : ''}" data-dia="${d}" onclick="calendarioToggle('${p}', ${d})">${d}</div>`;
    }
    html += '</div>';
    grid.innerHTML = html;

    document.getElementById(p + '-dias-count').textContent = dias.length;
    document.getElementById(p + '-dias-dias').value = JSON.stringify(dias);
    document.getElementById(p + '-dias-mes').value = ano + '-' + String(mes).padStart(2, '0');
    recalcularSalario(p);
}

function mesAtual(p) {
    const v = document.getElementById(p + '-cal-mes').value || '';
    const [ano, mes] = v.split('-').map(Number);
    return [ano, mes];
}
function diasSelecionados(p) {
    try { return JSON.parse(document.getElementById(p + '-dias-dias').value || '[]'); }
    catch (e) { return []; }
}
function calendarioToggle(p, dia) {
    const dias = diasSelecionados(p);
    const i = dias.indexOf(dia);
    if (i >= 0) dias.splice(i, 1); else dias.push(dia);
    dias.sort((a, b) => a - b);
    const [ano, mes] = mesAtual(p);
    calendarioRender(p, ano, mes, dias);
}
function calendarioSelecionarTodos(p) {
    const [ano, mes] = mesAtual(p);
    if (!ano || !mes) return;
    const n = new Date(ano, mes, 0).getDate();
    calendarioRender(p, ano, mes, Array.from({ length: n }, (_, i) => i + 1));
}
function calendarioLimparTodos(p) {
    const [ano, mes] = mesAtual(p);
    if (!ano || !mes) return;
    calendarioRender(p, ano, mes, []);
}
function calendarioDiasUteis(p) {
    const [ano, mes] = mesAtual(p);
    if (!ano || !mes) return;
    calendarioRender(p, ano, mes, calendarioDefaultDias(ano, mes));
}
function calendarioDefaultDias(ano, mes) {
    const dias = [];
    const n = new Date(ano, mes, 0).getDate();
    for (let d = 1; d <= n; d++) {
        const ds = new Date(ano, mes - 1, d).getDay();
        if (ds >= 1 && ds <= 5) dias.push(d);
    }
    return dias;
}

/* ===== Calculo dinamico do salario (divisor = dias realmente selecionados) ===== */
function recalcularSalario(p) {
    const base = parseFloat(document.getElementById(p + '-salario').value) || 0;
    const carga = Math.max(1, parseFloat(document.getElementById(p + '-carga').value) || 8);
    const dias = diasSelecionados(p).length || 30;
    const salarioDia = base / dias;
    const salarioHora = salarioDia / carga;
    document.getElementById(p + '-salario-dia').value = salarioDia.toFixed(2);
    document.getElementById(p + '-salario-hora').value = salarioHora.toFixed(2);
    document.getElementById(p + '-salario-minuto').value = (salarioHora / 60).toFixed(4);
    document.getElementById(p + '-formula').textContent =
        `Valor dia = ${base.toFixed(2)} ${MOEDA} ÷ ${dias} dias de trabalho · Valor hora = valor dia ÷ ${carga}h.`;
}

function recalcularHorario(p) {
    const h2d = t => { if (!/^\d{1,2}:\d{2}$/.test(t || '')) return null; const [h, m] = t.split(':'); return parseInt(h) + parseInt(m) / 60; };
    const d2h = d => { const h = Math.floor(d), m = Math.round((d - h) * 60); return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0'); };
    const he = h2d(document.getElementById(p + '-hora-entrada').value);
    const carga = parseFloat(document.getElementById(p + '-carga').value);
    if (he != null && carga > 0 && he + carga < 24) {
        document.getElementById(p + '-hora-saida').value = d2h(he + carga);
    }
}

function formatarHoraInput(el) {
    if (!el) return;
    el.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 4);
        if (v.length >= 3) v = v.slice(0, 2) + ':' + v.slice(2);
        this.value = v;
    });
}
['novo-hora-entrada', 'novo-hora-saida'].forEach(id => formatarHoraInput(document.getElementById(id)));
document.getElementById('novo-pin').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});

['novo-salario', 'novo-carga'].forEach(id => {
    const el = document.getElementById(id);
    el && el.addEventListener('input', () => recalcularSalario('novo'));
});
['novo-hora-entrada', 'novo-carga'].forEach(id => {
    const el = document.getElementById(id);
    el && el.addEventListener('input', () => recalcularHorario('novo'));
});
document.getElementById('novo-cal-mes').addEventListener('change', () => {
    const [ano, mes] = mesAtual('novo');
    if (ano && mes) calendarioRender('novo', ano, mes, calendarioDefaultDias(ano, mes));
});

/* ===== Codigo sugerido (dinamico, com base nos existentes) ===== */
(function sugerirCodigo() {
    const el = document.getElementById('novo-codigo');
    if (!el || el.value) return;
    el.value = <?= json_encode($codigoSugerido ?? '') ?> || '';
})();

/* ===== Submissao ===== */
document.getElementById('form-novo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-guardar');
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A criar...';
    try {
        const fd = new FormData(e.target);
        const r = await fetch(BASE_PATH + '/funcionarios/criar', { method: 'POST', body: fd }).then(res => res.json());
        if (r.ok) {
            showToast('Funcionario criado com sucesso!');
            setTimeout(() => { window.location.href = BASE_PATH + '/funcionario/' + r.id; }, 700);
        } else {
            showToast(r.erro || 'Erro ao criar funcionario', 'error');
            btn.disabled = false; btn.innerHTML = original;
        }
    } catch (err) {
        showToast('Erro de comunicacao com o servidor.', 'error');
        btn.disabled = false; btn.innerHTML = original;
    }
});

/* Inicializacao */
(function init() {
    const hoje = new Date();
    document.getElementById('novo-cal-mes').value = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0');
    calendarioRender('novo', hoje.getFullYear(), hoje.getMonth() + 1, calendarioDefaultDias(hoje.getFullYear(), hoje.getMonth() + 1));
    recalcularHorario('novo');
})();
</script>

<script>
// Sugere o valor de "Marca ponto" conforme o perfil escolhido (o admin pode alterar).
function sugerirMarcaPonto(perfil) {
    const sel = document.getElementById('novo-marca-ponto');
    if (sel) { sel.value = perfil === 'admin' ? '0' : '1'; }
}
</script>
