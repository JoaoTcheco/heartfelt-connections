<div class="min-h-screen flex items-center justify-center bg-gray-50 p-6">
    <div class="bg-white shadow-sm border border-gray-200 rounded-2xl p-8 w-full max-w-md">
        <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-700 mb-4">
            <i class="fa-solid fa-lock text-xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Nova palavra-passe</h1>
        <p class="text-sm text-gray-500 mb-6">Defina uma nova palavra-passe segura (mínimo 6 caracteres).</p>

        <form id="form-reset" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <input type="password" name="password" required minlength="6" placeholder="Nova palavra-passe"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg">Guardar</button>
        </form>
    </div>
</div>
<script>
document.getElementById('form-reset').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(BASE_PATH + '/reset-password', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
        window.toast && window.toast('Palavra-passe alterada. A redirecionar...', 'success');
        setTimeout(() => location.href = j.redirect, 800);
    } else {
        window.toast && window.toast(j.erro || 'Erro ao alterar.', 'error');
    }
});
</script>
