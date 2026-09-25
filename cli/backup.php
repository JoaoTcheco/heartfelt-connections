<?php
/**
 * ============================================================
 * FarmaPonto - cli/backup.php
 * ============================================================
 * Cria uma copia de seguranca da base de dados e roda as antigas.
 *
 * Uso:
 *   php cli/backup.php            (copia .sql)
 *   php cli/backup.php --completo (copia .zip com fotografias)
 *
 * Agendar (Linux, todos os dias as 23:30):
 *   30 23 * * * /usr/bin/php /caminho/farmaponto/cli/backup.php >> /caminho/farmaponto/storage/backup.log 2>&1
 * Agendar (Windows/XAMPP): Agendador de Tarefas -> php.exe cli\backup.php
 */

require __DIR__ . '/bootstrap.php';

use App\Helpers\Backup;

try {
    $completo = in_array('--completo', $argv, true);
    $r = $completo ? Backup::criarZipCompleto() : Backup::criarFicheiroSql();
    echo date('[Y-m-d H:i:s] ') . 'Copia criada: ' . $r['ficheiro']
        . ' (' . Backup::tamanho($r['bytes']) . ")\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, date('[Y-m-d H:i:s] ') . 'FALHOU: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
