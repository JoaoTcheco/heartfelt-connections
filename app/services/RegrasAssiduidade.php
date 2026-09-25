<?php
/**
 * ============================================================
 * FarmaPonto - RegrasAssiduidade
 * ============================================================
 * Regras puras de assiduidade (sem base de dados, sem sessao).
 *
 * Porque existe este ficheiro:
 *  - as regras ficam num unico lugar, faceis de ler e de alterar;
 *  - podem ser testadas automaticamente (tests/RegrasAssiduidadeTest.php);
 *  - o motor de relatorios/salarios passa a chamar estas funcoes em vez de
 *    repetir a aritmetica em varios sitios.
 *
 * PHP puro, sem dependencias externas.
 */

namespace App\Services;

final class RegrasAssiduidade {

    /** Converte segundos em horas decimais (nunca negativo). */
    public static function horas(int $segundos): float {
        return $segundos > 0 ? round($segundos / 3600, 4) : 0.0;
    }

    /**
     * Diferenca, em segundos, entre a marcacao real e a hora prevista.
     * Positivo = atraso na entrada (ou saida antecipada, ver saidaAntecipada).
     */
    public static function segundosAtraso(string $entradaReal, string $horaPrevista): int {
        $real = strtotime($entradaReal);
        $prev = strtotime(substr($entradaReal, 0, 10) . ' ' . substr($horaPrevista, 0, 5));
        if ($real === false || $prev === false) { return 0; }
        return $real - $prev;
    }

    /** Segundos de saida antecipada (positivo quando saiu antes da hora). */
    public static function segundosSaidaAntecipada(string $saidaReal, string $horaPrevista): int {
        $real = strtotime($saidaReal);
        $prev = strtotime(substr($saidaReal, 0, 10) . ' ' . substr($horaPrevista, 0, 5));
        if ($real === false || $prev === false) { return 0; }
        return $prev - $real;
    }

    /**
     * Ha atraso a contar? Só acima da tolerancia definida nas configuracoes.
     * A tolerancia conta para o registo do "atraso", mas as horas perdidas
     * sao sempre medidas a partir do primeiro segundo (ver horas()).
     */
    public static function atrasado(int $segundos, int $toleranciaMin): bool {
        return $segundos > max(0, $toleranciaMin) * 60;
    }

    /** Igual a atrasado(), mas para a saida antes da hora. */
    public static function saiuCedo(int $segundos, int $toleranciaMin): bool {
        return self::atrasado($segundos, $toleranciaMin);
    }

    /**
     * Horas efectivamente penalizadas, respeitando as opcoes do relatorio
     * (o gestor pode desligar atrasos ou saidas antecipadas).
     */
    public static function horasPenalizadas(
        float $horasAtraso,
        float $horasCedo,
        bool $contarAtrasos,
        bool $contarSaidaCedo
    ): float {
        $total = 0.0;
        if ($contarAtrasos)   { $total += max(0.0, $horasAtraso); }
        if ($contarSaidaCedo) { $total += max(0.0, $horasCedo); }
        return round($total, 4);
    }

    /** Este dia esta na escala do funcionario? */
    public static function trabalhaNoDia(string $dia, array $diasSemana, array $diasDoMes): bool {
        if ($diasDoMes) {
            return in_array((int) date('j', strtotime($dia)), $diasDoMes, true);
        }
        return in_array((int) date('N', strtotime($dia)), $diasSemana, true);
    }

    /** Horas decimais em HH:MM:SS, sem produzir 60 minutos ou 60 segundos. */
    public static function hms(float $horasDecimais): string {
        $tot = (int) round(max(0.0, $horasDecimais) * 3600);
        return sprintf('%02d:%02d:%02d', intdiv($tot, 3600), intdiv($tot % 3600, 60), $tot % 60);
    }
}
