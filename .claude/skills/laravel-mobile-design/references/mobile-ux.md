# UX Mobile — navegação, toque, formulários e gestos

Padrões de interação para telas mobile em Blade + JS vanilla. A meta é uma tela que
se opera com o polegar, sem depender de `:hover` e sem atraso no toque.

## Alvos de toque

- **Mínimo ~44×44px** de área clicável (recomendação de acessibilidade mobile).
  Ícones pequenos precisam de padding para atingir esse tamanho.
- **Espaçe** alvos vizinhos (≥ 8px) para reduzir toque errado.
- Se o visual precisa ser pequeno, expanda só a **área de toque**:

```css
.icone-acao {
  position: relative;
}
.icone-acao::after {   /* área de toque invisível de 44px */
  content: "";
  position: absolute;
  inset: -12px;
}
```

- **Elimine o atraso de 300ms** e o zoom por duplo-toque com `touch-action`:

```css
button, a, .clicavel { touch-action: manipulation; }
```

## Feedback de toque sem `:hover`

No celular `:hover` não existe de forma confiável. Use `:active` para resposta
imediata e limite `:hover` a ponteiros de verdade:

```css
.btn:active { transform: scale(.98); }

@media (hover: hover) {
  .btn:hover { background: var(--cor-primaria-escura); }
}
```

Remova o realce cinza padrão do toque quando ele atrapalhar o visual:

```css
* { -webkit-tap-highlight-color: transparent; }
```

## Navegação mobile

Escolha **um** padrão principal por app e seja consistente.

### Barra inferior (thumb-friendly) — bom para apps de uso frequente/PDV

```html
<nav class="c-tabbar" aria-label="Navegação principal">
  <a href="/inicio" class="c-tabbar__item" aria-current="page">
    <svg aria-hidden="true">...</svg><span>Início</span>
  </a>
  <a href="/vendas" class="c-tabbar__item">
    <svg aria-hidden="true">...</svg><span>Vendas</span>
  </a>
</nav>
```

```css
.c-tabbar {
  position: fixed;
  inset-inline: 0;
  bottom: 0;
  z-index: var(--z-header);
  display: flex;
  background: var(--neutro-0);
  box-shadow: var(--sombra-2);
  /* respeita a barra de gestos / notch inferior */
  padding-bottom: env(safe-area-inset-bottom);
}
.c-tabbar__item {
  flex: 1;
  min-height: 56px;
  display: grid;
  place-items: center;
  gap: 2px;
  font-size: var(--fs-xs);
  color: var(--texto-suave);
}
.c-tabbar__item[aria-current="page"] { color: var(--cor-primaria); }
```

Marque a aba ativa com `aria-current="page"` (server-side, no Blade) — é acessível
e não depende de JS.

### Drawer off-canvas — bom quando há muitos itens

Menu que desliza pela lateral, com overlay. Em JS vanilla, cuide de: `aria-expanded`
no botão, fechar no `Esc`, fechar ao tocar no overlay, e devolver o foco ao botão.

```html
<button class="c-menu-btn" aria-controls="menu" aria-expanded="false">Menu</button>
<div class="c-overlay" hidden></div>
<aside id="menu" class="c-drawer" aria-hidden="true"> ... </aside>
```

```js
const btn = document.querySelector('.c-menu-btn');
const drawer = document.getElementById('menu');
const overlay = document.querySelector('.c-overlay');

function abrir() {
  drawer.classList.add('is-aberto');
  drawer.setAttribute('aria-hidden', 'false');
  overlay.hidden = false;
  btn.setAttribute('aria-expanded', 'true');
  drawer.querySelector('a, button')?.focus();   // move o foco pra dentro
}
function fechar() {
  drawer.classList.remove('is-aberto');
  drawer.setAttribute('aria-hidden', 'true');
  overlay.hidden = true;
  btn.setAttribute('aria-expanded', 'false');
  btn.focus();                                   // devolve o foco
}

btn.addEventListener('click', () =>
  btn.getAttribute('aria-expanded') === 'true' ? fechar() : abrir());
overlay.addEventListener('click', fechar);
document.addEventListener('keydown', e => { if (e.key === 'Escape') fechar(); });
```

```css
.c-drawer {
  position: fixed;
  inset-block: 0;
  inset-inline-start: 0;
  width: min(84vw, 320px);
  z-index: var(--z-drawer);
  transform: translateX(-100%);
  transition: transform .25s ease;
  padding-top: env(safe-area-inset-top);
}
.c-drawer.is-aberto { transform: translateX(0); }
.c-overlay {
  position: fixed; inset: 0; z-index: var(--z-overlay);
  background: rgba(0,0,0,.4);
}
```

### Header compacto e fixo

Cabeçalho fino que gruda no topo, respeitando o notch:

```css
.c-header {
  position: sticky;
  top: 0;
  z-index: var(--z-header);
  padding: var(--espaco-2) var(--espaco-4);
  padding-top: calc(var(--espaco-2) + env(safe-area-inset-top));
  background: var(--cor-primaria);
  color: var(--texto-invertido);
}
```

## Formulários no celular

Formulário é onde o mobile mais falha. Cuide destes pontos:

- **Tipo e teclado corretos** por campo, para o teclado certo aparecer:

```html
<input type="tel"    inputmode="numeric" autocomplete="tel">
<input type="email"  inputmode="email"   autocomplete="email">
<input type="text"   inputmode="decimal"> <!-- valores monetários -->
```

- **`font-size` ≥ 16px** nos inputs para o iOS não dar zoom ao focar.
- **Rótulos sempre associados** (`<label for>`), nunca só `placeholder`.
- **`autocomplete`** e `autocapitalize`/`autocorrect` ajustados ao campo
  (ex.: `autocapitalize="off"` em CPF/CNPJ/e-mail).
- **Botão de envio alcançável**: em formulários longos, considere um botão fixo na
  base (respeitando `safe-area-inset-bottom`) para não sumir atrás do teclado.
- **Evite pulos de layout** ao aparecer mensagem de erro: reserve espaço ou anime
  suavemente.

## Gestos e rolagem

Prefira o mínimo de gesto necessário — descoberta é difícil no toque. Quando usar,
faça com Pointer/Touch Events vanilla e listeners **passivos** para não travar a
rolagem:

```js
let x0 = null;
alvo.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; },
  { passive: true });
alvo.addEventListener('touchend', e => {
  if (x0 === null) return;
  const dx = e.changedTouches[0].clientX - x0;
  if (Math.abs(dx) > 60) dx < 0 ? proximo() : anterior();
  x0 = null;
}, { passive: true });
```

- **Contenha o scroll** de áreas roláveis para não "vazar" para a página:
  `overscroll-behavior: contain;`.
- **Rolagem suave em iOS** dentro de containers: `-webkit-overflow-scrolling: touch;`.
- Cuidado com pull-to-refresh nativo conflitando com gestos próprios; desabilite só
  onde realmente precisar (`overscroll-behavior-y: contain`).

## Estados da tela

Toda tela mobile precisa cobrir explicitamente:

- **Carregando** — skeleton ou spinner, sem deixar a tela "morta".
- **Vazio** — mensagem + ação (ex.: "Nenhuma venda hoje. Nova venda").
- **Erro** — mensagem clara e caminho de recuperação.
- **Offline** — banner discreto quando o PWA perde conexão (ver `pwa.md`).
- **Desabilitado/enviando** — trave o botão durante submit para evitar duplo envio.

## Considerações para PDV / telas de toque

- Alvos **maiores** que o mínimo (teclados numéricos, botões de produto) — o uso é
  rápido e às vezes com luva/pressa.
- **Zero dependência de hover**; feedback só por `:active`/estado.
- **`touch-action: manipulation`** global para resposta imediata.
- Considere **travar orientação** (via manifest, ver `pwa.md`) e **impedir seleção
  de texto acidental** em botões: `user-select: none;`.
- Evite navegação acidental para fora do app — no modo standalone isso é atenuado
  (ver `pwa.md`).
