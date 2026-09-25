<div class="min-h-screen flex items-center justify-center bg-gray-50 p-6">
    <div class="bg-white shadow-sm border border-gray-200 rounded-2xl p-8 w-full max-w-md">
        <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-700 mb-4">
            <i class="fa-solid fa-key text-xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Recuperar palavra-passe</h1>
        <p class="text-sm text-gray-500 mb-6">Indique o email da sua conta. Se existir, enviaremos um link para definir uma nova palavra-passe.</p>

        <form id="form-forgot" class="space-y-4">
            <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
            <input type="email" name="email" required placeholder="seu@email.com"
                class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg">Enviar link</button>
        </form>

        <div class="mt-6 text-center">
            <a href="<?= BASE_PATH ?>/login" class="text-sm text-gray-500 hover:text-emerald-700">← Voltar ao login</a>
        </div>
    </div>
</div>
<script>
document.getElementById('form-forgot').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(BASE_PATH + '/forgot-password', { method: 'POST', body: fd });
    const j = await r.json();
    window.toast && window.toast(j.msg || j.erro || 'Pedido enviado.', j.ok ? 'success' : 'error');
});
</script>
