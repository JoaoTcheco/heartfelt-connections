<?php
/**
 * ============================================================
 * FarmaPonto - Saude do sistema
 * ============================================================
 * Responsabilidade: reunir num sitio unico o estado tecnico do
 * sistema (base de dados, tabelas, espaco em disco, ultima copia
 * de seguranca, erros recentes). Serve a rota /saude (JSON), a
 * pagina /diagnostico e os avisos do Painel.
 *
 * Comunica com: Database, Backup, Registador, Armazenamento.
 */

namespace App\Helpers;

use App\Core\Armazenamento;
use App\Core\Database;
use App\Core\Registador;
use Throwable;

final class Saude {

    /** @return array<string,mixed> */
    public static function estado(): array {
        $bd = ['ok' => false, 'versao' => null, 'tabelas' => 0, 'tamanho_mb' => 0.0, 'latencia_ms' => null];
        try {
            $t0 = microtime(true);
            $pdo = Database::pdo();
            $bd['versao'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $bd['latencia_ms'] = round((microtime(true) - $t0) * 1000, 2);
            $r = Database::dimensao();
            $bd['tabelas']    = (int) $r['n'];
            $bd['tamanho_mb'] = (float) $r['mb'];
            $bd['ok'] = true;
        } catch (Throwable $e) {
            $bd['erro'] = $e->getMessage();
        }

        $ultima = null;
        try { $ultima = Backup::ultima(); } catch (Throwable) { $ultima = null; }
        $horasSemCopia = $ultima ? round((time() - $ultima['em']) / 3600, 1) : null;

        $eventos = [];
        try { $eventos = Registador::contagens(24); } catch (Throwable) { $eventos = []; }

        $livreMb = round(Armazenamento::espacoLivre() / 1048576, 1);

        $avisos = [];
        if (!$bd['ok'])                          { $avisos[] = 'A base de dados não responde.'; }
        if ($ultima === null)                    { $avisos[] = 'Nunca foi criada uma cópia de segurança.'; }
        elseif ($horasSemCopia > 48)             { $avisos[] = 'A última cópia de segurança tem mais de 2 dias.'; }
        if ($livreMb > 0 && $livreMb < 500)      { $avisos[] = 'Espaço em disco abaixo de 500 MB.'; }
        if (($eventos['erro'] ?? 0) > 0)         { $avisos[] = ($eventos['erro']) . ' erro(s) registados nas últimas 24 horas.'; }

        $estado = $bd['ok'] ? ($avisos ? 'atencao' : 'ok') : 'falha';

        return [
            'estado'          => $estado,
            'em'              => date('c'),
            'php'             => PHP_VERSION,
            'bd'              => $bd,
            'tabelas'         => $bd['tabelas'],
            'disco_livre_mb'  => $livreMb,
            'fotografias_mb'  => round(Armazenamento::tamanhoPasta('selfies') / 1048576, 2),
            'copias_mb'       => round(Armazenamento::tamanhoPasta('backups') / 1048576, 2),
            'ultima_copia'    => $ultima ? date('Y-m-d H:i:s', $ultima['em']) : null,
            'horas_sem_copia' => $horasSemCopia,
            'eventos_24h'     => $eventos,
            'avisos'          => $avisos,
        ];
    }
}
