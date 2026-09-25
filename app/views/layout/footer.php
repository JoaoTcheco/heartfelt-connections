<?php if (\App\Helpers\Auth::user() && empty($kiosk)): ?>
        </div>
        <footer class="px-4 sm:px-6 lg:px-8 pb-6 pt-2 max-w-7xl mx-auto w-full no-print">
            <div class="border-t border-gray-200 pt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-400">
                <span><?= htmlspecialchars((new \App\Models\Config())->get('nome_empresa', 'FarmaPonto')) ?> &middot; Gestao de Assiduidade</span>
                <span><?= date('d/m/Y H:i') ?></span>
            </div>
        </footer>
    </main>
</div>
<?php else: ?>
</main>
<?php endif; ?>

<!-- Container global de toasts -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2 no-print"></div>

<!-- Modal de confirmação reutilizável (AlertDialog) -->
<div id="confirm-modal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/40 no-print">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6 animate-fade-in">
        <div class="flex items-start gap-4">
            <div id="confirm-icon" class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-triangle-exclamation text-xl"></i>
            </div>
            <div class="flex-1">
                <h3 id="confirm-title" class="text-lg font-semibold text-gray-900 mb-1">Confirmar</h3>
                <p id="confirm-msg" class="text-sm text-gray-600">Tem certeza?</p>
            </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
            <button id="confirm-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancelar</button>
            <button id="confirm-ok" class="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">Confirmar</button>
        </div>
    </div>
</div>

<script>
// ====== TOAST GLOBAL ======
window.toast = function(msg, type = 'success') {
    const c = document.getElementById('toast-container');
    if (!c) return;
    const styles = {
        success: 'bg-emerald-600 text-white',
        error:   'bg-red-600 text-white',
        info:    'bg-blue-600 text-white',
        warning: 'bg-amber-500 text-white'
    };
    const icons = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const el = document.createElement('div');
    el.className = `px-4 py-3 rounded-lg shadow-lg text-sm font-medium animate-slide-in flex items-center gap-2 max-w-sm ${styles[type] || styles.success}`;
    el.innerHTML = `<i class="fa-solid ${icons[type] || icons.success}"></i><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }, 3500);
};
window.showToast = window.toast; // alias legado

// ====== CONFIRM MODAL ======
window.confirmar = function({ title = 'Confirmar', msg = 'Tem certeza?', okText = 'Confirmar', cancelText = 'Cancelar', danger = false } = {}) {
    return new Promise((resolve) => {
        const m = document.getElementById('confirm-modal');
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-msg').textContent = msg;
        const ok = document.getElementById('confirm-ok');
        const cancel = document.getElementById('confirm-cancel');
        const icon = document.getElementById('confirm-icon');
        ok.textContent = okText; cancel.textContent = cancelText;
        ok.className = `px-4 py-2 text-sm font-medium text-white rounded-lg ${danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'}`;
        icon.className = `w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 ${danger ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600'}`;
        m.classList.remove('hidden'); m.classList.add('flex');
        const close = (val) => { m.classList.add('hidden'); m.classList.remove('flex'); ok.onclick = null; cancel.onclick = null; resolve(val); };
        ok.onclick = () => close(true);
        cancel.onclick = () => close(false);
    });
};

// ====== Service Worker (PWA) ======
if ('serviceWorker' in navigator && location.protocol !== 'file:') {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(BASE_PATH + '/sw.js').then(reg => {
            if (reg && reg.update) reg.update();
        }).catch(() => {});
        // Limpar caches antigos que guardavam paginas HTML (versoes <= v4)
        if (window.caches && caches.keys) {
            caches.keys().then(ks => ks.filter(k => /farmaponto-v[1-4]/.test(k)).forEach(k => caches.delete(k)));
        }
    });
}
</script>
</body>
</html>
