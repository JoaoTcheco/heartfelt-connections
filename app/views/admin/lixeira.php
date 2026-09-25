<?php
/** Lixeira (admin/gestor). Dados: $itens, $contagens, $tabela */
use App\Helpers\Auth;
use App\Helpers\Csrf;
$rotulos = ['registos' => 'Registos de ponto', 'folhas_salariais' => 'Folhas salariais', 'funcionarios' => 'Funcionários'];
$podeApagar = Auth::isAdmin();
?>
<div class="mb-6">
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Lixeira</h1>
    <p class="text-gray-500 mt-1">Tudo o que é eliminado fica aqui guardado 60 dias. Pode repor com um clique.</p>
</div>

<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-4 mb-6 flex flex-wrap items-center gap-2">
    <a href="<?= BASE_PATH ?>/lixeira" class="px-3 py-2 rounded-lg text-sm font-medium <?= $tabela === '' ? 'bg-emerald-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
        Tudo (<?= array_sum($contagens) ?>)
    </a>
    <?php foreach ($rotulos as $t => $r): ?>
        <a href="<?= BASE_PATH ?>/lixeira?tabela=<?= $t ?>" class="px-3 py-2 rounded-lg text-sm font-medium <?= $tabela === $t ? 'bg-emerald-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
            <?= $r ?> (<?= (int) ($contagens[$t] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
</div>

<div class="bg-white border border-gray-200 rounded-xl shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-sm">
            <caption class="sr-only">Itens eliminados, com data, tipo, descrição e acções de reposição</caption>
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">Eliminado em</th>
                    <th scope="col" class="px-4 py-3">Tipo</th>
                    <th scope="col" class="px-4 py-3">Descrição</th>
                    <th scope="col" class="px-4 py-3">Por quem</th>
                    <th scope="col" class="px-4 py-3 text-right">Acções</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (!$itens): ?>
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">A lixeira está vazia.</td></tr>
            <?php endif; ?>
            <?php foreach ($itens as $i):
                $d = $i['dados'];
                $desc = $i['rotulo'] ?: match ($i['tabela']) {
                    'registos' => trim(($d['tipo'] ?? 'registo') . ' de ' . ($d['marcado_em'] ?? '')),
                    'folhas_salariais' => 'Folha ' . ($d['referencia'] ?? '') . ' (' . ($d['periodo_de'] ?? '') . ' a ' . ($d['periodo_ate'] ?? '') . ')',
                    'funcionarios' => (string) ($d['nome'] ?? ''),
                    default => '#' . ($i['registo_id'] ?? '?'),
                };
            ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime((string) $i['eliminado_em'])) ?></td>
                    <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($rotulos[$i['tabela']] ?? $i['tabela']) ?></td>
                    <td class="px-4 py-3 text-gray-900 font-medium"><?= htmlspecialchars($desc) ?>
                        <?php if (!empty($i['motivo'])): ?><span class="block text-xs text-gray-400"><?= htmlspecialchars((string) $i['motivo']) ?></span><?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars((string) ($i['autor'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <button onclick="repor(<?= (int) $i['id'] ?>)" class="inline-flex items-center gap-1 px-3 py-2 border border-emerald-200 text-emerald-700 rounded-lg text-xs hover:bg-emerald-50">
                            <i class="fa-solid fa-rotate-left"></i> Repor
                        </button>
                        <?php if ($podeApagar): ?>
                        <button onclick="apagarSempre(<?= (int) $i['id'] ?>)" class="inline-flex items-center gap-1 px-3 py-2 border border-red-200 text-red-600 rounded-lg text-xs hover:bg-red-50">
                            <i class="fa-solid fa-trash-can"></i> Eliminar para sempre
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function repor(id) {
    const ok = await confirmar({ title: 'Repor item', msg: 'O item volta para o sítio de origem e sai da lixeira.', okText: 'Repor' });
    if (!ok) return;
    const fd = new FormData(); fd.append('csrf', '<?= Csrf::token() ?>'); fd.append('id', id);
    const r = await fetch(BASE_PATH + '/lixeira/recuperar', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) { toast('Item reposto.'); location.reload(); } else { toast(j.erro || 'Não foi possível repor.', 'error'); }
}
async function apagarSempre(id) {
    const texto = prompt('Esta acção não se desfaz. Escreva ELIMINAR para confirmar:');
    if (texto === null) return;
    const fd = new FormData(); fd.append('csrf', '<?= Csrf::token() ?>'); fd.append('id', id); fd.append('confirmacao', texto);
    const r = await fetch(BASE_PATH + '/lixeira/eliminar', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) { toast('Eliminado para sempre.'); location.reload(); } else { toast(j.erro || 'Não foi possível eliminar.', 'error'); }
}
</script>
