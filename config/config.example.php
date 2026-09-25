<?php
/**
 * ============================================================
 * FarmaPonto - Configuracoes do Sistema (EXEMPLO)
 * ============================================================
 * Copie este ficheiro para config.php e ajuste os valores.
 * 
 * LOCAL (XAMPP):  db_user=root, db_pass=''
 * PRODUCAO:       Ajuste host/usuario/password da BD
 */

defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__));

return [
    'db_host'    => 'localhost',
    'db_name'    => 'farmaponto',
    'db_user'    => 'root',
    'db_pass'    => '',
    'db_charset' => 'utf8mb4',

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

    'selfie_max_size'     => 2 * 1024 * 1024,
    'selfie_allowed_types'=> ['image/jpeg', 'image/jpg'],
    'selfie_dir'          => ROOT_PATH . '/storage/selfies',

    'timezone'        => 'Africa/Maputo',
    'date_format'     => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i:s',
    'time_format'     => 'H:i',
];
