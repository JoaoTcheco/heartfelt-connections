<?php
/** Ecra de copias de seguranca (admin). Dados: $copias, $saude, $raiz */
use App\Helpers\Backup;
use App\Helpers\Csrf;
$ultima = $copias[0] ?? null;
$totalBytes = array_sum(array_column($copias, 'bytes'));
?>
<div class="mb-6 flex items-start justify-between flex-wrap gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Cópias de Segurança</h1>
        <p class="text-gray-500 mt-1">Proteja os dados: crie cópias, guarde-as fora do computador e saiba como as repor.</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <button onclick="criarCopia(false, this)" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 flex items-center gap-2 min-h-[44px]">
            <i class="fa-solid fa-database"></i> Criar cópia agora
        </button>
        <button onclick="criarCopia(true, this)" class="px-4 py-2 bg-emerald-800 text-white rounded-lg text-sm font-medium hover:bg-emerald-900 flex items-center gap-2 min-h-[44px]">
            <i class="fa-solid fa-file-zipper"></i> Cópia completa (com fotografias)
        </button>
    </div>
</div>

<div role="status" aria-live="polite" id="avisos" class="sr-only"></div>

<!-- Estado -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php
    $cartoes = [
        ['Última cópia', $ultima ? date('d/m/Y H:i', $ultima['em']) : 'Nunca', $ultima ? 'fa-clock' : 'fa-triangle-exclamation', $ultima ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'],
        ['Cópias guardadas', count($copias) . ' ficheiro(s)', 'fa-copy', 'bg-blue-100 text-blue-600'],
        ['Espaço das cópias', Backup::tamanho($totalBytes), 'fa-hard-drive', 'bg-gray-100 text-gray-600'],
        ['Disco livre', number_format($saude['disco_livre_mb'], 0, ',', ' ') . ' MB', 'fa-server', $saude['disco_livre_mb'] < 500 ? 'bg-red-100 text-red-600' : 'bg-emerald-100 text-emerald-600'],
    ];
    foreach ($cartoes as [$rot, $val, $ic, $cor]): ?>
    <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg <?= $cor ?> flex items-center justify-center">
                <i class="fa-solid <?= $ic ?>"></i>
            </div>
            <div>
                <p class="text-xs text-gray-500"><?= $rot ?></p>
                <p class="text-base font-semibold text-gray-900"><?= htmlspecialchars($val) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!$ultima): ?>
<div class="mb-6 border-l-4 border-red-500 bg-red-50 p-4 rounded-r-xl">
    <p class="text-sm text-red-800 font-medium">Ainda não existe nenhuma cópia de segurança.</p>
    <p class="text-sm text-red-700 mt-1">Se o computador falhar agora, todos os registos de ponto e salários são perdidos. Clique em “Criar cópia agora” e guarde o ficheiro numa pen ou noutro computador.</p>
</div>
<?php endif; ?>

<!-- Lista de copias -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">
    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
        <h2 class="font-semibold text-gray-900">Cópias existentes</h2>
        <span class="text-xs text-gray-500">Guardadas em <code class="bg-gray-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($raiz) ?>/storage/backups</code></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <caption class="sr-only">Lista de cópias de segurança com data, tipo, tamanho e acções</caption>
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th scope="col" class="px-4 py-3">Ficheiro</th>
                    <th scope="col" class="px-4 py-3">Data</th>
                    <th scope="col" class="px-4 py-3">Tipo</th>
                    <th scope="col" class="px-4 py-3">Tamanho</th>
                    <th scope="col" class="px-4 py-3 text-right">Acções</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if (!$copias): ?>
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Sem cópias por enquanto.</td></tr>
            <?php endif; ?>
            <?php foreach ($copias as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars($c['ficheiro']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= date('d/m/Y H:i', $c['em']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($c['tipo']) ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= Backup::tamanho($c['bytes']) ?></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="<?= BASE_PATH ?>/backup/download?f=<?= urlencode($c['ficheiro']) ?>"
                           class="inline-flex items-center gap-1 px-3 py-2 border border-gray-300 rounded-lg text-xs hover:bg-gray-50"
                           aria-label="Descarregar <?= htmlspecialchars($c['ficheiro']) ?>">
                            <i class="fa-solid fa-download"></i> Descarregar
                        </a>
                        <button onclick="apagarCopia('<?= htmlspecialchars($c['ficheiro'], ENT_QUOTES) ?>')"
                                class="inline-flex items-center gap-1 px-3 py-2 border border-red-200 text-red-600 rounded-lg text-xs hover:bg-red-50"
                                aria-label="Apagar <?= htmlspecialchars($c['ficheiro']) ?>">
                            <i class="fa-solid fa-trash-can"></i> Apagar
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Restaurar -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Repor dados de uma cópia</h2>
        <p class="text-sm text-gray-500 mb-4">Substitui todos os dados actuais pelos da cópia escolhida. Antes de substituir, o sistema guarda automaticamente uma cópia do estado presente.</p>
        <form id="form-restaurar" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= Csrf::token() ?>">
            <div>
                <label for="copia" class="block text-sm font-medium text-gray-700 mb-1">Cópia já guardada</label>
                <select id="copia" name="copia" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">— escolher —</option>
                    <?php foreach ($copias as $c): ?>
                        <option value="<?= htmlspecialchars($c['ficheiro']) ?>"><?= htmlspecialchars($c['ficheiro']) ?> (<?= Backup::tamanho($c['bytes']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="ficheiro" class="block text-sm font-medium text-gray-700 mb-1">…ou enviar um ficheiro (.sql ou .zip)</label>
                <input id="ficheiro" type="file" name="ficheiro" accept=".sql,.zip"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label for="confirmacao" class="block text-sm font-medium text-gray-700 mb-1">Escreva <strong>RESTAURAR</strong> para confirmar</label>
                <input id="confirmacao" name="confirmacao" type="text" autocomplete="off"
                       aria-describedby="ajuda-restaurar"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm uppercase">
                <p id="ajuda-restaurar" class="text-xs text-gray-500 mt-1">Esta operação não se desfaz sozinha; é por isso que pedimos a palavra.</p>
            </div>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 min-h-[44px]">
                <i class="fa-solid fa-rotate-left mr-1"></i> Repor dados
            </button>
        </form>
    </div>

    <!-- Agendamento -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Cópia automática todos os dias</h2>
        <p class="text-sm text-gray-500 mb-3">O sistema cria uma cópia sozinho quando alguém abre o programa e ainda não existe cópia do dia. Para não depender disso, agende também no computador:</p>
        <p class="text-xs font-medium text-gray-700 mb-1">Linux / macOS (crontab -e)</p>
        <pre class="bg-gray-900 text-gray-100 text-xs rounded-lg p-3 overflow-x-auto mb-3">30 23 * * * php <?= htmlspecialchars($raiz) ?>/cli/backup.php</pre>
        <p class="text-xs font-medium text-gray-700 mb-1">Windows (Agendador de Tarefas)</p>
        <pre class="bg-gray-900 text-gray-100 text-xs rounded-lg p-3 overflow-x-auto mb-3">C:\xampp\php\php.exe <?= htmlspecialchars(str_replace('/', '\\', $raiz)) ?>\cli\backup.php</pre>
        <p class="text-sm text-gray-500">São mantidas as <strong><?= (int) (\App\Core\Database::config()['backup_manter'] ?? 14) ?></strong> cópias mais recentes; as mais antigas são apagadas automaticamente.</p>
    </div>
</div>

<script>
async function criarCopia(completo, btn) {
    const txt = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A criar cópia…';
    try {
        const fd = new FormData();
        fd.append('csrf', '<?= Csrf::token() ?>');
        const r = await fetch(BASE_PATH + (completo ? '/backup/completo' : '/backup/criar'), { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) { toast('Cópia criada: ' + j.ficheiro + ' (' + j.tamanho + ')'); setTimeout(() => location.reload(), 900); }
        else { toast(j.erro || 'Não foi possível criar a cópia.', 'error'); }
    } catch (e) { toast('Sem resposta do servidor. Verifique se o serviço está a correr.', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = txt; }
}

async function apagarCopia(ficheiro) {
    const ok = await confirmar({ title: 'Apagar cópia', msg: 'A cópia ' + ficheiro + ' será removida do disco. Se for a única cópia, fica sem protecção.', okText: 'Apagar', danger: true });
    if (!ok) return;
    const fd = new FormData();
    fd.append('csrf', '<?= Csrf::token() ?>');
    fd.append('ficheiro', ficheiro);
    const r = await fetch(BASE_PATH + '/backup/apagar', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) { toast('Cópia apagada.'); location.reload(); } else { toast(j.erro || 'Não foi possível apagar.', 'error'); }
}

document.getElementById('form-restaurar').addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const btn = this.querySelector('button[type=submit]');
    const ok = await confirmar({
        title: 'Repor dados', danger: true, okText: 'Repor agora',
        msg: 'Todos os dados actuais serão substituídos pelos da cópia. Uma cópia do estado presente é guardada antes.'
    });
    if (!ok) return;
    const txt = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> A repor dados…';
    try {
        const r = await fetch(BASE_PATH + '/backup/restaurar', { method: 'POST', body: new FormData(this) });
        const j = await r.json();
        if (j.ok) {
            toast('Dados repostos (' + j.comandos + ' comandos). Cópia anterior: ' + j.antes);
            setTimeout(() => location.href = BASE_PATH + '/logout', 1800);
        } else { toast(j.erro || 'Não foi possível repor os dados.', 'error'); }
    } catch (e) { toast('Sem resposta do servidor durante a reposição.', 'error'); }
    finally { btn.disabled = false; btn.innerHTML = txt; }
});
</script>
