<?php
/**
 * Testes das regras de assiduidade (app/services/RegrasAssiduidade.php).
 * Executar com: php tests/executar.php
 */

use App\Services\RegrasAssiduidade as R;

grupo('RegrasAssiduidade - horas');

afirmarIgual(1.0, R::horas(3600), '3600 segundos = 1 hora');
afirmarIgual(0.0, R::horas(-600), 'segundos negativos nao geram horas');
afirmarIgual(0.5, R::horas(1800), '1800 segundos = meia hora');

grupo('RegrasAssiduidade - atraso na entrada');

afirmarIgual(600, R::segundosAtraso('2026-01-15 08:10:00', '08:00'), 'entrada 10 minutos depois');
afirmarIgual(-300, R::segundosAtraso('2026-01-15 07:55:00', '08:00'), 'entrada 5 minutos antes da hora');
afirmar(R::atrasado(600, 5) === true, 'atraso de 10 min com tolerancia 5 conta');
afirmar(R::atrasado(240, 5) === false, 'atraso de 4 min dentro da tolerancia de 5 nao conta');
afirmar(R::atrasado(300, 5) === false, 'exactamente na tolerancia nao conta como atraso');
afirmar(R::atrasado(60, 0) === true, 'sem tolerancia qualquer atraso conta');

grupo('RegrasAssiduidade - saida antecipada');

afirmarIgual(900, R::segundosSaidaAntecipada('2026-01-15 16:45:00', '17:00'), 'saiu 15 minutos antes');
afirmarIgual(-600, R::segundosSaidaAntecipada('2026-01-15 17:10:00', '17:00'), 'saiu depois da hora (nao e antecipada)');
afirmar(R::saiuCedo(900, 10) === true, 'saida 15 min antes com tolerancia 10 conta');
afirmar(R::saiuCedo(300, 10) === false, 'saida 5 min antes dentro da tolerancia nao conta');

grupo('RegrasAssiduidade - horas penalizadas');

afirmarIgual(2.5, R::horasPenalizadas(2.0, 0.5, true, true), 'atraso + saida antecipada');
afirmarIgual(2.0, R::horasPenalizadas(2.0, 0.5, true, false), 'saidas antecipadas desligadas');
afirmarIgual(0.5, R::horasPenalizadas(2.0, 0.5, false, true), 'atrasos desligados');
afirmarIgual(0.0, R::horasPenalizadas(2.0, 0.5, false, false), 'ambos desligados nao penalizam');

grupo('RegrasAssiduidade - escala de trabalho');

afirmar(R::trabalhaNoDia('2026-01-15', [1,2,3,4,5], []) === true, 'quinta-feira esta na escala de 2a a 6a');
afirmar(R::trabalhaNoDia('2026-01-18', [1,2,3,4,5], []) === false, 'domingo nao esta na escala de 2a a 6a');
afirmar(R::trabalhaNoDia('2026-01-18', [1,2,3,4,5], [18,19]) === true, 'a escala do mes tem prioridade sobre os dias da semana');
afirmar(R::trabalhaNoDia('2026-01-15', [1,2,3,4,5], [18,19]) === false, 'dia fora da escala do mes nao conta');

grupo('RegrasAssiduidade - formato de horas');

afirmarIgual('01:00:00', R::hms(1.0), 'uma hora');
afirmarIgual('00:30:00', R::hms(0.5), 'meia hora');
afirmarIgual('00:00:00', R::hms(-3), 'valor negativo mostra zero');
afirmarIgual('08:15:30', R::hms(8 + 15/60 + 30/3600), 'horas, minutos e segundos');
afirmar(!str_contains(R::hms(0.99999), ':60'), 'nunca mostra 60 minutos ou 60 segundos');
