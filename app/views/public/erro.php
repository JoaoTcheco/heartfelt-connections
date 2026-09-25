<div class="flex items-center justify-center min-h-screen bg-gray-50">
    <div class="text-center">
        <h1 class="text-6xl font-bold text-gray-300 mb-4">403</h1>
        <h2 class="text-2xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($titulo ?? 'Acesso Negado') ?></h2>
        <p class="text-gray-500 mb-6"><?= htmlspecialchars($mensagem ?? 'Nao tem permissao para aceder a esta pagina.') ?></p>
        <a href="<?= BASE_PATH ?>/dashboard" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Voltar ao Dashboard</a>
    </div>
</div>