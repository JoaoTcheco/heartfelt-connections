<div class="min-h-screen bg-slate-50 flex">
    <section class="hidden lg:flex lg:w-[52%] bg-gradient-to-br from-emerald-700 via-emerald-600 to-teal-600 text-white relative overflow-hidden">
        <div class="absolute inset-0 opacity-20">
            <div class="absolute -top-32 -right-24 w-[28rem] h-[28rem] rounded-full bg-white/20"></div>
            <div class="absolute -bottom-40 -left-32 w-[30rem] h-[30rem] rounded-full bg-emerald-950/30"></div>
            <div class="absolute top-1/2 right-1/4 w-40 h-40 rounded-full bg-teal-300/10"></div>
        </div>
        <div class="relative z-10 w-full p-12 xl:p-16 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 mb-20">
                    <div class="w-12 h-12 bg-white/15 border border-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm font-bold text-xl">F</div>
                    <div>
                        <div class="font-bold text-xl tracking-tight">FarmaPonto</div>
                        <div class="text-emerald-100 text-xs">Gestão de assiduidade</div>
                    </div>
                </div>
                <div class="max-w-xl">
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 text-emerald-50 text-sm mb-6">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        Gestão simples e segura
                    </span>
                    <h1 class="text-5xl xl:text-6xl font-bold leading-[1.05] tracking-tight mb-6">
                        Controle a assiduidade da sua farmácia com clareza.
                    </h1>
                    <p class="text-emerald-50/90 text-lg leading-relaxed max-w-lg">
                        Marcação de ponto, atrasos, faltas, férias e informação salarial num único sistema.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-5 text-sm text-emerald-100">
                <span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check"></i> Ponto e assiduidade</span>
                <span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check"></i> Relatórios</span>
                <span class="inline-flex items-center gap-2"><i class="fa-solid fa-circle-check"></i> Folha salarial</span>
            </div>
        </div>
    </section>

    <section class="w-full lg:w-[48%] flex items-center justify-center px-5 py-10 sm:px-8 bg-white">
        <div class="w-full max-w-md">
            <div class="lg:hidden flex items-center gap-3 mb-10">
                <div class="w-11 h-11 bg-emerald-600 rounded-xl flex items-center justify-center text-white font-bold text-lg shadow-sm">F</div>
                <div>
                    <div class="font-bold text-slate-900 text-lg">FarmaPonto</div>
                    <div class="text-xs text-slate-500">Gestão de assiduidade</div>
                </div>
            </div>

            <div class="mb-8">
                <p class="text-sm font-semibold text-emerald-700 mb-2">Bem-vindo</p>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">Entrar na sua conta</h2>
                <p class="text-slate-500 mt-2">Aceda ao painel para gerir a assiduidade.</p>
            </div>

            <div class="grid <?= !empty($auto_registo) ? 'grid-cols-3' : 'grid-cols-2' ?> bg-slate-100 rounded-xl p-1 mb-7">
                <button type="button" class="py-2.5 px-3 rounded-lg bg-white shadow-sm text-sm font-semibold text-slate-900" aria-current="page">Entrar</button>
                <a href="<?= BASE_PATH ?>/quickpunch" class="py-2.5 px-3 rounded-lg text-sm font-medium text-slate-500 hover:text-slate-900 text-center transition-colors">Marcação rápida</a>
                <?php if (!empty($auto_registo)): ?>
                <a href="<?= BASE_PATH ?>/register" class="py-2.5 px-3 rounded-lg text-sm font-medium text-slate-500 hover:text-slate-900 text-center transition-colors">Criar conta</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($erro)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-xl mb-6 text-sm flex items-start gap-3" role="alert">
                <i class="fa-solid fa-circle-exclamation mt-0.5" aria-hidden="true"></i>
                <span><?= htmlspecialchars($erro) ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_PATH ?>/login" class="space-y-5" autocomplete="on">
                <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
                <input type="hidden" name="redir" value="<?= htmlspecialchars($_GET['redir'] ?? '', ENT_QUOTES) ?>">

                <div>
                    <label for="login-email" class="block text-sm font-semibold text-slate-700 mb-2">Email</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input id="login-email" type="email" name="email" required autocomplete="username" placeholder="nome@farmacia.mz"
                            class="w-full pl-11 pr-4 py-3 border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="login-password" class="block text-sm font-semibold text-slate-700">Palavra-passe</label>
                        <a href="<?= BASE_PATH ?>/forgot-password" class="text-sm font-medium text-emerald-700 hover:text-emerald-800 hover:underline">Esqueci-me</a>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" aria-hidden="true"></i>
                        <input id="login-password" type="password" name="password" required autocomplete="current-password"
                            class="w-full pl-11 pr-12 py-3 border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                        <button type="button" id="toggle-password" class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100" aria-label="Mostrar palavra-passe" aria-pressed="false">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2 shadow-sm">
                    Entrar <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="mt-7 pt-6 border-t border-slate-100 text-center">
                <a href="<?= BASE_PATH ?>/quickpunch" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-700">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    Marcação rápida (kiosk)
                </a>
            </div>

            <p class="mt-8 text-center text-xs text-slate-400">© <?= date('Y') ?> FarmaPonto</p>
        </div>
    </section>
</div>

<script>
(function () {
    const btn = document.getElementById('toggle-password');
    const input = document.getElementById('login-password');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        btn.setAttribute('aria-pressed', String(!visible));
        btn.setAttribute('aria-label', visible ? 'Mostrar palavra-passe' : 'Ocultar palavra-passe');
        const icon = btn.querySelector('i');
        if (icon) icon.className = visible ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
    });
})();
</script>
