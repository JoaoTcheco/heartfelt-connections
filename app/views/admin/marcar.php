<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900">Marcar Presenca</h1>
    <p class="text-gray-500 mt-1">
        Proxima marcacao: <strong class="text-emerald-700 capitalize" id="proximo-label"><?= htmlspecialchars($proximo) ?></strong>
        &middot; <span class="font-mono text-gray-700" id="relogio">--:--:--</span>
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 lg:col-span-2">
    <!-- Camera (opcional) -->
    <div class="mb-4">
        <div id="cam-wrap">
            <video id="cam" autoplay playsinline muted class="w-full max-w-md rounded-xl bg-gray-900 aspect-square object-cover"></video>
        </div>
        <div id="cam-aviso" class="hidden w-full max-w-md rounded-xl border border-amber-200 bg-amber-50 text-amber-800 p-4 text-sm">
            <p class="font-medium flex items-center gap-2"><i class="fa-solid fa-circle-info"></i> <span id="cam-aviso-titulo">Sem camera disponivel</span></p>
            <p class="mt-1 text-amber-700" id="cam-aviso-texto">
                Pode marcar o ponto normalmente — a fotografia e opcional e este registo ficara sem foto.
            </p>
        </div>
        <canvas id="snap" class="hidden"></canvas>
    </div>

    <div class="flex flex-wrap gap-3 mb-4">
        <button type="button" onclick="iniciarCamera(true)" id="btn-camera" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-camera mr-1"></i> Ativar camera
        </button>
        <span id="cam-estado" class="text-sm text-gray-500 self-center"></span>
    </div>

    <textarea id="observacao" placeholder="Observacao (opcional)"
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none resize-y mb-4 text-sm"></textarea>

    <!-- Botoes -->
    <div class="grid grid-cols-2 gap-3">
        <button type="button" onclick="marcar('entrada')" data-marcar class="py-4 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm rounded-xl font-medium flex flex-col items-center gap-1 transition-colors disabled:opacity-50">
            <i class="fa-solid fa-arrow-right-to-bracket text-lg"></i>
            <span class="text-sm">Entrada</span>
        </button>
        <button type="button" onclick="marcar('saida')" data-marcar class="py-4 bg-red-600 hover:bg-red-700 text-white shadow-sm rounded-xl font-medium flex flex-col items-center gap-1 transition-colors disabled:opacity-50">
            <i class="fa-solid fa-arrow-right-from-bracket text-lg"></i>
            <span class="text-sm">Saida</span>
        </button>
    </div>
</div>

<!-- Marcacoes de hoje (actualiza sozinho) -->
<div class="bg-white border border-gray-200 rounded-xl shadow-sm">
    <div class="p-5 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Hoje</h3>
        <span class="text-xs text-gray-400" id="hoje-updated"></span>
    </div>
    <div class="p-5" id="lista-hoje">
        <p class="text-gray-400 text-sm">A carregar...</p>
    </div>
</div>
</div>

<input type="hidden" id="csrf" value="<?= \App\Helpers\Csrf::token() ?>">

<script>
let stream = null, tipoAtual = null, cameraOk = false;
const csrf = document.getElementById('csrf').value;

/* ===== Relogio ===== */
setInterval(() => {
    const d = new Date();
    const p = n => String(n).padStart(2, '0');
    document.getElementById('relogio').textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}, 1000);

/* ===== Camera opcional =====
   A camera NUNCA bloqueia a marcacao. Se o computador nao tiver camera,
   se o utilizador recusar, ou se o navegador nao suportar, o ponto e
   marcado na mesma (apenas sem fotografia). */
function semCamera(titulo, texto) {
    cameraOk = false;
    document.getElementById('cam-wrap').classList.add('hidden');
    const aviso = document.getElementById('cam-aviso');
    aviso.classList.remove('hidden');
    document.getElementById('cam-aviso-titulo').textContent = titulo;
    if (texto) document.getElementById('cam-aviso-texto').textContent = texto;
    document.getElementById('cam-estado').textContent = '';
}

function comCamera() {
    cameraOk = true;
    document.getElementById('cam-wrap').classList.remove('hidden');
    document.getElementById('cam-aviso').classList.add('hidden');
    document.getElementById('cam-estado').textContent = 'Camera ativa — a fotografia sera anexada ao registo.';
    document.getElementById('btn-camera').classList.add('hidden');
}

async function iniciarCamera(manual) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        semCamera('Este dispositivo/navegador nao suporta camera',
                  'Pode marcar o ponto normalmente — a fotografia e opcional.');
        return;
    }
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
        document.getElementById('cam').srcObject = stream;
        comCamera();
    } catch (e) {
        const nome = e && e.name ? e.name : '';
        if (nome === 'NotAllowedError' || nome === 'SecurityError') {
            semCamera('Acesso a camera recusado',
                      'Pode marcar o ponto na mesma — o registo fica sem fotografia. Para anexar foto, autorize a camera e clique em "Ativar camera".');
        } else if (nome === 'NotFoundError' || nome === 'DevicesNotFoundError' || nome === 'OverconstrainedError') {
            semCamera('Sem camera disponivel neste computador',
                      'Pode marcar o ponto normalmente — a fotografia e opcional e este registo ficara sem foto.');
        } else {
            semCamera('Nao foi possivel ligar a camera',
                      'Pode marcar o ponto normalmente — a fotografia e opcional.');
        }
        if (manual) document.getElementById('btn-camera').classList.remove('hidden');
    }
}

function capturarSelfie() {
    if (!cameraOk) return '';
    const video = document.getElementById('cam');
    if (!video.srcObject || !video.videoWidth) return '';
    // Reduzir para 640px de largura e qualidade 0.7 antes de enviar:
    // a fotografia continua nitida para identificar a pessoa e passa a
    // ocupar cerca de 10x menos espaco no disco e na rede.
    const LARGURA = 640;
    const escala = Math.min(1, LARGURA / video.videoWidth);
    const canvas = document.getElementById('snap');
    canvas.width = Math.round(video.videoWidth * escala);
    canvas.height = Math.round(video.videoHeight * escala);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.7);
}

async function marcar(tipo) {
    tipoAtual = tipo;
    const botoes = document.querySelectorAll('[data-marcar]');
    botoes.forEach(b => b.disabled = true);

    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('tipo', tipo);
    fd.append('observacao', document.getElementById('observacao').value);
    const b64 = capturarSelfie();
    if (b64) fd.append('selfie_b64', b64);

    try {
        const r = await fetch(BASE_PATH + '/marcar/registar', { method: 'POST', body: fd }).then(r => r.json());
        if (r.ok) {
            showToast(b64 ? 'Presenca registada com fotografia!' : 'Presenca registada (sem fotografia).');
            document.getElementById('observacao').value = '';
            carregarHoje();
        } else {
            showToast(r.erro || 'Erro ao registar', 'error');
        }
    } catch (e) {
        showToast('Sem ligacao ao servidor. Tente novamente.', 'error');
    } finally {
        botoes.forEach(b => b.disabled = false);
    }
}

/* ===== Marcacoes de hoje em tempo real ===== */
const ROTULO_TIPO = { entrada: 'Entrada', saida: 'Saida', falta: 'Falta' };
const ORIGEM = {
    painel:  { rotulo: 'Painel',            icone: 'fa-solid fa-desktop',     cls: 'bg-sky-100 text-sky-700' },
    pin:     { rotulo: 'PIN',               icone: 'fa-solid fa-keyboard',    cls: 'bg-amber-100 text-amber-700' },
    digital: { rotulo: 'Impressão digital', icone: 'fa-solid fa-fingerprint', cls: 'bg-indigo-100 text-indigo-700' },
    sistema: { rotulo: 'Sistema',           icone: 'fa-solid fa-gear',        cls: 'bg-gray-100 text-gray-600' }
};
function corTipo(t) {
    if (t === 'entrada') return 'bg-emerald-100 text-emerald-700';
    if (t === 'saida') return 'bg-red-100 text-red-700';
    return 'bg-amber-100 text-amber-700';
}
async function carregarHoje() {
    try {
        const j = await fetch(BASE_PATH + '/dashboard/registos-hoje', { headers: { 'Accept': 'application/json' } }).then(r => r.json());
        if (!j.ok) return;
        const alvo = document.getElementById('lista-hoje');
        if (!j.registos.length) {
            alvo.innerHTML = '<p class="text-gray-400 text-sm">Ainda sem marcacoes hoje.</p>';
        } else {
            alvo.innerHTML = j.registos.map(r => {
                const hora = String(r.marcado_em || '').substring(11, 16);
                const tipo = r.tipo || '';
                const org = ORIGEM[r.metodo] || ORIGEM.painel;
                const detalhe = r.metodo === 'digital' && r.metodo_detalhe ? ' · ' + r.metodo_detalhe : '';
                return '<div class="flex items-center gap-3 text-sm py-1.5 border-b border-gray-50 last:border-0">'
                     + '<span class="font-mono text-gray-500">' + hora + '</span>'
                     + '<span class="ml-auto px-2 py-0.5 rounded-full text-xs font-medium inline-flex items-center gap-1 ' + org.cls + '">'
                     + '<i class="' + org.icone + '"></i>' + org.rotulo + detalhe + '</span>'
                     + '<span class=" px-2 py-0.5 rounded-full text-xs font-medium ' + corTipo(tipo) + '">'
                     + (ROTULO_TIPO[tipo] || tipo) + '</span></div>';
            }).join('');
        }
        const lbl = document.getElementById('proximo-label');
        if (j.proximo) lbl.textContent = j.proximo;
        const d = new Date();
        document.getElementById('hoje-updated').textContent = 'atualizado ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    } catch (e) { /* offline: mantem o que esta */ }
}

carregarHoje();
setInterval(carregarHoje, 20000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) carregarHoje(); });
iniciarCamera(false);
</script>
