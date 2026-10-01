# Acessibilidade e Performance Mobile

Duas frentes que andam juntas no celular: uma tela acessível costuma ser mais leve
e mais robusta. Este arquivo cobre semântica no Blade, acessibilidade prática e o
que pesa na performance mobile — incluindo o pipeline do Laravel Mix.

## Semântica no Blade

Boa semântica é a acessibilidade "de graça" e reduz a necessidade de ARIA.

- Use **landmarks**: `<header>`, `<nav>`, `<main>`, `<footer>`. Um `<main>` por página.
- **Hierarquia de headings** sem pular níveis (`h1` → `h2` → `h3`).
- **`<button>` para ação, `<a>` para navegação.** Nunca uma `<div>` com `onclick`
  fazendo papel de botão — perde teclado, foco e leitor de tela.
- **Listas** para coleções (`<ul>`/`<ol>`), tabelas só para dados tabulares (com
  `<th scope>`).
- Aproveite os componentes Blade para padronizar o markup acessível:

```blade
{{-- resources/views/components/botao.blade.php --}}
<button {{ $attributes->merge(['class' => 'btn', 'type' => 'button']) }}>
  {{ $slot }}
</button>
```

## ARIA só quando o HTML nativo não dá conta

A primeira regra do ARIA é não usar ARIA se um elemento nativo resolve. Onde ele é
necessário no mobile:

- **Botões de ícone** sem texto visível precisam de rótulo:

```html
<button class="icone-acao" aria-label="Excluir item">
  <svg aria-hidden="true">...</svg>
</button>
```

- **Estados de componente**: `aria-expanded`, `aria-current`, `aria-hidden` em
  drawer/menu/abas (exemplos em `mobile-ux.md`).
- **Regiões dinâmicas** (toast, contador, resultado de busca): `aria-live="polite"`
  para o leitor anunciar mudanças sem roubar o foco.

## Contraste e cor

- Texto normal: contraste **≥ 4.5:1**; texto grande (≥ 24px ou 18.66px bold) e
  ícones essenciais: **≥ 3:1**.
- **Nunca comunique só por cor** (ex.: status "erro" em vermelho): acrescente
  ícone, texto ou padrão.
- Teste as cores reais lidas do `brand-theme.css`, não valores presumidos.

## Foco e teclado

- **Foco sempre visível.** Não remova o outline sem substituir por algo claro:

```css
:focus-visible {
  outline: 2px solid var(--cor-primaria);
  outline-offset: 2px;
}
```

- **Ordem de foco lógica** — siga a ordem do DOM; evite `tabindex` positivo.
- **Skip link** no topo para pular direto ao conteúdo:

```html
<a href="#conteudo" class="skip-link">Pular para o conteúdo</a>
```

- Ao abrir drawer/modal, **mova o foco para dentro** e devolva ao fechar
  (ver `mobile-ux.md`).

## Movimento

Respeite quem pediu menos animação — importante em telas de uso intenso:

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .001ms !important;
    transition-duration: .001ms !important;
  }
}
```

## Formulários acessíveis

- `<label for>` associado a todo campo.
- Mensagem de erro ligada ao campo por `aria-describedby`, e campo inválido com
  `aria-invalid="true"`:

```html
<label for="cpf">CPF</label>
<input id="cpf" inputmode="numeric" aria-describedby="cpf-erro" aria-invalid="true">
<p id="cpf-erro" class="campo-erro">CPF inválido.</p>
```

- Agrupe campos relacionados com `<fieldset>`/`<legend>` quando fizer sentido.

---

# Performance mobile

No celular a rede e a CPU são limitadas. Cada KB e cada reflow contam.

## Peso e entrega de assets (Laravel Mix)

- **Sempre compile e versione.** Em produção rode o build minificado e referencie
  os assets com `mix()` no Blade para cache-busting automático:

```blade
<link rel="stylesheet" href="{{ mix('css/app.css') }}">
<script src="{{ mix('js/app.js') }}" defer></script>
```

```js
// webpack.mix.js
mix.js('resources/js/app.js', 'public/js')
   .css('resources/css/app.css', 'public/css')
   .version();          // gera o hash pro mix() usar
```

- **Não referencie o arquivo-fonte** direto: quebra o cache-busting.
- **Divida CSS/JS por área** se o app for grande, carregando por página só o
  necessário, em vez de um bundle gigante em toda tela.
- Rode o build de produção antes de medir peso (`npm run prod`).

## CSS crítico e carregamento

- Mantenha o CSS **enxuto**; remova regras mortas do template que não usa.
- Para above-the-fold em páginas-chave, considere **inline do CSS crítico** e
  carregar o resto de forma diferida — só quando o ganho justificar a complexidade.
- **`<script defer>`** para não bloquear a renderização; evite `<script>` síncrono
  no `<head>`.

## Imagens

- **`loading="lazy"`** em imagens abaixo da dobra.
- **`srcset`/`sizes`** para servir a resolução certa ao tamanho de tela:

```html
<img src="/img/prod-400.jpg"
     srcset="/img/prod-400.jpg 400w, /img/prod-800.jpg 800w"
     sizes="(max-width: 30rem) 100vw, 400px"
     width="400" height="300" alt="Produto X" loading="lazy">
```

- **Sempre defina `width`/`height`** (ou `aspect-ratio` no CSS) para não haver
  *layout shift* (CLS) quando a imagem carrega.
- Prefira formatos modernos (WebP/AVIF) quando possível.

## Fontes

- Use `font-display: swap` para o texto aparecer antes da fonte carregar.
- Faça `preconnect` para a origem da fonte se ela for externa; melhor ainda,
  **auto-hospede** para eliminar o round-trip.

## JavaScript e reflow

- Listeners de scroll/touch **passivos** (`{ passive: true }`).
- Evite ler e escrever layout alternadamente no mesmo frame (thrashing); agrupe
  leituras e escritas, use `requestAnimationFrame` para animações.
- Prefira `IntersectionObserver` a listeners de scroll para lazy load e sentinelas.
- Delegue eventos no container em vez de ligar um listener por item em listas
  grandes.

## Como validar

- Teste com **throttling de rede/CPU** nas DevTools (perfil mobile).
- Rode **Lighthouse** (aba mobile) e mire nos avisos de LCP, CLS e peso de JS.
- Confira num aparelho real sempre que possível — o emulador não pega tudo,
  principalmente toque e teclado.
