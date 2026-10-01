---
name: laravel-mobile-design
description: >-
  Use sempre que o trabalho envolver a interface mobile de um sistema Laravel com
  Blade puro, CSS próprio (ex.: brand-theme.css, adminator.css), JavaScript vanilla
  (sem jQuery/Vue/React) e compilação por Laravel Mix, especialmente quando for um
  PWA instalável. Cobre design system mobile-first (cores, tipografia, espaçamento),
  padrões de UX mobile (navegação, alvos de toque, gestos), acessibilidade e
  performance no celular, e a camada PWA (manifest, service worker, offline, prompt
  de instalação, telas de PDV/toque). Dispare para qualquer pedido do tipo "deixar
  responsivo", "ajustar no celular", "criar tela mobile", "arrumar o layout no
  telefone", "melhorar o toque", "transformar em PWA" ou "tela de PDV" — mesmo que
  a palavra "design" não apareça. Não é para telas puramente desktop nem para stacks
  com Tailwind, Livewire, Inertia, Vue ou React.
---

# Design Mobile para Laravel (Blade + CSS/JS vanilla + PWA)

Esta skill orienta o trabalho de interface mobile em sistemas Laravel que usam
**Blade puro, CSS escrito à mão e JavaScript vanilla**, compilados por Laravel Mix
e entregues como **PWA instalável**. O objetivo é produzir telas que funcionem bem
no celular respeitando a linguagem visual que **já existe no projeto**, sem
introduzir dependências novas nem reescrever o que já funciona.

## Princípio número um: leia antes de escrever

O erro mais caro nesta stack é o Claude inventar cores, espaçamentos, breakpoints
ou componentes que não batem com o CSS existente. O resultado é uma tela que parece
"colada" de outro sistema. **Antes de tocar em qualquer CSS ou Blade, mapeie a
linguagem visual atual.** Só depois estenda-a.

Faça este reconhecimento no início de toda tarefa:

1. **Encontre os pontos de entrada de CSS/JS.** Leia `webpack.mix.js` (ou
   `vite.config.js`, se for o caso) para descobrir quais arquivos são compilados e
   para onde vão. Não presuma os nomes — confirme.
2. **Leia o CSS de marca e o de base.** Tipicamente `brand-theme.css` (identidade
   visual do produto) e `adminator.css` (base do template). Extraia daí:
   - custom properties já definidas (`--cor-primaria`, `--espaco-2`, etc.);
   - a paleta real (primária, secundária, sucesso/erro, neutros);
   - a escala tipográfica (família, tamanhos, pesos);
   - a escala de espaçamento e o raio de borda padrão;
   - os breakpoints já em uso nas media queries.
3. **Leia os layouts Blade.** Veja `resources/views/layouts/*` e um par de telas
   reais para entender a estrutura de `@yield`/`@section`, os includes de header e
   navegação, e onde os assets são referenciados (`mix()`).
4. **Leia o JS existente.** Confirme que é vanilla e entenda os padrões já usados
   (delegação de evento, `IntersectionObserver`, etc.) para seguir o mesmo estilo.

Registre o que encontrou (tokens, breakpoints, componentes reutilizáveis) antes de
propor mudanças. Se um token que você precisa **não existe**, crie-o como custom
property no lugar certo (ver `references/design-system.md`) em vez de cravar um
valor solto no meio de uma regra.

## Regras invioláveis desta stack

- **Sem framework de CSS ou JS novo.** Nada de Tailwind, Bootstrap adicional além
  do que já vier no template, jQuery, Alpine, Vue ou React. Se sentir vontade de
  puxar uma biblioteca, resolva com CSS/JS vanilla.
- **Mobile-first de verdade.** O CSS base descreve o celular; telas maiores entram
  por `@media (min-width: ...)`. Nunca escreva desktop primeiro e tente "desfazer"
  no mobile com `max-width`.
- **Estenda, não sobrescreva.** Novas regras convivem com `adminator.css` e
  `brand-theme.css`. Evite `!important`; se precisar dele, quase sempre há um
  problema de especificidade a resolver antes.
- **Compile e versione.** Toda alteração de asset passa por `npm run dev`/`prod`
  (Laravel Mix) e é referenciada com `mix()` no Blade, para o cache-busting
  funcionar. Nunca aponte para o arquivo-fonte direto.
- **Toque em primeiro lugar.** Alvos clicáveis com no mínimo ~44×44px, sem depender
  de `:hover` para nada essencial, respeitando as safe areas do aparelho.

## Fluxo de trabalho para uma tarefa de design mobile

Trabalhe em etapas e confirme com o usuário ao final de cada uma antes de seguir —
principalmente antes de compilar assets ou mexer em vários arquivos.

1. **Mapeie o existente** (seção acima). Não pule.
2. **Defina o alvo com precisão.** Qual tela, qual comportamento no celular, quais
   estados (vazio, carregando, erro, offline). Se o pedido for vago, alinhe o
   escopo antes de codar.
3. **Desenhe com os tokens do projeto.** Escolha cores, espaços e tipos a partir do
   que já existe. Se faltar algo, proponha o token novo e onde ele entra.
4. **Escreva o CSS mobile-first**, seguindo a organização e a nomenclatura já
   usadas no projeto (ver `references/design-system.md`).
5. **Implemente a UX mobile** — navegação, formulários, alvos de toque e gestos em
   JS vanilla (ver `references/mobile-ux.md`).
6. **Garanta acessibilidade e performance** — HTML semântico no Blade, contraste,
   foco visível, peso de assets sob controle (ver `references/accessibility-performance.md`).
7. **Cuide da camada PWA** quando a tarefa tocar em instalação, offline, ícones ou
   comportamento standalone (ver `references/pwa.md`).
8. **Compile, versione e valide.** Rode o build do Mix, confira em viewport de
   celular (incluindo com notch), teste toque e — se for PWA — o comportamento
   offline. Entregue os arquivos completos alterados.

## O que cada arquivo de referência cobre

Leia o arquivo relevante quando a tarefa entrar naquele território — não é preciso
ler todos de uma vez.

- **`references/design-system.md`** — como definir e organizar custom properties,
  paleta, escala tipográfica fluida, escala de espaçamento, breakpoints e a
  convenção de nomes de classe. Leia ao criar ou ajustar tokens e estilos base.
- **`references/mobile-ux.md`** — padrões de navegação mobile (barra inferior,
  off-canvas, header compacto), formulários e teclado, alvos de toque, gestos e
  feedback, tudo em JS vanilla. Leia ao construir a interação de uma tela.
- **`references/accessibility-performance.md`** — semântica no Blade, ARIA quando
  necessário, contraste, foco, `prefers-reduced-motion`, além de critical CSS,
  lazy loading, peso de assets e versionamento com Mix. Leia ao revisar qualidade
  ou performance.
- **`references/pwa.md`** — manifest, service worker (cache e offline), prompt de
  instalação, ícones/maskable, safe-area-insets, `display: standalone`,
  particularidades de iOS e considerações para telas de PDV/toque. Leia em qualquer
  tarefa que envolva a natureza PWA do app.

## Entrega

Ao final, entregue o **conteúdo completo** de cada arquivo alterado (não apenas o
trecho mudado), diga o que precisa ser recompilado e como testar no celular. Se a
mudança afeta o comportamento offline ou a instalação do PWA, deixe explícito o que
o usuário deve verificar.
