<?php
/**
 * Testes do calculo do vencimento (app/services/CalculoSalarial.php).
 * Executar com: php tests/executar.php
 */

use App\Services\CalculoSalarial as C;

grupo('CalculoSalarial - valores unitarios');

afirmarIgual(1000.0, C::valorDia(22000, 22), 'valor/dia = base / dias programados');
afirmarIgual(500.0, C::valorDia(15000, 0, 500), 'sem dias programados usa o salario diario');
afirmarIgual(1000.0, C::valorDia(30000, 0), 'sem dias nem diario divide por 30');
afirmarIgual(125.0, C::valorHora(1000, 8), 'valor/hora = valor/dia / carga diaria');
afirmarIgual(0.0, C::valorHora(1000, 0), 'carga diaria zero nao divide por zero');

grupo('CalculoSalarial - salario do periodo');

afirmarIgual(22000.0, C::salarioPeriodo('mes_completo', 22000, 10, 10, 1000), 'mes completo paga a base');
afirmarIgual(10000.0, C::salarioPeriodo('proporcional', 22000, 10, 10, 1000), 'proporcional paga os dias pagos');
afirmarIgual(15000.0, C::salarioPeriodo('ate_dia', 22000, 22, 15, 1000), 'ate ao dia paga os dias programados ate ao corte');

grupo('CalculoSalarial - cortes');

// 22 dias, 1000/dia, 125/hora. Uma falta e 2 horas de atraso.
$c = C::cortes(22000, 1000, 125, 1, 2.0, 0, 21, 0, 0, true, 'mes_completo');
afirmarIgual(1000.0, $c['corte_falta'], 'uma falta corta um dia');
afirmarIgual(250.0, $c['corte_atraso'], '2 horas de atraso cortam 2 x valor/hora');
afirmarIgual(1250.0, $c['corte_bruto'], 'corte bruto = falta + atraso');
afirmar($c['corte_limitado'] === false, 'corte normal nao e limitado');

// Faltas desligadas nas opcoes do relatorio
$c2 = C::cortes(22000, 1000, 125, 3, 0.0, 0, 19, 0, 0, false, 'mes_completo');
afirmarIgual(0.0, $c2['corte_falta'], 'com faltas desligadas nao ha corte por falta');

// Tecto: o funcionario recebe sempre os dias que trabalhou por inteiro.
// 20 faltas + 3 dias sem vencimento dariam 23.000 de corte, mas so 2 dias
// foram trabalhados, logo o corte para nos 20.000 disponiveis.
$c3 = C::cortes(22000, 1000, 125, 20, 0.0, 3, 2, 0, 0, true, 'mes_completo');
afirmarIgual(20000.0, $c3['total_corte'], 'o corte para no maximo permitido');
afirmar($c3['corte_limitado'] === true, 'corte excessivo e marcado como limitado');
afirmarIgual(
    $c3['total_corte'],
    round($c3['corte_falta'] + $c3['corte_atraso'] + $c3['corte_sem_vencimento'], 2),
    'apos limitacao as parcelas somam o total'
);

// Dias sem vencimento (licenca/folga nao paga)
$c4 = C::cortes(22000, 1000, 125, 0, 0.0, 2, 20, 0, 0, true, 'mes_completo');
afirmarIgual(2000.0, $c4['corte_sem_vencimento'], 'dois dias sem vencimento cortam dois dias');
$c5 = C::cortes(20000, 1000, 125, 0, 0.0, 2, 20, 0, 0, true, 'proporcional');
afirmarIgual(0.0, $c5['corte_sem_vencimento'], 'na base proporcional os dias sem vencimento ja nao entram no salario');

grupo('CalculoSalarial - liquido');

afirmarIgual(20750.0, C::liquido(22000, 1250, 0), 'liquido = salario - cortes');
afirmarIgual(21000.0, C::liquido(22000, 1250, 250), 'horas extra somam ao liquido');
afirmarIgual(300.0, C::liquido(1000, 5000, 300), 'liquido nunca fica negativo (so as extras)');

grupo('CalculoSalarial - corte de um dia');

$dia = ['estado' => 'falta', 'pago' => false];
afirmarIgual(1000.0, C::corteDoDia($dia, 1000, 125, true, true, true, 'mes_completo'), 'dia de falta corta um dia');
afirmarIgual(0.0, C::corteDoDia($dia, 1000, 125, false, true, true, 'mes_completo'), 'faltas desligadas nao cortam o dia');

$diaAtraso = ['estado' => 'presente', 'pago' => true, 'horas_atraso' => 1.5, 'horas_cedo' => 0.5];
afirmarIgual(250.0, C::corteDoDia($diaAtraso, 1000, 125, true, true, true, 'mes_completo'), 'atraso + saida antecipada cortam 2h');
afirmarIgual(187.5, C::corteDoDia($diaAtraso, 1000, 125, true, true, false, 'mes_completo'), 'saidas antecipadas desligadas cortam so o atraso');

$diaFerias = ['estado' => 'ferias', 'pago' => true];
afirmarIgual(0.0, C::corteDoDia($diaFerias, 1000, 125, true, true, true, 'mes_completo'), 'ferias nunca cortam');

$diaLicenca = ['estado' => 'licenca', 'pago' => false];
afirmarIgual(1000.0, C::corteDoDia($diaLicenca, 1000, 125, true, true, true, 'mes_completo'), 'licenca sem vencimento corta o dia');
afirmarIgual(0.0, C::corteDoDia($diaLicenca, 1000, 125, true, true, true, 'proporcional'), 'na base proporcional a licenca nao corta duas vezes');
