# Relógio de Oração — guia para o Claude

Aplicação web da IEADIP (Patrocínio/MG) para inscrição em horários de oração. Especificação em `docs/ESPECIFICACAO.md`; decisões, segurança e publicação em `README.md`. Interface e mensagens sempre em **português**.

## Stack (não mudar sem pedir ao usuário)

- **PHP 8.1+ puro, sem framework e sem Composer.** Hospedagem compartilhada HostGator (Apache + `.htaccess`), em `oracao.omnibyte.com.br`.
- **SQLite** em `data/relogio.sqlite` (fora da raiz web). Migração automática em `migrate()` (`src/bootstrap.php`).
- **HTML renderizado no servidor + CSS próprio + JS vanilla.** Sem build, sem npm, sem bibliotecas de front.
- Única biblioteca: **FPDF** incluída em `src/lib/fpdf` (PDF de impressão). Não editar.

## Estrutura

- `public/index.php` — rotas e handlers. `public/` é a única pasta pública.
- `src/functions.php` — regras puras (blocos de 1 hora, validações, formatação de data).
- `src/bootstrap.php` — configuração (`config.php` / variáveis `RELOGIO_*`), banco e consultas.
- `src/http.php` — URLs, renderização, sessão, CSRF, código de acesso do admin, cabeçalhos de segurança.
- `src/views/*.php` — telas; `layout.php` é o layout base. Todo dado exibido passa por `e()`.
- `src/pdf.php` — PDF A4 da lista de participantes.
- `public/assets/style.css` — **todo o CSS e os tokens** (custom properties no `:root`). `public/assets/app.js` — todo o JS.
- `public/img/logo.png` — logo da igreja (o usuário substitui pelo oficial).

## Comandos

- Testes (rodar antes de todo commit): `php tests/run.php` — precisa terminar com `0 falha(s)`.
- Servidor local: `RELOGIO_ADMIN_PASSWORD=teste php -S localhost:8000 -t public public/index.php`.
- Commits e push direto na branch `main` (autorizado pelo usuário).

## Regras do produto que não podem quebrar

- Blocos de exatamente 1 hora; várias pessoas por horário; link público por edição (`/r/<token>`).
- **Nomes nunca aparecem na página pública**, nem escondidos no HTML. Só no `/admin`.
- `/admin` exige `admin_password` configurado; sem ele, fica bloqueado.
- Inscrições só por `INSERT`; proteção contra duplo envio por `submission_token`.

## Como usar as skills de `.claude/skills` neste projeto

**`frontend-design`** — vale como está para decisões visuais. A identidade já definida: azul-marinho `#1f3a5f`, dourado `#e0b04b`, fundo `#f6f3ee`, fonte do sistema. Mudanças grandes de visual devem ser propostas ao usuário antes.

**`laravel-mobile-design`** — foi escrita para Laravel + Blade + Laravel Mix + Adminator. Aqui, aplique os **princípios** (mobile-first, toque ≥ 44px, acessibilidade, tokens) com estas traduções:

| Na skill | Neste projeto |
|---|---|
| Blade (`resources/views`, `@yield`) | `src/views/*.php` + `src/views/layout.php` |
| `brand-theme.css` / `adminator.css` | `public/assets/style.css` (tokens no `:root`) |
| Laravel Mix, `webpack.mix.js`, `npm run prod` | **Não existe e não deve ser criado.** Edite os arquivos diretamente |
| `mix('css/app.css')` (cache-busting) | `asset('style.css')` em `src/http.php` (acrescenta `?v=<data do arquivo>`) |
| `{{ $var }}` | `<?= e($var) ?>` |
| Rotas Laravel | `route()` / `admin_route()` em `public/index.php` |

**PWA / service worker (`references/pwa.md`):** não implementar sem o usuário pedir. Se for implementado, o service worker **nunca** pode guardar em cache páginas do `/admin` nem a página pública de inscrição (dados de participantes, CSRF e estado dos horários precisam vir sempre do servidor). Ícones e manifest devem usar `public/img/logo.png`.
