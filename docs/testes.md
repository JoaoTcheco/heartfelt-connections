# FarmaPonto — Testes

## Testes automáticos

```bash
php tests/executar.php
```

Correm sem base de dados, sem internet e sem instalar nada. No fim mostram
quantas verificações passaram; se alguma falhar, o comando termina com erro
(útil para agendar). Ficheiros:

| Ficheiro | O que garante |
|---|---|
| `tests/RegrasAssiduidadeTest.php` | tolerância de atraso, saída antecipada, escala de trabalho, conversão de horas |
| `tests/CalculoSalarialTest.php` | valor do dia e da hora, salário do período, cortes, tecto de corte, líquido, corte por dia |

Para acrescentar testes, criar `tests/OQueQuiserTest.php` e usar
`grupo()`, `afirmar()` e `afirmarIgual()` — o executor descobre o ficheiro
sozinho.

**Regra**: sempre que uma regra de cálculo mudar, o teste muda com ela na
mesma alteração. É esta a rede que impede um erro num recibo.

## Verificação manual antes de entregar (10 minutos)

1. Entrar como administrador → o painel abre com números do mês actual.
2. **Funcionários** → criar um funcionário de teste, editar e desactivar.
3. **Marcar Presença** → entrada e saída com fotografia; a foto abre no histórico.
4. Terminal `/quickpunch` → marcar com PIN; não deve mostrar menu lateral.
5. **Relatórios** → escolher o mês, trocar a base de pagamento, exportar CSV e PDF.
6. Abrir o detalhe de um funcionário → a soma dos cortes diários iguala o total.
7. **Minha Assiduidade** (entrando como funcionário) → só mostra os próprios dados.
8. **Cópias de Segurança** → criar cópia, descarregar, ver o tamanho > 0.
9. **Diagnóstico** e `/saude` → tudo verde.
10. **Lixeira** → eliminar um registo, recuperar, confirmar que voltou.
