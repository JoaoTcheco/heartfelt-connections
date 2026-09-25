<div class="flex min-h-screen">
    <div class="hidden lg:flex lg:w-1/2 bg-emerald-600 flex-col justify-between p-12 text-white relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-16">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm"><i class="fa-solid fa-link"></i></div>
                <span class="font-semibold text-lg">FarmaPonto</span>
            </div>
            <h2 class="text-4xl font-bold leading-tight mb-6">Crie a sua conta em segundos.</h2>
            <p class="text-emerald-100 text-lg leading-relaxed max-w-md">Marcação de ponto, faltas, atrasos e cálculo automático de cortes salariais.</p>
        </div>
        <p class="relative z-10 text-emerald-200 text-sm">© <?= date('Y') ?> FarmaPonto</p>
        <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500 rounded-full -translate-y-1/2 translate-x-1/2 opacity-50"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-emerald-700 rounded-full translate-y-1/2 -translate-x-1/2 opacity-50"></div>
    </div>

    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
        <div class="w-full max-w-md">
            <div class="flex bg-gray-100 rounded-lg p-1 mb-8">
                <a href="<?= BASE_PATH ?>/login" class="flex-1 py-2 px-4 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700 text-center">Entrar</a>
                <button class="flex-1 py-2 px-4 rounded-md bg-white shadow-sm text-sm font-medium text-gray-900">Criar conta</button>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-1">Criar conta</h1>
            <p class="text-sm text-gray-500 mb-6">O perfil inicial é <b>funcionário</b>. Um administrador poderá ajustar permissões.</p>

            <form id="form-register" class="space-y-4">
                <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nome completo</label>
                    <input type="text" name="nome" required minlength="3" maxlength="120" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" required class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Palavra-passe</label>
                        <input type="password" name="password" required minlength="6" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">PIN (4-8 dígitos)</label>
                        <input type="text" name="pin" required pattern="\d{4,8}" inputmode="numeric" maxlength="8" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none font-mono">
                    </div>
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    Criar conta <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                Já tem conta? <a href="<?= BASE_PATH ?>/login" class="text-emerald-700 font-medium hover:underline">Entrar</a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('form-register').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(BASE_PATH + '/register', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
        window.toast && window.toast('Conta criada com sucesso!', 'success');
        setTimeout(() => location.href = j.redirect, 600);
    } else {
        window.toast && window.toast(j.erro || 'Erro ao criar conta', 'error');
    }
});
</script>
