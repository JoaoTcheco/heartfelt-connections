<?php
/**
 * ============================================================
 * FarmaPonto - Helper Metodo (origem da marcacao)
 * ============================================================
 * Centraliza a forma como a origem de um registo de ponto e
 * apresentada em todo o sistema (historico, relatorios, PDFs,
 * auditoria e recibos). Uma unica fonte de verdade.
 *
 * Metodos suportados:
 *   painel     -> marcado pelo proprio funcionario na area logada
 *   pin        -> terminal rapido com PIN de 4 digitos
 *   digital    -> terminal rapido com impressao digital
 *   sistema    -> gerado automaticamente (faltas, ferias, ajustes)
 */

namespace App\Helpers;

final class Metodo {

    public const PAINEL  = 'painel';
    public const PIN     = 'pin';
    public const DIGITAL = 'digital';
    public const SISTEMA = 'sistema';

    /** Todos os metodos validos (usar em validacoes e filtros). */
    public static function todos(): array {
        return [self::PAINEL, self::PIN, self::DIGITAL, self::SISTEMA];
    }

    public static function normalizar(?string $m): string {
        $m = strtolower(trim((string) $m));
        return in_array($m, self::todos(), true) ? $m : self::PAINEL;
    }

    /**
     * Rotulo legivel da origem.
     * $comFoto indica se o registo tem selfie associada.
     * $detalhe e o dedo usado (quando digital).
     */
    public static function rotulo(?string $metodo, bool $comFoto = false, ?string $detalhe = null): string {
        switch (self::normalizar($metodo)) {
            case self::DIGITAL:
                $dedo = trim((string) $detalhe);
                return $dedo !== '' ? 'Impressão digital · ' . $dedo : 'Impressão digital';
            case self::PIN:
                return $comFoto ? 'PIN + foto' : 'PIN (sem foto)';
            case self::SISTEMA:
                return 'Sistema (automático)';
            default:
                return $comFoto ? 'Painel + foto' : 'Painel (sem foto)';
        }
    }

    /** Rotulo curto, sem o dedo (para colunas estreitas e graficos). */
    public static function rotuloCurto(?string $metodo): string {
        return match (self::normalizar($metodo)) {
            self::DIGITAL => 'Impressão digital',
            self::PIN     => 'PIN',
            self::SISTEMA => 'Sistema',
            default       => 'Painel',
        };
    }

    /** Icone Font Awesome correspondente. */
    public static function icone(?string $metodo): string {
        return match (self::normalizar($metodo)) {
            self::DIGITAL => 'fa-solid fa-fingerprint',
            self::PIN     => 'fa-solid fa-keyboard',
            self::SISTEMA => 'fa-solid fa-gear',
            default       => 'fa-solid fa-desktop',
        };
    }

    /** Classes Tailwind do badge. */
    public static function classe(?string $metodo): string {
        return match (self::normalizar($metodo)) {
            self::DIGITAL => 'bg-indigo-100 text-indigo-700',
            self::PIN     => 'bg-amber-100 text-amber-700',
            self::SISTEMA => 'bg-gray-100 text-gray-600',
            default       => 'bg-sky-100 text-sky-700',
        };
    }

    /**
     * Deriva a origem a partir do nome de uma accao de log
     * (usado na Auditoria, que trabalha sobre a tabela `logs`).
     * Devolve null quando a accao nao e uma marcacao de ponto.
     */
    public static function daAcao(?string $acao): ?string {
        $a = strtolower((string) $acao);
        if (str_starts_with($a, 'digital_'))    { return self::DIGITAL; }
        if (str_starts_with($a, 'quickpunch_')) { return self::PIN; }
        if ($a === 'registo')                   { return self::PAINEL; }
        if (str_starts_with($a, 'falta_') || $a === 'processar_faltas') { return self::SISTEMA; }
        return null;
    }
}
