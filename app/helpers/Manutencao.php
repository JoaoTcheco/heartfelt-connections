<?php
/**
 * ============================================================
 * FarmaPonto - Manutencao automatica
 * ============================================================
 * Responsabilidade: correr, no maximo uma vez por dia e sem atrasar
 * o utilizador, as tarefas de casa do sistema:
 *   - copia de seguranca diaria da base de dados
 *   - purga de fotografias antigas (retencao configurada)
 *   - resumo e purga dos registos de actividade antigos
 *   - limpeza de eventos/metricas e da lixeira
 *
 * Sem cron nem servicos externos: o marcador em storage/cache
 * garante uma execucao por dia. O agendamento no sistema
 * operativo (cli/backup.php) continua a ser o caminho recomendado.
 *
 * Comunica com: index.php, Backup, Selfie, Log, Registador, Lixeira.
 */

namespace App\Helpers;

use App\Core\Armazenamento;
use App\Core\Registador;
use App\Models\Config;
use App\Models\Lixeira;
use App\Models\Log;
use App\Models\Selfie;
use Throwable;

final class Manutencao {

    /** Corre as tarefas do dia (idempotente e silenciosa). */
    public static function diaria(): void {
        if (PHP_SAPI === 'cli') { return; }
        // Não interferir com marcações de ponto nem com envios de formulário.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { return; }

        $marcador = Armazenamento::garantirPasta('cache') . '/manutencao-' . date('Y-m-d') . '.ok';
        if (is_file($marcador)) { return; }
        // Escrever o marcador ANTES de trabalhar evita que dois pedidos
        // simultâneos façam a mesma tarefa duas vezes.
        if (@file_put_contents($marcador, date('c'), LOCK_EX) === false) { return; }
        self::limparMarcadoresAntigos();

        self::executar();
    }

    /** Executa as tarefas e devolve o relatório (usado tambem por cli/manutencao.php). */
    public static function executar(): array {
        $relatorio = [];

        try {
            $r = Backup::criarFicheiroSql();
            $relatorio['copia'] = $r['ficheiro'];
        } catch (Throwable $e) {
            Registador::erro('Cópia diária automática falhou: ' . $e->getMessage(), [], 'manutencao');
        }

        try { $relatorio['fotografias_removidas'] = (new Selfie())->purgaAutomatica(); }
        catch (Throwable $e) { Registador::aviso('Purga de fotografias falhou: ' . $e->getMessage(), [], 'manutencao'); }

        try {
            $dias = (int) (new Config())->get('logs_retencao_dias', '365');
            $relatorio['logs_resumidos'] = Log::resumirEPurgar($dias);
        } catch (Throwable $e) {
            Registador::aviso('Resumo dos registos de actividade falhou: ' . $e->getMessage(), [], 'manutencao');
        }

        try { $relatorio['eventos_removidos'] = Registador::purgar(30); } catch (Throwable) {}
        try { $relatorio['lixeira_purgada'] = (new Lixeira())->purgar(60); } catch (Throwable) {}

        Registador::info('Manutenção diária concluída', $relatorio, 'manutencao');
        return $relatorio;
    }

    /** Mantém apenas os marcadores dos últimos 7 dias. */
    private static function limparMarcadoresAntigos(): void {
        foreach (glob(Armazenamento::caminho('cache') . '/manutencao-*.ok') ?: [] as $f) {
            if (filemtime($f) < time() - 7 * 86400) { @unlink($f); }
        }
    }
}
