<?php
/**
 * ============================================================
 * FarmaPonto - Folhas Salariais (historico de fechos)
 * ============================================================
 * Variaveis: $folhas (lista), $folha (opcional, detalhe), $itens (opcional)
 * O fecho e apenas um snapshot: o calculo pode ser refeito a qualquer momento.
 */
$folhas = $folhas ?? [];
$folha  = $folha  ?? null;
$itens  = $itens  ?? [];
$fmt    = static fn($v): string => number_format((float) $v, 2, ',', '.');
$estadoBadge = [
    'fechada'  => ['Fechada', 'bg-emerald-100 text-emerald-700'],
    'reaberta' => ['Reaberta', 'bg-amber-100 text-amber-700'],
];
?>
<div class="mb-6 flex items-center justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Folhas Salariais</h1>
        <p class="text-gray-500 mt-1">Histórico de cálculos fechados. O relatório continua a poder ser recalculado sempre que quiser.</p>
    </div>
    <a href="<?= BASE_PATH ?>/relatorios" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2">
        <i class="fa-solid fa-calculator"></i> Novo cálculo
    </a>
</div>

<?php if ($folha): ?>
<!-- ================== DETALHE DA FOLHA ================== -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-5">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-lg font-bold text-gray-900">Folha #<?= (int) $folha['id'] ?> · Ref. <?= htmlspecialchars($folha['referencia']) ?></h2>
            <p class="text-sm text-gray-500">
                Período <?= date('d/m/Y', strtotime($folha['periodo_de'])) ?> a <?= date('d/m/Y', strtotime($folha['periodo_ate'])) ?>
                · Fechada em <?= date('d/m/Y H:i', strtotime($folha['criado_em'])) ?>
                <?= $folha['autor'] ? ' por ' . htmlspecialchars($folha['autor']) : '' ?>
            </p>
            <?php if (!empty($folha['observacao'])): ?>
                <p class="text-sm text-gray-600 mt-1"><i class="fa-solid fa-note-sticky text-gray-400"></i> <?= htmlspecialchars($folha['observacao']) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="<?= BASE_PATH ?>/relatorios?<?= htmlspecialchars(\App\Models\FolhaSalarial::paraQueryString($folha)) ?>"
               class="px-3 py-2 bg-emerald-100 text-emerald-700 rounded-lg text-sm font-medium hover:bg-emerald-200">
                <i class="fa-solid fa-rotate"></i> Recalcular com estes filtros
            </a>
            <a href="<?= BASE_PATH ?>/folhas" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="overflow-x-auto mt-4">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-3 py-2">Funcionário</th>
                    <th class="text-left px-3 py-2">Cargo</th>
                    <th class="text-right px-3 py-2">Dias pagos</th>
                    <th class="text-right px-3 py-2">Faltas</th>
                    <th class="text-right px-3 py-2">H. extra</th>
                    <th class="text-right px-3 py-2">Salário</th>
                    <th class="text-right px-3 py-2">Cortes</th>
                    <th class="text-right px-3 py-2">Líquido</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($itens as $i): $d = $i['dados']; ?>
                <tr>
                    <td class="px-3 py-2 font-medium text-gray-900"><?= htmlspecialchars($i['nome']) ?></td>
                    <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars($i['cargo'] ?: '—') ?></td>
                    <td class="px-3 py-2 text-right"><?= (int) ($d['dias_pagos'] ?? 0) ?></td>
                    <td class="px-3 py-2 text-right"><?= (int) ($d['faltas'] ?? 0) ?></td>
                    <td class="px-3 py-2 text-right"><?= number_format((float) ($d['horas_extra'] ?? 0), 2, ',', '.') ?></td>
                    <td class="px-3 py-2 text-right"><?= $fmt($i['salario']) ?></td>
                    <td class="px-3 py-2 text-right text-red-600"><?= $fmt($i['total_corte']) ?></td>
                    <td class="px-3 py-2 text-right font-bold text-emerald-700"><?= $fmt($i['liquido']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$itens): ?>
                <tr><td colspan="8" class="px-3 py-6 text-center text-gray-400">Folha sem itens.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ================== LISTA DE FOLHAS ================== -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto [scrollbar-gutter:stable]">
    <table class="w-full text-sm min-w-[1040px]">
        <thead class="bg-gray-50 text-gray-600">
            <tr>
                <th class="text-left px-3 py-2">Ref.</th>
                <th class="text-left px-3 py-2">Período</th>
                <th class="text-left px-3 py-2">Estado</th>
                <th class="text-right px-3 py-2">Func.</th>
                <th class="text-right px-3 py-2">Cortes</th>
                <th class="text-right px-3 py-2">Líquido</th>
                <th class="text-left px-3 py-2">Fechada em</th>
                <th class="text-right px-3 py-2">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
        <?php foreach ($folhas as $f): [$lbl, $cls] = $estadoBadge[$f['estado']] ?? [$f['estado'], 'bg-gray-100 text-gray-600']; ?>
            <tr>
                <td class="px-3 py-2 font-mono text-xs"><?= htmlspecialchars($f['referencia']) ?></td>
                <td class="px-3 py-2"><?= date('d/m/Y', strtotime($f['periodo_de'])) ?> – <?= date('d/m/Y', strtotime($f['periodo_ate'])) ?></td>
                <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= $lbl ?></span></td>
                <td class="px-3 py-2 text-right"><?= (int) ($f['n_itens'] ?? 0) ?></td>
                <td class="px-3 py-2 text-right text-red-600"><?= $fmt($f['totais']['total_corte'] ?? 0) ?></td>
                <td class="px-3 py-2 text-right font-bold text-emerald-700"><?= $fmt($f['totais']['liquido'] ?? 0) ?></td>
                <td class="px-3 py-2 text-gray-500"><?= date('d/m/Y H:i', strtotime($f['criado_em'])) ?></td>
                <td class="px-3 py-2 text-right whitespace-nowrap">
                    <a href="<?= BASE_PATH ?>/folhas/<?= (int) $f['id'] ?>" class="px-2 py-1 text-emerald-700 hover:bg-emerald-50 rounded" title="Ver detalhe"><i class="fa-solid fa-eye"></i></a>
                    <a href="<?= BASE_PATH ?>/relatorios?<?= htmlspecialchars(\App\Models\FolhaSalarial::paraQueryString($f)) ?>" class="px-2 py-1 text-gray-600 hover:bg-gray-100 rounded" title="Recalcular"><i class="fa-solid fa-rotate"></i></a>
                    <?php if ($f['estado'] === 'fechada'): ?>
                    <button type="button" onclick="acaoFolha(<?= (int) $f['id'] ?>, 'reabrir')" class="px-2 py-1 text-amber-600 hover:bg-amber-50 rounded" title="Reabrir"><i class="fa-solid fa-lock-open"></i></button>
                    <?php endif; ?>
                    <button type="button" onclick="acaoFolha(<?= (int) $f['id'] ?>, 'eliminar')" class="px-2 py-1 text-red-600 hover:bg-red-50 rounded" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$folhas): ?>
            <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400">
                Ainda não fechou nenhuma folha. Faça o cálculo em <a class="text-emerald-700 underline" href="<?= BASE_PATH ?>/relatorios">Relatórios &amp; Salários</a> e clique em “Fechar folha”.
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<input type="hidden" id="csrf-folha" value="<?= \App\Helpers\Csrf::token() ?>">
<script>
function acaoFolha(id, accao) {
    const msg = accao === 'eliminar'
        ? 'Eliminar definitivamente esta folha?'
        : 'Reabrir esta folha? Poderá fechar uma nova para o mesmo período.';
    if (!confirm(msg)) return;
    const fd = new FormData();
    fd.append('csrf', document.getElementById('csrf-folha').value);
    fd.append('id', id);
    fetch(BASE_PATH + '/folhas/' + accao, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) { window.toast && window.toast(j.erro || 'Erro.', 'error'); return; }
            window.location.href = BASE_PATH + '/folhas';
        })
        .catch(() => window.toast && window.toast('Erro de comunicação.', 'error'));
}
</script>
