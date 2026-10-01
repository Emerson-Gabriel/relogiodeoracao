# PWA — manifest, service worker, instalação e offline

Como tornar o app instalável e resiliente offline em Laravel, servindo manifest e
service worker corretamente e cuidando das particularidades de iOS e de telas de
PDV.

## Meta tags e viewport

No layout Blade principal, dentro do `<head>`:

```html
<meta name="viewport"
      content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1a73e8">

<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">

{{-- iOS: não suporta manifest para instalação --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Nome Curto">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
```

`viewport-fit=cover` é o que habilita as safe areas (notch). Combine com o padding
`env(safe-area-inset-*)` nos elementos fixos (ver exemplos em `mobile-ux.md`).

## Manifest

Arquivo `public/manifest.webmanifest` (servido como arquivo estático simples):

```json
{
  "name": "Sistema Completo",
  "short_name": "Sistema",
  "start_url": "/?source=pwa",
  "scope": "/",
  "display": "standalone",
  "orientation": "portrait",
  "background_color": "#ffffff",
  "theme_color": "#1a73e8",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
    {
      "src": "/icons/maskable-512.png", "sizes": "512x512",
      "type": "image/png", "purpose": "maskable"
    }
  ]
}
```

Pontos que decidem se o app é instalável:

- **`display: standalone`** (ou `fullscreen` para PDV/quiosque) — remove a barra do
  navegador.
- Ícones **192 e 512px** obrigatórios; inclua ao menos um **`maskable`** para o
  ícone não ficar cortado em Android.
- `start_url` com um parâmetro (`?source=pwa`) ajuda a medir aberturas via app.
- `scope` define o que "pertence" ao app; rotas fora dele abrem no navegador.

## Service worker

O service worker (SW) é o coração do offline. Três cuidados de escopo antes do
código:

1. **Servido da raiz do escopo.** Um SW em `/sw.js` controla todo o site; se ficar
   em `/js/sw.js`, só controla `/js/`. Para escopo `/`, sirva o arquivo da raiz de
   `public/`.
2. **URL estável.** Não versione o arquivo do SW com hash do Mix — o navegador
   precisa buscar sempre a mesma URL para detectar atualização. Versione o **cache
   por dentro** (constante `CACHE`), não o nome do arquivo.
3. **Cuidado com conteúdo autenticado.** Nunca faça cache agressivo de páginas com
   dados de sessão ou tokens CSRF. Faça cache do *app shell* e de estáticos;
   páginas dinâmicas vão por network-first (ou nem entram no cache).

### Registro (JS vanilla)

```js
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .catch(err => console.error('Falha ao registrar SW:', err));
  });
}
```

### Estratégias de cache

```js
// public/sw.js
const CACHE = 'app-v1';                 // suba a versão a cada deploy relevante
const SHELL = [
  '/',
  '/offline',
  '/css/app.css',                       // ajuste às URLs reais compiladas
  '/js/app.js',
  '/icons/icon-192.png'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;     // não mexa em POST/PUT

  const url = new URL(req.url);

  // Estáticos (css/js/img): cache-first
  if (/\.(css|js|png|jpg|jpeg|svg|woff2?)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then(hit => hit || fetch(req).then(res => {
        const copy = res.clone();
        caches.open(CACHE).then(c => c.put(req, copy));
        return res;
      }))
    );
    return;
  }

  // Navegação/HTML: network-first com fallback offline
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() => caches.match('/offline'))
    );
    return;
  }
});
```

O `.clone()` é obrigatório: um `Response` só pode ser lido uma vez, então clona-se
para servir e guardar no cache ao mesmo tempo.

### Atualização do SW ao publicar

Como a URL do SW é fixa, o navegador só atualiza quando o **conteúdo** do arquivo
muda. Subir a constante `CACHE` (`app-v1` → `app-v2`) muda o byte e dispara o
`install` da nova versão. Para o usuário não ficar preso na versão antiga, ou
chame `skipWaiting()` (como acima) ou avise a UI que há atualização e recarregue.

Se o app shell referencia assets versionados pelo Mix (com hash no nome), lembre de
**atualizar a lista `SHELL`** para as novas URLs a cada build — senão o SW cacheia
arquivos que não existem mais. Uma alternativa é gerar a lista de precache a partir
do `mix-manifest.json`.

## Offline

- Crie uma rota `/offline` simples em Laravel com uma página estática leve e
  inclua-a no precache. É o que aparece quando a navegação falha sem rede.
- Mostre um **banner de status** quando a conexão cair, em JS vanilla:

```js
function status() {
  document.body.classList.toggle('is-offline', !navigator.onLine);
}
window.addEventListener('online', status);
window.addEventListener('offline', status);
status();
```

```css
.is-offline .c-banner-offline { display: block; }
.c-banner-offline { display: none; }  /* padrão escondido */
```

## Prompt de instalação (Android/Chrome)

Capture o evento e ofereça um botão próprio, em vez de deixar só o aviso do
navegador:

```js
let promptEvent = null;
const btnInstalar = document.getElementById('instalar');

window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  promptEvent = e;
  btnInstalar.hidden = false;
});

btnInstalar?.addEventListener('click', async () => {
  if (!promptEvent) return;
  promptEvent.prompt();
  await promptEvent.userChoice;
  promptEvent = null;
  btnInstalar.hidden = true;
});

window.addEventListener('appinstalled', () => { btnInstalar.hidden = true; });
```

## iOS — o que muda

- **Não existe `beforeinstallprompt`.** A instalação é manual (Compartilhar →
  "Adicionar à Tela de Início"). Se quiser, mostre uma dica ensinando o caminho,
  detectando Safari em iOS.
- Use **`apple-touch-icon`** e as meta tags `apple-mobile-web-app-*` (acima).
- Detecte se está rodando instalado para ajustar a UI:

```js
const standalone = window.matchMedia('(display-mode: standalone)').matches
                   || window.navigator.standalone === true;  // iOS
```

- iOS respeita `env(safe-area-inset-*)` com `viewport-fit=cover` — essencial para o
  conteúdo não ficar sob o notch/barra inferior.

## Telas de PDV / quiosque

- **`display: fullscreen`** no manifest (ou `standalone`) para máximo de área útil.
- **`orientation`** travada no manifest se o terminal tiver posição fixa.
- Impeça seleção/realce acidental em botões (`user-select: none;`,
  `-webkit-tap-highlight-color: transparent;`).
- Para manter a tela acesa durante o uso, considere a **Screen Wake Lock API**
  (`navigator.wakeLock.request('screen')`), com fallback silencioso onde não houver
  suporte.
- Teste o comportamento offline a sério: o PDV precisa continuar operável (ou
  degradar com clareza) quando a rede cair.

## Servindo manifest e SW no Laravel

- Coloque `manifest.webmanifest`, `sw.js` e os ícones em `public/` e referencie com
  `asset()`. Arquivos estáticos em `public/` são servidos direto pelo servidor web,
  sem passar pelo roteador — é o mais simples e confiável para o SW manter escopo
  `/`.
- Se precisar gerar o manifest ou o SW dinamicamente (ex.: injetar versão), sirva
  por uma rota Laravel com o **`Content-Type` correto**
  (`application/manifest+json` para o manifest, `application/javascript` para o SW)
  e o header de escopo adequado — mas prefira arquivos estáticos quando não houver
  necessidade real de dinamismo.
- A cada deploy, garanta que o cache do SW seja invalidado (constante `CACHE` nova)
  para o usuário receber os assets atualizados.
