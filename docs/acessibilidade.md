# FarmaPonto — Acessibilidade

Objectivo: qualquer pessoa consegue usar o FarmaPonto, incluindo quem navega
só com o teclado, quem usa um leitor de ecrã e quem tem baixa visão.
Referência: WCAG 2.1 nível AA.

## O que já está feito

| Medida | Onde |
|---|---|
| Link "Saltar para o conteúdo" (aparece ao premir Tab) | topo de todas as páginas |
| Conteúdo principal identificado (`main#conteudo`) | layout comum |
| Contorno de foco sempre visível ao navegar com teclado | estilos do layout |
| Item do menu actual anunciado (`aria-current="page"`) | menu lateral |
| Ícones decorativos escondidos dos leitores de ecrã (`aria-hidden`) | menu e botões |
| Alvos de toque de pelo menos 44px em ecrãs pequenos | estilos do layout |
| Texto alternativo nas fotografias de ponto | histórico e detalhe |
| Barras de progresso com o valor também em texto | Minha Assiduidade |
| Avisos e confirmações anunciados (`role="status"`, `aria-live`) | painel e formulários |

## Como verificar (5 minutos, sem instalar nada)

1. **Só com teclado**: abrir `/login`, percorrer tudo com `Tab`, activar com
   `Enter`/`Espaço`. Nunca se pode perder de vista onde está o foco.
2. **Ampliar**: `Ctrl` + `+` até 200%. Nenhum texto pode ficar cortado nem
   obrigar a arrastar para os lados.
3. **Leitor de ecrã**: Windows → `Ctrl`+`Win`+`Enter` (Narrador). Ouvir o
   nome dos botões: devem dizer o que fazem ("Marcar entrada"), não "botão".
4. **Sem cor**: imprimir em tons de cinza uma página de relatório. Faltas e
   atrasos têm de continuar distinguíveis pelo texto, não só pela cor.

## Regras para quem alterar o código

- Toda a imagem leva `alt`. Se for decorativa: `alt=""`.
- Todo o campo leva `<label for="...">`; não usar apenas o texto de exemplo.
- Erros de formulário: mensagem junto ao campo **e** ligada por
  `aria-describedby`, com resumo no topo.
- Não transmitir informação apenas por cor: juntar texto ou ícone.
- Modais: o foco entra no modal, não sai enquanto está aberto e `Esc` fecha.
- Contraste mínimo 4,5:1 para texto normal (o verde `#047857` sobre branco cumpre).

## Ainda por fazer

- Revisão do contraste dos textos cinza claro em tabelas densas.
- Teste completo com leitor de ecrã no terminal de marcação rápida.
