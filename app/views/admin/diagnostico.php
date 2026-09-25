<?php
/** Diagnostico do sistema (admin). Dados: $saude, $eventos, $contagens, $desempenho, $nivel, $tabelas, $ambiente */
use App\Helpers\Csrf;
$maxMs = max(1, (int) max(array_map(fn($d) => (float) $d['media_ms'], $desempenho ?: [['media_ms' => 1]])));
$estadoCor = ['ok' => 'bg-emerald-100 text-emerald-700', 'atencao' => 'bg-amber-100 text-amber-700', 'falha' => 'bg-red-100 text-red-700'][$saude['estado']] ?? 'bg-gray-100 text-gray-700';
$estadoTxt = ['ok' => 'Tudo em ordem', 'atencao' => 'A precisar de atenção', 'falha' => 'Com falha'][$saude['estado']] ?? '—';
?>
<div class="mb-6 flex items-start justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Diagnóstico do Sistema</h1>
        <p class="text-gray-500 mt-1">Saúde, desempenho, avisos técnicos, estrutura de dados e fluxos de utilização.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <span class="px-3 py-2 rounded-lg text-sm font-medium <?= $estadoCor ?>"><?= $estadoTxt ?></span>
        <a href="<?= BASE_PATH ?>/saude" target="_blank" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 min-h-[44px] inline-flex items-center gap-2">
            <i class="fa-solid fa-heart-pulse"></i> Estado em JSON
        </a>
        <a href="<?= BASE_PATH ?>/backup" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 min-h-[44px] inline-flex items-center gap-2">
            <i class="fa-solid fa-database"></i> Cópias de segurança
        </a>
    </div>
</div>

<?php if (!empty($saude['avisos'])): ?>
<div class="mb-6 border-l-4 border-amber-500 bg-amber-50 p-4 rounded-r-xl" role="status" aria-live="polite">
    <p class="text-sm font-medium text-amber-900 mb-1">O sistema pede atenção a <?= count($saude['avisos']) ?> ponto(s):</p>
    <ul class="list-disc list-inside text-sm text-amber-800 space-y-0.5">
        <?php foreach ($saude['avisos'] as $a): ?><li><?= htmlspecialchars($a) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Indicadores -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php
    $ind = [
        ['Base de dados', ($saude['bd']['ok'] ? 'A responder' : 'Sem resposta'), $saude['bd']['versao'] . ' · ' . $saude['bd']['latencia_ms'] . ' ms', 'fa-database'],
        ['Tamanho dos dados', $saude['bd']['tamanho_mb'] . ' MB', $saude['tabelas'] . ' tabelas', 'fa-table'],
        ['Fotografias', $saude['fotografias_mb'] . ' MB', 'Cópias: ' . $saude['copias_mb'] . ' MB', 'fa-camera'],
        ['Erros (24 h)', (string) ($contagens['erro'] ?? 0), 'Avisos: ' . ($contagens['aviso'] ?? 0), 'fa-triangle-exclamation'],
    ];
    foreach ($ind as [$rot, $val, $sub, $ic]): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-xs text-gray-500"><?= $rot ?></p>
                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars((string) $val) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars((string) $sub) ?></p>
            </div>
            <i class="fa-solid <?= $ic ?> text-gray-300 text-xl" aria-hidden="true"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6">
    <!-- Desempenho por rota -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Tempo de resposta por página (7 dias)</h2>
        <p class="text-xs text-gray-500 mb-4">Média de milissegundos por pedido. Amostra de 1 em cada 20 visitas.</p>
        <?php if (!$desempenho): ?>
            <p class="text-sm text-gray-500">Ainda sem amostras. Navegue pelo sistema e volte a este ecrã.</p>
        <?php else: foreach ($desempenho as $d):
            $pct = max(2, round(((float) $d['media_ms'] / $maxMs) * 100)); ?>
            <div class="mb-3">
                <div class="flex justify-between text-xs text-gray-600 mb-1">
                    <span class="font-medium truncate"><?= htmlspecialchars($d['rota']) ?></span>
                    <span><?= $d['media_ms'] ?> ms · máx <?= $d['max_ms'] ?> ms · <?= $d['pedidos'] ?> pedidos</span>
                </div>
                <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden" role="progressbar"
                     aria-valuenow="<?= (int) $d['media_ms'] ?>" aria-valuemin="0" aria-valuemax="<?= $maxMs ?>"
                     aria-label="Tempo médio de <?= htmlspecialchars($d['rota']) ?>: <?= $d['media_ms'] ?> milissegundos">
                    <div class="h-full bg-emerald-500" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Estrutura de dados -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Estrutura de dados</h2>
        <p class="text-xs text-gray-500 mb-4">Tabelas do banco único, ordenadas pelo espaço que ocupam.</p>
        <div class="overflow-x-auto max-h-80 overflow-y-auto">
            <table class="w-full min-w-[420px] text-sm">
                <caption class="sr-only">Tabelas da base de dados com número de linhas e espaço ocupado</caption>
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 sticky top-0">
                    <tr><th scope="col" class="px-3 py-2">Tabela</th><th scope="col" class="px-3 py-2">Linhas</th><th scope="col" class="px-3 py-2">Espaço</th><th scope="col" class="px-3 py-2">Motor</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php foreach ($tabelas as $t): ?>
                    <tr><td class="px-3 py-2 font-medium text-gray-800"><?= htmlspecialchars($t['tabela']) ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= number_format((int) $t['linhas'], 0, ',', ' ') ?></td>
                        <td class="px-3 py-2 text-gray-600"><?= number_format((int) $t['kb'], 0, ',', ' ') ?> KB</td>
                        <td class="px-3 py-2 text-gray-500"><?= htmlspecialchars((string) $t['motor']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Fluxos do sistema -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
    <h2 class="font-semibold text-gray-900 mb-1">Como a informação circula</h2>
    <p class="text-xs text-gray-500 mb-4">Do momento em que a pessoa marca o ponto até ao recibo de salário.</p>
    <div class="overflow-x-auto">
        <pre class="text-xs leading-5 text-gray-700 bg-gray-50 rounded-lg p-4 min-w-[680px]">
 Funcionário                      Sistema                            Gestor / Admin
 ───────────                      ───────                            ──────────────
 Painel (selfie)  ─┐
 Terminal (PIN)   ─┼─►  registos  ──►  classificação do dia  ──►  Relatórios & Salários
 Impressão digital─┘   (entrada/saída)   (presente, atraso,        (motor de cálculo:
                                          falta, férias)            cortes e extras)
                                              │                          │
 Minha Assiduidade  ◄──────────────────────────┤                          ▼
 (só os seus dados)                            │                  Folhas Salariais
                                               │                   (fecho imutável)
                                               ▼                          │
                              logs + sistema_eventos ◄────────────────────┘
                              (auditoria e diagnóstico)
                                               │
                                               ▼
                                  Cópias de segurança (storage/backups)
        </pre>
    </div>
</div>

<!-- Ambiente -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Ambiente</h2>
        <dl class="text-sm divide-y divide-gray-100">
            <?php foreach ($ambiente as $k => $v): ?>
            <div class="flex justify-between gap-3 py-1.5">
                <dt class="text-gray-500"><?= htmlspecialchars($k) ?></dt>
                <dd class="text-gray-900 font-medium text-right break-all"><?= htmlspecialchars((string) $v) ?></dd>
            </div>
            <?php endforeach; ?>
        </dl>
    </div>

    <!-- Eventos -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 xl:col-span-2">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <h2 class="font-semibold text-gray-900">Eventos técnicos recentes</h2>
            <form method="get" class="flex items-center gap-2">
                <label for="nivel" class="text-xs text-gray-500">Nível</label>
                <select id="nivel" name="nivel" onchange="this.form.submit()" class="px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <option value="">Todos</option>
                    <?php foreach (['erro' => 'Erros', 'aviso' => 'Avisos', 'info' => 'Informação'] as $k => $r): ?>
                        <option value="<?= $k ?>" <?= $nivel === $k ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" onclick="purgarEventos()" class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-sm hover:bg-red-50">Limpar antigos</button>
            </form>
        </div>
        <div class="overflow-x-auto max-h-96 overflow-y-auto">
            <table class="w-full min-w-[720px] text-sm">
                <caption class="sr-only">Eventos técnicos com data, nível, mensagem e referência do pedido</caption>
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 sticky top-0">
                    <tr><th scope="col" class="px-3 py-2">Quando</th><th scope="col" class="px-3 py-2">Nível</th><th scope="col" class="px-3 py-2">Canal</th><th scope="col" class="px-3 py-2">Mensagem</th><th scope="col" class="px-3 py-2">Pedido</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php if (!$eventos): ?>
                    <tr><td colspan="5" class="px-3 py-8 text-center text-gray-500">Sem eventos registados — bom sinal.</td></tr>
                <?php endif; ?>
                <?php
                $cores = ['erro' => 'bg-red-100 text-red-700', 'aviso' => 'bg-amber-100 text-amber-700', 'info' => 'bg-blue-100 text-blue-700'];
                foreach ($eventos as $e): ?>
                    <tr class="hover:bg-gray-50 align-top">
                        <td class="px-3 py-2 text-gray-500 whitespace-nowrap"><?= date('d/m H:i:s', strtotime((string) $e['criado_em'])) ?></td>
                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $cores[$e['nivel']] ?? 'bg-gray-100 text-gray-700' ?>"><?= htmlspecialchars($e['nivel']) ?></span></td>
                        <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars((string) $e['canal']) ?></td>
                        <td class="px-3 py-2 text-gray-800"><?= htmlspecialchars((string) $e['mensagem']) ?>
                            <?php if (!empty($e['url'])): ?><span class="block text-xs text-gray-400"><?= htmlspecialchars((string) $e['metodo']) ?> <?= htmlspecialchars((string) $e['url']) ?></span><?php endif; ?>
                        </td>
                        <td class="px-3 py-2 text-gray-400 font-mono text-xs"><?= htmlspecialchars((string) $e['pedido_id']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function purgarEventos() {
    const ok = await confirmar({ title: 'Limpar eventos antigos', msg: 'São apagados os eventos e medições com mais de 30 dias. O histórico de acções dos utilizadores (Logs) não é tocado.', okText: 'Limpar', danger: true });
    if (!ok) return;
    const fd = new FormData();
    fd.append('csrf', '<?= Csrf::token() ?>');
    fd.append('dias', '30');
    const r = await fetch(BASE_PATH + '/diagnostico/purgar', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) { toast(j.removidos + ' registos removidos.'); location.reload(); } else { toast(j.erro || 'Não foi possível limpar.', 'error'); }
}
</script>
