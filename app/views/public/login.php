<div class="flex min-h-screen">
    <!-- Lado Esquerdo - Verde -->
    <div class="hidden lg:flex lg:w-1/2 bg-emerald-600 flex-col justify-between p-12 text-white relative overflow-hidden">
        <div class="relative z-10">
            <div class="flex items-center gap-3 mb-16">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                    <i class="fa-solid fa-link"></i>
                </div>
                <span class="font-semibold text-lg">FarmaPonto</span>
            </div>

            <h2 class="text-4xl font-bold leading-tight mb-6">
                Gestao de assiduidade simples para a sua farmacia.
            </h2>
            <p class="text-emerald-100 text-lg leading-relaxed max-w-md">
                Marcacao de ponto, faltas, atrasos e calculo automatico de cortes salariais.
            </p>
        </div>

        <p class="relative z-10 text-emerald-200 text-sm">© <?= date('Y') ?> FarmaPonto</p>

        <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-500 rounded-full -translate-y-1/2 translate-x-1/2 opacity-50"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-emerald-700 rounded-full translate-y-1/2 -translate-x-1/2 opacity-50"></div>
    </div>

    <!-- Lado Direito - Formulario -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
        <div class="w-full max-w-md">
            <!-- Tabs -->
            <div class="flex bg-gray-100 rounded-lg p-1 mb-8">
                <button class="flex-1 py-2 px-4 rounded-md bg-white shadow-sm text-sm font-medium text-gray-900">Entrar</button>
                <a href="<?= BASE_PATH ?>/quickpunch" class="flex-1 py-2 px-4 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700 text-center">Marcação Rápida</a>
                <?php if (!empty($auto_registo)): ?>
                <a href="<?= BASE_PATH ?>/register" class="flex-1 py-2 px-4 rounded-md text-sm font-medium text-gray-500 hover:text-gray-700 text-center">Criar conta</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($erro)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($erro) ?>
            </div>
            <?php endif; ?>

            <form method="post" action="<?= BASE_PATH ?>/login" class="space-y-5">
                <input type="hidden" name="csrf" value="<?= \App\Helpers\Csrf::token() ?>">
                <input type="hidden" name="redir" value="<?= htmlspecialchars($_GET['redir'] ?? '', ENT_QUOTES) ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input type="email" name="email" required placeholder="ana@farmacia.mz"
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                </div>

                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="block text-sm font-medium text-gray-700">Palavra-passe</label>
                        <a href="<?= BASE_PATH ?>/forgot-password" class="text-xs text-emerald-700 hover:underline">Esqueci-me</a>
                    </div>
                    <input type="password" name="password" required
                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all">
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    Entrar <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="<?= BASE_PATH ?>/quickpunch" class="text-sm text-gray-500 hover:text-emerald-600 underline">← Marcação rápida (kiosk)</a>
            </div>
        </div>
    </div>
</div>
