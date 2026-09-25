<?php
/**
 * ============================================================
 * FarmaPonto - Invariantes (rede de seguranca do calculo)
 * ============================================================
 * Responsabilidade: verificar, em cada calculo salarial, que os
 * numeros respeitam as regras que nunca podem ser violadas
 * (ex.: o liquido nunca e negativo, os dias contados nunca excedem
 * os dias programados). Se algo falhar, o problema fica registado
 * em /diagnostico em vez de sair silenciosamente num recibo.
 *
 * Comunica com: RelatoriosController::calcular(), Registador, testes.
 */

namespace App\Helpers;

use App\Core\Registador;

final class Invariantes {

    private const TOLERANCIA = 0.02; // centavos de arredondamento

    /**
     * @param array<string,mixed> $l Linha do motor de calculo
     * @return array<int,string> Lista de problemas (vazia = tudo certo)
     */
    public static function verificarLinhaSalarial(array $l): array {
        $p = [];
        $nome = (string) ($l['nome'] ?? ('#' . ($l['id'] ?? '?')));
        $num = static fn($k) => (float) ($l[$k] ?? 0);

        if ($num('salario') < 0)      { $p[] = "{$nome}: salário negativo"; }
        if ($num('liquido') < -self::TOLERANCIA) { $p[] = "{$nome}: líquido negativo"; }
        if ($num('total_corte') < -self::TOLERANCIA) { $p[] = "{$nome}: corte negativo"; }
        if ($num('total_corte') > $num('salario') + $num('ganho_extra') + self::TOLERANCIA) {
            $p[] = "{$nome}: corte maior do que o salário";
        }
        // O liquido fecha sempre com o salario DO PERIODO (que depende da base
        // escolhida no relatorio), nao com o salario base do contrato.
        $esperado = max(0.0, $num('salario') - $num('total_corte')) + $num('ganho_extra');
        if (abs($esperado - $num('liquido')) > 0.05) {
            $p[] = sprintf('%s: líquido (%.2f) não fecha com base - cortes + extras (%.2f)',
                $nome, $num('liquido'), $esperado);
        }
        if ($num('dias_avaliados') > $num('dias_programados') + self::TOLERANCIA) {
            $p[] = "{$nome}: dias avaliados acima dos dias programados";
        }
        foreach (['faltas', 'atrasos', 'saidas_cedo', 'dias_trabalhados', 'ferias'] as $k) {
            if ($num($k) < 0) { $p[] = "{$nome}: {$k} negativo"; }
        }
        return $p;
    }

    /**
     * Verifica todas as linhas de um calculo e registra os desvios.
     * @return array<int,string>
     */
    public static function verificarCalculo(array $calculo): array {
        $problemas = [];
        foreach (($calculo['linhas'] ?? []) as $linha) {
            foreach (self::verificarLinhaSalarial($linha) as $p) { $problemas[] = $p; }
        }
        if ($problemas) {
            Registador::aviso('Invariantes do cálculo salarial violadas', [
                'total'     => count($problemas),
                'problemas' => array_slice($problemas, 0, 10),
                'periodo'   => ($calculo['de'] ?? '') . ' a ' . ($calculo['ate'] ?? ''),
            ], 'calculo');
        }
        return $problemas;
    }
}
