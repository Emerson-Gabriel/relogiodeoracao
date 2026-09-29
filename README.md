# Relógio de Oração — IEADIP

Aplicação web para organizar o **Relógio de Oração** da Igreja Evangélica Assembleia de Deus Madureira — Interestadual de Patrocínio/MG.

- O administrador cadastra uma edição (data, início e término) em `/admin` e compartilha o link público.
- Pelo link, cada pessoa escolhe um horário de 1 hora e informa o nome. Mais de uma pessoa pode ficar no mesmo horário.
- A página pública mostra só se o horário está **Disponível** ou **Já tem participante(s)**. Os nomes ficam visíveis apenas no `/admin`.

A especificação completa está em [`docs/ESPECIFICACAO.md`](docs/ESPECIFICACAO.md).

## Decisões técnicas

| Tema | Escolha | Por quê |
|---|---|---|
| Backend | **PHP 8.1+ puro, sem framework** | O escopo é de 3 telas e 2 tabelas. O Laravel traria Composer, centenas de dependências, `artisan`, cache e atualizações periódicas, sem ganho real aqui. PHP puro roda em qualquer hospedagem compartilhada barata (Hostinger, Locaweb, HostGator etc.) só copiando os arquivos. |
| Banco | **SQLite** (arquivo `data/relogio.sqlite`) | Não exige servidor de banco, é criado automaticamente na primeira execução e o backup é copiar um arquivo. O volume (dezenas de inscrições por semana) está muito abaixo do limite do SQLite. O modo WAL e o `busy_timeout` tornam as gravações simultâneas seguras. |
| Frontend | **HTML renderizado no servidor + CSS próprio + ~60 linhas de JS opcional** | Carrega rápido no navegador do WhatsApp, funciona até sem JavaScript, e não precisa de build (npm, Vite, React). O JS só melhora a experiência: impede duplo clique e copia o link. |
| Dependências | **Nenhuma** | Só as extensões `pdo_sqlite` e `mbstring`, que vêm habilitadas em praticamente toda hospedagem PHP. |

### Regras de comportamento adotadas

- **Blocos de 1 hora:** gerados de início até término. O período precisa ter horas completas; caso contrário, o cadastro é recusado com explicação. Início em minuto “quebrado” é permitido (ex.: 07:30–09:30 gera 07:30–08:30 e 08:30–09:30).
- **Sem passar da meia-noite** na primeira versão (o término precisa ser depois do início, no mesmo dia).
- **Fuso:** tudo em `America/Sao_Paulo`. Datas e horários são gravados como hora local de Brasília.
- **Link público:** `/r/<código>`, com código aleatório de 16 caracteres (96 bits). Não é sequencial nem adivinhável.
- **Duplo clique ou recarregar a página:** cada abertura do formulário gera um código de envio único, gravado com restrição `UNIQUE`. Reenviar os mesmos dados não duplica. Depois do envio a pessoa é redirecionada para a confirmação (padrão Post/Redirect/Get), então recarregar a página não reenvia.
- **Mesma pessoa em mais de um horário:** é permitido. Na confirmação há o botão “Escolher outro horário também”.
- **Concorrência:** inscrições são apenas `INSERT`, sem ler, alterar e gravar de volta, então nunca sobrescrevem outra. O teste automatizado dispara 10 inscrições simultâneas.
- **Privacidade:** o HTML público recebe apenas a lista de horários ocupados, sem nomes, contagem ou qualquer outro dado, nem mesmo escondidos. A página de confirmação mostra o nome da própria pessoa, e seu endereço contém um código aleatório que só quem se inscreveu conhece. As páginas levam `noindex` para não aparecer no Google.

## ⚠️ Segurança da área `/admin`

Por padrão, conforme a especificação, **o `/admin` não pede login**. Isso significa que **qualquer pessoa que descobrir o endereço `/admin` consegue ver os nomes de todos os inscritos e cadastrar relógios**. A própria tela do admin mostra esse aviso enquanto a área estiver aberta.

**Recomendação:** antes de publicar na internet, defina um **código de acesso**. É uma única senha compartilhada pela organização, sem cadastro de usuários:

```php
// config.php
return [
    'admin_password' => 'um-codigo-dificil-de-adivinhar',
];
```

Com o código definido, o `/admin` pede o código uma vez por sessão do navegador. Também é possível guardar um hash no lugar do texto puro:
`php -r "echo password_hash('seu-codigo', PASSWORD_DEFAULT);"`.

Outras proteções já incluídas: CSRF nos formulários do admin, cabeçalhos de segurança (CSP, `X-Frame-Options`), escape de todo conteúdo exibido, e bloqueio do acesso direto a `data/`, `src/` e `config.php`. Use **HTTPS** na hospedagem.

## Como executar localmente

Requisitos: PHP 8.1 ou superior com `pdo_sqlite` e `mbstring`.

```bash
php -S localhost:8000 -t public public/index.php
```

- Administração: http://localhost:8000/admin
- O banco `data/relogio.sqlite` é criado automaticamente.

## Testes

```bash
php tests/run.php
```

O script roda testes das regras (blocos, validações) e testes de ponta a ponta. Ele sobe o servidor com um banco temporário e verifica cadastro, link público, inscrição, várias pessoas no mesmo horário, duplo envio, concorrência, ausência de nomes no HTML público e o código de acesso. Os testes cobrem os critérios de aceite da especificação.

## Publicação na HostGator — `oracao.omnibyte.com.br`

1. cPanel → **MultiPHP Manager**: PHP **8.1 ou mais novo** para o subdomínio.
2. cPanel → **Domínios → Criar domínio**: `oracao.omnibyte.com.br`, com **Document Root** = `relogiodeoracao/public`.
3. Envie o projeto (ZIP do GitHub → Gerenciador de Arquivos → Extrair) para `/home/<usuario>/relogiodeoracao`.
4. Copie `config.example.php` para `config.php` e defina `admin_password` (o `app_url` já vem com `https://oracao.omnibyte.com.br`).
5. cPanel → **SSL/TLS Status** → **Run AutoSSL** para ativar o HTTPS.
6. Teste: `https://oracao.omnibyte.com.br/admin`.

## Publicação (hospedagem compartilhada com Apache, genérica)

1. Envie todos os arquivos do projeto para a hospedagem.
2. Aponte a **raiz do site para a pasta `public/`** (recomendado). Se o painel não permitir, deixe na raiz: o `.htaccess` da raiz redireciona para `public/` e bloqueia as pastas internas.
3. Copie `config.example.php` para `config.php` e preencha `admin_password` e `app_url` (ex.: `https://oracao.suaigreja.com.br`).
4. Garanta que a pasta `data/` tenha permissão de escrita para o PHP.
5. **Backup:** copie periodicamente o arquivo `data/relogio.sqlite`.

Em Nginx, use `root .../public;` e `try_files $uri /index.php?$query_string;`.

## Estrutura

```
public/            raiz web (index.php = rotas, assets/)
src/functions.php  regras de negócio puras (blocos, validações, formatação)
src/bootstrap.php  configuração, banco SQLite e consultas
src/http.php       rotas auxiliares, sessão, CSRF, código de acesso
src/views/         telas (HTML)
data/              banco SQLite (fora da raiz web)
tests/run.php      testes
docs/              especificação
```

## Fora do escopo (primeira versão)

Login de participantes, notificações, limite por horário, recorrência automática, cancelamento pelo participante e coleta de dados além do nome.
