<?php
/**
 * ============================================================
 * FarmaPonto - Preparacao da pasta de dados (primeiro arranque)
 * ============================================================
 * Responsabilidade: garantir que a pasta de dados do utilizador tem
 * base de dados criada, esquema aplicado, pastas de ficheiros e um
 * administrador inicial. Idempotente: pode correr em cada arranque.
 * Comunica com: Database (SQLite), Funcionario (hash de senha/PIN).
 * Uso: php scripts/preparar-dados.php [--esquema=ficheiro.sql]
 */

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/app/core/SqlTradutor.php';
require ROOT_PATH . '/app/core/PdoSqlite.php';
require ROOT_PATH . '/app/core/PaginaErro.php';
require ROOT_PATH . '/app/core/Database.php';

use App\Core\Database;

$cfg = Database::config();
date_default_timezone_set((string) ($cfg['timezone'] ?? 'Africa/Maputo'));

if (Database::motor() !== 'sqlite') {
    fwrite(STDERR, "Este script prepara apenas a versao de secretaria (SQLite).\n");
    exit(1);
}

// 1. Pastas de dados
foreach ([
    (string) ($cfg['storage_dir'] ?? ROOT_PATH . '/storage'),
    (string) ($cfg['backup_dir'] ?? ROOT_PATH . '/storage/backups'),
    (string) ($cfg['selfie_dir'] ?? ROOT_PATH . '/storage/selfies'),
    dirname((string) $cfg['db_file']),
] as $dir) {
    if ($dir !== '' && !is_dir($dir)) { @mkdir($dir, 0775, true); }
}

// 2. Esquema
$esquema = ROOT_PATH . '/database.sqlite.sql';
foreach ($argv as $a) {
    if (str_starts_with($a, '--esquema=')) { $esquema = substr($a, 10); }
}

$pdo = Database::pdo();
$novo = !Database::temTabela('funcionarios');

if ($novo) {
    $sql = file_get_contents($esquema);
    if ($sql === false) {
        fwrite(STDERR, "Esquema nao encontrado: {$esquema}\n");
        exit(1);
    }
    $pdo->exec($sql);
    echo "Esquema aplicado.\n";
}

// 3. Administrador inicial
$total = (int) $pdo->query('SELECT COUNT(*) FROM "funcionarios"')->fetchColumn();
if ($total === 0) {
    $email = getenv('FP_ADMIN_EMAIL') ?: 'admin@farmaponto.local';
    $senha = getenv('FP_ADMIN_SENHA') ?: 'admin123';
    $pin   = getenv('FP_ADMIN_PIN') ?: '1234';

    $st = $pdo->prepare(
        'INSERT INTO "funcionarios"
         ("codigo","nome","email","cargo","password_hash","pin_hash","perfil","marca_ponto",
          "salario_base","dias_uteis_mes","hora_entrada","hora_saida","ativo")
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1)'
    );
    $st->execute([
        'ADM001', 'Administrador', $email, 'Administrador',
        password_hash($senha, PASSWORD_DEFAULT), password_hash($pin, PASSWORD_DEFAULT),
        'admin', 0, 0, 22, '08:00:00', '17:00:00',
    ]);
    echo "Administrador criado: {$email}\n";
}

// 4. Verificacao final
$tabelas = Database::dimensao();
printf("Base de dados pronta: %s (%d tabelas, %.2f MB)\n", $cfg['db_file'], $tabelas['n'], $tabelas['mb']);
