# Guia de implantação (produção)

Este guia segue o caminho de um **servidor Linux próprio (VPS)** com Nginx, PHP-FPM, MySQL ou Postgres, cron e supervisor. A plataforma ainda não foi escolhida (ver [`HOSPEDAGEM.md`](HOSPEDAGEM.md)). Em plataformas gerenciadas (Laravel Cloud, Forge), boa parte dos passos 1, 5 e 6 é feita pelo painel, mas o `.env`, a primeira vez e a verificação continuam valendo.

## 1. Requisitos do servidor

- PHP **8.3** ou mais novo, com as extensões: `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql` ou `pdo_pgsql`, `tokenizer`, `xml`, `intl`.
- **OPcache** ligado (desempenho: o PHP não recompila o código a cada requisição).
- Composer 2.
- **MySQL 8** ou **Postgres 16** (os dois rodam no CI). SQLite não é recomendado em produção.
- Nginx com certificado HTTPS (Let's Encrypt via `certbot`).
- `cron` e `supervisor`.
- Um serviço de email SMTP (o da faculdade, ou um transacional).

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

O cron e o supervisor estão em [`README.md`](README.md#em-produção-agendador-e-fila). Resumo:

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
- [ ] `CORS_ALLOWED_ORIGINS` só com o front.
- [ ] `.env` com permissão `600` e cópia num gerenciador de senhas.
- [ ] Banco com usuário próprio, senha forte e sem acesso externo (só do servidor da API).
- [ ] Logs do Laravel por 14 dias (`LOG_DAILY_DAYS`) e do Nginx por 14 dias (`logrotate`).
- [ ] Sem dados de teste: só `CursoSeeder` e o admin principal.
- [ ] `ADMIN_INICIAL_SENHA` vazio.
- [ ] Backups criptografados, com acesso restrito e prazo de guarda (quando forem definidos). Depois de restaurar um backup, refazer as anonimizações pedidas depois da data dele.
- [ ] Definir com a faculdade o prazo de guarda do histórico de auditoria (hoje sem prazo, por decisão do usuário).
- [ ] `php artisan sistema:verificar-producao` todo ✅.
