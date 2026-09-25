<?php
/**
 * ============================================================
 * FarmaPonto - Minha Assiduidade (visao do proprio funcionario)
 * ============================================================
 * O funcionario ve apenas os seus proprios dias, atrasos, faltas
 * e o valor descontado em cada situacao. Nenhum dado de colegas
 * chega a esta pagina (o controlador restringe o calculo ao id
 * do utilizador em sessao).
 */

$fmt = static fn($v): string => number_format((float) $v, 2, ',', '.');
$hora = static fn(?string $ts): string => $ts ? substr((string) $ts, 11, 5) : '--:--';

$estados = [
    'presente'          => ['Presente', 'bg-emerald-100 text-emerald-700'],
    'falta'             => ['Falta', 'bg-red-100 text-red-700'],
    'falta_justificada' => ['Falta justificada', 'bg-indigo-100 text-indigo-700'],
    'ferias'            => ['Ferias', 'bg-blue-100 text-blue-700'],
    'folga'             => ['Folga', 'bg-purple-100 text-purple-700'],
    'licenca'           => ['Licenca', 'bg-orange-100 text-orange-700'],
    'futuro'            => ['Por decorrer', 'bg-gray-100 text-gray-500'],
    'em_curso'          => ['Em curso', 'bg-amber-100 text-amber-700'],
    'nao_trabalha'      => ['Nao trabalha', 'bg-gray-100 text-gray-500'],

];
$diasSemana = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sab', 7 => 'Dom'];
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Minha Assiduidade</h1>
        <p class="text-gray-500 mt-1"><?= htmlspecialchars($nomeMes) ?> de <?= (int) $ano ?> &middot; os seus dias, atrasos e cortes</p>
    </div>
    <form method="get" action="<?= BASE_PATH ?>/minha-assiduidade" class="flex items-end gap-2 no-print">
        <div>
            <label for="mes" class="block text-xs font-medium text-gray-500 mb-1">Mes</label>
            <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($mesRef) ?>"
                   max="<?= date('Y-m') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        </div>
        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-medium">
            <i class="fa-solid fa-magnifying-glass mr-1"></i> Ver
        </button>
    </form>
</div>

<?php if (!$resumo): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-8 shadow-sm text-center">
        <div class="w-14 h-14 mx-auto bg-gray-100 rounded-full flex items-center justify-center text-gray-400 text-xl mb-3">
            <i class="fa-regular fa-folder-open"></i>
        </div>
        <p class="text-gray-600 text-sm">Ainda nao existe informacao de assiduidade para este mes.</p>
    </div>
<?php else: ?>

<!-- Progresso de assiduidade -->
<div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div>
            <p class="text-sm text-gray-500">Progresso de assiduidade do mes</p>
            <p class="text-2xl font-bold text-gray-900"><?= $fmt($percentual) ?>%</p>
        </div>
        <p class="text-sm text-gray-500">
            <?= (int) $resumo['dias_trabalhados'] ?> de <?= (int) $resumo['dias_avaliados'] ?> dia(s) de trabalho avaliados
        </p>
    </div>
    <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
        <div class="h-3 rounded-full <?= $percentual >= 90 ? 'bg-emerald-500' : ($percentual >= 70 ? 'bg-amber-500' : 'bg-red-500') ?>"
             style="width: <?= max(0, min(100, $percentual)) ?>%"></div>
    </div>
</div>

<!-- Os meus numeros -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php
    $cards = [
        ['Dias trabalhados', (int) $resumo['dias_trabalhados'], 'fa-calendar-check', 'emerald'],
        ['Atrasos', (int) $resumo['atrasos'], 'fa-clock', 'amber'],
        ['Faltas', (int) $resumo['faltas'], 'fa-triangle-exclamation', 'red'],
        ['Saidas antecipadas', (int) $resumo['saidas_cedo'], 'fa-door-open', 'amber'],
    ];
    foreach ($cards as [$rotulo, $valor, $icone, $cor]):
    ?>
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-500 mb-1"><?= $rotulo ?></p>
                <p class="text-3xl font-bold text-gray-900"><?= $valor ?></p>
            </div>
            <div class="w-12 h-12 bg-<?= $cor ?>-50 rounded-full flex items-center justify-center text-<?= $cor ?>-600 text-xl">
                <i class="fa-solid <?= $icone ?>"></i>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- O que foi descontado -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm lg:col-span-2">
        <h2 class="font-semibold text-gray-900 mb-4"><i class="fa-solid fa-scissors text-red-500 mr-2"></i>Cortes do mes</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-6 text-sm">
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <dt class="text-gray-500">Por faltas</dt>
                <dd class="text-red-600 font-medium"><?= $fmt($resumo['corte_falta']) ?> <?= htmlspecialchars($moeda) ?></dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <dt class="text-gray-500">Por atrasos / saidas cedo</dt>
                <dd class="text-red-600 font-medium"><?= $fmt($resumo['corte_atraso']) ?> <?= htmlspecialchars($moeda) ?></dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <dt class="text-gray-500">Dias sem vencimento</dt>
                <dd class="text-red-600 font-medium"><?= $fmt($resumo['corte_sem_vencimento']) ?> <?= htmlspecialchars($moeda) ?></dd>
            </div>
            <div class="flex justify-between border-b border-gray-100 pb-2">
                <dt class="text-gray-500">Horas extra pagas</dt>
                <dd class="text-emerald-700 font-medium">+<?= $fmt($resumo['ganho_extra']) ?> <?= htmlspecialchars($moeda) ?></dd>
            </div>
            <div class="flex justify-between sm:col-span-2 pt-1">
                <dt class="font-semibold text-gray-900">Total descontado</dt>
                <dd class="text-red-600 font-bold text-lg"><?= $fmt($resumo['total_corte']) ?> <?= htmlspecialchars($moeda) ?></dd>
            </div>
        </dl>
        <p class="text-xs text-gray-500 mt-4">
            Valor de referencia por dia: <strong><?= $fmt($resumo['valor_dia']) ?></strong> <?= htmlspecialchars($moeda) ?> &middot;
            por hora: <strong><?= $fmt($resumo['valor_hora']) ?></strong> <?= htmlspecialchars($moeda) ?> &middot;
            tolerancia de atraso: <strong><?= (int) $tolerancia ?> min</strong>.
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <h2 class="font-semibold text-gray-900 mb-4"><i class="fa-solid fa-wallet text-emerald-600 mr-2"></i>Estimativa do mes</h2>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Salario do periodo</span>
                <span class="text-gray-900 font-medium"><?= $fmt($resumo['salario']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Cortes</span>
                <span class="text-red-600 font-medium">-<?= $fmt($resumo['total_corte']) ?></span>
            </div>
            <div class="flex justify-between border-t border-gray-100 pt-3">
                <span class="font-semibold text-gray-900">Liquido estimado</span>
                <span class="text-emerald-700 font-bold text-lg"><?= $fmt($resumo['liquido']) ?></span>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span>Horas trabalhadas</span>
                <span class="font-mono"><?= htmlspecialchars($resumo['horas']) ?></span>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span>Horas de atraso</span>
                <span class="font-mono"><?= htmlspecialchars($resumo['horas_atraso']) ?></span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mt-4">Valores indicativos. A folha salarial fechada pelo gestor e o documento oficial.</p>
    </div>
</div>

<!-- Detalhe dia-a-dia -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-x-auto [scrollbar-gutter:stable]">
    <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900">Dia a dia</h2>
        <p class="text-xs text-gray-500 mt-0.5">Cada linha mostra o que aconteceu e quanto foi descontado nesse dia.</p>
    </div>
    <table data-paginate="20" class="w-full text-sm min-w-[900px]">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr class="text-left text-xs font-medium text-gray-500">
                <th class="px-4 py-3">Dia</th>
                <th class="px-4 py-3">Sem.</th>
                <th class="px-4 py-3">Estado</th>
                <th class="px-4 py-3">Entrada</th>
                <th class="px-4 py-3">Saida</th>
                <th class="px-4 py-3">Horas</th>
                <th class="px-4 py-3">Atraso</th>
                <th class="px-4 py-3">Saida cedo</th>
                <th class="px-4 py-3">Corte (<?= htmlspecialchars($moeda) ?>)</th>
                <th class="px-4 py-3">Observacao</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($detalhe as $d):
                [$rotulo, $cor] = $estados[$d['estado']] ?? ['-', 'bg-gray-100 text-gray-500'];
                $corte = (float) ($d['corte'] ?? 0);
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700"><?= htmlspecialchars(date('d/m/Y', strtotime($d['dia']))) ?></td>
                <td class="px-4 py-2.5 text-xs text-gray-500"><?= $diasSemana[$d['dia_semana']] ?? '' ?></td>
                <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cor ?>"><?= $rotulo ?></span></td>
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700"><?= $hora($d['entrada']) ?></td>
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700"><?= $hora($d['saida']) ?></td>
                <td class="px-4 py-2.5 font-mono text-xs text-gray-700"><?= $fmt($d['horas']) ?>h</td>
                <td class="px-4 py-2.5 font-mono text-xs <?= !empty($d['atrasado']) ? 'text-amber-600' : 'text-gray-400' ?>"><?= $fmt($d['horas_atraso']) ?>h</td>
                <td class="px-4 py-2.5 font-mono text-xs <?= !empty($d['saiu_cedo']) ? 'text-amber-600' : 'text-gray-400' ?>"><?= $fmt($d['horas_cedo']) ?>h</td>
                <td class="px-4 py-2.5 text-xs <?= $corte > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' ?>"><?= $fmt($corte) ?></td>
                <td class="px-4 py-2.5 text-xs text-gray-500"><?= htmlspecialchars((string) ($d['observacao'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$detalhe): ?>
            <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500 text-sm">Sem dias a mostrar neste mes.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
