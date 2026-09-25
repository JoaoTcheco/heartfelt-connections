<?php
/**
 * ============================================================
 * FarmaPonto - Configuracoes do Sistema
 * ============================================================
 * Fonte unica de configuracao de infra-estrutura.
 *
 * Ordem de precedencia (do mais fraco para o mais forte):
 *   1. valores por omissao deste ficheiro
 *   2. variaveis de ambiente FP_* (ex.: FP_DB_HOST, FP_DB_PASS, FP_MODO)
 *   3. config/config.local.php (nao versionado; ver config.local.exemplo.php)
 *
 * LOCAL (XAMPP):  db_user=root, db_pass=''
 * PRODUCAO:       criar config/config.local.php com host/utilizador/password
 *
 * 100% offline: nenhuma chave aqui exige servicos externos.
 */

defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__));

$cfg = [
    // ---- Base de dados ----
    // db_driver: 'mysql' (servidor web) | 'sqlite' (programa de secretaria)
    // Em SQLite toda a base de dados e um unico ficheiro (db_file).
    'db_driver'    => 'mysql',
    'db_file'      => ROOT_PATH . '/storage/farmaponto.db',
    'db_host'      => 'localhost',
    'db_port'      => 3306,
    'db_name'      => 'farmaponto',
    'db_user'      => 'root',
    'db_pass'      => '',
    'db_charset'   => 'utf8mb4',
    'db_socket'    => '',        // ex.: /run/mysqld/mysqld.sock (deixe vazio para TCP)
    'timezone_sql' => '+02:00',  // fuso aplicado a sessao MySQL

    // ---- Modo de execucao: 'desenvolvimento' | 'producao' ----
    'modo' => 'desenvolvimento',

    'site_title'       => 'FarmaPonto',
    'site_subtitle'    => 'Gestao de Assiduidade',
    'site_description' => 'Sistema de gestao de assiduidade para farmacias',
    'site_url'         => (isset($_SERVER['HTTP_HOST'])
        ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST']
          . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/')
        : 'http://localhost/farmaponto'),
    'site_email'       => 'admin@farmaponto.mz',

    'smtp_host'   => 'smtp.gmail.com',
    'smtp_port'   => 587,
    'smtp_user'   => '',
    'smtp_pass'   => '',
    'smtp_secure' => 'tls',
    'smtp_from_name'  => 'FarmaPonto',
    'smtp_from_email' => 'admin@farmaponto.mz',

    'session_lifetime'    => 3600,
    'csrf_token_name'     => 'csrf_token',
    'password_min_length' => 6,
    'pin_min_length'      => 4,
    'login_attempts'      => 5,
    'login_lockout_time'  => 900,

    // Fotografias: o navegador reduz para 640px/JPEG 0.7 antes de enviar,
    // por isso 512 KB e folgado e protege o disco.
    'selfie_max_size'     => 512 * 1024,
    'selfie_allowed_types'=> ['image/jpeg', 'image/jpg'],
    'selfie_dir'          => ROOT_PATH . '/storage/selfies',

    // ---- Armazenamento local (backups, uploads, cache) ----
    'storage_dir'  => ROOT_PATH . '/storage',
    'backup_dir'   => ROOT_PATH . '/storage/backups',
    'backup_manter'=> 14,   // numero de copias diarias a preservar

    'timezone'        => 'Africa/Maputo',
    'date_format'     => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i:s',
    'time_format'     => 'H:i',
];

// ---- 2. Variaveis de ambiente FP_* ----
foreach ($cfg as $chave => $valor) {
    $env = getenv('FP_' . strtoupper($chave));
    if ($env === false || $env === '') { continue; }
    $cfg[$chave] = is_int($valor) ? (int) $env : (is_array($valor) ? $valor : $env);
}

// ---- 3. config/config.local.php ----
$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $extra = require $local;
    if (is_array($extra)) { $cfg = array_replace($cfg, $extra); }
}

return $cfg;
