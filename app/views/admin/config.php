<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Configuracoes</h1>
    <p class="text-gray-500 mt-1">Parametros do sistema e regras de corte.</p>
</div>

<!-- Logotipo / identidade visual -->
<div class="bg-white border border-emerald-200 rounded-xl shadow-sm p-6 max-w-2xl mb-6">
    <h2 class="text-lg font-semibold text-emerald-700 flex items-center gap-2 mb-4">
        <i class="fa-solid fa-image"></i> Logotipo &amp; Identidade
    </h2>
    <?php $logoAtual = $configs['instituicao_logo'] ?? ''; ?>
    <div class="flex items-center gap-6 flex-wrap">
        <div class="w-24 h-24 rounded-xl border-2 border-dashed border-emerald-200 bg-emerald-50 flex items-center justify-center overflow-hidden">
            <?php if ($logoAtual && is_file(__DIR__ . '/../../../' . $logoAtual)): ?>
                <img id="logo-preview" src="<?= BASE_PATH ?>/logo?v=<?= @filemtime(__DIR__ . '/../../../' . $logoAtual) ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <i id="logo-preview-empty" class="fa-solid fa-image text-emerald-300 text-3xl"></i>
                <img id="logo-preview" class="w-full h-full object-cover hidden">
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-[250px]">
            <form id="form-logo" enctype="multipart/form-data" class="space-y-3">
                <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
                <label class="block">
                    <span class="block text-sm font-medium text-gray-700 mb-1">Carregar logo (PNG, JPG, WEBP ou SVG — máx. 2 MB)</span>
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required
                        class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-emerald-600 file:text-white file:font-medium hover:file:bg-emerald-700">
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700"><i class="fa-solid fa-upload"></i> Enviar</button>
                    <?php if ($logoAtual): ?>
                    <button type="button" onclick="removerLogo()" class="px-4 py-2 border border-red-300 text-red-600 rounded-lg text-sm hover:bg-red-50"><i class="fa-solid fa-trash"></i> Remover</button>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500">O logo aparece no menu lateral, no quickpunch e no cabeçalho dos PDFs.</p>
            </form>
        </div>
    </div>
    <form id="form-inst" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 pt-6 border-t border-gray-100">
        <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Instituição (nos PDFs)</label>
            <input type="text" name="instituicao" value="<?= htmlspecialchars($configs['instituicao'] ?? 'FarmaPonto Lda') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nota de rodapé (PDFs)</label>
            <input type="text" name="relatorio_nota_rodape" value="<?= htmlspecialchars($configs['relatorio_nota_rodape'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        <div class="md:col-span-2">
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700">Guardar identidade</button>
        </div>
    </form>
</div>

<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 max-w-2xl">
    <form id="form-config" class="space-y-5">
        <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
        
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome da empresa</label>
                <input type="text" name="nome_empresa" value="<?= htmlspecialchars($configs['nome_empresa'] ?? 'FarmaPonto') ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Moeda</label>
                <input type="text" name="moeda" value="<?= htmlspecialchars($configs['moeda'] ?? 'MZN') ?>" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tolerancia de atraso (min)</label>
            <input type="number" name="tolerancia_atraso_min" value="<?= htmlspecialchars($configs['tolerancia_atraso_min'] ?? '5') ?>" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
        </div>
        
        <div class="pt-4 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800 mb-3"><i class="fa-solid fa-shield-halved text-emerald-600"></i> Segurança &amp; privacidade</h3>

            <label class="flex items-start gap-3 mb-4">
                <input type="hidden" name="bloqueio_tentativas" value="0">
                <input type="checkbox" name="bloqueio_tentativas" value="1" class="mt-1"
                    <?= (($configs['bloqueio_tentativas'] ?? '0') === '1') ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-medium text-gray-700">Bloquear o computador após várias tentativas falhadas</span>
                    <span class="block text-xs text-gray-500">Desligado por omissão: em balcões partilhados o erro de um funcionário não pode impedir os restantes de entrar ou marcar ponto.</span>
                </span>
            </label>

            <label class="block text-sm font-medium text-gray-700 mb-1">Apagar automaticamente selfies antigas</label>
            <div class="flex flex-wrap items-center gap-2">
                <?php $ret = (string) ($configs['selfies_retencao_meses'] ?? '0'); ?>
                <select name="selfies_retencao_meses" id="cfg-retencao"
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                    <?php foreach ([
                        '0'  => 'Nunca apagar',
                        '1'  => 'Mais de 1 mês',
                        '2'  => 'Mais de 2 meses',
                        '3'  => 'Mais de 3 meses',
                        '6'  => 'Mais de 6 meses',
                        '12' => 'Mais de 12 meses',
                        '24' => 'Mais de 24 meses',
                    ] as $v => $rot): ?>
                        <option value="<?= $v ?>" <?= $ret === (string) $v ? 'selected' : '' ?>><?= $rot ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="btn-limpar-selfies"
                    class="px-4 py-2 border border-emerald-300 text-emerald-700 rounded-lg text-sm hover:bg-emerald-50">
                    <i class="fa-solid fa-broom"></i> Limpar agora
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                A limpeza corre sozinha uma vez por dia. Os registos de ponto mantêm-se — apenas a fotografia é apagada.
                <?php if (!empty($configs['selfies_purga_ultima'])): ?>
                    Última limpeza: <?= htmlspecialchars($configs['selfies_purga_ultima']) ?>.
                <?php endif; ?>
            </p>

            <div class="mt-6 pt-6 border-t border-gray-100">
                <label for="cfg-logs-retencao" class="block text-sm font-medium text-gray-700 mb-1">
                    Guardar os registos de actividade durante
                </label>
                <?php $retLogs = (string) ($configs['logs_retencao_dias'] ?? '365'); ?>
                <select name="logs_retencao_dias" id="cfg-logs-retencao"
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                    <?php foreach ([
                        '90'  => '3 meses',
                        '180' => '6 meses',
                        '365' => '1 ano (recomendado)',
                        '730' => '2 anos',
                        '0'   => 'Guardar para sempre',
                    ] as $v => $rot): ?>
                        <option value="<?= $v ?>" <?= $retLogs === (string) $v ? 'selected' : '' ?>><?= $rot ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Passado este tempo, o histórico detalhado de actividade é substituído por um resumo
                    (quantas acções de cada tipo, por dia e por pessoa). Nada de importante se perde e a
                    base de dados mantém-se rápida. As marcações de ponto e as folhas nunca são apagadas.
                </p>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700">Guardar</button>
        </div>
    </form>
</div>

<div class="bg-white border border-amber-200 rounded-xl shadow-sm p-6 max-w-2xl mt-6">
    <h2 class="text-lg font-semibold text-amber-700 flex items-center gap-2 mb-4">
        <i class="fa-solid fa-umbrella-beach"></i> Férias
    </h2>
    <form id="form-ferias-cfg" class="space-y-5">
        <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
        <input type="hidden" name="nome_empresa" value="<?= htmlspecialchars($configs['nome_empresa'] ?? 'FarmaPonto') ?>">
        <input type="hidden" name="moeda" value="<?= htmlspecialchars($configs['moeda'] ?? 'MZN') ?>">
        <input type="hidden" name="tolerancia_atraso_min" value="<?= htmlspecialchars($configs['tolerancia_atraso_min'] ?? '5') ?>">

        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Máximo de dias/ano por funcionário</label>
                <input type="number" min="0" max="365" name="ferias_max_dias_ano"
                    value="<?= htmlspecialchars($configs['ferias_max_dias_ano'] ?? '22') ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">0 = sem limite</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Aviso prévio mínimo (dias)</label>
                <input type="number" min="0" max="365" name="ferias_min_aviso_dias"
                    value="<?= htmlspecialchars($configs['ferias_min_aviso_dias'] ?? '0') ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none">
                <p class="text-xs text-gray-400 mt-1">0 = pode lançar para hoje</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Permitir datas no passado</label>
                <select name="ferias_permitir_passado"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 outline-none">
                    <option value="1" <?= ($configs['ferias_permitir_passado'] ?? '1') === '1' ? 'selected' : '' ?>>Sim (regularizações)</option>
                    <option value="0" <?= ($configs['ferias_permitir_passado'] ?? '1') === '0' ? 'selected' : '' ?>>Não</option>
                </select>
            </div>
        </div>
        <div class="pt-2">
            <button type="submit" class="px-6 py-2 bg-amber-500 text-white rounded-lg font-medium hover:bg-amber-600">Guardar Férias</button>
        </div>
    </form>
</div>

<script>
document.getElementById('form-config').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(BASE_PATH + '/configuracoes/salvar', { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) showToast('Configuracoes guardadas!');
    else alert(r.erro || 'Erro');
});

document.getElementById('form-ferias-cfg').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(BASE_PATH + '/configuracoes/salvar', { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) showToast('Configurações de férias guardadas!');
    else alert(r.erro || 'Erro');
});

// ===== Limpeza manual de selfies =====
document.getElementById('btn-limpar-selfies')?.addEventListener('click', async () => {
    const meses = document.getElementById('cfg-retencao').value;
    if (meses === '0') { showToast('Escolha primeiro um período de retenção.', 'error'); return; }
    if (!(await confirmar({ title:'Limpar selfies', msg:'Apagar todas as selfies com mais de ' + meses + ' mês(es)? Os registos de ponto mantêm-se.', danger:true, okText:'Apagar' }))) return;
    const fd = new FormData();
    fd.append('csrf', document.querySelector('#form-config input[name=csrf]').value);
    fd.append('meses', meses);
    const r = await fetch(BASE_PATH + '/configuracoes/limpar-selfies', { method:'POST', body: fd }).then(r=>r.json());
    if (r.ok) showToast(r.removidas + ' selfie(s) apagada(s).');
    else showToast(r.erro || 'Erro', 'error');
});

// ===== Logo upload =====
document.getElementById('form-logo')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(BASE_PATH + '/configuracoes/logo', { method: 'POST', body: fd }).then(r => r.json());
    if (r.ok) {
        showToast('Logo actualizado!');
        setTimeout(()=>location.reload(), 700);
    } else {
        showToast(r.erro || 'Erro ao enviar logo', 'error');
    }
});
async function removerLogo() {
    if (!(await confirmar({ title:'Remover logo', msg:'Tem a certeza que quer remover o logotipo?', danger:true, okText:'Remover' }))) return;
    const fd = new FormData();
    fd.append('csrf', document.querySelector('#form-logo input[name=csrf]').value);
    const r = await fetch(BASE_PATH + '/configuracoes/logo/remover', { method:'POST', body: fd }).then(r=>r.json());
    if (r.ok) { showToast('Logo removido.'); setTimeout(()=>location.reload(), 500); }
    else showToast(r.erro || 'Erro', 'error');
}
document.getElementById('form-inst')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    // backfill outros campos para não os apagar
    ['nome_empresa','moeda','tolerancia_atraso_min'].forEach(k=>{
        if (!fd.has(k)) {
            const el = document.querySelector(`#form-config [name=${k}]`);
            if (el) fd.append(k, el.value);
        }
    });
    const r = await fetch(BASE_PATH + '/configuracoes/salvar', { method:'POST', body: fd }).then(r=>r.json());
    if (r.ok) showToast('Identidade guardada!'); else showToast(r.erro||'Erro','error');
});
</script>