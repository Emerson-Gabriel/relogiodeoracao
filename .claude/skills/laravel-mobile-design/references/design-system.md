# Design System — tokens, tipografia, espaçamento e cores

Este arquivo orienta como definir e organizar a linguagem visual em CSS vanilla,
mobile-first, estendendo o que já existe em `brand-theme.css`/`adminator.css` sem
quebrá-los.

## Onde cada coisa mora

- **`adminator.css`** (ou o CSS de base do template): trate como intocável. É a
  fundação herdada. Não edite regras dele — sobrescreva de fora quando necessário.
- **`brand-theme.css`** (ou o CSS de marca): é aqui que vivem os **tokens do
  produto** e os ajustes de identidade. Novos custom properties e overrides de
  marca entram aqui.
- **Estilos novos de tela/componente**: crie um arquivo próprio (ex.:
  `resources/css/mobile.css`) e adicione-o ao pipeline no `webpack.mix.js`, em vez
  de inflar o CSS de marca com regras específicas de uma tela.

Nunca aponte o Blade para o arquivo-fonte: compile com Mix e use `mix()` para o
cache-busting funcionar (ver `accessibility-performance.md`).

## Custom properties (tokens)

Centralize os valores repetidos em custom properties no `:root`. Isso dá um ponto
único de verdade e permite tema/ajuste fino sem caçar valores soltos.

```css
:root {
  /* Cores de marca — ajuste aos valores reais lidos do projeto */
  --cor-primaria: #1a73e8;
  --cor-primaria-escura: #1257b0;
  --cor-secundaria: #00897b;

  /* Cores semânticas */
  --cor-sucesso: #2e7d32;
  --cor-erro: #c62828;
  --cor-alerta: #f9a825;
  --cor-info: #0277bd;

  /* Neutros (do mais claro ao mais escuro) */
  --neutro-0: #ffffff;
  --neutro-100: #f5f6f8;
  --neutro-300: #d9dce1;
  --neutro-600: #6b7280;
  --neutro-900: #1f2430;

  /* Texto */
  --texto-forte: var(--neutro-900);
  --texto-suave: var(--neutro-600);
  --texto-invertido: var(--neutro-0);

  /* Espaçamento — escala base 4px */
  --espaco-1: 0.25rem;  /* 4px  */
  --espaco-2: 0.5rem;   /* 8px  */
  --espaco-3: 0.75rem;  /* 12px */
  --espaco-4: 1rem;     /* 16px */
  --espaco-6: 1.5rem;   /* 24px */
  --espaco-8: 2rem;     /* 32px */

  /* Raio e sombra */
  --raio-sm: 6px;
  --raio-md: 10px;
  --raio-pill: 999px;
  --sombra-1: 0 1px 2px rgba(0,0,0,.08);
  --sombra-2: 0 4px 12px rgba(0,0,0,.12);

  /* Camadas (z-index) — evita números mágicos espalhados */
  --z-header: 100;
  --z-drawer: 200;
  --z-overlay: 190;
  --z-toast: 300;
}
```

Regras de ouro:

- **Antes de criar um token, procure um equivalente** já definido no CSS do projeto
  e reutilize o nome existente. Só invente nomes novos quando não houver.
- **Nunca crave um valor de cor/espaço solto** dentro de uma regra de componente se
  ele se repete — promova a token.
- Prefira nomes por **função** (`--texto-suave`) a nomes por **aparência**
  (`--cinza-claro`): sobrevivem a mudanças de tema.

## Tipografia mobile

No celular o texto precisa ser legível sem zoom. Comece a família pela stack de
sistema como fallback (rápida e nativa), e use `clamp()` para uma escala fluida que
cresce do celular para telas maiores.

```css
:root {
  --fonte-base: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                Helvetica, Arial, sans-serif;

  /* Escala fluida: min no celular, max no desktop */
  --fs-xs:  0.8125rem;                       /* 13px */
  --fs-sm:  0.875rem;                        /* 14px */
  --fs-md:  1rem;                            /* 16px — base */
  --fs-lg:  clamp(1.125rem, 1rem + 1vw, 1.375rem);
  --fs-xl:  clamp(1.375rem, 1.1rem + 2vw, 2rem);

  --lh-apertado: 1.25;
  --lh-normal: 1.5;
}

body {
  font-family: var(--fonte-base);
  font-size: var(--fs-md);
  line-height: var(--lh-normal);
  color: var(--texto-forte);
  -webkit-text-size-adjust: 100%; /* impede o iOS de reescalar texto sozinho */
}
```

- **Corpo de texto nunca abaixo de 16px** no mobile — além de legibilidade, campos
  de formulário com `font-size < 16px` fazem o iOS dar zoom automático ao focar.
- Limite a largura de leitura em blocos longos (`max-width: 65ch`).

## Espaçamento e layout mobile-first

Escreva o layout base para uma coluna estreita e deixe telas maiores entrarem por
`min-width`. Use os tokens de espaçamento em vez de números avulsos.

```css
/* Base = celular */
.pagina {
  padding: var(--espaco-4);
  display: flex;
  flex-direction: column;
  gap: var(--espaco-4);
}

/* Tablet para cima */
@media (min-width: 48rem) {   /* 768px */
  .pagina {
    padding: var(--espaco-6);
    max-width: 60rem;
    margin-inline: auto;
  }
}
```

**Breakpoints são uma convenção, não um token.** Custom properties não funcionam
dentro de `@media`. Padronize os valores como comentário no topo do CSS e use-os
sempre iguais:

```
/* Breakpoints do projeto:
   sm  = 30rem  (480px)
   md  = 48rem  (768px)
   lg  = 64rem  (1024px)
*/
```

Confirme os breakpoints já usados no CSS existente e siga-os — não introduza um
novo conjunto que conflite com o que `adminator.css` já assume.

## Nomenclatura de classes

Siga a convenção que já estiver no projeto. Se não houver uma clara e você
introduzir componentes novos, adote um esquema simples e consistente e **prefixe**
para não colidir com classes do template:

```
.c-cartao            /* bloco  */
.c-cartao__titulo    /* elemento */
.c-cartao--destaque  /* modificador */
```

O prefixo (`c-`, ou a inicial do produto) deixa óbvio o que é código seu versus
herdado do Adminator, e facilita remover o template no futuro sem caçar dependências.

## Sobrescrever o template sem `!important`

Quando precisar ajustar algo do `adminator.css`, ganhe a especificidade pelo
contexto, não pela força bruta:

```css
/* Ruim: --------------------------------- */
.btn { border-radius: 0 !important; }

/* Bom: escopo pelo container da sua tela -- */
.tela-pdv .btn { border-radius: var(--raio-sm); }
```

Se `!important` parecer inevitável, quase sempre há um seletor mais específico do
template competindo — investigue-o antes de recorrer à força.
