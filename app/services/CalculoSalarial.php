<?php
/**
 * ============================================================
 * FarmaPonto - CalculoSalarial
 * ============================================================
 * Aritmetica pura do vencimento: valores unitarios, cortes e tecto de corte.
 * Nao toca na base de dados nem na sessao, por isso pode ser testada
 * automaticamente (tests/CalculoSalarialTest.php).
 *
 * Principio que nunca muda: o funcionario recebe SEMPRE os dias que
 * trabalhou e os dias pagos por justificacao/ferias. Os cortes so podem
 * consumir a parte do salario acima desse piso.
 *
 * PHP puro, sem dependencias externas.
 */

namespace App\Services;

final class CalculoSalarial {

    /** Valor de um dia de trabalho: salario base / dias programados do mes. */
    public static function valorDia(float $salarioBase, int $diasProgramados, float $salarioDiario = 0.0): float {
        if ($diasProgramados > 0) {
            return round($salarioBase / $diasProgramados, 2);
        }
        if ($salarioDiario > 0) { return round($salarioDiario, 2); }
        return round($salarioBase / 30, 2);
    }

    /** Valor de uma hora: valor do dia / carga diaria. */
    public static function valorHora(float $valorDia, float $cargaDiaria): float {
        return $cargaDiaria > 0 ? round($valorDia / $cargaDiaria, 2) : 0.0;
    }

    /** Salario do periodo conforme a base escolhida no relatorio. */
    public static function salarioPeriodo(
        string $base,
        float $salarioBase,
        int $diasPagos,
        int $diasProgramadosAteCorte,
        float $valorDia
    ): float {
        if ($base === 'proporcional') { return round($diasPagos * $valorDia, 2); }
        if ($base === 'ate_dia')      { return round($diasProgramadosAteCorte * $valorDia, 2); }
        return round($salarioBase, 2);
    }

    /**
     * Cortes do periodo, com aplicacao do tecto.
     *
     * Devolve: corte_falta, corte_atraso, corte_sem_vencimento, corte_bruto,
     * total_corte, corte_limitado, fator, piso, corte_maximo.
     */
    public static function cortes(
        float $salarioPeriodo,
        float $valorDia,
        float $valorHora,
        int $faltas,
        float $horasPenalizadas,
        int $diasSemVencimento,
        int $diasTrabalhados,
        int $faltasJustificadas,
        int $diasFerias,
        bool $contarFaltas,
        string $base
    ): array {
        $corteFalta  = $contarFaltas ? round($faltas * $valorDia, 2) : 0.0;
        $corteAtraso = round($horasPenalizadas * $valorHora, 2);
        $corteSemVenc = $base === 'mes_completo' ? round($diasSemVencimento * $valorDia, 2) : 0.0;

        $bruto = round($corteFalta + $corteAtraso + $corteSemVenc, 2);

        // Piso garantido: os dias trabalhados, os justificados e as ferias.
        // Aos dias trabalhados retira-se apenas o tempo que o funcionario
        // faltou nesses mesmos dias (atrasos e saidas antecipadas), para que
        // essa penalizacao continue a produzir efeito sem nunca poder comer
        // os dias em que ele esteve presente por inteiro.
        $piso = round(
            ($diasTrabalhados + $faltasJustificadas + $diasFerias) * $valorDia
            - max(0.0, $horasPenalizadas) * $valorHora,
            2
        );
        $piso = max(0.0, min($piso, $salarioPeriodo));
        $corteMaximo = max(0.0, round($salarioPeriodo - $piso, 2));

        $total = min($bruto, $corteMaximo);
        $limitado = $total < $bruto;
        $fator = $bruto > 0 ? $total / $bruto : 0.0;

        if ($limitado) {
            $corteFalta  = round($corteFalta * $fator, 2);
            $corteAtraso = round($corteAtraso * $fator, 2);
            $corteSemVenc = round($total - $corteFalta - $corteAtraso, 2);
        }

        return [
            'corte_falta'          => $corteFalta,
            'corte_atraso'         => $corteAtraso,
            'corte_sem_vencimento' => $corteSemVenc,
            'corte_bruto'          => $bruto,
            'total_corte'          => round($total, 2),
            'corte_limitado'       => $limitado,
            'fator'                => $fator,
            'piso'                 => $piso,
            'corte_maximo'         => $corteMaximo,
        ];
    }

    /** Valor liquido: salario do periodo - cortes + horas extra pagas. */
    public static function liquido(float $salarioPeriodo, float $totalCorte, float $ganhoExtra): float {
        return round(max(0.0, $salarioPeriodo - $totalCorte) + $ganhoExtra, 2);
    }

    /** Corte imputado a um unico dia (usado no detalhe dia-a-dia). */
    public static function corteDoDia(
        array $dia,
        float $valorDia,
        float $valorHora,
        bool $contarFaltas,
        bool $contarAtrasos,
        bool $contarSaidaCedo,
        string $base,
        float $fator = 1.0
    ): float {
        $corte = 0.0;
        $estado = (string) ($dia['estado'] ?? '');

        if ($estado === 'falta' && $contarFaltas) { $corte += $valorDia; }

        if (in_array($estado, ['folga', 'licenca'], true)
            && empty($dia['pago']) && $base === 'mes_completo') {
            $corte += $valorDia;
        }

        if ($estado === 'presente') {
            if ($contarAtrasos)   { $corte += (float) ($dia['horas_atraso'] ?? 0) * $valorHora; }
            if ($contarSaidaCedo) { $corte += (float) ($dia['horas_cedo'] ?? 0) * $valorHora; }
        }

        return round($corte * $fator, 2);
    }
}
