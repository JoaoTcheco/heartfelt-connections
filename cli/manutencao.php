<?php
/**
 * ============================================================
 * FarmaPonto - Manutencao pela linha de comandos
 * ============================================================
 * Corre as tarefas de casa do sistema (copia de seguranca, purga de
 * fotografias antigas, resumo dos registos de actividade, limpeza da
 * lixeira) sem depender de ninguem abrir o navegador.
 *
 * Uso:      php cli/manutencao.php
 * Agendar:  (Linux)   0 2 * * *  php /caminho/farmaponto/cli/manutencao.php
 *           (Windows) Agendador de Tarefas -> php.exe cli\manutencao.php
 *
 * PHP puro, sem dependencias externas.
 */

require __DIR__ . '/bootstrap.php';

use App\Helpers\Manutencao;

$inicio = microtime(true);
echo "FarmaPonto - manutencao (" . date('Y-m-d H:i:s') . ")\n";

try {
    $relatorio = Manutencao::executar();
} catch (Throwable $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    exit(1);
}

foreach ($relatorio as $chave => $valor) {
    printf("  %-24s %s\n", $chave, is_scalar($valor) ? (string) $valor : json_encode($valor));
}
printf("Concluido em %.2f s\n", microtime(true) - $inicio);
exit(0);
