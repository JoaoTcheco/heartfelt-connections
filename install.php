<?php
/**
 * ============================================================
 * FarmaPonto - Instalacao
 * ============================================================
 * Cria o primeiro usuario administrador.
 */

$cfg = require __DIR__ . '/config/config.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}",
            $cfg['db_user'],
            $cfg['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $pin = $_POST['pin'] ?? '';

        if (strlen($password) < 6) {
            $erro = 'Password deve ter no minimo 6 caracteres.';
        } elseif (strlen($pin) < 4) {
            $erro = 'PIN deve ter no minimo 4 digitos.';
        } else {
            // Verificar se ja existe admin
            $st = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE perfil = 'admin'");
            $st->execute();
            if ($st->fetchColumn() > 0) {
                $erro = 'Ja existe um administrador no sistema. Use a pagina de login.';
            } else {
                $st = $pdo->prepare(
                    "INSERT INTO funcionarios (codigo, nome, email, cargo, password_hash, pin_hash, perfil, salario_base, carga_diaria, hora_entrada, hora_saida, ativo)
                     VALUES (?, ?, ?, 'Administrador', ?, ?, 'admin', 0, 8, '08:00:00', '17:00:00', 1)"
                );
                $st->execute([
                    'ADMIN001',
                    $nome,
                    $email,
                    password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                    password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12])
                ]);
                $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $sucesso = 'Administrador criado com sucesso! <a href="' . $base . '/login" class="text-emerald-600 underline">Ir para login</a>';
            }
        }
    } catch (PDOException $e) {
        $erro = 'Erro na base de dados: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar FarmaPonto</title>
    <script src="assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="assets/vendor/inter.css">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-8 w-full max-w-md">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 21.072a9.004 9.004 0 01-8.828-2.072 9 9 0 1112.728-12.728 9.004 9.004 0 01-3.9 14.8z"/></svg>
            </div>
            <div>
                <h1 class="font-bold text-xl text-gray-900">FarmaPonto</h1>
                <p class="text-xs text-gray-500">Instalacao do Sistema</p>
            </div>
        </div>

        <?php if ($sucesso): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <?= $sucesso ?>
        </div>
        <?php endif; ?>

        <?php if ($erro): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
            <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <?php if (!$sucesso): ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Administrador</label>
                <input type="text" name="nome" required value="Administrador"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required value="admin@farmaponto.mz"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password (min 6 caracteres)</label>
                <input type="password" name="password" required minlength="6"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">PIN (4 digitos)</label>
                <input type="password" name="pin" required minlength="4" maxlength="4" inputmode="numeric"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none">
            </div>
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 rounded-lg transition-colors">
                Criar Administrador
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-6 text-center text-xs text-gray-400">
            <p>1. Crie a base de dados no phpMyAdmin</p>
            <p>2. Importe o ficheiro <code>database.sql</code></p>
            <p>3. Preencha o formulario acima</p>
        </div>
    </div>
</body>
</html>