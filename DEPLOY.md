# Guia de implantação (produção)

Este guia segue o caminho de um **servidor Linux próprio (VPS)** com Nginx, PHP-FPM, MySQL ou Postgres, cron e supervisor. A plataforma ainda não foi escolhida (ver [`HOSPEDAGEM.md`](HOSPEDAGEM.md)). Em plataformas gerenciadas (Laravel Cloud, Forge), boa parte dos passos 1, 5 e 6 é feita pelo painel, mas o `.env`, a primeira vez e a verificação continuam valendo.

## 1. Requisitos do servidor

- PHP **8.3** ou mais novo, com as extensões: `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql` ou `pdo_pgsql`, `tokenizer`, `xml`, `intl`.
- **OPcache** ligado (desempenho: o PHP não recompila o código a cada requisição).
- Composer 2.
- **MySQL 8** ou **Postgres 16** (os dois rodam no CI). SQLite não é recomendado em produção.
- Nginx com certificado HTTPS (Let's Encrypt via `certbot`).
- `cron` e `supervisor`.
- Um serviço de email SMTP (o da faculdade, ou um transacional). **⏸️ Ainda não escolhido** (pendente desde 16/11/2026, a pedido do usuário); com o domínio da faculdade, configurar SPF e DKIM.

## 2. Configuração (`.env`)

Copie `.env.production.example` para `.env` no servidor e preencha. O `.env` **nunca** vai para o git. Guarde uma cópia num gerenciador de senhas.

| Variável | O que colocar |
|---|---|
| `APP_KEY` | gerada por `php artisan key:generate` (só na primeira vez; trocar depois invalida dados criptografados) |
| `APP_URL` | endereço da API, com `https://` |
| `FRONTEND_URL` | endereço do front Angular, com `https://` (os links dos emails apontam para ele) |
| `CORS_ALLOWED_ORIGINS` | endereço(s) do front, separados por vírgula. Só eles podem chamar a API pelo navegador |
| `TRUSTED_PROXIES` | IP do proxy/balanceador na frente do servidor (ou `*` se só ele alcança o servidor). Vazio se o Nginx atende direto |
| `DB_*` | banco e usuário próprios do sistema, com senha forte |
| `MAIL_*` | dados do SMTP |
| `ALERTAS_EMAIL` | email (ou lista separada por vírgula) que recebe os alertas, além dos admins ativos |
| `ADMIN_INICIAL_*` | deixe **vazio** (ver passo 4) |
| `PARCEIROS_*` | só se a instituição quiser mudar os padrões dos sistemas parceiros (termos da API, validade de 12 meses, aviso 30 dias antes, limite de 60 por minuto). A versão dos termos (`PARCEIROS_TERMOS_VERSAO`) muda quando o texto mudar |

## 3. Implantação (toda atualização)

```bash
cd /var/www/reservas
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

- `composer install --no-dev --optimize-autoloader`: sem pacotes de desenvolvimento e com o autoload otimizado.
- `php artisan optimize`: cache de configuração, rotas, eventos e views. **Depois de mudar o `.env`, rode de novo**, senão a mudança não vale.
- `php artisan queue:restart`: o worker termina o job atual e volta com o código novo.

## 4. Primeira vez

Depois do passo 3:

```bash
php artisan key:generate
php artisan db:seed --class=CursoSeeder --force
php artisan admin:criar-principal --nome="Seu Nome" --email=seu@email.edu.br
php artisan feriados:importar
php artisan feriados:importar 2027
php artisan sistema:verificar-producao
```

- **Admin principal:** criado pelo comando, que pede a senha na hora (não fica no código, no `.env` nem no histórico do terminal). Os outros admins são convidados por ele pelo sistema.
- **Alternativa (decisão do usuário, 09/11/2026):** preencher `ADMIN_INICIAL_NOME`, `ADMIN_INICIAL_EMAIL` e `ADMIN_INICIAL_SENHA` no `.env` e rodar `php artisan db:seed --class=AdminSeeder --force`. Nesse caso, **apague `ADMIN_INICIAL_SENHA` do `.env` logo depois** e rode `php artisan optimize`. O verificador acusa ❌ enquanto a senha estiver lá.
- **Nunca** rode `db:seed` sem `--class` em produção: o `DemoSeeder` só roda em `local`, mas o comando explícito evita surpresas.
- **Feriados:** o agendador importa o ano seguinte todo dia 1º de dezembro. Na primeira vez, importe o ano atual e o próximo.
- **`sistema:verificar-producao`:** lista cada item com ✅ ou ❌ e o que fazer. Só abra o sistema com tudo ✅.

## 5. Nginx

**Recomendado (11.8, 17/11/2026): front e API no mesmo domínio**, com o exemplo de [`frontend/deploy/nginx.conf`](frontend/deploy/nginx.conf), explicado no passo 11. O bloco abaixo é para a **alternativa** da API num subdomínio próprio.

```nginx
server {
    listen 80;
    server_name api.reservas.exemplo.edu.br;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.reservas.exemplo.edu.br;
    root /var/www/reservas/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/api.reservas.exemplo.edu.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.reservas.exemplo.edu.br/privkey.pem;

    client_max_body_size 2m;
    server_tokens off;

    gzip on;
    gzip_types application/json text/csv;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }

    access_log /var/log/nginx/reservas.access.log;
}
```

- HTTP redireciona para HTTPS. A própria API envia o HSTS quando a requisição chega por HTTPS.
- `gzip` diminui o tamanho das respostas JSON e da exportação (desempenho).
- **LGPD:** o `access_log` do Nginx guarda IPs e URLs. As rotas do sistema não levam dados pessoais na URL (RA e email vão no corpo, via POST), mas os IPs são dados pessoais: configure o `logrotate` do Nginx para **14 dias**, igual aos logs do Laravel.

## 6. Agendador e fila

O cron e o supervisor estão em [`README.md`](README.md#em-produção-agendador-e-fila). **Escolha quem envia os emails da fila** (explicação completa no README, "Quem envia os emails da fila: supervisor ou agendador"):

- **A hospedagem tem supervisor** (VPS, servidor da faculdade, Forge, Laravel Cloud): configure o supervisor abaixo e deixe **`FILA_PELO_AGENDADOR=false`** (já é o valor do `.env.production.example`). Motivo: o worker sempre ligado envia em segundos; ligar também a fila pelo agendador só criaria um segundo processo a cada minuto sem necessidade.
- **A hospedagem não tem supervisor** (só cron, ex.: hospedagem compartilhada): pule o bloco do supervisor e ponha **`FILA_PELO_AGENDADOR=true`**. Motivo: sem worker sempre ligado, os emails ficariam parados; com a variável ligada, o cron do agendador envia a fila a cada minuto (até ~1 min de atraso).
- Nunca deixe os dois desligados: com `false` e sem supervisor, nenhum email da fila sai (só o código de login, que não usa fila). Resumo:

```cron
* * * * * cd /var/www/reservas && php artisan schedule:run >> /dev/null 2>&1
```

```ini
[program:reservas-worker]
command=php /var/www/reservas/artisan queue:work --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/reservas/storage/logs/worker.log
stopwaitsecs=3600
```

Se uma tarefa agendada ou um job falhar, os **admins ativos** e o `ALERTAS_EMAIL` recebem um email (no máximo 1 por hora para o mesmo problema, sem dados pessoais). Os detalhes ficam em `storage/logs`.

## 7. Permissões

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
chmod 600 .env
```

## 8. Desempenho em produção

- `php artisan optimize` a cada deploy e OPcache ligado.
- Worker da fila sempre rodando: os emails não atrasam as respostas.
- Logs só a partir de `warning`.
- **Evolução, se o volume crescer:** Redis para cache e fila (`CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`). Hoje o banco dá conta.

## 9. Backup (pendente: decidir com a plataforma)

Por decisão do usuário (09/11/2026), o backup é definido na hora do deploy. As opções estão em [`README.md`](README.md#ideias-registradas-para-depois):
- banco gerenciado com backup automático (mais simples);
- ou `spatie/laravel-backup` com cópia num armazenamento externo;
- regra 3-2-1, retenção de 7 diários, 4 semanais e 6 mensais, e um teste de restauração por mês.

## 10. Checklist de LGPD na produção

- [ ] HTTPS em tudo (API e front) e `APP_DEBUG=false` (o erro detalhado mostraria dados).
- [ ] Cabeçalhos do front no ar (CSP, `Referrer-Policy: no-referrer` etc., passo 11.5): sem scripts de terceiros nem rastreamento.
- [ ] `CORS_ALLOWED_ORIGINS` só com o front.
- [ ] `.env` com permissão `600` e cópia num gerenciador de senhas.
- [ ] Banco com usuário próprio, senha forte e sem acesso externo (só do servidor da API).
- [ ] Logs do Laravel por 14 dias (`LOG_DAILY_DAYS`) e do Nginx por 14 dias (`logrotate`).
- [ ] Sem dados de teste: só `CursoSeeder` e o admin principal.
- [ ] `ADMIN_INICIAL_SENHA` vazio.
- [ ] Backups criptografados, com acesso restrito e prazo de guarda (quando forem definidos). Depois de restaurar um backup, refazer as anonimizações pedidas depois da data dele.
- [ ] Definir com a faculdade o prazo de guarda do histórico de auditoria (hoje sem prazo, por decisão do usuário).
- [ ] Termos de uso da API (`app/Support/TermosApi.php`) e o trecho "Sistemas parceiros" do aviso de privacidade revisados pela faculdade antes de cadastrar o primeiro sistema parceiro.
- [ ] A documentação da API (`/docs/api`) **não** existe na produção (o Scramble é só de desenvolvimento): para os parceiros, mande o `docs/API-PARCEIROS.md` e o `docs/api-v1.openapi.json`.
- [ ] `php artisan sistema:verificar-producao` todo ✅.

## 11. Front-end (Angular)

O front é um site estático (HTML, JS e CSS gerados pelo build). Recomendação aprovada no 11.8 (17/11/2026): **front e API no mesmo domínio**, com a API em **`/api`** (ex.: `https://reservas.exemplo.edu.br` e `https://reservas.exemplo.edu.br/api`).

**Por que o mesmo domínio:** o front já chama `/api` (`frontend/src/ambientes/ambiente.ts`), então não há o que mudar no código; o navegador não precisa de CORS (menos uma requisição `OPTIONS` por chamada e uma configuração a menos para errar); a CSP fica só com `'self'`; e um certificado só.

### 11.1 Build

Numa máquina com Node 22.22+/24.15+/26 (o servidor não precisa de Node se o build for feito fora, por exemplo no CI):

```bash
cd frontend
npm ci
npx ng build
```

O resultado fica em `frontend/dist/reservas-front/browser/`. Copie **o conteúdo** dessa pasta para o servidor (ex.: `/var/www/reservas-front/browser/`), substituindo a versão anterior. O build já sai sem comentários, minificado, com o nome de cada arquivo com um código (hash) e com o orçamento conferido (aviso em 330 kB e erro em 380 kB no pacote inicial).

### 11.2 Nginx (front + API num servidor só)

O exemplo completo está em [`frontend/deploy/nginx.conf`](frontend/deploy/nginx.conf). Ele substitui o bloco do passo 5 (que serve a API num domínio próprio). O que cada parte faz:

- **`/api/` e `/up`** vão direto para o `public/index.php` do Laravel (PHP-FPM). Todo o resto é o front.
- **`location /` com `try_files $uri /index.html`:** as rotas do Angular (`/espacos/1`, `/admin/historico`) não existem como arquivo; o Nginx devolve o `index.html` e o Angular mostra a página certa. Sem isso, recarregar a página daria 404.
- **Cache:**
  - arquivos com hash no nome (`main-SCVWP3S6.js`, `chunk-…`, `styles-…`, fontes): **1 ano, `immutable`**. Como o nome muda a cada versão, o navegador nunca usa um arquivo velho por engano, e a segunda visita quase não baixa nada (desempenho);
  - `index.html`: **`no-cache`** (o navegador sempre confere se mudou). É ele que aponta para os arquivos novos, então uma versão nova chega a todos na hora;
  - `favicon.ico` não tem hash e fica com o cache padrão.
- **Cabeçalhos de segurança** (no `index.html`, que é a página carregada; a API manda os dela):
  - `Content-Security-Policy`: só scripts, estilos, fontes e chamadas do **próprio site** (`'self'`), nenhum script de terceiros, nada de `<iframe>` do site em outro lugar (`frame-ancestors 'none'`). Barra a maior parte dos ataques de XSS: mesmo que alguém conseguisse injetar um `<script>`, o navegador não o executaria. Também é a garantia técnica de que o site não tem rastreamento (LGPD);
  - `Referrer-Policy: no-referrer` (os links dos emails têm token; nada vaza para outro site), `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Strict-Transport-Security` (sempre HTTPS) e `Permissions-Policy` (sem câmera, microfone ou localização).
- **`gzip`** para JS, CSS e JSON.

**Por que o build não embute CSS nem script no `index.html`** (`inlineCritical: false` no `angular.json`): por padrão o Angular coloca o CSS principal e um pequeno script dentro do `index.html` para pintar a tela um pouco antes. A CSP acima bloqueia qualquer script ou estilo embutido, e o site ficaria sem estilo (conferido: a fonte caía para Times New Roman). A troca custa quase nada: o CSS é um arquivo de 6 kB comprimido que fica em cache por 1 ano. O teste `e2e/seguranca-do-front.spec.ts` carrega o site com **exatamente a CSP deste arquivo** e falha se algo for bloqueado ou se o estilo não for aplicado; se mudar a CSP, mude no `nginx.conf` e o teste acompanha.

### 11.3 Variáveis da API para o mesmo domínio

- `APP_URL=https://reservas.exemplo.edu.br`
- `FRONTEND_URL=https://reservas.exemplo.edu.br` (links dos emails e cabeçalho dos emails)
- `CORS_ALLOWED_ORIGINS=https://reservas.exemplo.edu.br` (no mesmo domínio o navegador nem usa CORS, mas fica restrito ao próprio site)

### 11.4 Alternativa: API num subdomínio próprio

Se a hospedagem exigir domínios separados (ex.: front num serviço de sites estáticos e API em `api.reservas.exemplo.edu.br`):

1. API com o bloco do passo 5; `CORS_ALLOWED_ORIGINS` com o endereço exato do front.
2. No front, `frontend/src/ambientes/ambiente.ts` com `urlApi: 'https://api.reservas.exemplo.edu.br/api'` e build de novo.
3. Na CSP, `connect-src 'self' https://api.reservas.exemplo.edu.br`.
4. No serviço de sites estáticos, configurar o equivalente: todas as rotas voltando para `index.html` ("SPA fallback" ou "rewrite"), os cabeçalhos de segurança e o cache acima.

O token do admin continua no `sessionStorage` com `Authorization: Bearer` nos dois casos (decisão do passo 11).

### 11.5 Conferência depois de publicar

- Abrir o site, recarregar numa página interna (ex.: `/espacos/1`) e ver que não dá 404.
- No navegador, F12 → Console: nenhuma mensagem de "Content Security Policy".
- F12 → Rede: `index.html` com `Cache-Control: no-cache`; `main-….js` com `max-age=31536000`.
- `curl -I https://reservas.exemplo.edu.br` mostra os cabeçalhos de segurança.
