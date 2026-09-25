<div class="flex min-h-screen">
    <div class="hidden lg:flex lg:w-1/2 bg-emerald-600 flex-col justify-between p-12 text-white relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-16">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                    <i class="fa-solid fa-link"></i>
                </div>
                <span class="font-semibold text-lg">FarmaPonto</span>
            </div>
            <h2 class="text-4xl font-bold leading-tight mb-6">Gestao de assiduidade simples para a sua farmacia.</h2>
            <p class="text-emerald-100 text-lg leading-relaxed max-w-md">Marcacao de ponto, faltas, atrasos e calculo automatico de cortes salariais.</p>
        </div>
        <p class="relative z-10 text-emerald-200 text-sm">© <?= date('Y') ?> FarmaPonto</p>
    </div>
    
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
        <div class="w-full max-w-md">
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 mb-6">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fa-solid fa-bolt text-emerald-500"></i>
                    <h3 class="font-semibold text-gray-900">Marcacao Rapida</h3>
                </div>
                <p class="text-sm text-gray-500 mb-5">Sem login — encoste o dedo no leitor ou use o seu PIN.</p>

                <div class="grid grid-cols-2 gap-1 p-1 bg-gray-100 rounded-xl mb-5">
                    <button type="button" id="aba-digital" onclick="qpAba('digital')"
                        class="py-2 rounded-lg text-sm font-medium bg-white text-emerald-700 shadow-sm">
                        <i class="fa-solid fa-fingerprint mr-1"></i> Impressao digital
                    </button>
                    <button type="button" id="aba-pin" onclick="qpAba('pin')"
                        class="py-2 rounded-lg text-sm font-medium text-gray-500">
                        <i class="fa-solid fa-keyboard mr-1"></i> PIN
                    </button>
                </div>

                <div id="painel-digital">
                    <div class="rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50/60 p-6 text-center">
                        <i id="dg-icone" class="fa-solid fa-fingerprint text-6xl text-emerald-500 transition-transform"></i>
                        <p id="dg-estado" class="mt-3 text-emerald-800 font-medium">Encoste o dedo no leitor</p>
                        <p class="text-xs text-gray-500 mt-1">O sistema reconhece-o e marca entrada ou saida automaticamente.</p>
                        <input type="text" id="dg-template" autocomplete="off" aria-label="Leitura do leitor"
                            class="mt-4 w-full px-3 py-2 border border-emerald-200 rounded-lg text-center text-sm tracking-widest bg-white/70 focus:ring-2 focus:ring-emerald-500 outline-none"
                            placeholder="A aguardar leitura…">
                        <button type="button" onclick="dgSensorDispositivo()"
                            class="mt-3 text-xs text-emerald-700 underline hover:text-emerald-800">
                            Usar o sensor deste computador
                        </button>
                    </div>
                    <div id="dg-msg" class="hidden mt-4 rounded-xl px-4 py-3 text-center text-sm"></div>
                </div>

                <form id="quickpunch-form" class="space-y-4 hidden">
                    <input type="hidden" id="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">PIN de 4 digitos</label>
                        <div class="flex gap-2 justify-center">
                            <input type="password" id="qp-pin" maxlength="4" inputmode="numeric"
                                class="w-full text-center text-2xl tracking-[1em] px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                    </div>
                    
                    <div id="qp-codigo-wrap" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Codigo do funcionario</label>
                        <input type="text" id="qp-codigo" maxlength="20" autocomplete="off"
                            class="w-full text-center text-lg uppercase px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none"
                            placeholder="Ex: F002">
                        <p class="text-xs text-gray-500 mt-1 text-center">Necessario porque este PIN e usado por mais do que uma pessoa.</p>
                    </div>

                    <input type="hidden" id="qp-tipo" name="tipo" value="">
                    
                    <button type="button" id="btn-continuar" onclick="iniciarSelfie()"
                        class="w-full bg-emerald-200 text-emerald-800 font-medium py-3 rounded-lg transition-colors">
                        Continuar
                    </button>
                </form>
                
                <!-- Camera -->
                <div id="camera-area" class="hidden mt-4">
                    <div id="camera-label" class="text-center text-sm font-medium text-emerald-700 bg-emerald-50 rounded-lg py-2 mb-3"></div>
                    <video id="qp-cam" autoplay playsinline muted class="w-full rounded-lg bg-gray-900 aspect-square object-cover"></video>
                    <canvas id="qp-snap" class="hidden"></canvas>
                    <div class="flex gap-2 mt-3">
                        <button onclick="capturar()" class="flex-1 bg-emerald-600 text-white py-2 rounded-lg font-medium">
                            <i class="fa-solid fa-camera mr-1"></i> Capturar
                        </button>
                        <button onclick="cancelarCamera()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600">Cancelar</button>
                    </div>
                </div>
                
                <div id="qp-msg" class="mt-3 text-sm text-center hidden"></div>
            </div>
            
            <div class="text-center">
                <a href="<?= BASE_PATH ?>/login" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                    Iniciar sessao (equipa) <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
let stream = null;
let qpCodigo = null;
let qpNome = null;
let qpTipo = null;
const csrf = document.getElementById('csrf').value;

async function iniciarSelfie() {
    const pin = document.getElementById('qp-pin').value;
    if (pin.length < 4) {
        alert('Insira um PIN de 4 digitos');
        return;
    }

    const btn = document.getElementById('btn-continuar');
    btn.disabled = true;
    btn.textContent = 'A verificar...';

    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('pin', pin);
    const codigoEl = document.getElementById('qp-codigo');
    if (codigoEl && codigoEl.value.trim() !== '') fd.append('codigo', codigoEl.value.trim().toUpperCase());

    try {
        const r = await fetch(BASE_PATH + '/quickpunch/verificar-pin', { method: 'POST', body: fd }).then(r => r.json());
        if (!r.ok) {
            if (r.pedir_codigo) {
                document.getElementById('qp-codigo-wrap').classList.remove('hidden');
                setTimeout(() => codigoEl.focus(), 50);
            }
            alert(r.erro || 'PIN invalido');
            btn.disabled = false;
            btn.textContent = 'Continuar';
            return;
        }
        qpCodigo = r.codigo;
        qpNome = r.nome;
        qpTipo = r.tipo;
    } catch (e) {
        alert('Erro de conexao');
        btn.disabled = false;
        btn.textContent = 'Continuar';
        return;
    }
    
    const label = document.getElementById('camera-label');
    label.textContent = qpTipo === 'entrada' ? 'A registar Entrada' : 'A registar Saida';
    
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 1280, height: 1280 }, audio: false });
        document.getElementById('qp-cam').srcObject = stream;
        document.getElementById('camera-area').classList.remove('hidden');
        btn.classList.add('hidden');
    } catch (e) {
        // Sem camara o ponto e marcado na mesma, apenas sem foto.
        const msg = document.getElementById('qp-msg');
        msg.classList.remove('hidden');
        msg.className = 'mt-3 text-sm text-center text-gray-500';
        msg.textContent = 'Sem camara neste equipamento — a marcar sem foto…';
        enviarPunch('');
    }
}

function cancelarCamera() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    document.getElementById('camera-area').classList.add('hidden');
    document.getElementById('btn-continuar').classList.remove('hidden');
    document.getElementById('btn-continuar').disabled = false;
    document.getElementById('btn-continuar').textContent = 'Continuar';
    qpTipo = null;
}

async function capturar() {
    const video = document.getElementById('qp-cam');
    const canvas = document.getElementById('qp-snap');
    // Fotografia reduzida a 640px / qualidade 0.7 (ver Marcar Presenca):
    // menos espaco em disco e envio mais rapido no terminal.
    const LARGURA = 640;
    const escala = Math.min(1, LARGURA / (video.videoWidth || LARGURA));
    canvas.width = Math.round((video.videoWidth || LARGURA) * escala);
    canvas.height = Math.round((video.videoHeight || LARGURA) * escala);
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    const b64 = canvas.toDataURL('image/jpeg', 0.7);
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    await enviarPunch(b64);
}

async function enviarPunch(b64) {
    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('codigo', qpCodigo || '');
    fd.append('pin', document.getElementById('qp-pin').value);
    fd.append('tipo', qpTipo);
    fd.append('selfie_b64', b64);
    
    const msg = document.getElementById('qp-msg');
    msg.classList.remove('hidden');
    msg.textContent = 'A processar...';
    
    try {
        const r = await fetch(BASE_PATH + '/quickpunch/punch', { method: 'POST', body: fd }).then(r => r.json());
        if (r.ok) {
            msg.className = 'mt-3 text-sm text-center text-emerald-600 font-medium';
            msg.innerHTML = `<i class="fa-solid fa-check-circle mr-1"></i> Presenca registada, ${r.nome}!`;
            setTimeout(() => { window.location.href = BASE_PATH + '/login'; }, 2000);
        } else {
            msg.className = 'mt-3 text-sm text-center text-red-600';
            msg.textContent = r.erro || 'Erro';
        }
    } catch (e) {
        msg.className = 'mt-3 text-sm text-center text-red-600';
        msg.textContent = 'Erro de conexao';
    }
}

// ===== Abas =====
function qpAba(qual) {
    const dig = qual === 'digital';
    document.getElementById('painel-digital').classList.toggle('hidden', !dig);
    document.getElementById('quickpunch-form').classList.toggle('hidden', dig);
    if (dig) { cancelarCamera(); }
    document.getElementById('camera-area').classList.add('hidden');
    document.getElementById('qp-msg').classList.add('hidden');
    document.getElementById('aba-digital').className = 'py-2 rounded-lg text-sm font-medium ' + (dig ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500');
    document.getElementById('aba-pin').className = 'py-2 rounded-lg text-sm font-medium ' + (dig ? 'text-gray-500' : 'bg-white text-emerald-700 shadow-sm');
    if (dig) { setTimeout(() => document.getElementById('dg-template').focus(), 50); }
    else { setTimeout(() => document.getElementById('qp-pin').focus(), 50); }
}

// ===== Impressao digital =====
let dgOcupado = false;

function dgResultado(texto, ok, sub) {
    const el = document.getElementById('dg-msg');
    el.classList.remove('hidden');
    el.className = 'mt-4 rounded-xl px-4 py-3 text-center text-sm ' +
        (ok ? 'bg-emerald-600 text-white' : 'bg-red-50 text-red-700 border border-red-200');
    el.innerHTML = (ok ? '<i class="fa-solid fa-circle-check mr-1"></i> ' : '<i class="fa-solid fa-circle-exclamation mr-1"></i> ')
        + texto + (sub ? '<div class="text-xs opacity-90 mt-1">' + sub + '</div>' : '');
    setTimeout(() => el.classList.add('hidden'), 8000);
}

async function dgMarcar(template) {
    if (dgOcupado) { return; }
    dgOcupado = true;
    document.getElementById('dg-estado').textContent = 'A identificar…';
    document.getElementById('dg-icone').classList.add('animate-pulse');

    const fd = new FormData();
    fd.append('csrf', csrf);
    fd.append('template', template);
    try {
        const r = await fetch(BASE_PATH + '/quickpunch/digital', { method: 'POST', body: fd }).then(x => x.json());
        if (r.ok) {
            dgResultado(r.mensagem + ' — ' + r.nome, true, r.codigo + ' · ' + r.hora);
        } else {
            dgResultado(r.erro || 'Nao foi possivel marcar o ponto.', false);
        }
    } catch (e) {
        dgResultado('Sem ligacao ao servidor. Tente novamente.', false);
    }
    document.getElementById('dg-template').value = '';
    document.getElementById('dg-estado').textContent = 'Encoste o dedo no leitor';
    document.getElementById('dg-icone').classList.remove('animate-pulse');
    dgOcupado = false;
    document.getElementById('dg-template').focus();
}

(function () {
    const inp = document.getElementById('dg-template');
    inp.addEventListener('input', function () {
        document.getElementById('dg-estado').textContent = this.value.length ? 'A ler…' : 'Encoste o dedo no leitor';
    });
    inp.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        e.preventDefault();
        const v = this.value.trim();
        if (v.length < 8) { dgResultado('Leitura incompleta. Repita, mantendo o dedo bem assente.', false); this.value = ''; return; }
        dgMarcar(v);
    });
    document.addEventListener('click', function () {
        if (!document.getElementById('painel-digital').classList.contains('hidden')) { inp.focus(); }
    });
    setTimeout(() => inp.focus(), 200);
})();

async function dgSensorDispositivo() {
    if (!window.PublicKeyCredential || !navigator.credentials) {
        dgResultado('Este computador nao tem sensor compativel. Use o leitor ou o PIN.', false);
        return;
    }
    try {
        const desafio = new Uint8Array(32);
        crypto.getRandomValues(desafio);
        const cred = await navigator.credentials.get({
            publicKey: { challenge: desafio, userVerification: 'required', timeout: 60000 }
        });
        const id = btoa(String.fromCharCode(...new Uint8Array(cred.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        dgMarcar(id);
    } catch (e) {
        dgResultado('Leitura cancelada ou sem sensor disponivel.', false);
    }
}

</script>