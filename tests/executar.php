<?php
/**
 * ============================================================
 * FarmaPonto - Executor de testes (PHP puro, sem dependencias)
 * ============================================================
 * Uso:  php tests/executar.php
 * Sai com codigo 0 se tudo passou, 1 se alguma verificacao falhou.
 *
 * Nao precisa de Composer, PHPUnit nem internet: o sistema continua
 * 100% offline.
 */

require __DIR__ . '/../cli/bootstrap.php';

final class Testes {
    public static int $passes = 0;
    public static int $falhas = 0;
    public static string $grupo = '';
    /** @var array<int,string> */
    public static array $erros = [];
}

function grupo(string $nome): void {
    Testes::$grupo = $nome;
    echo "\n== {$nome} ==\n";
}

function afirmar(bool $condicao, string $descricao): void {
    if ($condicao) {
        Testes::$passes++;
        echo "  [ok]    {$descricao}\n";
        return;
    }
    Testes::$falhas++;
    Testes::$erros[] = Testes::$grupo . ' :: ' . $descricao;
    echo "  [FALHA] {$descricao}\n";
}

function afirmarIgual($esperado, $obtido, string $descricao): void {
    $igual = is_float($esperado) || is_float($obtido)
        ? abs((float) $esperado - (float) $obtido) < 0.005
        : $esperado === $obtido;
    if (!$igual) {
        $descricao .= sprintf(' (esperado %s, obtido %s)',
            var_export($esperado, true), var_export($obtido, true));
    }
    afirmar($igual, $descricao);
}

$ficheiros = glob(__DIR__ . '/*Test.php') ?: [];
sort($ficheiros);

echo "FarmaPonto - testes automaticos\n";
echo str_repeat('-', 54) . "\n";
foreach ($ficheiros as $f) { require $f; }

echo "\n" . str_repeat('-', 54) . "\n";
printf("Total: %d verificacoes | Passaram: %d | Falharam: %d\n",
    Testes::$passes + Testes::$falhas, Testes::$passes, Testes::$falhas);

if (Testes::$falhas > 0) {
    echo "\nFalhas:\n";
    foreach (Testes::$erros as $e) { echo " - {$e}\n"; }
    exit(1);
}
echo "Tudo certo.\n";
exit(0);
