# Sistema de Reservas de Espaços — Documentação de Desenvolvimento

[![CI](https://github.com/ericjunq/reserva-quadra/actions/workflows/ci.yml/badge.svg)](https://github.com/ericjunq/reserva-quadra/actions/workflows/ci.yml)

API (backend) em Laravel para reservar os espaços da faculdade (quadra, laboratórios e salas) e os equipamentos desses espaços, e o front-end em Angular (pasta [`frontend/`](frontend/)), que só consome a API e tem deploy próprio.

> Este documento descreve **o que o sistema faz, como rodar, as decisões tomadas e o que vem pela frente**.
> O registro detalhado de cada alteração, arquivo por arquivo, fica em [`implementacoes-com-claude-code.txt`](implementacoes-com-claude-code.txt).
> Um resumo simples do que o sistema já faz e do que falta fica em [`CHECKLIST.md`](CHECKLIST.md).
> O modelo de dados (diagrama) e as decisões de modelagem ficam em [`DATABASE.md`](DATABASE.md).

---

## Sumário

1. [Visão geral](#1-visão-geral)
2. [Como rodar](#2-como-rodar)
3. [Estrutura do código](#3-estrutura-do-código)
4. [Modelo de dados](#4-modelo-de-dados)
5. [Endpoints](#5-endpoints)
6. [Regras de negócio](#6-regras-de-negócio)
7. [Decisões tomadas](#7-decisões-tomadas)
8. [Testes](#8-testes)
9. [Histórico do desenvolvimento](#9-histórico-do-desenvolvimento)
10. [Próximos passos](#10-próximos-passos)

---

## 1. Visão geral

Há dois tipos de usuário:

| Quem | Como acessa | O que faz |
|---|---|---|
| **Aluno** | Sem login, informando o RA | Escolhe espaço, data, horário e (opcionalmente) equipamentos e quantidades |
| **Admin** (funcionário) | Login com token (Sanctum) | Gerencia espaços, equipamentos, cursos e alunos; cria eventos; revoga reservas |

- Toda reserva nasce com status `RESERVADO`.
- A quadra usa **horários fixos** de 1h30: `19:00–20:30` e `20:30–22:00`. Laboratórios e salas podem usar **horário livre** (o aluno escolhe início e fim dentro da janela do espaço, arredondados a 15 min; na demonstração, 07:00–22:00 e até 3h).
- Toda reserva de aluno precisa ser **confirmada pelo email** (com aceite dos termos) e, se feita com antecedência, reconfirmada 3 dias úteis antes; sem isso, é revogada sozinha.
- O calendário público mostra o que está livre e ocupado, **sem identificar quem reservou** (só os admins veem).
- O sistema começou pela quadra (atléticas), mas já está pronto para laboratórios e salas, cada um com nome próprio.

**Stack:** PHP 8.3+, Laravel 13, Laravel Sanctum, SQLite em desenvolvimento. Front: Angular 22 (sem zone.js, signals), Tailwind CSS v4, Vitest, Node 22.22+/24.15+/26.

---

## 2. Como rodar

### Primeira vez

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

O `--seed` cria:

- **Admin:** `admin@email.com` / senha `Admin` (senha simples de propósito, porque o banco é resetado com frequência em desenvolvimento);
- **Cursos** da faculdade;
- **Dados de demonstração** (só com `APP_ENV=local`): quadra (limite de 3 reservas por curso na semana), laboratórios, salas, equipamentos e alunos.

### No dia a dia

```bash
php artisan serve          # API em http://localhost:8000
php artisan queue:work     # envia os emails (em dev eles aparecem em storage/logs/laravel.log)
php artisan admin:criar-principal               # cria o admin principal (produção)
php artisan admin:transferir-principal {email}  # passa o papel de principal para outro admin
php artisan schedule:work  # roda o agendador em dev (reconfirmação às 08:00, revogação a cada 15 min)
php artisan reservas:pedir-reconfirmacao        # dispara o pedido de reconfirmação na hora
php artisan reservas:revogar-nao-confirmadas    # revoga na hora as que passaram do prazo de confirmação
php artisan test           # roda os testes
./vendor/bin/pint          # formata o código no padrão do projeto (pint.json)
php artisan feriados:importar 2027                 # importa os feriados nacionais como datas bloqueadas
php artisan feriados:importar 2027 --facultativos  # inclui Carnaval, Quarta de Cinzas e Corpus Christi
```

Depois de alterar migrations, rode `php artisan migrate:fresh --seed` (**apaga os dados locais**).

### Front-end (Angular)

```bash
cd frontend
npm install                     # primeira vez
npm start                       # http://localhost:4200 (com a API rodando em http://localhost:8000)
npx ng test --watch=false       # testes unitários (Vitest)
npx ng build                    # build de produção em frontend/dist (falha se passar do orçamento de tamanho)
npx playwright test             # testes no navegador (Chrome instalado; sobe o front sozinho na porta 4300)
npx prettier --write src        # formata no padrão do projeto (.prettierrc)
```

- O endereço da API vem de `frontend/src/ambientes/`: `ambiente.development.ts` (`http://localhost:8000/api`, usado pelo `npm start` e pelos testes) e `ambiente.ts` (produção; `/api`, porque front e API ficam no mesmo domínio, decisão do 11.8; deploy do front no `DEPLOY.md`, passo 11).
- O `FRONTEND_URL` do `.env` da API já é `http://localhost:4200`, então o CORS libera o front local.
- **Rede que bloqueia o `registry.npmjs.org`** (aconteceu no PC Ubuntu): `npm config set registry https://registry.yarnpkg.com/` (espelho com os mesmos pacotes). Fica no `~/.npmrc` do PC, fora do repositório; o `package-lock.json` continua apontando para o registro padrão.
- A coleta de dados de uso do Angular CLI fica **desligada** no projeto (`"analytics": false` no `angular.json`), em linha com a decisão de não usar rastreamento.
- **Para ver o front:** um terminal com `php artisan serve` (raiz) e outro com `cd frontend && npm start`; abrir http://localhost:4200. Para os emails e as tarefas automáticas, um terceiro terminal com **`php artisan schedule:work`**: desde 17/11/2026 ele também processa a fila a cada minuto (`FILA_PELO_AGENDADOR`, ligado por padrão em `APP_ENV=local`), então não precisa mais do `queue:work` (se quiser os emails em segundos, o `queue:work` continua funcionando junto) (ver "Enviar emails de verdade (teste local)").

### Preparar um PC novo

O que foi preciso no PC Ubuntu (09/11/2026), sem `sudo` e sem `curl`:

1. **PHP 8.4 + Composer:** binários do php.new (herd-lite) em `~/.config/herd-lite/bin`, baixados com `wget`, e o caminho no `~/.bashrc` (`PATH` e `PHP_INI_SCAN_DIR`).
2. **Node LTS:** tarball oficial do nodejs.org (com o checksum conferido) extraído em `~/.local/node`, e `~/.local/node/bin` no `PATH`. O Angular 22 aceita Node 22.22+, 24.15+ ou 26.
3. Abrir um terminal novo (ou `source ~/.bashrc`) e rodar o "Primeira vez" acima; no front, `cd frontend && npm install`.
4. **Git:** `git config user.name` e `user.email` iguais aos dos commits anteriores.
5. **Push por HTTPS:** o GitHub não aceita a senha da conta no terminal. Usar um **token de acesso pessoal** (*Settings → Developer settings → Personal access tokens → Fine-grained*), só para este repositório, com **Contents** e **Workflows** em *Read and write* (Workflows porque os commits podem mexer no `.github/workflows`). Para não colar o token a cada push: `git config --global credential.helper store` (guarda em `~/.git-credentials`; aceitável num PC pessoal).

### Fazer login como admin

O login tem **duas etapas** (11.4b, 16/11/2026): senha e depois um código de 6 dígitos enviado por email.

```http
POST /api/login
{ "email": "admin@email.com", "password": "Admin" }
→ { "etapa": "CODIGO", "desafio": "<64 caracteres>", "expira_em": "..." }

POST /api/login/codigo
{ "desafio": "<o mesmo>", "codigo": "123456" }
→ { "token": "...", "expires_at": "...", "user": { ... } }
```

Use o `token` devolvido no cabeçalho `Authorization: Bearer <token>`. O token vale 8 horas. O código chega por email (neste PC, no painel do Mailtrap; com `MAIL_MAILER=log`, no `storage/logs/laravel.log`). **Em desenvolvimento**, enquanto o envio real de emails está pendente, dá para desligar o código com `LOGIN_COM_CODIGO=false` no `.env` (o `/api/login` volta a devolver o token direto); em produção ele precisa ficar ligado (`sistema:verificar-producao` confere).

### Variáveis de ambiente do negócio

Ficam em [`config/reservas.php`](config/reservas.php) e podem ser alteradas pelo `.env`:

| Variável | Padrão | O que controla |
|---|---|---|
| `RESERVA_ANTECEDENCIA_MAXIMA_DIAS_UTEIS` | 10 | Até quantos dias úteis à frente o aluno pode reservar |
| `RESERVA_BLOCO_MINUTOS` | 15 | Passo dos horários livres: a API arredonda início e fim para o múltiplo mais próximo (14:07 → 14:00; 15:38 → 15:45); janela e duração máxima do espaço também são múltiplos dele |
| `RESERVA_MINUTOS_ANTES_DO_FIM` | 20 | Até quantos minutos antes do fim um horário de hoje pode ser reservado |
| `RESERVA_CONFIRMACAO_HORAS` | 24 | Prazo para confirmar pelo email depois de reservar (nunca passa do início) |
| `RESERVA_CONFIRMACAO_MINUTOS_MESMO_DIA` | 60 | Prazo para confirmar uma reserva para o mesmo dia |
| `RESERVA_RECONFIRMACAO_DIAS_UTEIS_ANTES` | 3 | Quantos dias **úteis** antes da reserva sai o pedido de reconfirmação (prazo: 00:00 da véspera) |
| `RESERVA_RECONFIRMACAO_DISPENSADA_DIAS_UTEIS` | 2 | Quem confirmou há menos desses dias úteis antes do pedido não recebe a reconfirmação |
| `RESERVA_HORARIO_PEDIDO_RECONFIRMACAO` | 08:00 | Horário diário do comando que pede as reconfirmações |
| `RESERVA_SOLICITACAO_PRAZO_HORAS_ANTES` | 24 | Prazo normal de resposta do admin a um pedido de equipamento (horas antes do início) |
| `RESERVA_SOLICITACAO_PRAZO_CURTO_HORAS_ANTES` | 3 | Prazo curto, usado quando o normal deixaria pouco tempo ao admin |
| `RESERVA_SOLICITACAO_TEMPO_MINIMO_RESPOSTA_HORAS` | 2 | Tempo mínimo que o admin precisa ter para responder; sem ele, o pedido é presencial |
| `RESERVA_MANTER_HORAS` | 12 | Horas que o aluno tem para manter a reserva depois de o pedido não ser liberado |
| `RESERVA_MANTER_MINIMO_HORAS_ANTES` | 1 | O prazo para manter nunca passa deste tanto de horas antes do início |
| `RESERVA_TERMOS_VERSAO` | 2026-11 | Versão dos termos de uso e do aviso de privacidade (gravada na reserva no aceite) |
| `RESERVA_FERIADOS_FACULTATIVOS` | false | Se a importação automática de feriados inclui Carnaval, Quarta de Cinzas e Corpus Christi |
| `RESERVA_TOKENS_RETENCAO_DIAS` | 30 | Depois de quantos dias os links de email usados ou vencidos são apagados |
| `RESERVA_JOBS_FALHOS_RETENCAO_HORAS` | 168 | Depois de quantas horas os jobs que falharam são apagados (7 dias) |
| `RESERVA_FIM_DOS_SEMESTRES` | 06-30,12-31 | Último dia de cada semestre letivo (mês-dia). O calendário público vai até o fim do semestre atual |
| `RESERVA_CALENDARIO_MINIMO_SEMANAS` | 4 | Mínimo de semanas que o calendário mostra (perto do fim do semestre, já aparece o começo do próximo) |
| `APP_TIMEZONE` | America/Sao_Paulo | Fuso usado nas regras de "hoje" e no agendador |
| `FRONTEND_URL` | http://localhost:4200 | Endereço do front Angular; base dos links enviados por email (ex.: cancelamento) |
| `AUTH_REDEFINICAO_EXPIRA_MINUTOS` | 60 | Validade do link de redefinição de senha |
| `AUTH_CONVITE_EXPIRA_MINUTOS` | 2880 | Validade do link de convite de admin (2 dias) |
| `CORS_ALLOWED_ORIGINS` | `FRONTEND_URL` | Endereços (separados por vírgula) que podem chamar a API pelo navegador |
| `CORS_MAX_AGE` | 86400 | Por quantos segundos o navegador guarda a liberação do CORS (menos requisições `OPTIONS`) |
| `TRUSTED_PROXIES` | vazio | IPs do proxy/balanceador na frente do servidor (ou `*`), para o Laravel enxergar o HTTPS e o IP real |
| `SESSAO_ADMIN_MINUTOS` | 480 | Validade do token de login do admin (8 h). Fica no próprio token: a validade global do Sanctum é `null` desde o passo 13, senão os tokens de 12 meses dos parceiros venceriam em 8 h |
| `PARCEIROS_TERMOS_VERSAO` | 2026-11 | Versão dos termos de uso da API (gravada no sistema parceiro no aceite) |
| `PARCEIROS_VALIDADE_MESES` | 12 | Validade do token de um sistema parceiro |
| `PARCEIROS_AVISO_DIAS_ANTES` | 30 | Quantos dias antes do vencimento o responsável recebe o link de renovação |
| `PARCEIROS_LINK_ACEITE_DIAS` | 7 | Validade do link de aceite dos termos da API (o de renovação vale até o vencimento, no mínimo isso) |
| `PARCEIROS_LIMITE_POR_MINUTO` | 60 | Limite padrão de requisições por minuto de um sistema parceiro (ajustável no cadastro, até 600) |
| `PARCEIROS_HORARIO_AVISO` | 08:30 | Horário diário do comando que manda os links de renovação |
| `LOGIN_COM_CODIGO` | `true` | Login do admin com senha + código de 6 dígitos por email. `false` só em desenvolvimento (o `sistema:verificar-producao` acusa em produção) |
| `FILA_PELO_AGENDADOR` | `true` em `APP_ENV=local`, `false` nos outros | O agendador roda `queue:work --stop-when-empty --max-time=55 --tries=3` a cada minuto, em segundo plano e sem sobreposição. Em desenvolvimento, basta o `schedule:work` para os emails saírem. Em produção, `false` com supervisor; `true` só se a hospedagem não tiver supervisor |
| `LIMITE_IP_ACOES_POR_MINUTO` | 60 | Pedidos por minuto de um mesmo IP nas ações públicas (reservar, cadastro, minhas reservas, reenviar, links do email), somando todas. Alto porque o Wi-Fi da faculdade junta muitos alunos num IP |
| `LIMITE_IP_CONSULTAS_POR_MINUTO` | 120 | Pedidos por minuto de um mesmo IP nas consultas do calendário e dos equipamentos |
| `LIMITE_POR_PESSOA_POR_MINUTO` | 5 | Pedidos por minuto do mesmo RA (ou do mesmo link do email) em cada ação pública. A chave guarda só um HMAC do RA |
| `LIMITE_CONSULTA_POR_RA_POR_MINUTO` | 20 | Consultas por minuto do limite semanal do curso com o mesmo RA |
| `ALERTAS_EMAIL` | vazio | Email (ou lista separada por vírgula) que recebe os alertas, além dos admins ativos |
| `ALERTAS_INTERVALO_MINUTOS` | 60 | Intervalo mínimo entre dois alertas do mesmo problema |
| `LOG_DAILY_DAYS` | 14 | Por quantos dias os arquivos de log são guardados (um por dia) |
| `ADMIN_INICIAL_NOME` / `_EMAIL` / `_SENHA` | vazio | Admin principal criado pelo `AdminSeeder`. Uso em desenvolvimento; em produção, preferir `admin:criar-principal` e deixar a senha vazia |

### Fila e jobs (o que são)

**Job** é uma tarefa que o sistema anota para fazer depois, em vez de fazer no meio da requisição. Hoje todos os jobs são **envios de email**.

- Mandar email demora (conectar no servidor de email, enviar, esperar resposta). Se a API fizesse isso durante a reserva, o aluno esperaria a tela carregar, e uma falha no servidor de email derrubaria a reserva.
- Por isso todo `Mail::to(...)->queue(...)` só **anota** o envio na fila (milissegundos) e a resposta volta na hora. Um processo separado, o **worker** (`php artisan queue:work`), pega os jobs da fila e envia de fato.
- A fila fica no banco (`QUEUE_CONNECTION=database`): tabela **`jobs`** (esperando) e **`failed_jobs`** (deram erro depois das novas tentativas; podem ser reenviados com `php artisan queue:retry all`).
- **LGPD:** um job guarda tudo o que é preciso para o email (endereço, nome, dados da reserva). Os que falham ficariam em `failed_jobs` para sempre, então o agendador apaga os que falharam há mais de 7 dias.
- Sem o worker rodando, os emails ficam parados na tabela `jobs` e não saem.

### Agendador (tarefas automáticas)

Definidas em `routes/console.php`, no fuso `APP_TIMEZONE` (America/Sao_Paulo). Nenhuma roda duas vezes ao mesmo tempo (`withoutOverlapping`).

| Tarefa | Quando | O que faz |
|---|---|---|
| `reservas:vencer-solicitacoes` | A cada 15 minutos | Encerra os pedidos de equipamento que passaram do prazo de resposta e pede ao aluno para manter a reserva |
| `reservas:revogar-nao-confirmadas` | A cada 15 minutos | Revoga as reservas que passaram do prazo de confirmação, de reconfirmação ou de manter a reserva sem o equipamento |
| `reservas:limpar-tokens` | Todo dia, 03:10 | Apaga links de email usados ou vencidos há mais de 30 dias |
| `model:prune` (`DesafioLogin`) | Todo dia, 03:15 | Apaga os códigos de login vencidos há mais de 1 dia |
| `sanctum:prune-expired --hours=24` | Todo dia, 03:20 | Apaga tokens de login vencidos |
| `queue:prune-failed --hours=168` | Todo dia, 03:30 | Apaga jobs que falharam há mais de 7 dias |
| `parceiros:avisar-vencimento` | Todo dia, 08:30 | Manda ao responsável de cada sistema parceiro com acesso vencendo em até 30 dias um link para aceitar os termos de novo e gerar um token novo (uma vez por vencimento) |
| `reservas:pedir-reconfirmacao` | Todo dia, 08:00 | Pede a reconfirmação das reservas que acontecem em até 3 dias úteis |
| `feriados:importar {ano seguinte}` | 1º de dezembro, 03:00 | Importa os feriados do próximo ano (sem os facultativos, salvo `RESERVA_FERIADOS_FACULTATIVOS=true`) |

Em desenvolvimento: `php artisan schedule:work` (roda o agendador) e `php artisan schedule:list` (mostra as tarefas e a próxima execução).

### Quem envia os emails da fila: supervisor ou agendador (`FILA_PELO_AGENDADOR`)

Os emails da fila precisam de alguém rodando o `queue:work`. Há **dois jeitos**, e **só um deve estar em uso** em cada ambiente. A variável `FILA_PELO_AGENDADOR` diz qual (decisão do usuário em 17/11/2026, depois de um email de reserva ficar parado por falta do worker).

**Jeito 1 — supervisor (worker sempre ligado). `FILA_PELO_AGENDADOR=false`.**
- O **supervisor** é um programa do servidor Linux que mantém outro programa rodando o tempo todo e o liga de novo se ele cair ou se o servidor reiniciar. Aqui ele mantém o `php artisan queue:work` sempre ligado (configuração logo abaixo, em "Em produção").
- O worker fica olhando a fila sem parar: o email sai **em segundos** depois da reserva.
- **Por que desligar a fila pelo agendador quando há supervisor:** o worker do supervisor já envia tudo; ligar os dois não quebra nada (cada email é pego por um só, com trava), mas sobe um segundo processo a cada minuto sem necessidade e confunde quem for investigar um problema ("quem enviou este email?"). Por isso, **com supervisor, `false`**.
- **Quando usar:** em produção, sempre que a hospedagem permitir (VPS, servidor da faculdade, Laravel Forge e Laravel Cloud têm supervisor ou equivalente). É o **padrão do `.env.production.example`**.

**Jeito 2 — agendador (a cada minuto). `FILA_PELO_AGENDADOR=true`.**
- O agendador (o mesmo que roda as revogações e a reconfirmação) chama, **uma vez por minuto**, `queue:work --stop-when-empty --max-time=55 --tries=3`: envia o que estiver na fila e **encerra sozinho**. Nunca roda dois ao mesmo tempo (`withoutOverlapping`), roda em segundo plano (não atrasa as outras tarefas do minuto) e avisa por alerta se falhar.
- O email sai em **até ~1 minuto**. O código de login não muda: continua saindo na hora, sem fila.
- **Por que ligar:** não depende de um processo sempre rodando; basta o agendador, que o sistema já precisa de qualquer jeito.
- **Quando usar:**
  - **em desenvolvimento** (vem ligado sozinho com `APP_ENV=local`, mesmo sem a variável no `.env`): basta um terminal com `php artisan schedule:work` para emails e tarefas automáticas, e não acontece mais de o email ficar parado porque o `queue:work` não foi aberto;
  - **em produção só se a hospedagem não tiver supervisor** (ex.: hospedagem compartilhada, que só oferece cron). Aí o cron do agendador passa a cuidar também dos emails.

**Resumo:**

| Ambiente | Tem supervisor? | `FILA_PELO_AGENDADOR` | Quem envia | Atraso do email |
|---|---|---|---|---|
| Desenvolvimento | não precisa | `true` (automático com `APP_ENV=local`) | `schedule:work` a cada minuto (e o `queue:work`, se você abrir) | até ~1 min (segundos com `queue:work` aberto) |
| Produção | sim | `false` | worker do supervisor | segundos |
| Produção | não | `true` | cron do agendador a cada minuto | até ~1 min |

**Se os emails não saírem:** com `false`, confira se o worker do supervisor está rodando (`supervisorctl status`); com `true`, confira se o cron do agendador está rodando (`php artisan schedule:list` mostra a próxima execução). Nos dois casos, `php artisan queue:failed` lista o que falhou, e a tabela `jobs` mostra o que está esperando.

### Em produção (agendador e fila)

O agendador precisa de **um** cron chamando o Laravel a cada minuto:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

Com supervisor (`FILA_PELO_AGENDADOR=false`, ver a seção acima), o worker da fila precisa ficar sempre rodando e voltar sozinho se cair. Exemplo com **supervisor** (`/etc/supervisor/conf.d/reservas-worker.conf`):

```ini
[program:reservas-worker]
command=php /caminho/do/projeto/artisan queue:work --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/caminho/do/projeto/storage/logs/worker.log
stopwaitsecs=3600
```

Depois de cada deploy, rodar `php artisan queue:restart` para o worker carregar o código novo. O passo a passo completo da implantação está no [`DEPLOY.md`](DEPLOY.md), e as opções de hospedagem no [`HOSPEDAGEM.md`](HOSPEDAGEM.md).

### Alertas por email

Quando uma **tarefa agendada** termina com erro ou um **job** falha depois de todas as tentativas, o sistema envia um email aos **admins ativos** (com convite aceito) e ao `ALERTAS_EMAIL`.

- **Sem dados pessoais:** o email diz só qual tarefa ou job falhou, o tipo do erro e quando. A mensagem do erro (que pode conter um email ou um nome) fica só no log do servidor.
- **No máximo 1 por hora** para o mesmo problema (`ALERTAS_INTERVALO_MINUTOS`), para não lotar a caixa de entrada.
- **Enviado na hora, sem passar pela fila:** se o problema for a própria fila, o alerta ainda sai. Se o envio do alerta falhar (ex.: SMTP fora do ar), o erro vai para o log e as outras tentativas daquele alerta são abandonadas, para não travar o sistema esperando o servidor de email.
- Se o próprio email cair, nenhum alerta sai. Por isso o **monitor externo** do `/up` ficou registrado em "Ideias registradas para depois".

### Segurança HTTP e produção

- **CORS:** só os endereços de `CORS_ALLOWED_ORIGINS` (o front) podem chamar a API pelo navegador. A exportação expõe o cabeçalho `Content-Disposition`, para o front saber o nome do arquivo.
- **Cabeçalhos em toda resposta** (`CabecalhosDeSeguranca`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer` e, por HTTPS, `Strict-Transport-Security` (1 ano).
- **HTTPS:** em produção, todo endereço gerado pelo Laravel usa `https://`. O redirecionamento de HTTP para HTTPS é feito pelo Nginx.
- **Logs:** um arquivo por dia, guardados por 14 dias; em produção, só a partir de `warning`. Erros de regra de negócio (`RegraNegocioException`, ex.: "Link inválido ou expirado", horário ocupado) **não** vão para o log: são respostas normais da API. Os testes usam `LOG_CHANNEL=null` e não gravam no log do desenvolvimento.
- **Mensagens em português (pt_BR):** os arquivos de tradução ficam em `lang/pt_BR`, com os nomes dos campos do sistema (ex.: `data_reserva` aparece como "data da reserva"). Foram gerados pelo laravel-lang, que depois foi removido para não deixar dependência.
- **`php artisan sistema:verificar-producao`:** confere a configuração (ambiente, debug, HTTPS, CORS, banco, fila, email, logs, alertas, admin principal, feriados, caches) e mostra ✅ ou ❌ com o que fazer.

---

## 3. Estrutura do código

| Pasta | Papel |
|---|---|
| `app/Constants` | Enums: horários, status, tipo de espaço, tipo de reserva |
| `app/Http/Controllers` | Recebem a requisição e devolvem o resource. Não têm regra de negócio |
| `app/Http/Requests` | Validação da **entrada** (erro 422, com o campo) |
| `app/Http/Requests/Concerns` | Validações compartilhadas entre requests (ex.: equipamentos da reserva) |
| `app/Rules` | Regras de validação reutilizáveis (dia útil, antecedência, horário ainda reservável) |
| `app/Services` | Regras de negócio que dependem do banco (conflitos, estoque, limites), dentro de transações |
| `app/Repositories` | Consultas complexas ou reaproveitadas em vários lugares (ex.: disponibilidade de equipamentos) |
| `app/Http/Resources` | Formato das respostas JSON (sempre dentro de `data`) |
| `app/Exceptions` | `RegraNegocioException` (respostas 409/422 com mensagem e dados extras) |
| `app/Mail` + `resources/views/emails` | Emails (revogação) |
| `app/Console/Commands` | Comandos de terminal (importação de feriados) |
| `database/factories` + `database/seeders` | Dados de teste e de demonstração |
| `tests/Feature` | Testes da API, separados por assunto |
| `app/Http/Controllers/V1`, `app/Http/Resources/V1`, `app/Http/Requests/V1` | API para sistemas parceiros (`/api/v1`, passo 13): controllers e resources próprios, com tipos explícitos, para a v1 não mudar quando as rotas do front mudarem |
| `docs/` | `api-v1.openapi.json` (especificação gerada pelo Scramble e conferida no `DocumentacaoApiTest`) e `API-PARCEIROS.md` (guia para quem desenvolve um sistema parceiro) |

**Front-end (`frontend/src`):**

| Pasta | Papel |
|---|---|
| `ambientes/` | Endereço da API por ambiente (desenvolvimento e produção) |
| `app/nucleo/` | Peças usadas pelo app inteiro: `SessaoService` (token do admin no `sessionStorage`), `interceptorApi` (cabeçalhos e erros) e `erro-api.ts` (formato único de erro) |
| `app/publico/` | Área do aluno: `LayoutPublico` (identidade esportiva), páginas e `publico.routes.ts`; `ApiPublicaService` (chamadas da área pública), `RaEmMemoriaService`, `modelos.ts` (formato das respostas), `datas.ts` e `textos.ts` |
| `app/publico/inicio/` | `/`: espaços ativos em cartões, com filtro por tipo, horário e limite por curso |
| `app/publico/calendario/` | `/espacos/:id?semana=&dia=`: horário fixo em 5 colunas (dias empilhados no celular); horário livre com a escolha do dia (botões com "N livres") e a grade compacta do dia escolhido, mais as reservas do dia; "Reservado" sem identificar quem (LGPD, 13/11), motivo dos dias fechados e, na quadra, o limite do curso pelo RA |
| `app/publico/reservar/` | `/espacos/:id/reservar?data=&horario=` (fixo) ou `?data=&inicio=&fim=` (livre): RA, fim do intervalo (livre), equipamentos, resumo lateral e a tela de sucesso com o próximo email |
| `app/publico/minhas-reservas/` | `/minhas-reservas?pagina=`: RA + email no corpo do POST, só na memória da tela; reservas futuras ou todas, com status, confirmação, revogação e devolução; páginas anterior/próxima repetem o POST |
| `app/publico/link-reserva/` | `/reservas/confirmar/:token` e `/reservas/cancelar/:token` (um componente, modo pela rota): lê o token e o tira da barra de endereço; confirmar mostra os termos e exige "Li e aceito" na primeira confirmação; cancelar faz o POST |
| `app/publico/privacidade/` | `/privacidade`: termos de uso e aviso de privacidade vindos de `GET /api/termos` (aviso de texto provisório) |
| `app/publico/cartao-reserva/`, `secoes-termos/`, `situacao-reserva.ts` | Resumo de uma reserva (espaço, dia, horário, equipamentos e devolução), seções dos termos e textos de status/confirmação/devolução |
| `app/publico/cadastro/` | `/cadastro?voltar=`: cadastro do aluno com o aviso de uso dos dados; volta para a reserva com o RA preenchido |
| `app/admin/` | Área do admin: `LayoutAdmin` (neutro), páginas e `admin.routes.ts` |
| `app/admin/acesso/` | Acesso do admin (11.4 e 11.4b): `AcessoService` (login em duas etapas com o código por email — o desafio fica só na memória da tela —, reenvio do código, `/me` uma vez por sessão, sair, sair de todos, esqueci, redefinir, aceitar convite, trocar senha), guardas `exigirLogin` (manda para `/admin/entrar?voltar=`) e `somenteSemLogin`, `LayoutAcesso` (layout simples), telas `entrar`, `esqueci-senha`, `definir-senha` (redefinir e convite, modo pela rota) e `minha-conta`, regras da senha (`regras-senha.ts` + componente) e `acesso.routes.ts` (rotas `/redefinir-senha` e `/aceitar-convite`, carregadas só quando abertas) |
| `app/admin/reservas/` | Reservas do admin (11.5a): `ReservasAdminService` (consulta e exportação por POST, detalhe, revogar, devolução), `filtros.ts` (filtros da URL validados, atalhos de período, limite de 366 dias na exportação), `linha-do-tempo.ts` (criada, prazos, confirmação com a versão dos termos, reconfirmação, cancelamento, revogação), `selos.ts` (cores do status e da confirmação, sempre com texto), `lista-reservas/` (`/admin/reservas?periodo=&de=&ate=&espaco=&curso=&tipo=&status=&confirmacao=&devolucao=&pagina=`: filtros, totais por status que filtram, tabela no computador e cartões no celular, nome e curso sem RA/email), `detalhe-reserva/` (`/admin/reservas/:id`: aluno com RA e email, linha do tempo, equipamentos, revogar e registrar devolução) e `exportar-reservas/` (CSV com colunas a escolher e aviso de dados pessoais) |
| `app/admin/calendario/` | Calendário do admin (11.5b): `/admin/calendario?espaco=&semana=`, semana de segunda a domingo pela rota do admin, com quem reservou (nome · curso), o selo da confirmação, eventos com quem criou, bloqueios e "Criar evento aqui" nos horários livres; `itens-do-dia.ts` junta ocupados e livres de cada dia (fixo e livre) e monta o link do evento; `CalendarioAdminService` (espaços do admin, inclusive desativados, calendário e criar evento) |
| `app/publico/manter-reserva/` | `/reservas/manter/:token`: tira o token da barra, mostra a reserva e o prazo, e mantém a reserva sem o equipamento |
| `app/publico/reservar/pedidos-de-equipamento.ts` | Seção recolhida "Pedir equipamento de outro espaço" (11.5d): busca os itens só quando o aluno abre, mostra o espaço de origem e o disponível, zera os pedidos quando o horário muda |
| `app/admin/reservas/detalhe-reserva/responder-solicitacao.ts` | Aprovar ou recusar (com motivo) um pedido de equipamento no detalhe da reserva |
| `app/admin/compartilhado/campos-do-uso.ts` | Campos de espaço, data, horário (fixo ou início/fim) e equipamentos com o disponível no intervalo, usados pelo novo evento e pelo registro de uso; funções `horarioDoUso`, `horarioDaUrl` e `equipamentosEscolhidos` |
| `app/admin/reservas/registrar-uso/` | `/admin/reservas/registrar?espaco=&data=&horario=` ou `&inicio=&fim=` (11.5c): registro de uso posterior (aluno pelo RA, só no corpo, ou grupo), conflito com link, aviso do limite levado ao detalhe |
| `app/admin/cadastros/` | Cadastros do admin (11.6): página índice (`/admin/cadastros`), `CadastrosService`, `Paginacao` e `ConfirmarAcao` (botão com confirmação que diz a consequência) reaproveitados em todos os cadastros. **11.6a:** `espacos/` (lista com filtro de tipo; formulário de criar e editar com horário fixo ou livre, janela, duração máxima sugerida de 180 min e limite por curso; desativar e reativar) e `equipamentos/` (lista com filtro de espaço e situação; formulário com "Aceita pedido de outros espaços" e "Compartilhado com"; o espaço não muda depois de criado; desativar e reativar). Salvar um espaço limpa a lista de espaços guardada na memória (admin e público). **11.6b:** `cursos/` (uma página: criar, renomear e desativar/reativar na própria lista; salvar limpa a lista de cursos guardada do cadastro do aluno) e `datas-bloqueadas/` (lista de hoje em diante com filtros de período e espaço, feriados marcados, editar o motivo e excluir com confirmação; `form-bloqueio` para criar dia inteiro ou parte do dia, num espaço ou em todos, mostrando quantas reservas foram revogadas). **11.6c:** `alunos/` (lista com busca por nome ou RA por POST, filtro de curso e situação, sem RA e email; detalhe com RA e email, editar, desativar/reativar com o total de reservas revogadas, as 10 últimas reservas e a anonimização com confirmação forte: marcar "Entendo que não dá para desfazer" e digitar ANONIMIZAR). **11.6d:** `admins/` (lista com busca por nome ou email por POST e filtro de situação, admin principal e convite pendente marcados, sem email na lista; convidar com nome e email; detalhe com editar, reenviar o convite enquanto pendente e desativar/reativar com confirmação; o principal não mostra desativar; as proteções vêm da API) |
| `app/admin/historico/` | Histórico de auditoria (11.7): `historico.ts` (página `/admin/historico`, filtros na URL: `periodo` ou `de`/`ate`, `tipo`, `registro`, `acao`, `quem` = id do admin ou `sistema`; só ids e códigos, nada pessoal), `entrada-historico.ts` (uma mudança: quando, quem, ação, link do registro e a tabela antes → depois), `historico-do-registro.ts` (seção dos detalhes de reserva, aluno, espaço, equipamento e admin; só consulta ao abrir, as 10 mais recentes e "Ver tudo no histórico"), `rotulos.ts` (nomes dos tipos, ações e campos; Sim/Não, datas dd/mm/aaaa hh:mm, códigos traduzidos; `[oculto]` e `[anonimizado]` como vêm da API), `historico.service.ts`. Sem busca por texto: os valores guardam dados pessoais (decisão de 17/11/2026) |
| `app/admin/cadastros/estoque/` | Estoque (11.9): `estoque-equipamento.ts` (resumo total/manutenção/para reservar, ajuste e lista paginada de movimentações no detalhe do equipamento), `ajustar-estoque.ts` (formulário reaproveitado no detalhe da reserva, com tipo, quantidade e reserva já preenchidos a partir da devolução), `tipos.ts` (nomes e ajudas dos tipos). Também: `admin/compartilhado/campos-do-responsavel.ts` (responsável do evento na Nova reserva e no Registrar uso) e `reservas/detalhe-reserva/responsavel-do-evento.ts` (mostra, corrige e remove a pedido) |
| `app/admin/cadastros/sistemas-parceiros/` | Sistemas parceiros (13c): lista com busca por sistema ou organização e situação (aguardando aceite, ativo, acesso vencido, desativado); formulário com responsável, finalidade, permissões e limite por minuto; no detalhe, termos aceitos, vencimento, link pendente, "Enviar link de aceite/renovação", "Revogar o token", desativar/reativar e o histórico. O admin nunca vê o token |
| `app/publico/aceite-parceiro/` | `/parceiros/aceite/:token` (13c): tira o token da barra, mostra o cadastro, as permissões e os termos da API, exige "Li e aceito" e mostra o token **uma única vez**, com "Copiar"; nada é guardado no navegador |
| `app/admin/reservas/nova-reserva/` | `/admin/reservas/nova?espaco=&data=&horario=` ou `&inicio=&fim=` e `&tipo=evento` (11.5e): "Para um aluno (RA)" ou "Evento da faculdade"; para aluno, RA só no corpo e equipamento de outro espaço já aprovado; para evento, descrição e conflito com justificativa. Antiga página de novo evento (`/admin/eventos/novo` redireciona) |
| `app/admin/compartilhado/equipamentos-de-outros-espacos.ts` | Seção "Equipamento de outro espaço" da Nova reserva: busca na rota do admin só ao abrir, itens entram aprovados |
| `app/compartilhado/caminho-interno.ts` | Aceita só caminhos do próprio site no `?voltar=` (cadastro e login) |
| `app/compartilhado/` | Componentes usados nas duas áreas (ex.: página 404) |
| `styles.css` | Tailwind com a paleta da identidade visual (`azul`, `azul-escuro`, `azul-medio`, `amarelo`) e a Montserrat |

- **Rotas sob demanda:** `app.routes.ts` carrega a área pública e a do admin em pacotes separados (`loadChildren`), e cada página no seu pacote (`loadComponent`). O aluno nunca baixa o código do admin.
- **Cliente da API:** o `interceptorApi` só age nas chamadas para a URL da API: põe `Accept: application/json` e, com sessão, `Authorization: Bearer`. O token **nunca** vai para outro endereço. Toda falha chega às telas como `ErroApi` (`status`, `mensagem`, `campos` do 422, `dados` da regra de negócio):
  - sem conexão (0), limite de requisições (429) e erro do servidor (5xx) têm mensagens próprias; o 5xx nunca mostra o texto do servidor;
  - 401 com sessão ativa encerra a sessão ("Sua sessão terminou"); 401 sem sessão (login recusado) mostra a mensagem da API;
  - 403, 404, 409 e 422 mostram a mensagem da API, que já vem em português.
- **Área pública (11.2):**
  - **Cache por visita:** espaços e cursos são buscados uma vez; cada semana do calendário fica guardada e a tela de reserva reaproveita a semana já carregada (nenhuma requisição extra vindo do calendário). Depois de uma reserva, ou de um 409, as semanas daquele espaço são descartadas e recarregadas.
  - **Data e horário na URL** (*query params*), para voltar ou recarregar sem perder a escolha. RA e email nunca vão na URL.
  - **RA só na memória da aba** (`RaEmMemoriaService`): digitado no calendário, já vem preenchido na reserva; some ao recarregar a página e depois de reservar. Campos de RA, nome e email com `autocomplete="off"` (computadores compartilhados).
  - **Limite do curso só na quadra** (espaços com `limite_semanal_por_curso`), pela rota `POST .../disponibilidade/curso`. Em salas e laboratórios o RA só é pedido na reserva.
  - **Horário livre (11.2b):** o aluno escolhe o dia e vê a janela do espaço, os **horários ocupados** ("Reservado", eventos e bloqueios; sem nome e curso desde 13/11) e os **horários livres**; digita **início e fim** e a tela mostra na hora como vai ficar com o arredondamento de 15 min (mesmo cálculo da API, em `intervalo.ts`) ou o motivo da recusa (fora da janela, mais que a duração máxima, conflito com um ocupado, hoje antes do mínimo). "Continuar" leva à reserva com o intervalo já arredondado; lá, "Alterar horário" volta ao dia. Os equipamentos do intervalo vêm de `/equipamentos/disponibilidade`.
  - **Dados sempre atualizados sem pesar:** enquanto o calendário ou a reserva estão abertos e visíveis, o front consulta `GET /api/disponibilidade/versao` a cada 15 s (resposta de poucos bytes, uma leitura de cache na API) e também na hora em que o aluno volta para a aba; só quando a versão muda ele baixa a semana de novo, em segundo plano, sem piscar a tela. Com a aba escondida, para de consultar.
  - **Situações novas** da API (ex.: "aula", do passo 12) aparecem como "Indisponível", com o motivo, sem precisar mudar o front.
  - **Cadastro:** o `voltar` só aceita caminhos do próprio site (começando com `/`, sem `//` nem `\`), para o link não poder mandar o aluno para outro site.
  - **Escolha do horário mais amigável (11.2d):** no painel do dia, uma **barra do dia** (abertura → fechamento) mostra os trechos livres em verde, os ocupados e os horários já encerrados de hoje em cinza e a escolha em amarelo (vermelho se bater com algo); tocar num trecho livre preenche o início com o horário livre mais próximo. Os **horários livres** viraram botões ("Começar às 15:30"). O **início** aceita digitar (14:00, 14h, 1400, 9) ou escolher numa lista de sugestões só com os inícios livres de 15 em 15 min; a **duração** vem em botões (30 min a 3h, só os que cabem antes do próximo ocupado e dentro da duração máxima); o **fim** continua digitável, com sugestões. Tudo é calculado na tela com o que já veio da API (nenhuma chamada a mais). As sugestões usam o componente próprio `compartilhado/campo-horario` (combobox: a lista sempre mostra todos os horários possíveis, destaca e rola até o escolhido, e funciona com setas, Enter e Esc), sem o Angular CDK. Antes era o `<datalist>` do navegador, trocado porque ele filtra as sugestões pelo texto do campo e só mostrava o horário já escolhido.
  - **Acesso do admin (11.4):** `/admin/entrar` (login; `?voltar=` só com caminho interno; `?motivo=sessao` mostra "Sua sessão terminou"), `/admin/esqueci-senha` (resposta genérica da API), `/redefinir-senha/:token` e `/aceitar-convite/:token` (links dos emails; o token sai da barra de endereço; o email é digitado, porque a API pede token + email + senha e o email não vai no link), `/admin/minha-conta` (nome e email, trocar senha, sair de todos os dispositivos) e "Sair" no cabeçalho. Todas as rotas `/admin` (menos entrar e esqueci) exigem login; quem já entrou e abre `/admin/entrar` vai para o painel. Se a sessão acabar (8 h ou 401), o layout leva ao login com o caminho de volta. Regras da senha (8+, letra, número, confirmação) conferidas na tela enquanto digita. Sessão só na aba (`sessionStorage`).
  - **Telas pós-reserva (11.3):** "Minhas reservas" no cabeçalho e "Termos e privacidade" no rodapé e no aviso do cadastro. Minhas reservas: RA + email (decisão 3: só na memória da tela; a URL leva só `?pagina=`), resposta genérica da API para dados errados, cartões com "Reservada/Cancelada/Revogada", a etapa da confirmação ("Aguardando a sua confirmação pelo email até…", "Confirme de novo…", "Confirmada"), motivo da revogação, devolução (só depois da data) e "Para cancelar, use o link do email" (decisão 1: o aluno cancela só pelo email). Links do email (decisão 2): o token sai da barra de endereço logo que é lido (`Location.replaceState`); recarregar a página pede para abrir o link de novo. Confirmar (decisão 6: sem botão de cancelar): na primeira confirmação, termos numa caixa com rolagem e a caixa "Li e aceito"; na reconfirmação, só o botão. Página de privacidade com o texto da API (decisão 5: controlador e contato "a confirmar" no próprio texto).
  - **Calendário até o fim do semestre (11.2e):** setas e um **seletor de semana** vão da semana atual até `calendario_ate`. Os dias que ainda não abriram aparecem com o aviso amarelo "Abre para reserva em dd/mm": na quadra, os horários livres viram "Livre · ainda não abriu" (sem link) e os ocupados mostram quem/qual evento; no horário livre, o botão do dia diz "Abre dd/mm" e o painel mostra a barra e os ocupados, sem o formulário.
  - **Calendário do horário livre:** o aluno escolhe o dia (botões com "N livres", "Lotado" ou "Fechado"; o primeiro dia com horário livre vem selecionado) e vê só aquele dia em grade compacta, com os horários de início livres como link e a lista das reservas do dia. O dia vai na URL (`?dia=`).
  - **Tamanho:** cada tela é um pacote sob demanda (início 2,4 kB, calendário 4,2 kB, reserva 5 kB, cadastro 2,5 kB comprimidos); formulários do Angular (17 kB) e ícones (só os usados) também ficam fora do pacote inicial. O pacote inicial (~300 kB, 80 kB comprimido) é quase todo do Angular (núcleo, router) e do rxjs; o orçamento é 350 kB (aviso) e 500 kB (erro).
- **Sessão do admin:** token e validade (`expires_at` do login) no `sessionStorage`, que some ao fechar a aba. Sessão vencida ou conteúdo inválido é descartado ao abrir e a cada uso.
- **Datas e números em pt-BR** (`LOCALE_ID`), `<html lang="pt-BR">` e `Referrer-Policy: no-referrer` no `index.html`, para os tokens dos links de email não vazarem para outros sites.
- **Acessibilidade:** link "Pular para o conteúdo" nos dois layouts e foco visível em todos os elementos (contorno azul). Desde o 11.8b (17/11/2026), o `e2e/acessibilidade.spec.ts` roda o **axe** (`@axe-core/playwright`, só de desenvolvimento) com as regras WCAG 2.0/2.1/2.2 A e AA em 10 páginas públicas e 15 do admin, no computador e no celular, e reserva um horário só pelo teclado. **Toda página nova entra nesse teste.** Regra que o axe cobrou: o nome lido pelo leitor de tela precisa **começar pelo texto visível** (WCAG 2.5.3); informação extra vai num `<span class="sr-only">` depois do texto, nunca num `aria-label` diferente do que aparece.
- **Formatação** pelo Prettier (`.prettierrc`: aspas simples, 100 colunas). **Sem comentários** no código, como na API.

**Convenções:**

- Erros de **entrada** (campo inválido) → `422` no request.
- Erros de **regra de negócio** que dependem do estado do banco (horário ocupado, estoque, limite semanal) → `RegraNegocioException`: `409` para conflitos, `422` para as demais.
- Update só por **PATCH** (atualização parcial).
- Exclusão de espaços, equipamentos e cursos = **desativação** (`ativo = false`), para não perder o histórico.
- Sem comentários no código: as explicações ficam no `implementacoes-com-claude-code.txt` e neste documento.
- Formatação pelo **Laravel Pint** (preset `laravel`, mantendo o alinhamento de `=>`). Rodar `./vendor/bin/pint` antes de commitar.
- Todas as listagens do admin usam a mesma paginação: `per_page` padrão 15, máximo 100 (valor acima vira 100; inválido vira 15).

---

## 4. Modelo de dados

```
cursos ──< alunos ──< reservas >── espacos ──< equipamentos
                         │                          │
                         └──────< item_reservas >───┘
users (admins) ──< reservas (criador de eventos / quem revogou)
```

| Tabela | Colunas principais |
|---|---|
| `cursos` | `nome` (único), `ativo` |
| `alunos` | `nome_completo`, `registro_academico` (RA, único, 10 dígitos), `email`, `curso_id`, `ativo`, `anonimizado_em` |
| `espacos` | `nome` (único), `tipo` (QUADRA, LABORATORIO, SALA), `ativo`, `limite_semanal_por_curso` (vazio = sem limite), `modo_horario` (FIXO, LIVRE), `abre_as`, `fecha_as`, `duracao_maxima_minutos` (só no LIVRE) |
| `equipamentos` | `espaco_id`, `nome` (único dentro do espaço), `quantidade_total`, `ativo` |
| `equipamento_compartilhamentos` | Espaços com os quais o admin compartilhou cada equipamento (`espaco_id`, `equipamento_id`) |
| `reservas` | `data_reserva`, `horario` (enum), `hora_inicio`, `hora_fim`, `espaco_id`, `aluno_id`, `status`, `tipo` (ALUNO, EVENTO), `descricao`, `justificativa_revogacao`, `revogada_em`, `revogada_por`, `cancelada_em`, `confirmar_ate`, `confirmada_em`, `termos_aceitos_em`, `termos_versao`, `reconfirmacao_pedida_em`, `reconfirmar_ate`, `reconfirmada_em` |
| `tokens_reserva` | `reserva_id`, `finalidade` (CANCELAR, CONFIRMAR), `token_hash` (SHA-256, único), `expira_em`, `usado_em` |
| `item_reservas` | `reserva_id`, `equipamento_id`, `quantidade`, `situacao_devolucao` (PENDENTE, DEVOLVIDO, DEVOLVIDO_COM_AVARIA, NAO_DEVOLVIDO), `quantidade_devolvida`, `observacao_devolucao`, `devolvido_em`, `devolvido_por` (model `ItemReserva`, pivot auditado) |
| `datas_bloqueadas` | `data_inicio`, `data_fim`, `hora_inicio`/`hora_fim` (vazias = dia inteiro), `espaco_id` (vazio = todos), `motivo`, `origem` (MANUAL, FERIADO) |
| `auditorias` | `auditavel_type`/`auditavel_id` (registro alterado), `acao` (CRIACAO, ATUALIZACAO, EXCLUSAO), `antes`, `depois` (JSON), `user_id` (admin), `created_at` |

Cursos, alunos, espaços, equipamentos, reservas e datas bloqueadas têm também `criado_por` e `atualizado_por` (auditoria). Diagrama e decisões de modelagem em [`DATABASE.md`](DATABASE.md).

---

## 5. Endpoints

### Públicos (alunos, sem login)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/cursos` | Cursos ativos (para o cadastro) |
| GET | `/api/disponibilidade/versao` | Código que muda sempre que algo do calendário muda (reserva, bloqueio, espaço, equipamento, aluno, curso) e a cada 5 minutos; o front consulta a cada 15 s e só recarrega a semana quando ele muda |
| POST | `/api/alunos` | Cadastro de aluno |
| GET | `/api/espacos?tipo=QUADRA` | Espaços ativos (filtro opcional) |
| GET | `/api/espacos/{id}/equipamentos` | Equipamentos reserváveis no espaço (os dele + os que o admin compartilhou com ele) |
| GET | `/api/espacos/{id}/equipamentos/disponibilidade?data=&hora_inicio=&hora_fim=` | Disponível de cada equipamento num intervalo (usado nos espaços LIVRE) |
| GET | `/api/espacos/{id}/disponibilidade?de=&ate=` | Calendário do espaço: situação de cada horário e equipamentos disponíveis (opcional `curso_id`) |
| POST | `/api/espacos/{id}/disponibilidade/curso` | O mesmo calendário com o limite semanal do curso do aluno (`registro_academico`, `de`, `ate` no corpo); só em espaços com limite por curso |
| POST | `/api/reservas` | Cria uma reserva |
| POST | `/api/admin/alunos/consulta` e `/api/admin/admins/consulta` | As mesmas listas do GET, com os filtros no corpo: o front usa POST para a busca por nome ou RA não ir na URL (LGPD; 17/11/2026) |
| POST | `/api/minhas-reservas/reenviar-confirmacao` | Reenvia o email de confirmação (RA, email e reserva no corpo); invalida os links anteriores; prazo igual; até 3 reenvios por etapa, 5 min entre eles |
| POST | `/api/minhas-reservas` | O aluno consulta as próprias reservas (RA + email no corpo) |
| GET | `/api/reservas/cancelamento/{token}` | Resumo da reserva do link do email (`pode_cancelar`) |
| POST | `/api/reservas/cancelamento/{token}` | Cancela a reserva do link do email |
| GET | `/api/reservas/confirmacao/{token}` | Resumo da reserva do link de confirmação (`etapa`, `pode_confirmar`, `prazo_confirmacao`, `exige_aceite_termos`, `termos_versao`) |
| POST | `/api/reservas/confirmacao/{token}` | Confirma a reserva; na primeira confirmação exige `{"aceite_termos": true}` |
| GET | `/api/termos` | Termos de uso e aviso de privacidade (versão e seções), com cache de 1 hora |
| POST | `/api/login` | Login do admin, 1ª etapa: email e senha certos mandam o código por email e respondem `etapa`, `desafio` e `expira_em` (com `LOGIN_COM_CODIGO=false`, responde direto com `token`, `expires_at` e `user`); 5/min |
| POST | `/api/login/codigo` | 2ª etapa: `desafio` + `codigo` → `token`, `expires_at` e `user`; 10/min |
| POST | `/api/login/reenviar` | Manda um código novo para o mesmo `desafio` (até 3 vezes, 60 s entre elas); 3/min |
| POST | `/api/esqueci-senha` | Pede o link de redefinição de senha (resposta sempre igual) |
| POST | `/api/redefinir-senha` | Define a senha nova com o token do email |
| POST | `/api/convites/aceitar` | Admin convidado define a senha com o token do convite |

`POST /api/alunos`, `POST /api/reservas`, `POST /api/minhas-reservas` e as rotas de cancelamento e de confirmação aceitam no máximo 10 requisições por minuto. O login e o "esqueci a senha" aceitam 5; a redefinição e o aceite de convite, 10. A consulta de disponibilidade aceita 60; a consulta com o RA (`/disponibilidade/curso`), 20.

**Consulta de disponibilidade:**

`GET /api/espacos/1/disponibilidade?de=2026-11-09&ate=2026-11-13`

- `de` é obrigatório. `ate` é opcional (sem ele, a consulta é de um dia) e o período pode ter até 31 dias, para o front navegar pelo calendário.
- Opcional: `curso_id`. Com ele, a resposta mostra todas as reservas igualmente e acrescenta os dados do curso (`curso`: `id`, `nome`, `limite_semanal` e `semanas` com quantas reservas ele já tem e quantas restam), `do_curso` em cada reserva e `disponivel_para_o_curso` em cada horário.
- **Com o RA do aluno** (o front mostra "quantas reservas o seu curso ainda tem"): `POST /api/espacos/{id}/disponibilidade/curso` com `registro_academico`, `de` e `ate` no **corpo**. A resposta é a mesma, mas `curso` vem **sem `id` e `nome`** (traz só `limite_semanal` e `semanas`).
  - Só vale em espaços com `limite_semanal_por_curso` (hoje, a quadra); nos outros responde 422, sem nem procurar o aluno. Em salas e laboratórios o RA só é pedido na reserva.
  - RA inexistente ou de aluno inativo: 422 "Não encontramos um aluno ativo com este RA." (o mesmo que a reserva já revela). Espaço inativo: 404. No máximo 20 consultas por minuto.
  - O `GET` não aceita mais o `registro_academico`: um RA na URL ficaria nos logs do servidor e no histórico do navegador, e a validação respondia diferente para RA cadastrado e não cadastrado com 60 tentativas por minuto.
- `reservas_ate`: último dia que aceita reservas de alunos (10 dias úteis à frente).
- `calendario_ate` (11.2e): último dia que o calendário mostra — o **fim do semestre letivo** (jan–jun, jul–dez), com no mínimo 4 semanas a partir de hoje. Consultas que começam depois dele recebem 422 ("O calendário mostra os dias até dd/mm/aaaa (fim do semestre)."); um `ate` depois dele é cortado nele.
- Cada dia tem `reservavel`, `motivo` (data passada, fim de semana, bloqueio do dia inteiro ou "Abre para reserva em dd/mm/aaaa.") e **`abre_em`**: a data em que o dia passa a aceitar reservas (dia − 10 dias úteis), só quando ele está fechado **apenas** porque ainda não abriu (com bloqueio do dia inteiro, vem `null` e o motivo é o do bloqueio). Os ocupados (reservas, eventos, bloqueios) aparecem normalmente nesses dias; depois dos 10 dias úteis não existem reservas de alunos, então só aparecem eventos e bloqueios.
- Cada horário tem `situacao`: `LIVRE`, `RESERVADO`, `EVENTO`, `BLOQUEADO`, `INDISPONIVEL` (o dia não aceita reservas) ou `ENCERRADO` (horário de hoje passou do limite). Também traz `motivo`, `reserva` (`id`, `tipo` e, só nos eventos, `descricao`; desde 13/11/2026 **não identifica quem reservou**: nome, curso, RA e email ficam só para os admins) e `equipamentos` (`total` e `disponivel` naquele horário, incluindo os compartilhados com o espaço).
- **Espaços LIVRE:** `espaco` traz `modo_horario`, `abre_as`, `fecha_as`, `duracao_maxima_minutos` e `bloco_minutos` (o passo do arredondamento, 15). Cada dia traz `reservavel`, `motivo`, **`ocupados`** (lista de `inicio`, `fim`, `situacao` = `RESERVADO`, `EVENTO` ou `BLOQUEADO`, `motivo` e `reserva`, sem quem reservou) e, só para hoje, **`fim_minimo`** (o primeiro fim aceito hoje, já arredondado; se passar do fechamento, o dia vem fechado com "Os horários de hoje já foram encerrados."). Não há mais blocos nem `horarios` no horário livre: a resposta fica pequena e o front monta o resto. Equipamentos do intervalo escolhido: `/equipamentos/disponibilidade`.


**Exemplo de reserva:**

```json
POST /api/reservas
{
  "registro_academico": "2024000001",
  "espaco_id": 1,
  "data_reserva": "2026-11-10",
  "horario": "PRIMEIRO_HORARIO",
  "equipamentos": [ { "id": 1, "quantidade": 2 } ]
}
```

Num espaço **LIVRE**, no lugar de `horario`: `"hora_inicio": "14:00", "hora_fim": "15:30"`.

### Aceite dos termos da API (público, link do email do responsável)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/parceiros/termos` | Termos de uso da API em vigor (versão e seções) |
| GET | `/api/parceiros/aceite/{token}` | Resumo do pedido de acesso (sistema, organização, responsável, finalidade, permissões, se é renovação) e os termos |
| POST | `/api/parceiros/aceite/{token}` | Aceita os termos (`aceite_termos: true`) e devolve o token **uma única vez** (`Cache-Control: no-store`) |

### Sistemas parceiros (`/api/v1`, token de parceiro)

Guia completo em [`docs/API-PARCEIROS.md`](docs/API-PARCEIROS.md) e especificação em [`docs/api-v1.openapi.json`](docs/api-v1.openapi.json). Toda rota passa por `auth:sanctum`, `parceiro` (só token de sistema parceiro ativo; token de admin recebe 403), `throttle:parceiros` (limite do sistema e por RA) e `permissao:<permissão>` (403 com `dados.permissoes_necessarias`).

| Método | Rota | Permissão | Descrição |
|---|---|---|---|
| GET | `/api/v1/espacos` | `espacos:ler` | Espaços ativos (`?tipo=`) |
| GET | `/api/v1/espacos/{id}/disponibilidade` | `espacos:ler` | Calendário, igual ao público (sem quem reservou) |
| GET | `/api/v1/espacos/{id}/equipamentos` | `espacos:ler` | Equipamentos usáveis no espaço |
| GET | `/api/v1/espacos/{id}/equipamentos/disponibilidade` | `espacos:ler` | Disponível de cada equipamento no intervalo |
| POST | `/api/v1/reservas` | `reservas:criar` | Reserva para um aluno pelo RA (regras do site, confirmação pelo email do aluno, origem `API`) |
| GET | `/api/v1/reservas` | `reservas:ler` | Só as reservas do próprio sistema (`?de=&ate=&status=`), paginadas, sem dados pessoais |
| GET | `/api/v1/reservas/{id}` | `reservas:ler` | Uma reserva do próprio sistema (404 para as outras) |
| POST | `/api/v1/reservas/{id}/cancelar` | `reservas:cancelar` | Cancela uma reserva do próprio sistema antes do início; o aluno recebe o email de cancelamento |

### Admin (exigem `Authorization: Bearer <token>`)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/admin/clientes-api` | Sistemas parceiros (`?busca=` sistema ou organização, `?ativo=`) |
| GET | `/api/admin/clientes-api/permissoes` | Permissões que um sistema pode receber, com o nome de cada uma |
| POST | `/api/admin/clientes-api` | Cadastra (`nome`, `organizacao`, `responsavel_nome`, `responsavel_email`, `finalidade`, `permissoes[]`, `limite_por_minuto`) e manda o link de aceite ao responsável |
| GET/PATCH | `/api/admin/clientes-api/{id}` | Detalhe (com `situacao`, termos, vencimento e link pendente; nunca o token) e edição (trocar permissões vale para o token atual; trocar o email do responsável invalida o link pendente e manda outro; reativar manda link novo) |
| DELETE | `/api/admin/clientes-api/{id}` | Desativa e apaga os tokens na hora |
| POST | `/api/admin/clientes-api/{id}/enviar-aceite` | Manda um link novo ao responsável; com `revogar_atual: true`, apaga o token atual na hora (vazamento) |
| POST | `/api/logout` · `/api/logout-geral` | Encerra o token atual ou todos os tokens |
| GET | `/api/me` | Admin logado (`UserResource`) |
| PATCH | `/api/me/senha` | Troca a própria senha (`senha_atual`, `password`, `password_confirmation`) |
| GET | `/api/admin/auditorias` | Histórico de alterações (filtros abaixo) |
| GET | `/api/admin/admins` | Lista de admins (`?busca=` nome/email, `?ativo=`) |
| POST | `/api/admin/admins` | Convida um admin (`nome`, `email`); ele recebe o link para definir a senha |
| GET | `/api/admin/admins/{id}` | Detalhe do admin |
| PATCH | `/api/admin/admins/{id}` | Edita nome, email e `ativo` (reativar) |
| DELETE | `/api/admin/admins/{id}` | Desativa o admin (sem login e sem sessões) |
| POST | `/api/admin/admins/{id}/reenviar-convite` | Reenvia o convite de quem ainda não definiu a senha |
| GET/POST/PATCH/DELETE | `/api/admin/espacos` | CRUD de espaços (`?tipo=`); `modo_horario`, `abre_as`, `fecha_as`, `duracao_maxima_minutos` para horário livre |
| GET/POST | `/api/admin/equipamentos/{id}/movimentacoes` | Movimentações do estoque (lista paginada; registrar com `tipo`, `quantidade`, `estado`, `reserva_id`, `da_manutencao`) |
| PATCH | `/api/admin/reservas/{id}/responsavel` | Corrige ou remove a pedido o responsável de um evento |
| GET/POST/PATCH/DELETE | `/api/admin/equipamentos` | CRUD de equipamentos (`?espaco_id=`, `?nome=`, `?ativo=`). A quantidade só é informada na criação; depois muda pelas movimentações. `espacos_compartilhados`: lista de ids dos outros espaços onde o item aparece (vazia = só no próprio espaço; o próprio espaço dá 422 "O equipamento já pertence a este espaço."); tirar um espaço com reserva futura do item dá 409 com `dados.espacos`. A resposta do admin traz `espacos_compartilhados` (`id`, `nome`); a rota pública não traz |
| GET/POST/PATCH/DELETE | `/api/admin/cursos` | CRUD de cursos |
| GET | `/api/admin/alunos` | Lista de alunos (`?busca=` nome/RA/email, `?curso_id=`, `?ativo=`) |
| GET | `/api/admin/alunos/{id}` | Detalhe do aluno, com o total de reservas futuras |
| PATCH | `/api/admin/alunos/{id}` | Edita nome, RA, email, curso e `ativo` (desativar revoga as reservas futuras) |
| DELETE | `/api/admin/alunos/{id}` | Desativa o aluno e revoga as reservas futuras (responde com `reservas_revogadas`) |
| POST | `/api/admin/alunos/{id}/anonimizar` | Anonimiza os dados pessoais (LGPD); exige `{"confirmar": true}` |
| GET/POST/PATCH/DELETE | `/api/admin/datas-bloqueadas` | Bloqueios (`?de=`, `?ate=`, `?espaco_id=`); PATCH altera só o motivo |
| GET | `/api/admin/reservas` | Lista reservas com filtros (ver abaixo) |
| GET | `/api/admin/espacos/{id}/disponibilidade?de=&ate=` | Calendário do admin (11.5b): o mesmo do público, mais `reservado_por` (nome e curso do aluno, ou o admin do evento) e `situacao_confirmacao`; sem o limite do fim do semestre (passado e futuro), até 31 dias; vale para espaço desativado; no horário livre, o bloqueio do dia inteiro aparece em `ocupados` |
| POST | `/api/admin/reservas/consulta` | A mesma lista, com os filtros no corpo: usada pelo front para a **busca por nome ou RA não ir na URL** (LGPD; desde 16/11/2026) |
| GET/POST | `/api/reservas/manter/{token}` | Pública: resumo (`pode_manter`) e manter a reserva sem o equipamento pedido (link do email) |
| POST | `/api/admin/reservas/{id}/solicitacoes/{equipamento}/aprovar` | Aprova o pedido de equipamento (11.5d) |
| POST | `/api/admin/reservas/{id}/solicitacoes/aprovar` e `/recusar` | Aprova ou recusa todos os pedidos aguardando da reserva de uma vez (17/11/2026) |
| POST | `/api/admin/reservas/{id}/solicitacoes/{equipamento}/recusar` | Recusa o pedido, com `justificativa` (11.5d) |
| POST | `/api/admin/reservas/aluno` | Reserva feita pelo admin para um aluno (11.5e): mesmas regras do site, confirmação pelo email, origem `ADMIN`; `solicitacoes` entram já aprovadas |
| GET | `/api/admin/espacos/{id}/equipamentos/solicitaveis` | Itens de outros espaços que aceitam pedido, sem o limite de prazo (para o pedido presencial) |
| POST | `/api/admin/reservas/registro-posterior` | Registra um uso que já aconteceu ou está acontecendo (aluno pelo RA ou grupo); responde a reserva e `aviso` (limite semanal) |
| GET/POST | `/api/admin/reservas/exportar` | Exporta as reservas filtradas em CSV (o front usa POST, pelo mesmo motivo) |
| GET | `/api/admin/reservas/exportar/colunas` | Colunas obrigatórias e opcionais da exportação |
| GET | `/api/admin/reservas/{id}` | Detalhe de uma reserva |
| POST | `/api/admin/reservas/eventos` | Reserva de evento da faculdade |
| POST | `/api/admin/reservas/{id}/revogar` | Revoga uma reserva (`justificativa` obrigatória) |
| POST | `/api/admin/reservas/{id}/devolucao` | Registra (ou corrige) a devolução dos equipamentos da reserva |

**Listagem de reservas:** `GET /api/admin/reservas?de=2026-11-01&ate=2026-11-30&curso_id=2&status=RESERVADO`

- Filtros opcionais e combináveis: `periodo` (atalho: `HOJE`, `SEMANA_ATUAL`, `PROXIMA_SEMANA`, `MES_ATUAL`, `MES_ANTERIOR`, `ANO_ATUAL`; não combina com `de`/`ate`), `de`, `ate`, `espaco_id`, `tipo_espaco`, `curso_id`, `busca` (nome ou RA do aluno, busca parcial), `aluno_id` (reservas de um aluno, usado no detalhe do aluno), `status`, `tipo`, `horario`, `devolucao` (situação de algum item: só reservas ativas que já começaram; sem `de`/`ate`, não aplica o padrão de hoje em diante, para achar pendências antigas), `confirmacao` (`AGUARDANDO_CONFIRMACAO`, `CONFIRMADA`, `AGUARDANDO_RECONFIRMACAO` ou `RECONFIRMADA`; só reservas de aluno feitas pelo site), `origem` (`SITE`, `ADMIN` ou `REGISTRO_POSTERIOR`).
- Sem `de` e `ate`, lista de hoje em diante. `ordem=asc|desc` (padrão `asc`, por data e horário). `per_page` até 100 (padrão 15).
- Cada reserva traz `aluno` (nome, RA, email e curso), `espaco`, `equipamentos`, `criado_por` (eventos), `revogada_por` e a justificativa (revogadas), além de **`situacao_confirmacao`** (`AGUARDANDO_CONFIRMACAO`, `CONFIRMADA`, `AGUARDANDO_RECONFIRMACAO`, `RECONFIRMADA`; `null` nos eventos), calculada em PHP pela mesma regra do filtro `confirmacao` (`Reserva::situacaoConfirmacao()`).
- `totais_por_status` traz a contagem de cada status com os mesmos filtros, menos o de status.

**Exportação de reservas:** `GET /api/admin/reservas/exportar?periodo=MES_ANTERIOR&colunas[]=curso&colunas[]=equipamentos`

- Aceita os mesmos filtros da listagem (inclusive `periodo` e `ordem`). Período de no máximo 366 dias.
- Gera um CSV que abre direto no Excel em português (separador `;`, UTF-8 com BOM, datas em dd/mm/aaaa), com nome conforme o período (ex.: `reservas_2026-10-01_a_2026-10-31.csv`).
- **Colunas obrigatórias** (sempre saem): ID, data, horário, espaço, tipo, status e responsável (aluno ou admin do evento).
- **Colunas opcionais** (`colunas[]=...`, ou `colunas[]=todas`): `tipo_espaco`, `registro_academico`, `email`, `curso`, `equipamentos`, `devolucao` (ex.: `Bola: Devolvido com avaria 2/2 (Furada); Rede: Pendente 0/1`), `descricao`, `criada_em`, `revogacao` (revogada por, em e justificativa). Relações só são carregadas quando a coluna é pedida.
- Gerado em *stream*, em lotes de 500 reservas, sem carregar tudo na memória.
- Valores que começam com `=`, `+`, `-` ou `@` ganham um `'` na frente, para o Excel não executá-los como fórmula.

---

## 6. Regras de negócio

### Reservas de alunos

| Regra | Erro |
|---|---|
| RA cadastrado, espaço ativo | 422 |
| Data não pode ser passada nem cair em fim de semana | 422 |
| No máximo **10 dias úteis** à frente (fins de semana não contam) | 422 |
| Horário de **hoje**: aceito até **20 minutos antes do término** (ex.: 19:00–20:30 até as 20:10) | 422 |
| Um espaço aceita **uma reserva por horário** | 409 |
| Espaço com `limite_semanal_por_curso` (quadra = 3): no máximo N reservas do mesmo curso de **segunda a sexta** ("O seu curso já atingiu o limite…", sem o nome do curso) | 409 |
| Data/horário não pode estar numa **data bloqueada** (mensagem traz o motivo) | 422 |

### Horários fixos e livres

- **FIXO** (padrão; a quadra): a reserva usa `horario` (`PRIMEIRO_HORARIO` 19:00–20:30, `SEGUNDO_HORARIO` 20:30–22:00, iguais para todos os espaços). Mandar `hora_inicio`/`hora_fim` é recusado.
- **LIVRE**: o espaço tem janela (`abre_as`–`fecha_as`) e `duracao_maxima_minutos`; a reserva usa `hora_inicio` e `hora_fim`:
  - **arredondados para o múltiplo de 15 min mais próximo** antes de validar (o aluno digita o que quiser; a resposta traz o horário final), dentro da janela, fim depois do início, duração até a máxima do espaço (422);
  - mandar `horario` é recusado;
  - as outras regras valem igual: dia útil, antecedência, hoje até 20 min antes do **fim escolhido**, bloqueios (inclusive de parte do dia), conflito por sobreposição (14:00–15:00 e 15:00–16:00 não conflitam), limite semanal (conta reservas, não horas), confirmação, cancelamento e devolução.
- **Eventos** em espaço LIVRE: horários arredondados da mesma forma, podendo sair da janela e sem duração máxima.
- **Configuração:** o admin define no cadastro/edição do espaço (no LIVRE, os três campos são obrigatórios, alinhados ao bloco, fechamento depois da abertura, duração máxima múltipla do bloco e que caiba na janela). **Trocar o modo** com reservas futuras é recusado (409). Mudar a janela ou a duração não afeta as reservas já feitas.

### Pedido de equipamento de outro espaço (11.5d, com o ajuste de 16/11/2026)

- Na reserva, além dos equipamentos do espaço (e dos compartilhados), o aluno pode **pedir** um equipamento de outro espaço, se o admin marcou o item como `aceita_solicitacao`. Lista: `GET /api/espacos/{id}/equipamentos/solicitaveis?data=&hora_inicio=&hora_fim=` (item, espaço de origem, disponível e `responder_ate`). O pedido vai no `POST /api/reservas`, no campo `solicitacoes` (`[{id, quantidade}]`).
- **Prazo de resposta do admin**, gravado no item (`responder_ate`): **24 h antes do início**; se isso deixar ao admin menos de **2 h**, o **prazo curto** é **3 h antes do início**; se nem assim houver 2 h, o pedido online é recusado com "Faltam poucas horas para a reserva: peça o equipamento diretamente na coordenação" (a lista vem vazia com `responder_ate` nulo). Valores em `RESERVA_SOLICITACAO_*`.
- O pedido **segura a quantidade** enquanto aguarda (conta na disponibilidade para todos); recusado ou vencido, libera na hora. A aprovação vale **só para aquela reserva**: não vira compartilhamento (outra reserva que quiser o item pede de novo).
- O admin responde no detalhe: `POST /api/admin/reservas/{id}/solicitacoes/{equipamento}/aprovar` ou `/recusar` (com `justificativa`); 409 se já respondido ou a reserva não está ativa. Sem resposta, vence no `responder_ate` (`reservas:vencer-solicitacoes`, a cada 15 min).
- **Vários pedidos e um email só (17/11/2026):** o aluno pode pedir vários itens e marcar cada um como **obrigatório** ("A reserva só serve com este item"; só informação para o admin, que vê a marca no detalhe). O admin responde item por item ou com **"Aprovar todos"/"Recusar todos"** (`POST /api/admin/reservas/{id}/solicitacoes/aprovar` e `/recusar`, um motivo só). Quando **todos** os pedidos da reserva têm resposta (ou vencem, todos juntos), sai **um email só**: todos aprovados → "Seu pedido de equipamento foi aprovado"; algum recusado ou sem resposta → a lista de cada item (aprovado, recusado com o motivo, sem resposta) e a pergunta de manter.
- **Recusado ou sem resposta: a reserva será cancelada, a menos que o aluno a mantenha** (lógica pedida pelo usuário em 16/11/2026; substituiu a opção A). A reserva recebe `manter_ate` = aviso + **12 h**, nunca depois de **1 h antes do início** (se já faltar menos de 1 h, não pergunta e a reserva continua). O email "Equipamento não liberado: confirme se quer manter a reserva" traz **"Manter a reserva sem o equipamento"** (link com token, página `/reservas/manter`) e o link de cancelar. `GET/POST /api/reservas/manter/{token}`. Sem clicar, a revogação automática (a cada 15 min) revoga com "Equipamento não liberado e a reserva não foi mantida." Se outro pedido da mesma reserva for recusado depois, não pergunta de novo.
- "Minhas reservas" mostra o aviso com o prazo; o admin vê o selo "Aguardando o aluno manter" e a linha do tempo. Só itens normais e pedidos aprovados entram na devolução. Lista: filtro `solicitacao` e selo "Pedido de equipamento"; painel com o contador. Exportação: a coluna "Equipamentos" mostra a situação do pedido.

### Equipamentos na reserva

| Regra | Erro |
|---|---|
| Equipamento ativo, do próprio espaço **ou** compartilhado com ele pelo admin | 422 |
| Sem equipamento repetido; quantidade ≥ 1 e ≤ estoque total | 422 |
| Quantidade ≤ **disponível** naquela data/horário | 409 (lista o que faltou e quanto há) |

**Disponível** = `quantidade_total` − **pico de uso** no intervalo: o maior total reservado (status `RESERVADO`, **em qualquer espaço**) em um mesmo momento. Ex.: 2 projetores das 14:00 às 15:00 e outros 2 das 15:00 às 16:00 dão pico 2 no intervalo 14:00–16:00 (e não 4). Nos horários fixos o resultado é o mesmo da soma.
O cálculo fica todo em `DisponibilidadeEquipamentoRepository`: uma única consulta agrupada por equipamento, independente de quantos itens forem pedidos.
Exemplo: 2 microscópios compartilhados com 3 labs e os 3 labs no mesmo horário → o terceiro recebe 409.

### Reserva feita pelo admin para um aluno (11.5e)

- Na página **Nova reserva** (`/admin/reservas/nova`, aberta por "Reservar aqui" nos horários livres futuros do calendário ou pelo botão "Nova reserva"), o admin escolhe **"Para um aluno (RA)"** ou **"Evento da faculdade"**.
- Para aluno: `POST /api/admin/reservas/aluno`, com as **mesmas regras do site** (dia útil, antecedência, horário ainda reservável, conflito, limite semanal do curso) e o mesmo **pedido de confirmação por email**; fica com `origem = ADMIN` e `criado_por` = o admin, e aparece com o selo **"Pelo admin"** na lista e no detalhe.
- **Equipamento de outro espaço** (o "pedido presencial"): o admin escolhe na seção "Equipamento de outro espaço", que usa `GET /api/admin/espacos/{id}/equipamentos/solicitaveis` (sem o limite de prazo do site); o item entra **já aprovado**, com o estoque conferido.
- `/admin/eventos/novo` redireciona para a página nova.

### Registro de uso posterior (admin, 11.5c)

- `POST /api/admin/reservas/registro-posterior`: o admin registra um uso que **está acontecendo ou já aconteceu** (ex.: alunos acharam a sala livre e usaram sem reservar).
- Quem usou: **aluno pelo RA** (`registro_academico`; vira reserva do tipo ALUNO, conta no limite semanal do curso e o aluno recebe o email "Registramos um uso de espaço em seu nome") **ou grupo** (`descricao`, ex.: "Turma de Enfermagem"; vira tipo EVENTO, sem email). Um dos dois, nunca os dois.
- Datas: de 30 dias atrás até hoje; hoje, só se o início já passou.
- Não valem antecedência, dia útil, janela do espaço nem confirmação; valem espaço ativo, horário do espaço (fixo ou intervalo de 15 em 15 min), bloqueios, conflito (409 com `reserva_conflitante_id`, sem revogar nada) e estoque dos equipamentos.
- Limite semanal: conta, mas não bloqueia; a resposta traz `aviso` quando o curso passa do limite.
- Fica com `origem = REGISTRO_POSTERIOR` e `criado_por` = o admin; selo **"Registro posterior"** (fúcsia, contorno tracejado) na lista, no detalhe e no calendário do admin; no calendário público aparece só como "Reservado". Filtro `origem` na lista e coluna opcional "Origem" na exportação.

### Eventos (admin)

- Podem ser em fim de semana e não têm limite de antecedência.
- Se o horário estiver ocupado: sem `justificativa` → 409 com `reserva_conflitante_id`; com `justificativa` → a reserva do aluno é revogada, o aluno recebe email e o evento é criado, tudo na mesma transação.
- Podem reservar equipamentos (os da reserva revogada já ficam liberados).
- **Responsável (11.9, 17/11/2026):** todo evento tem `responsavel_nome` (obrigatório, decisão do usuário: "o nome é bom sempre") e `responsavel_contato` (opcional), também no registro de uso posterior de um grupo. Só aparece nas rotas do admin (`responsavel: {nome, contato}`); o público continua vendo só a descrição. `PATCH /api/admin/reservas/{id}/responsavel` corrige (`responsavel_nome`, `responsavel_contato`) ou **remove a pedido** (`remover: true`), trocando também os valores por `[removido]` no histórico de auditoria (LGPD); a tela mostra "Responsável removido". Na devolução com pendência de um evento, o email vai ao contato quando ele é um endereço de email.

### Estoque de equipamentos (admin, 11.9)

- Cada equipamento tem `quantidade_total` e `quantidade_em_manutencao`; o **utilizável** (total − manutenção) é o que vale para o disponível, para a validação da reserva e para o que o aluno vê.
- Toda mudança é uma **movimentação** com tipo, quantidade e **estado do equipamento** (obrigatório, até 500 caracteres): Entrada, Foi para manutenção, Voltou da manutenção, Baixa (com "estas unidades estavam em manutenção"), **Não houve devolução** (baixa ligada a uma reserva ou evento que tem o item; `reserva_id` obrigatório) e Ajuste de inventário (com sinal).
- Nenhuma movimentação deixa o utilizável abaixo do maior uso já reservado **a partir de agora** (409 com `dados.maior_uso_futuro`); manutenção ou baixa além do que existe dá 422.
- A quantidade não muda mais pelo `PATCH` do equipamento; criar um equipamento registra a entrada inicial ("Cadastro do equipamento.").
- `GET /api/admin/equipamentos/{id}/movimentacoes` (paginado, da mais recente) e `POST` no mesmo endereço (resposta com a movimentação e o equipamento atualizado).
- Tela: no detalhe do equipamento, resumo (total, em manutenção, para reservar), "Ajustar estoque" e a lista; no detalhe da reserva, item devolvido com avaria ou não devolvido ganha "Ajustar o estoque" já preenchido (manutenção ou não houve devolução, com a quantidade e a observação). A tela avisa para não escrever nomes no estado.
- Desempenho: a disponibilidade lê duas colunas (nada de somar movimentações); a lista é paginada pelo índice `(equipamento_id, created_at)`.

### Sistemas parceiros (passo 13)

- **Quem é quem:** a instituição é a controladora; cada sistema parceiro tem uma **pessoa responsável** (nome e email) que aceita os **termos de uso da API** (`app/Support/TermosApi.php`, rascunho versionado em `PARCEIROS_TERMOS_VERSAO`) e responde pelo uso.
- **Cadastro pelo admin → link ao responsável → aceite → token.** O link vale 7 dias e guarda só o hash (`aceite_token_hash`). O token é do Sanctum, pertence ao `ClienteApi`, tem as permissões como *abilities* e vence em 12 meses. Aparece uma única vez na página de aceite. O admin nunca vê o token.
- **Situações** (calculadas, sem coluna): `DESATIVADO` (`ativo = false`), `AGUARDANDO_ACEITE` (sem acesso), `VENCIDO` (`acesso_expira_em` passou), `ATIVO`.
- **Renovação:** 30 dias antes do vencimento, o comando diário manda um link de renovação (uma vez). No aceite, sai um token novo; **o antigo continua até vencer**, para o parceiro trocar sem parar. Em caso de vazamento, o admin usa "Revogar o token" (`revogar_atual`), que apaga na hora.
- **Separação dos tokens:** o middleware `admin.ativo` recusa com 403 qualquer token que não seja de admin, e o `parceiro` recusa o de admin na v1. Os dois rodam **antes** da busca do registro da rota (prioridade antes do `SubstituteBindings`): sem isso, um parceiro receberia 404 ou 403 conforme o id existisse.
- **Auditoria:** `auditorias.cliente_api_id` diz qual sistema fez a mudança (`user_id` fica nulo). Na tela, "Sistema parceiro: nome". `criado_por` das reservas só guarda admins.
- **Reservas:** origem `API` e `reservas.cliente_api_id`; selo "Por sistema parceiro" e o nome do sistema no detalhe do admin. O parceiro só vê e cancela as próprias reservas, sem nome, email, curso nem RA.

### Datas bloqueadas (admin)

- Um dia ou um período; para todos os espaços ou um só; o dia inteiro ou uma janela de horas (que vale em cada dia do período).
- A janela usa **sobreposição**: um bloqueio das 20:00 às 21:00 atinge os dois horários da quadra.
- Bloqueiam **reservas de alunos**. **Eventos ignoram** bloqueios (a faculdade pode fazer evento num feriado ou recesso).
- Ao criar um bloqueio, as reservas ativas de alunos atingidas são **revogadas automaticamente**, com o motivo como justificativa, e cada aluno recebe o email. A resposta traz `reservas_revogadas`.
- Só o motivo pode ser editado. Para mudar o período, exclui e cria outro. Excluir não devolve as reservas revogadas.
- **Feriados:** `feriados:importar` usa a biblioteca Yasumi. Por padrão importa os 9 feriados oficiais + Sexta-feira Santa; `--facultativos` inclui Carnaval (seg e ter), Quarta de Cinzas e Corpus Christi. Rodar de novo não duplica. Feriados estaduais/municipais são cadastrados pelo admin.

### Revogação (admin)

- Exige `justificativa` (10 a 500 caracteres); só reservas ativas e não passadas.
- Registra quem revogou e quando; o aluno é avisado por email (enviado pela fila, depois do commit).
- Revogadas e canceladas liberam o horário, o estoque e o limite semanal.

### Espaços e equipamentos

- Não é possível desativar um espaço com reservas futuras (409).
- Não é possível reduzir o estoque de um equipamento abaixo do maior uso já reservado num mesmo horário (409).

### Concorrência

Reservas simultâneas são tratadas com *locks* dentro da transação:

- trava a **linha do espaço**, porque o conflito de horário depende dela, e quando ainda não existe reserva não há linha de reserva para travar;
- trava as **linhas dos equipamentos** pedidos (em ordem de id, para evitar deadlock), porque os compartilhados podem ser disputados por espaços diferentes;
- o admin, ao reduzir o estoque, trava o mesmo equipamento.

### Auditoria

- Cursos, alunos, espaços, equipamentos, reservas e datas bloqueadas guardam `criado_por` e `atualizado_por` (o admin logado). Ações pela rota pública (aluno) e por comandos (importação de feriados) ficam com esses campos vazios.
- Toda criação, alteração e exclusão grava uma linha em `auditorias` com os valores **antes** e **depois** (só os campos que mudaram), quem fez e quando. Alteração sem mudança real não gera linha.
- O preenchimento é automático, pelo trait `App\Models\Concerns\Auditavel`, nos eventos do Eloquent. Por isso as alterações precisam passar pelo model (`$model->update()`); um `update` em massa direto no query builder não é auditado.
- Nas rotas do admin, as respostas trazem `criado_por` e `atualizado_por` (id e nome), `criado_em` e `atualizado_em`. As rotas públicas não mostram esses campos.
- Admins (`users`) e itens de reserva (`item_reservas`) também são auditados.
- No banco, `auditavel_type` guarda um **nome curto** (`reserva`, `aluno`, `espaco`, `equipamento`, `curso`, `data_bloqueada`, `item_reserva`, `admin`), não o nome da classe PHP (morph map no `AppServiceProvider`).

**Consulta do histórico:** `GET /api/admin/auditorias`, paginada, da mais recente para a mais antiga.

- Filtros: `tipo` (`RESERVA`, `ALUNO`, `ESPACO`, `EQUIPAMENTO`, `CURSO`, `DATA_BLOQUEADA`, `ITEM_RESERVA`, `ADMIN`), `registro_id` (exige `tipo`; ex.: `?tipo=RESERVA&registro_id=15` é o histórico da reserva 15), `admin_id` (o que um admin fez), `sem_admin=true` (feito pela rota pública ou pelo sistema; não combina com `admin_id`), `acao` (`CRIACAO`, `ATUALIZACAO`, `EXCLUSAO`), `periodo` ou `de`/`ate`.
- Cada item: `tipo`, `registro_id`, `acao`, `campos_alterados`, `antes`, `depois`, `feito_por` (`{id, nome}` ou `null`) e `em`.
- Senhas aparecem como `[oculto]`; dados de aluno anonimizado, como `[anonimizado]`.

### Reenvio do email de confirmação (17/11/2026)

- Em "Minhas reservas" (RA + email), a reserva que aguarda confirmação ou reconfirmação mostra **"Reenviar o email de confirmação"**: `POST /api/minhas-reservas/reenviar-confirmacao` com `registro_academico`, `email` e `reserva_id` no corpo (limite das ações públicas: 60/min por IP e 5/min por RA).
- O email novo tem links novos e **os links de confirmar e cancelar do email anterior deixam de valer**. O **prazo não muda**.
- No máximo **3 reenvios por etapa** (confirmação ou reconfirmação), com **5 min** entre eles, contados pelos próprios links gravados (sem coluna nova). Dados errados ou reserva de outro aluno: a mesma resposta genérica de "Minhas reservas" (404); reserva que não aguarda confirmação: 409.

### Cancelamento pelo aluno (link do email)

- Ao reservar, o aluno recebe **"Confirme sua reserva"** (ver abaixo) com os detalhes, o botão de confirmar e o botão **Cancelar reserva**, que leva a `FRONTEND_URL/reservas/cancelar/{token}` (página do front Angular).
- O front chama `GET /api/reservas/cancelamento/{token}` para mostrar o resumo (sem RA, email ou nome) e `POST /api/reservas/cancelamento/{token}` para cancelar. **Só o POST cancela**: leitores de email e antivírus abrem links sozinhos.
- O token tem 64 caracteres aleatórios; no banco fica só o **hash SHA-256**. Vale **até o início da reserva** e só pode ser usado **uma vez**. Token inválido, vencido ou usado: `404` com "Link inválido ou expirado.".
- Cancelar: só reservas `RESERVADO` (409) que não começaram (422). Grava `CANCELADO` e `cancelada_em`, libera horário e equipamentos e envia **"Reserva cancelada"**. Na auditoria fica sem admin (foi o aluno).
- Eventos não recebem esses emails nem token.
- O link do email abre a página do front (`/reservas/cancelar/{token}` ou `/reservas/confirmar/{token}`, passo 11.3), que tira o token da barra de endereço e chama a API. Em desenvolvimento os emails ficam em `storage/logs/laravel.log`; copie o link de lá com o front rodando (`npm start`).

### Confirmação da reserva (item 9, refeito no passo 11.3a em 13/11/2026)

Ideia do usuário (10/11/2026): **toda reserva de aluno é confirmada pelo email**, e a confirmação **exige aceitar os termos de uso e o aviso de privacidade** (prova de que o dono do email fez a reserva e de que foi informado; protege a instituição e o desenvolvedor). Substituiu o fluxo antigo (pedido 2 dias úteis antes, prazo até a véspera, reserva para hoje já confirmada).

- **Na criação:** a reserva nasce `RESERVADO` e já **segura o horário**, com `confirmar_ate` = **24 horas** depois (`RESERVA_CONFIRMACAO_HORAS`) ou, se for **para hoje**, **60 minutos** depois (`RESERVA_CONFIRMACAO_MINUTOS_MESMO_DIA`); nos dois casos, **nunca depois do início** (se a reserva de hoje já começou, o limite é o fim). O aluno recebe **"Confirme sua reserva"** com **Confirmar** (`FRONTEND_URL/reservas/confirmar/{token}`, token `CONFIRMAR` que vence em `confirmar_ate`) e **Cancelar** (token `CANCELAR`, vence no início; se já começou, no fim). Se a reserva for depois da janela de reconfirmação, o email avisa que virá um segundo pedido perto da data.
- **Confirmar (primeira vez):** o front mostra os termos (`GET /api/termos`) e envia `POST /api/reservas/confirmacao/{token}` com `{"aceite_termos": true}`. Sem o aceite: 422 em `aceite_termos` ("Para confirmar, leia e aceite os termos de uso e o aviso de privacidade."). Grava `confirmada_em`, `termos_aceitos_em` e `termos_versao` (`RESERVA_TERMOS_VERSAO`, hoje `2026-11`). Na auditoria fica sem admin.
- **Reconfirmação perto da data:** o comando `reservas:pedir-reconfirmacao` (todo dia às 08:00) pede uma nova confirmação das reservas confirmadas que acontecem até **3 dias úteis** à frente (`RESERVA_RECONFIRMACAO_DIAS_UTEIS_ANTES`) e depois de amanhã. Grava `reconfirmacao_pedida_em` e `reconfirmar_ate` = **00:00 da véspera** (o email mostra "até 23:59" do dia anterior à véspera). **Dispensa:** quem confirmou a primeira há menos de **2 dias úteis** antes da data do pedido (`RESERVA_RECONFIRMACAO_DISPENSADA_DIAS_UTEIS`) não recebe a segunda (acabou de provar que vai usar). A reconfirmação **não pede o aceite de novo** e grava `reconfirmada_em`.
- **Revogação automática:** o comando `reservas:revogar-nao-confirmadas` roda **a cada 15 minutos** e revoga (justificativa "Reserva não confirmada no prazo.", email de revogação, sem admin na auditoria) as reservas `RESERVADO` com `confirmar_ate` vencido sem `confirmada_em` **ou** com `reconfirmar_ate` vencido sem `reconfirmada_em`. São duas consultas por índice (`status` + prazo), leves mesmo rodando a cada 15 min.
- **Resumo do link:** `GET /api/reservas/confirmacao/{token}` devolve a reserva (sem dados do aluno) e `etapa` (`CONFIRMACAO` ou `RECONFIRMACAO`), `pode_confirmar`, `prazo_confirmacao`, `exige_aceite_termos` (só na primeira) e `termos_versao`.
- **Termos:** `GET /api/termos` devolve `versao`, `rascunho: true` e as seções de `termos_de_uso` e `privacidade` (título e parágrafos, com os prazos reais vindos da configuração). O texto é um **rascunho do Claude**; nome da instituição, contato do encarregado (LGPD) e prazo do histórico estão como "[a confirmar com a instituição]". Mudou o texto, muda a versão. Resposta com `Cache-Control: public, max-age=3600`.
- **Eventos** não precisam de confirmação.
- O `ReservaResource` mostra `confirmar_ate`, `confirmada_em`, `termos_aceitos_em`, `termos_versao`, `reconfirmacao_pedida_em`, `reconfirmar_ate` e `reconfirmada_em`.

### Devolução de equipamentos (admin)

- Todo item de reserva nasce `PENDENTE`. Depois que a reserva começa, o admin registra cada item:

```json
POST /api/admin/reservas/15/devolucao
{
  "itens": [
    { "equipamento_id": 1, "quantidade_devolvida": 2, "situacao": "DEVOLVIDO" },
    { "equipamento_id": 2, "quantidade_devolvida": 0, "situacao": "NAO_DEVOLVIDO", "observacao": "Rede não foi entregue" }
  ]
}
```

- Só reservas **ativas** (409 se revogada/cancelada) e **já iniciadas** (422 antes do horário de início).
- Quantidade coerente com a situação: `DEVOLVIDO` = quantidade inteira; `DEVOLVIDO_COM_AVARIA` = de 1 até a reservada; `NAO_DEVOLVIDO` = menos que a reservada. Itens inválidos voltam em `dados.itens_invalidos` (422). `observacao` é obrigatória em avaria e não devolvido.
- Chamar de novo **corrige** o registro; cada alteração fica no histórico de auditoria (`ItemReserva`).
- Avaria ou falta **avisa o aluno por email** (uma vez por mudança; repetir o mesmo registro não reenvia). Eventos não têm aluno, então não há email.
- **Não** mexe no estoque: o admin decide se reduz a `quantidade_total`. **Não** bloqueia o aluno de novas reservas.
- Pendências: `GET /api/admin/reservas?devolucao=PENDENTE` (ou `NAO_DEVOLVIDO`, `DEVOLVIDO_COM_AVARIA`).

### Admins e senhas

- **Email do admin sempre em minúsculas** (e sem espaços nas pontas): no cadastro, na edição, no login e na redefinição. Assim `Ana@fac.edu` e `ana@fac.edu` são a mesma conta em qualquer banco, e não existe admin duplicado.
- **Convite:** o admin novo é criado com uma senha aleatória (inutilizável) e recebe **"Convite para acessar o sistema de reservas"**, com o link `FRONTEND_URL/aceitar-convite/{token}`, válido por **2 dias**. No front, ele informa o email e a senha nova (`POST /api/convites/aceitar`). Dá para reenviar enquanto o convite não foi aceito.
- **Esqueci a senha:** `POST /api/esqueci-senha` responde sempre a mesma mensagem (não revela quais emails são de admins); admin ativo recebe **"Redefinição de senha"** com `FRONTEND_URL/redefinir-senha/{token}`, válido por **60 minutos**, no máximo um pedido por minuto. No front, informa email e senha nova (`POST /api/redefinir-senha`).
- Os links levam **só o token** (sem email na URL). Os tokens ficam com hash, um por email, em tabelas **separadas** para convite (`convites_admin_tokens`) e redefinição (`password_reset_tokens`), para um link de 60 minutos não valer 2 dias na rota do convite.
- **Senha nova:** no mínimo 8 caracteres, com letras e números, e confirmação.
- Ao **redefinir** ou **aceitar convite**: todas as sessões do admin caem e os links pendentes dele são invalidados. Ao **trocar logado** (`PATCH /api/me/senha`, exige a senha atual): caem as outras sessões, a atual continua. Nos três casos, o admin recebe **"Sua senha foi alterada"**.
- **Limites de pedidos das rotas públicas (11.8a, 17/11/2026):** duas camadas, com limitadores nomeados em `AppServiceProvider`:
  - `acoes-publicas` (reservar, cadastro do aluno, minhas reservas, reenviar a confirmação e os links do email de confirmar, cancelar e manter): **60/min por IP**, somando todas as ações, **e 5/min por pessoa em cada rota** (pessoa = o RA do corpo ou o token do link);
  - `consulta-com-ra` (limite semanal do curso pelo RA): 120/min por IP e 20/min por RA;
  - `consultas-publicas` (disponibilidade do calendário e dos equipamentos): 120/min por IP; a versão da disponibilidade continua em 300/min;
  - login, código, esqueci a senha, redefinir e convite continuam com os limites próprios (5, 10 e 3 por minuto por IP).
  - Por quê: no Wi-Fi da faculdade muitos alunos saem pelo mesmo IP, e o limite antigo (10/min por IP) recusaria todos depois do 10º pedido; o limite por pessoa impede testar um RA ou um link à vontade. A chave da pessoa é um **HMAC do RA com a `APP_KEY`** (o RA nunca vai em claro para o cache). Valores no `.env` (`LIMITE_*`). Custo: uma leitura e uma escrita no cache por limite.
- **Proteção das rotas do admin (17/11/2026):** todas as rotas `api/admin/*`, `/me`, `/me/senha`, `/logout` e `/logout-geral` ficam no grupo `auth:sanctum` + `admin.ativo`. O `admin.ativo` (`app/Http/Middleware/AdminAtivo.php`) recusa com 401 um admin desativado mesmo que ainda tenha token, e apaga os tokens dele. O `RotasProtegidasTest` percorre a lista de rotas e exige 401 sem token em todas elas, para que uma rota nova criada fora do grupo seja pega pelo teste. Não há papéis: toda conta em `users` é admin, então autenticado = autorizado (Policies só se surgirem papéis).
- **Desativar admin:** ele não faz mais login e perde todas as sessões na hora. Ninguém desativa a si mesmo, e sempre sobra pelo menos um admin ativo (verificado com trava). Reativar é `PATCH` com `ativo: true`.
- **Login** de admin inativo ou com senha errada: a mesma resposta, "Credenciais inválidas".
- **Código de acesso (11.4b, 16/11/2026):** depois da senha certa, um código de 6 dígitos vai por email **na hora** (sem fila, para o login não depender do `queue:work`); vale 10 minutos, uma vez só, com até 5 tentativas (na 5ª errada é preciso digitar a senha de novo) e até 3 reenvios com 60 s entre eles; um login novo invalida o código anterior. Erros com a mesma mensagem genérica ("Código inválido ou vencido. Entre de novo se precisar."). Se o envio falhar, 503 e nada fica guardado. O código nunca vai para os logs do sistema e não aparece no assunto do email ("Seu código de acesso").
- **Auditoria:** convites, edições e desativações de admins ficam no histórico; senha e `remember_token` aparecem só como `[oculto]`.
- **Admin principal** (um só; conta de testes e manutenção): **ninguém o desativa** (409) e **só ele mesmo edita os próprios dados** (403 para os outros). Fora isso, tem os mesmos poderes dos outros admins. A flag `principal` não muda pela API: o seeder cria o `admin@email.com` como principal em desenvolvimento; em produção, `php artisan admin:criar-principal` (pergunta nome, email e senha, que não aparece na tela) e `php artisan admin:transferir-principal {email}` (passa o papel para outro admin ativo que já definiu a senha).

### Alunos (admin)

- **Desativar** (DELETE ou PATCH com `ativo: false`) revoga as reservas ativas que ainda não terminaram, com a justificativa "Cadastro do aluno desativado." e email para o aluno. Reativar é PATCH com `ativo: true`.
- Aluno inativo não reserva (RA recusado com 422) e não pode ser usado na consulta de disponibilidade.
- **Mudança de curso:** o limite semanal conta pelo curso atual do aluno, então as reservas dele passam a contar para o curso novo.
- O RA continua único; ao editar, a validação ignora o próprio aluno.

### LGPD

- **Rotas públicas** nunca mostram RA, email, nem nome e curso de quem reservou (decisão de 13/11/2026; antes mostravam nome e curso). O público vê "Reservado"; quem reservou, só os admins.
- **RA nunca na URL:** a consulta do limite do curso pelo RA é um `POST` com o RA no corpo (`/disponibilidade/curso`), com 20 por minuto, só em espaços com limite por curso, e sem o nome do curso na resposta.
- **Consulta das próprias reservas** (`POST /api/minhas-reservas`): exige RA **e** email do mesmo aluno (email sem diferenciar maiúsculas), enviados no **corpo** da requisição, nunca na URL. Dados errados recebem sempre `404` com a mesma mensagem ("Não encontramos reservas para esses dados."), sem revelar se o RA existe. Aluno inativo consulta o próprio histórico; aluno anonimizado não aparece. A resposta não traz RA, email nem auditoria.
  - `periodo`: `FUTURAS` (padrão; de hoje em diante, ordem crescente, todos os status) ou `TODAS` (ordem decrescente). Paginada (`per_page` na URL).
- **Anonimização** (`POST /api/admin/alunos/{id}/anonimizar`, irreversível, exige `"confirmar": true`): troca nome por "Aluno anonimizado", RA por `A` + id (ex.: `A000000042`) e email por um endereço inválido; desativa o aluno; revoga as reservas futuras **sem enviar email**; e substitui nome, RA e email por `[anonimizado]` no histórico de auditoria. As reservas passadas continuam, para as estatísticas, ligadas ao aluno anônimo. O RA original fica livre para um novo cadastro.
- Aluno anonimizado não pode mais ser editado, desativado ou anonimizado de novo (409).
- **Fora do sistema:** CSVs já exportados e emails já enviados não são alcançados pela anonimização. Em desenvolvimento, `MAIL_MAILER=log` grava os emails (com dados pessoais) em `storage/logs`; em produção, usar um serviço de envio e não o log.

### Compatibilidade com bancos de dados

O desenvolvimento usa SQLite, mas o código foi ajustado para rodar igual em **MySQL (5.7+)** e **Postgres**:

- Buscas por texto usam `whereLike(..., caseSensitive: false)`: vira `like` no MySQL/SQLite e `ilike` no Postgres, sem diferenciar maiúsculas em nenhum deles.
- Nomes únicos (espaços, cursos e equipamentos no mesmo espaço) são validados com a regra `NomeUnico`, que compara em minúsculas (`LOWER`). Assim "Quadra" e "quadra" são o mesmo nome em qualquer banco (o MySQL já fazia isso; o Postgres e o SQLite, não).
- Datas são comparadas direto na coluna (sem `whereDate`), o que funciona nos três e usa os índices.
- Não há SQL específico de um banco; as consultas escritas à mão usam só `SUM`, `COUNT` e `GROUP BY`.
- O SQLite ignora `lockForUpdate` (ele trava o banco inteiro na escrita). No MySQL e no Postgres, as travas por linha passam a valer de fato.

Os testes rodam localmente em SQLite em memória e, no **CI**, também em **MySQL 8.0** e **Postgres 16** (ver seção 8). No SQLite, `LOWER` só converte letras sem acento, então "Ética" e "ética" passam como nomes diferentes; no MySQL e no Postgres, não.

---

## 7. Decisões tomadas

| Decisão | Motivo |
|---|---|
| Aluno sem login, só com RA | Simplicidade de uso. Risco: alguém usar o RA de outra pessoa. Mitigação: a cada reserva o dono do RA recebe email com link de cancelamento |
| Listagem de alunos só para admin | Evita expor nome, RA e email publicamente |
| Desativar aluno revoga as reservas futuras | Normalmente o aluno saiu da faculdade; manter as reservas só prenderia horários |
| Anonimizar em vez de apagar o aluno | Atende o pedido de exclusão da LGPD sem perder as estatísticas de uso; o histórico de auditoria também é limpo |
| Anonimização exige `confirmar: true` | É irreversível; evita execução acidental |
| Cancelamento até o **início** da reserva | Com a tolerância de 20 minutos, outra pessoa ainda consegue reservar o horário liberado |
| Link do email aponta para o **front Angular** (`FRONTEND_URL`) | Padrão do projeto é Angular, sem Blade; a API fica pronta e testada, e a página entra com o front (passo 11 do CHECKLIST) |
| Token genérico em `tokens_reserva` (`CANCELAR`/`CONFIRMAR`) | Reaproveitado no item 9 (confirmação); só o hash no banco, validade e uso único |
| Resumo do cancelamento sem o nome do aluno | O link pode ser encaminhado; o resumo não expõe ninguém |
| Só o POST cancela | GET pode ser disparado por leitores de email e antivírus |
| Devolução registrada por item, na própria `item_reservas` | Cada equipamento pode voltar de um jeito; a intermediária já é a linha "item da reserva" |
| Model `ItemReserva` como *pivot* (`->using()`) | A intermediária ganhou regras e auditoria; o `attach()` e as relações continuam iguais |
| Só a devolução, sem registro de retirada | A retirada é implícita no horário da reserva; pode entrar depois |
| Avaria/falta não reduz o estoque sozinha | O item pode ser consertado; o admin ajusta a `quantidade_total` se for perda |
| Item não devolvido **não** bloqueia o aluno | Decisão do usuário: o controle fica no registro, na listagem de pendências e no email |
| Listagem de reservas sem filtro de data mostra **de hoje em diante** | É o que o admin mais consulta; o histórico vem com `de`/`ate` |
| Totais por status **ignoram o filtro de status** | O painel mostra quantas estão ativas, revogadas e canceladas mesmo quando a lista exibe só uma delas |
| Consulta com filtros de reservas em `ReservaRepository` | Começou no service; foi para o repositório quando a exportação passou a reaproveitá-la |
| Exportação em **CSV**, não `.xlsx` | Abre no Excel sem biblioteca nova; `.xlsx` fica para quando alguém pedir |
| Colunas obrigatórias + opcionais na exportação | O arquivo traz só o necessário: menos tamanho e menos consultas (relações só carregam se a coluna for pedida) |
| Auditoria com colunas **e** tabela de histórico | As colunas mostram o último a alterar; o histórico mostra o que mudou em cada alteração (antes/depois) |
| Auditoria por trait nos eventos do Eloquent | Nenhum service precisa lembrar de preencher; vale para qualquer alteração feita pelo model |
| `reservas.user_id` renomeado para `criado_por` | Mesmo nome de coluna em todas as tabelas auditadas |
| CI no GitHub Actions com SQLite, MySQL 8.0 e Postgres 16, Pint e `composer audit` | Primeira verificação real nos bancos de produção possíveis; roda em push e pull request para a `main`; só PHP 8.3 (a do projeto) |
| Sem obrigar branches/pull requests (por enquanto) | Só o usuário mexe no projeto; o CI avisa a cada push. Proteção de branch quando houver mais pessoas |
| Código compatível com SQLite, MySQL e Postgres | O banco de produção ainda não foi escolhido; buscas e nomes únicos se comportam igual nos três |
| Proteção contra fórmulas no CSV | Nome ou descrição começando com `=` poderia virar fórmula no Excel (*CSV injection*) |
| Cursos em **tabela**, não enum | Permitir reaproveitar o sistema em outras instituições da rede |
| Equipamento pertence a um espaço; compartilhamento por lista de espaços escolhida pelo admin (11.2c) | Por padrão a bola fica na quadra e o microscópio no laboratório; a flag `compartilhavel` antiga liberava para todos os espaços (o usuário viu o microscópio na quadra) |
| Disponibilidade **calculada** a partir das reservas, num repositório | Ela muda por data e horário; um número fixo no equipamento não representa isso. Uma tabela de ocupação teria que ser atualizada em cada operação e poderia ficar errada. O repositório concentra a consulta (uma ida ao banco, com índices) |
| Repositórios **só para consultas complexas** | Entidades com operações simples usam o Eloquent direto nos services; repositório onde a query é complexa ou reaproveitada |
| Data da reserva gravada como `Y-m-d` | Permite comparar a coluna diretamente e usar índices (o `whereDate` impede o uso de índice) |
| Espaço exclusivo por horário | Regra atual da quadra. Está isolada em `verificarConflitoDeEspaco()` e pode ser removida se um dia o espaço for compartilhado |
| Reserva guarda `hora_inicio` e `hora_fim`; conflito por **sobreposição** | Funciona igual nos horários fixos e livres |
| Modo de horário **por espaço** (FIXO/LIVRE) | A quadra continua fixa; laboratórios e salas podem ter horário livre |
| Passo de **15 min global** (configurável), com **arredondamento na API** | Ideia do usuário (10/11/2026): o aluno digita qualquer hora e a API arredonda para não sobrarem horários quebrados (14:07); antes era 30 min e o horário precisava vir alinhado |
| Duração mínima de 1 bloco; **máxima por espaço** | Cada espaço decide quanto tempo uma reserva pode ocupar |
| Equipamentos pelo **pico de uso** | Somar reservas que se sobrepõem ao intervalo superestima o uso quando elas não acontecem ao mesmo tempo |
| Calendário LIVRE com **janela e intervalos ocupados**, sem blocos | Pedido do usuário (11.2b): o aluno vê o que está ocupado e monta o próprio horário; a resposta fica menor que a lista de blocos |
| Atualização por **versão** (`/disponibilidade/versao`) a cada 15 s | O usuário pediu dados atualizados com o menor intervalo possível sem afetar o desempenho: a consulta de versão é uma leitura de cache; a semana só é baixada quando algo muda. A versão também muda a cada 5 min, porque o "hoje" (horários encerrados) muda com o relógio |
| Eventos podem sair da janela e passar da duração máxima | Decisão do admin (ex.: palestra até 23:00) |
| Limite semanal continua contando **reservas**, não horas | Mantém a regra combinada para a quadra |
| Trocar o modo com reservas futuras é recusado | Reserva fixa em espaço livre (ou o contrário) ficaria incoerente |
| Horários fixos iguais para todos os espaços | Horários fixos por espaço ficam como opcional |
| Regras de entrada em `app/Rules` (uma linha no request) | Fáceis de ligar e desligar quando a regra mudar |
| Números do negócio em `config/reservas.php` | Mudar 10 dias úteis, 20 minutos ou 2 dias sem mexer no código |
| Antecedência em **dias úteis** | Com dias corridos, quem reservava na sexta só alcançava 5 dias úteis; contando dias úteis, a janela é a mesma qualquer dia da semana. Feriados contam como dia útil na contagem e aparecem no calendário como bloqueados: a janela fica previsível e não muda quando um bloqueio é cadastrado. Prazo de 10 dias úteis |
| Disponibilidade como **calendário** (até 31 dias) | O front navega por semanas/meses; reservas continuam limitadas a 10 dias úteis, e os dias fora da janela vêm com o motivo |
| ~~Consulta mostra **quem reservou** (nome e curso)~~ (substituída em 13/11/2026) | Facilitava o mapeamento do uso |
| Calendário público **não identifica quem reservou**; só os admins veem nome e curso | Pedido do usuário (13/11): mínima exposição (LGPD); para o aluno basta saber que o horário está ocupado |
| Um email só por reserva quando todos os pedidos de equipamento têm resposta; qualquer item não aprovado pede para manter | Pedido do usuário (17/11/2026): um email por item confundia; a marca "obrigatório" ficou só como informação para o admin (opção A), porque a regra de manter vale para qualquer item não aprovado |
| Reenvio do email de confirmação invalida os links anteriores e não muda o prazo | Ideia do usuário (17/11/2026): email perdido ou no spam; reenviar não pode dar mais tempo; limite de 3 por etapa e 5 min entre eles contra abuso |
| Admin reserva para um aluno com as mesmas regras do site e com confirmação por email (11.5e) | Protege contra RA digitado errado e mantém um fluxo só; o pedido de equipamento feito na coordenação entra já aprovado (17/11/2026) |
| Prazo de resposta do pedido gravado no item: 24 h antes, prazo curto 3 h antes, mínimo de 2 h ao admin, senão presencial | Pedido do usuário (16/11/2026): 2 h era em cima da hora para quem vem de outra cidade; reservas próximas ainda podem pedir com prazo curto |
| Pedido recusado ou sem resposta: a reserva é cancelada se o aluno não clicar em "Manter" em até 12 h (nunca depois de 1 h antes do início) | Pedido do usuário (16/11/2026, "lógica invertida"): libera o espaço de quem só queria com o equipamento; segue o padrão da confirmação por email |
| Pedido de equipamento no próprio item da reserva, segurando a quantidade | Sem tabela nova; a aprovação vale só para aquela reserva (decisão do usuário: outra reserva pede de novo) (16/11/2026) |
| ~~Pedido recusado ou sem resposta: a reserva continua e o email traz "Cancelar reserva" (opção A)~~ (substituída em 16/11/2026 pela lógica invertida) | Escolha do usuário (16/11/2026); a opção B (perguntar e esperar resposta) foi descartada por exigir um estado novo com prazo, e a C (reserva condicionada) ficou como ideia para depois |
| ~~Pedido vence 2 h antes do início, e precisa de 2 h de antecedência~~ (substituída em 16/11/2026) | Dá tempo ao aluno de decidir e ao horário de ser aproveitado se ele cancelar (16/11/2026) |
| Origem da reserva em coluna própria (`SITE`, `ADMIN`, `REGISTRO_POSTERIOR`) | Pedido do usuário (13/11): marcar e colorir o registro feito depois do uso; enum em vez de booleano por causa do passo 13 (16/11/2026) |
| Registro posterior: aluno pelo RA ou grupo sem RA (tipo EVENTO) | Não muda as regras de quem é aluno e quem é evento; conflito não revoga nada (o uso já aconteceu) (16/11/2026) |
| Login do admin com **senha + código por email** (dois fatores) | Ideia do usuário (16/11/2026), opção A recomendada: roubar só a senha ou só o email não basta; só o código deixaria a segurança inteira no email e o login dependente do envio |
| Código de login enviado **na hora, sem fila** | O login é interativo e não pode depender do `queue:work`; custa 1 a 3 s no primeiro passo |
| Código e desafio guardados com **HMAC**, sem IP | Com o limite de tentativas, bcrypt não acrescenta segurança e o HMAC permite buscar o desafio por índice; IP não tem finalidade (LGPD) |
| Calendário do admin em **rota própria** (`/api/admin/espacos/{id}/disponibilidade`) | A rota pública continua sem identificar ninguém; o modo admin só carrega aluno, curso e criador quando é o admin que pede (16/11/2026) |
| Admin vê o calendário **sem o limite do semestre** e de segunda a domingo | Histórico e eventos futuros; eventos podem cair no fim de semana (16/11/2026) |
| Criar evento em **página própria**, preenchida pelo calendário | Mais simples e acessível que uma janela por cima, e com URL (16/11/2026) |
| Lista e exportação do admin por **POST** no front | A busca por nome ou RA é dado pessoal e não pode ir na URL (fica em logs de servidor e no histórico); o GET continua para quem usa a API direto (16/11/2026) |
| API para parceiros em **`/api/v1`**, separada das rotas do front (passo 13) | Mesmo antes da produção e sem parceiro confirmado (o usuário pediu para registrar o porquê, 17/11/2026): as rotas do front mudam a cada passo (o 11.9 tirou a quantidade da edição do equipamento; o 12 vai pôr aulas no calendário) e um parceiro quebraria a cada mudança. O número custa só um prefixo; uma mudança que quebre vira `/api/v2`, com a v1 no ar por um prazo avisado. Descartado: `/api/parceiros` sem número (a primeira mudança que quebrasse pediria outro nome) |
| Situação da confirmação calculada na **API** (`situacao_confirmacao`) | Uma regra só (a mesma do filtro), sem duplicar no front (16/11/2026) |
| Filtros do admin na URL, **menos a busca** | Recarregar e voltar mantêm os filtros; a busca (nome/RA) fica só na memória da tela (16/11/2026) |
| Consulta com RA ou curso **não filtra** as reservas | Mostra o espaço inteiro e apenas aponta o limite e a disponibilidade do curso |
| Mensagem do limite semanal sem o nome do curso | Quem digitasse um RA alheio descobria o curso dele quando o limite estourava (achado no 11.2, aprovado pelo usuário em 10/11/2026) |
| Orçamento do front: aviso 350 kB, erro 500 kB | A base do Angular (núcleo, router, rxjs) já ocupa ~275 kB do pacote inicial; o limite de 300 kB seria ultrapassado sem código nosso (aprovado em 10/11/2026) |
| Horário livre escolhido **por dia** no calendário | Ideia do usuário: 30 blocos por dia em 5 colunas deixavam a tela enorme; agora o aluno escolhe o dia e vê só ele |
| Equipamento só no próprio espaço por padrão; compartilhamento escolhido pelo admin; solicitação pelo aluno | Decisão do usuário (13/11): microscópio não aparece na quadra nem bola no laboratório; o admin controla o que é compartilhado e o aluno pode pedir o resto |
| Calendário navegável até o fim do semestre, reserva limitada a 10 dias úteis | Decisão do usuário (13/11): ver eventos e aulas com antecedência, sem permitir que um aluno reserve muito adiantado (injusto com os outros) |
| Registro de uso posterior com marcação própria | Decisão do usuário (13/11): uso de última hora ou já acontecido fica registrado e diferenciado do que passou pelo site |
| Lista de horários com componente próprio (`CampoHorario`), sem o Angular CDK | O `<datalist>` filtrava pelo texto do campo (defeito relatado pelo usuário); o componente próprio mostra todos os horários, é acessível e quase não aumenta o pacote |
| Laboratórios e salas de demonstração com horário livre | Pedido do usuário: 07:00–22:00, até 3h (duração de uma aula); a quadra continua com os horários fixos |
| RA do calendário por **POST** (`/disponibilidade/curso`), fora do `GET` | Ideia do usuário (10/11/2026): o aluno digita o RA e o sistema descobre o curso. Por POST para o RA não ir para URL e logs; 20 por minuto (pedido do usuário; o limite é por IP e o Wi-Fi da faculdade compartilha IP); só em espaços com limite por curso, porque o curso só importa na quadra (atléticas); sem o nome do curso na resposta |
| Eventos podem revogar reservas de alunos | Eventos da faculdade têm prioridade; justificativa obrigatória e aluno avisado |
| Reservas não confirmadas são revogadas automaticamente | Libera horários de quem não vai usar |
| ~~Pedido de confirmação **2 dias úteis** antes~~ (substituída em 13/11: confirmação na criação + reconfirmação **3 dias úteis** antes) | Em dias corridos, reserva de segunda pediria confirmação no sábado, quando muita gente não abre o email da faculdade |
| Prazo até as **23:59 do dia anterior** (dia corrido) | Dá o maior prazo possível (ex.: o fim de semana inteiro para reservas de segunda); a revogação às 00:05 ainda libera o horário no próprio dia |
| Email do admin em minúsculas e único sem diferenciar maiúsculas | O usuário pediu diferenciar "para mais segurança"; foi explicado (09/11/2026) que provedores entregam `Ana@` e `ana@` na mesma caixa, que diferenciar permitiria admins duplicados ou cópias de um admin e logins falhando por digitação, e que o comportamento mudaria conforme o banco. Decisão: minúsculas |
| Convite e redefinição pelo Password Broker do Laravel | Mecanismo oficial: token com hash, validade e limite de pedidos |
| Tabelas de token separadas para convite e redefinição | Com a mesma tabela, um link de redefinição (60 min) valeria 2 dias na rota do convite |
| Link só com o token; email digitado no front | Nada pessoal na URL (LGPD) |
| Convite válido por **2 dias**; redefinição por **60 minutos** | Decisão do usuário; o convite pode ser reenviado |
| Trocar a senha logado **e** "esqueci a senha" | Decisão do usuário: as duas opções |
| Senha com 8+ caracteres, letras e números, sem checagem de vazamento | A checagem `uncompromised` consulta um serviço externo a cada cadastro |
| Email "Sua senha foi alterada" | Se não foi o próprio admin, ele fica sabendo na hora |
| Sem perfis de permissão (por enquanto) | Todo admin pode tudo; perfis ficam como opcional |
| **Admin principal** que não pode ser desativado | Pedido do usuário: conta garantida para testes e manutenção. A única diferença de poder é não poder ser desativado |
| Outros admins não editam o principal | Se pudessem trocar o email dele, bastaria usar "esqueci a senha" para tomar a conta |
| Principal só criado/transferido por comando no servidor | Quem tem acesso ao servidor já responde pelo sistema; a API não promove ninguém |
| Histórico em **uma rota só, com filtros** | O histórico de qualquer registro sai com `?tipo=&registro_id=`, sem uma rota por entidade |
| **Morph map** (nomes curtos em `auditavel_type`) | O histórico não quebra se uma classe for renomeada ou movida |
| Histórico guardado **sem prazo** (por enquanto) | Rastreabilidade foi o motivo da auditoria; retenção entra na preparação de produção se o volume pedir |
| Sem exportação do histórico (por enquanto) | Opcional; dá para reaproveitar o exportador de reservas |
| Fila no banco (`QUEUE_CONNECTION=database`) para os emails | A requisição não espera o envio, e uma falha no servidor de email não derruba a reserva |
| Jobs que falharam apagados após **7 dias** | Guardam dados pessoais (LGPD); 7 dias dão tempo de investigar |
| Links de email usados/vencidos apagados após **30 dias** | Não guardar o que não serve mais; 30 dias ajudam a responder "cliquei e não funcionou" |
| Importação automática de feriados **sem os facultativos** por padrão | Cada instituição decide se fecha nesses dias (`RESERVA_FERIADOS_FACULTATIVOS`) |
| Sem aviso por email quando uma tarefa falha (por enquanto) | Falhas ficam no log; o aviso entra na preparação de produção, junto com o monitoramento |
| Prazo **não** é "24h / 1 dia útil depois do email" (alternativa avaliada em 09/11/2026) | Liberaria o horário um dia antes, mas o ganho é pequeno: o horário já é liberado às 00:05 do próprio dia e pode ser reservado até 20 min antes do fim (horários à noite). A alternativa criaria prazos de poucas horas para reservas feitas perto da data, exigiria tratar fim de semana e revogação de hora em hora. Rever só se, em uso, horários liberados no próprio dia ficarem vazios com a quadra lotada |
| ~~Reserva dentro da janela pede a confirmação já na criação~~ (substituída em 13/11: **toda** reserva pede na criação) | Um email só; também confirma que quem reservou é o dono do email (reserva com RA de outra pessoa cai sozinha) |
| ~~Reserva para hoje nasce confirmada~~ (substituída em 13/11: tem **1 hora** para confirmar) | O prazo (dia anterior) já teria passado; um prazo de poucas horas exigiria revogação de hora em hora para um caso raro |
| **Toda** reserva de aluno confirmada pelo email, com **aceite dos termos** (versão gravada) | Ideia do usuário (10/11): prova que o dono do email reservou (RA de outra pessoa cai sozinho) e registra que o aluno foi informado; protege a instituição e o desenvolvedor |
| Prazo de **24 h** (mesmo dia: **1 h**), nunca depois do início | Decisão do usuário (11/11); prazo curto porque a reserva segura o horário enquanto espera |
| Reconfirmação **3 dias úteis** antes, revogação às **00:00 da véspera**, dispensada para quem confirmou há menos de 2 dias úteis | Ideia do usuário (revogar 1 dia antes) + ajuste do Claude (pedir 3 dias úteis antes, para o aluno ter ~1 dia e meio); quem acabou de confirmar não precisa repetir |
| Revogação por prazo **a cada 15 minutos** | O prazo de 1 h do mesmo dia exige verificar várias vezes ao dia; duas consultas por índice, sem peso |
| Login do admin em `/admin/entrar`, links de senha/convite fora do painel, token fora da barra e email digitado, sessão só na aba, regras da senha na tela, mensagens genéricas (11.4) | Proposta aprovada pelo usuário em 13/11: quem abre os links ainda não entrou; LGPD (email e token fora da URL; nada que revele quem é admin); segurança (sessão some ao fechar a aba) |
| Termos servidos pela API (`/api/termos`), texto em rascunho | Uma fonte só para o texto e a versão aceita; o texto precisa ser validado pela instituição |
| Email de confirmação também tem **Cancelar** | Quem não vai mais libera o horário na hora, sem esperar a revogação |
| ~~Email da criação "Reserva registrada"~~ (removido em 13/11: a criação manda "Confirme sua reserva") | "Confirmada" confundia com a confirmação do item 9 |
| Datas bloqueadas em tabela própria | Uma fonte só para feriados (importados pela Yasumi), recesso e manutenção (cadastrados pelo admin) |
| Bloqueios valem só para alunos | Eventos da faculdade podem acontecer em feriados e recessos |
| Bloqueio de parte do dia | Manutenções pontuais sem fechar o dia inteiro; reaproveita a verificação por sobreposição |
| Bloqueio só edita o motivo | Mudar o período levantaria a dúvida de devolver ou não reservas já revogadas; excluir e criar de novo é explícito |
| Sexta-feira Santa importada por padrão | É feriado nacional por lei, embora a Yasumi a classifique como "observância" |
| Campo `data_reserva` (e não `data`) | Com um campo `data`, o Laravel não envolvia a resposta em `{"data": ...}` |
| Update só por PATCH; exclusão = desativação | Atualização parcial e preservação do histórico |
| Alertas aos admins ativos e ao `ALERTAS_EMAIL` | Decisão do usuário: os admins cuidam da gerência; ele também quer saber |
| Alertas sem a mensagem do erro, sem fila e no máximo 1 por hora | LGPD (a mensagem pode ter dados pessoais), o alerta não pode depender da fila, e não lotar a caixa de entrada |
| Traduções geradas pelo laravel-lang e o pacote removido | Mensagens em português sem dependência extra (o pacote exige a extensão `bcmath`) |
| Admin inicial do seeder lido do `.env` | Decisão do usuário: o admin de teste vira o admin dele; a senha sai do código público. Em produção, preferir `admin:criar-principal` |
| Backup e monitor externo na hora do deploy | Dependem da plataforma, ainda não escolhida |
| Front na pasta `frontend/` deste repositório | O usuário preferia um repositório separado; ficou aqui para guias, CI e mudanças de API e front no mesmo commit. A separação que importa é a de execução: o front só consome a API e tem deploy próprio. Dá para mover depois com `git subtree split`, sem perder o histórico |
| Token do admin no `sessionStorage` com `Bearer` | API e front ficarão em domínios separados; o cookie do Sanctum (mais seguro contra XSS) exige o mesmo domínio e fica como evolução. O `localStorage` deixaria o token exposto por mais tempo |
| Tailwind + componentes próprios (+ Angular CDK quando houver diálogo) | Angular Material e PrimeNG pesam mais e impõem outro visual |
| Signals e services, sem NgRx | O sistema não precisa de um gerenciador de estado |
| Montserrat servida pelo próprio site (`@fontsource`, só o alfabeto latino, pesos 400/600/800 e 800 itálico) | O Google Fonts recebe o IP de cada visitante (dado pessoal) e deixa o site mais lento |
| Sem ferramentas de rastreamento e com página de privacidade | Transparência da LGPD: a página lista todos os dados coletados, para quê e por quanto tempo |
| Ícones (Lucide) e Angular CDK instalados só quando uma tela usar | Nenhuma dependência sem uso no pacote |
| Orçamento do pacote inicial: aviso em 300 kB, erro em 450 kB | O padrão do Angular (500 kB/1 MB) deixaria o site crescer sem ninguém notar |

---

## 8. Testes

```bash
php artisan test
```

**Hoje (17/11/2026, depois do passo 13):** 394 testes da API, 178 testes de unidade do front (Vitest) e 64 testes no navegador (Playwright, computador e celular).

### CI (GitHub Actions)

Arquivo: [`.github/workflows/ci.yml`](.github/workflows/ci.yml). Roda a cada **push na `main`** e em **pull requests para a `main`** (repositório público: sem limite de minutos).

| Job | O que faz |
|---|---|
| `Pint e composer audit` | `vendor/bin/pint --test` (acusa código fora do padrão, sem alterar) e `composer audit` (dependência com vulnerabilidade conhecida) |
| `Front-end` | Node 24, `npm ci`, Prettier (`--check` em `src` e `e2e`), testes (Vitest), build de produção (com o orçamento de tamanho), `npm audit --audit-level=high` e os testes no navegador (Playwright com o Chrome do runner) |
| `Testes (sqlite)` · `Testes (mysql)` · `Testes (pgsql)` | `php artisan test` em cada banco, em paralelo. MySQL 8.0 e Postgres 16 sobem como serviços temporários do GitHub |

- Primeira execução (09/11/2026): os 226 testes passaram nos três bancos, sem nenhum ajuste de compatibilidade.
- PHP 8.3, com cache do Composer. O `phpunit.xml` continua com SQLite por padrão; no CI, as variáveis de ambiente de cada banco têm prioridade.
- **Como ler um erro:** na aba *Actions* do repositório, abrir a execução com ❌, entrar no job que falhou e expandir o passo vermelho. Um job falhar não cancela os outros (`fail-fast: false`), então dá para ver se o problema é de um banco só.
- Um push novo na mesma branch cancela a execução anterior que ainda estiver rodando.
- **Hoje se trabalha direto na `main`:** o CI avisa (❌), mas não impede o push. Para **bloquear** código que quebra os testes, usar uma branch por funcionalidade + pull request e ativar no GitHub *Settings → Branches → Branch protection rules* → "Require status checks to pass" (marcar os 5 jobs). Recomendado quando mais pessoas mexerem no projeto.

Os testes ficam em `tests/Feature`, um arquivo por assunto, e usam as factories de `database/factories`. A data dos testes é fixada em **segunda-feira, 09/11/2026, 10:00** (`tests/TestCase.php`), para não dependerem do dia em que rodam. O limite de requisições por minuto fica desligado nos testes, menos no `LimitesPublicosTest`, que o religa.

| Arquivo | O que cobre |
|---|---|
| `AlunoTest` | Cadastro, RA duplicado, curso inativo, listagem só para admin |
| `ReenvioConfirmacaoTest` | Reenvio com links novos e antigos invalidados sem mudar o prazo, espera de 5 min e limite de 3, resposta genérica para dados errados ou reserva de outro aluno, reserva já confirmada |
| `ReservaPeloAdminTest` | Login, reserva para o aluno com origem ADMIN e email de confirmação, mesmas regras do site (fim de semana, RA, conflito, limite do curso), equipamento de outro espaço aprovado na hora mesmo perto do início (rota do admin sem prazo), reserva pelo site continua com origem SITE |
| `SolicitacaoTest` | Lista de itens que podem ser pedidos, pedido aguardando segura a quantidade para todos, aprovação não vira compartilhamento, item que não aceita ou do próprio espaço, prazo normal, curto e presencial, aprovar (email sem link), recusar (libera e pede para manter em 12 h, com os links), manter pelo link e revogação de quem não manteve, prazo para manter limitado a 1 h antes, vencer no prazo gravado, filtro e devolução só dos que valem, exportação, marcar `aceita_solicitacao` |
| `RegistroPosteriorTest` | Login, uso passado do aluno sem confirmação e com email, grupo sem RA e sem email, RA ou descrição, 30 dias e hoje só o que começou, fim de semana e fora da janela aceitos, bloqueio recusado, conflito 409 sem revogar, limite semanal só avisa, estoque, fora da revogação automática e do filtro de confirmação, filtro e exportação por origem, calendário público só "Reservado" |
| `LoginCodigoTest` | Senha certa manda o código na hora (sem fila) e não entrega token; senha errada não manda; código certo entra uma vez só; vencido; 5 tentativas; login novo invalida o anterior; admin desativado no meio; reenvio com espera de 60 s e limite de 3; desafio inválido genérico; falha no envio (503, nada guardado); formato do código; nada no log; configuração desligada; limpeza diária |
| `AdminTest` | Login e /me com resource, email sem maiúsculas, inativo sem login, convite (email, 2 dias, link só com token), email único, filtros, edição, desativação derrubando sessões e reativação, não desativar a si mesmo, sempre um ativo, reenvio de convite, senha nunca no histórico |
| `HorarioLivreTest` | Configuração do espaço LIVRE e validações, troca de modo com reservas, reserva com início/fim e validações do intervalo, **arredondamento para 15 min** (inclusive em conflitos e na duração máxima), espaço fixo recusa início/fim, conflito por sobreposição, hoje até 20 min antes do fim, bloqueio parcial, evento fora da janela e arredondado, equipamentos pelo pico, redução de estoque pelo pico, **calendário com janela e ocupados** (reservas e bloqueios, sem RA), **`fim_minimo` de hoje e dia encerrado**, consulta de equipamentos por intervalo, link de cancelamento no início escolhido |
| `VersaoDisponibilidadeTest` | Versão igual sem mudanças; muda depois de uma reserva e de um bloqueio; muda a cada faixa de 5 minutos; resposta só com a versão |
| `EmailsTest` | Os 13 emails (com o aceite e a renovação do acesso à API) montam com o assunto e o conteúdo esperados, sem link para a API ou `localhost`, e só o alerta e o código de login saem sem fila |
| `ConsultasDasListagensTest` | Histórico, espaços, equipamentos, cursos, datas bloqueadas, alunos, sistemas parceiros e admins fazem o mesmo número de consultas com 2 e com 12 itens (sem N+1) |
| `LimitesPublicosTest` | Mesmo RA limitado sem afetar outro aluno do mesmo IP, limite por IP valendo para todos os RAs do IP, links do email limitados por token, consultas do calendário com o limite das consultas, RA fora da chave do limite |
| `RotasProtegidasTest` | Toda rota do admin (e `/me`, `/logout`) responde 401 sem token; todas têm `auth:sanctum` e `admin.ativo`; token de sistema parceiro recebe 403 em **todas** as rotas do admin; toda rota `/api/v1` tem `auth:sanctum`, `parceiro`, `throttle:parceiros` e uma `permissao:*`, e responde 401 sem token, 403 com token de admin e 403 sem a permissão **antes** de buscar o registro (com teste de mutação); admin desativado com token ainda válido é recusado e perde os tokens; admin ativo continua entrando |
| `ClienteApiTest` | Cadastro do sistema parceiro com link ao responsável e nenhum token na resposta, nome único e permissões válidas, página de aceite com os termos e o aceite obrigatório, token gerado uma vez com permissões e validade de 12 meses, link usado/vencido/inventado com a mesma resposta, token de parceiro barrado no admin, desativar cortando o acesso e reativar mandando link, link novo com e sem revogar o token atual, trocar permissões valendo para o token, trocar o responsável invalidando o link, renovação 30 dias antes uma vez só, situação vencida, histórico sem o hash e com o sistema parceiro, busca, sessão do admin ainda em 8 h |
| `ApiParceirosTest` | `/api/v1`: 401 sem token, 403 com token de admin, 403 sem a permissão com a permissão que falta, sistema desativado perde os tokens, token vencido recusado, espaços e calendário sem quem reservou, reserva pelo RA com origem `API`, histórico com o sistema e email de confirmação ao aluno (mesmas regras do site), admin vê o sistema e filtra pela origem, só as próprias reservas e sem dados pessoais, listagem sem N+1, cancelar só as próprias com email ao aluno, limite por minuto de cada sistema |
| `DocumentacaoApiTest` | O `docs/api-v1.openapi.json` é igual ao gerado do código (no SQLite; `ATUALIZAR_OPENAPI=1` regrava), cobre só a v1, com token Bearer e sem dados pessoais do aluno no formato da reserva |
| `AuditoriaConsultaTest` | Histórico de um registro em ordem, nome curto no banco, filtros por admin e sem admin, por ação e período, senha oculta e aluno anonimizado, validações, paginação |
| `AdminPrincipalTest` | Principal não desativado nem editado por outros, edita a si mesmo, desativa os outros, flag não muda pela API, seeder, comandos de criação (senha fraca, duplicado) e de transferência |
| `SenhaTest` | Esqueci a senha (link só com token, 60 min), resposta igual para inexistente/inativo, redefinição derrubando sessões com email de aviso, uso único e vencimento, token/email errados, regras da senha, aceite de convite em até 2 dias, link de redefinição não serve no convite, troca logado mantendo a sessão atual |
| `AgendadorTest` | Horários e `withoutOverlapping` de todas as tarefas, feriados do ano seguinte sem facultativos, retenção de 7 dias dos jobs falhos, limpeza só dos tokens antigos |
| `ConfirmacaoTest` | (refeito no 11.3a) Toda reserva pede confirmação na criação com prazo de 24 h (links, termos no email), aviso de reconfirmação futura, 1 h no mesmo dia sem passar do início, prazo até o fim quando já começou, aceite dos termos obrigatório (422) e gravado, vencimento no prazo, revogação por prazo, reconfirmação 3 dias úteis antes (prazo e email), dispensa para quem confirmou há pouco, o que a reconfirmação ignora, reconfirmação sem novo aceite, revogação na véspera, fluxo completo, filtros novos, agendamento, termos com cache. Antes: cancelar pelo email de confirmação, revogação só das não confirmadas do dia, fluxo completo pelos comandos, filtro do admin, agendamento |
| `CancelamentoTest` | Email de criação com link do front, só o hash no banco e validade até o início, resumo sem dados pessoais, cancelamento liberando horário e equipamentos com email, uso único, token inválido e vencido, reserva revogada, evento sem email, criação sem expor o aluno |
| `MinhasReservasTest` | Futuras e todas, ordem, espaço/equipamentos/justificativa, sem dados pessoais nem auditoria, email sem maiúsculas, resposta igual para dados errados, inativo x anonimizado, só POST, validações, paginação |
| `DevolucaoTest` | Itens pendentes, devolução completa, avaria/falta com email, estoque e aluno não afetados, correção com histórico, coerência de quantidades, item fora da reserva, validações, reserva ativa e iniciada, evento sem email, filtro de pendências, coluna na exportação |
| `AlunoAdminTest` | Filtros, detalhe, edição, desativação com revogação e email, reativação, aluno inativo sem reservar, mudança de curso no limite semanal, anonimização (confirmação, dados e histórico limpos, sem email, bloqueio de alterações, RA liberado) |
| `EspacoTest` | CRUD, PATCH, filtros, bloqueio de desativação com reservas futuras, paginação padronizada |
| `EquipamentoTest` | Nome único por espaço, filtros, lista pública com só os compartilhados com o espaço, admin escolhendo e tirando espaços (com histórico), validações do compartilhamento, 409 ao tirar espaço com reserva futura, redução de estoque por movimentação |
| `EstoqueTest` | Entrada inicial no cadastro, efeito de cada tipo (entrada, manutenção, retorno, baixa da manutenção, ajuste negativo), quantidades impossíveis e validações, não devolvido exige reserva com o item, limite pelo uso futuro sem contar reservas já terminadas, manutenção só para o admin, lista em ordem |
| `ResponsavelEventoTest` | Nome obrigatório em todo evento e no registro de grupo, contato opcional, só o admin vê, correção e remoção a pedido apagando do histórico, só eventos têm responsável, pendência de devolução para o contato quando é email |
| `ReservaTest` | Reserva, conflito de horário, RA/espaço inválidos |
| `ReservaEquipamentosTest` | Validações dos itens, disponibilidade entre espaços, revogação liberando estoque |
| `EventoERevogacaoTest` | Eventos com e sem conflito, revogação, email |
| `RegrasDeDataTest` | Fim de semana, 10 dias úteis, data passada, horário de hoje |
| `LimiteSemanalTest` | Limite por curso na quadra, semana seguinte, revogadas e eventos não contam |
| `DatasBloqueadasTest` | Dia inteiro, por espaço, janela de horas, eventos ignorando, revogação automática com email, validações, edição e filtros |
| `ImportarFeriadosTest` | Importação sem duplicar, facultativos, revogação de reservas em feriado |
| `MaiusculasEMinusculasTest` | Nomes únicos e buscas sem diferenciar maiúsculas |
| `AuditoriaTest` | Colunas e histórico na criação, alteração, desativação e exclusão; ações públicas sem admin; revogação; alteração sem mudança; auditoria só nas rotas do admin; histórico após remover o admin |
| `ExportacaoReservasTest` | Login, colunas obrigatórias, opcionais e todas, responsável em eventos, período e atalhos, filtros, mais de um lote, proteção contra fórmulas, validações |
| `ListagemReservasTest` | Login obrigatório, padrão de hoje em diante, ordem, cada filtro e combinações, dados do aluno/espaço/equipamentos/revogação, totais por status, paginação, consultas sem N+1, validações |
| `DisponibilidadeTest` | Situações dos horários, reserva sem identificar quem reservou (sem nome, curso, RA e email), bloqueios, motivos do calendário, equipamentos por horário, dados do curso por `curso_id`, consulta com RA só por POST (sem id/nome do curso, só em espaço com limite, RA inexistente ou inativo com a mesma mensagem, espaço inativo), `GET` ignorando o RA, validações |
| `TraducaoTest` | Mensagens de validação em português com o nome do campo |
| `SegurancaHttpTest` | CORS só para o front, cabeçalhos de segurança, HSTS só por HTTPS |
| `AlertaTest` | Destinatários (admins ativos e `ALERTAS_EMAIL`), sem dados pessoais, limite de 1 por hora, toda tarefa agendada com alerta |
| `AdminSeederTest` | Admin principal pelo `.env`, sem duplicar; sem senha não cria |
| `VerificarProducaoTest` | Itens com ❌ e ✅ e o código de saída |
| `LogTest` | Erro de regra de negócio (`RegraNegocioException`) fora do log; erro inesperado continua no log |
| `DisponibilidadeEquipamentoRepositoryTest` | Disponível por data/horário, soma entre espaços, revogadas, sobreposição, maior uso futuro |

**Testes no navegador** (`frontend/e2e`, Playwright): abrem o build de produção no Chrome já instalado (`channel: 'chrome'`, sem baixar navegador), na porta 4300, em tamanho de computador e de celular, e clicam nos fluxos como uma pessoa. A API é simulada no próprio navegador (`e2e/api-falsa.ts`, `page.route`), com datas calculadas a partir do dia real pelas mesmas funções do app.

| Arquivo | O que cobre |
|---|---|
| `e2e/reserva-no-laboratorio.spec.ts` | Laboratório: escolher o dia, ver o ocupado, digitar um horário em conflito (botão desligado), digitar 15:37–17:52 e ver 15:30–17:45, continuar, ver os equipamentos e reservar com o intervalo arredondado |
| `e2e/reserva-na-quadra.spec.ts` | Início → quadra → próxima semana → horário → RA + equipamento → reserva feita, com o corpo enviado e o RA fora das URLs; calendário aberto direto pelo endereço e a semana anterior reaproveitada sem nova consulta; cadastro saindo da reserva e voltando com o RA preenchido |

- **Por que não fixar a data no navegador:** com o relógio falso do Playwright (`page.clock`), o Angular deixava de atualizar a tela depois das respostas quando a página era aberta direto pelo endereço; os testes acusavam um defeito que o app não tem. Os dados simulados seguem o dia real.
- **Por que o build de produção:** no servidor de desenvolvimento cada página é compilada na primeira visita, o que deixava os testes lentos e instáveis; o de produção é o que o aluno usa.

**Testes do front** (`frontend/src`, ao lado de cada arquivo, `*.spec.ts`):

| Arquivo | O que cobre |
|---|---|
| `app.spec.ts` | Componente raiz com a área das rotas |
| `app.routes.spec.ts` | Início no layout público, painel no layout do admin (sem o público), página 404, título da página, link "Pular para o conteúdo" |
| `nucleo/sessao.service.spec.ts` | Token só no `sessionStorage` (nunca no `localStorage`), recuperação ao recarregar, sessão vencida ou inválida descartada, vencimento durante o uso, encerrar |
| `publico/datas.spec.ts` | Datas sem erro de fuso, segunda-feira da semana, dias úteis, validação de data e hora da URL, formato em português |
| `publico/api-publica.service.spec.ts` | Espaços buscados uma vez, semana guardada, RA só no corpo do POST, calendário recarregado depois da reserva, equipamentos do intervalo, cadastro |
| `publico/inicio/inicio.spec.ts` | Cartões com horário, limite e link; filtro por tipo; erro com "tentar de novo" |
| `publico/intervalo.spec.ts` | Arredondamento igual ao da API, intervalo aceito com os horários finais, cada motivo de recusa, horários livres do dia, duração legível |
| `publico/atualizacao-disponibilidade.service.spec.ts` | Consulta da versão ao abrir e a cada 15 s, aviso só quando muda, para ao sair da tela, sem acumular consultas |
| `publico/calendario/calendario.spec.ts` | Recarga em segundo plano quando a versão muda. Horário livre: botões dos dias com as horas livres, janela, ocupados e livres do dia, arredondamento ao digitar com o erro de conflito, "Continuar" para a reserva, troca de dia limpando o horário, início pela lista de livres e duração em botões (o fim é apagado quando não cabe no novo início), toque na barra do dia, hora digitada de outros jeitos e início ocupado. Semana de segunda a sexta com links de reserva, "Reservado" sem nome e curso, dias fechados, situação desconhecida, RA só em espaço com limite, consulta do limite por POST e RA só na memória, RA inexistente, formato do RA, sem voltar para semanas passadas |
| `admin/acesso/acesso.spec.ts`, `regras-senha.spec.ts` | Guardas (volta com o caminho, passa com login, quem já entrou não vê o login), service (sessão no login, `/me` uma vez, sair apaga mesmo com erro), entrar (volta só para caminho interno, erro genérico, aviso de sessão), esqueci (resposta genérica), convite (token fora do endereço, regras, corpo enviado), sem token, minha conta (troca de senha e sair de todos), layout (sessão acabou x sair) |
| `admin/reservas/reservas.spec.ts` | Filtros da URL (valores inválidos ignorados, período antes das datas), dias do período, linha do tempo (prazo, versão dos termos, prazo da reconfirmação às 23:59, revogação), lista por POST com os filtros e os selos sem RA/email, busca só no corpo, totais que filtram, detalhe com RA e email, revogação com justificativa, devolução com erro do item, exportação com as colunas e o nome do arquivo, limite de 366 dias |
| `admin/calendario/calendario.spec.ts` | Itens do dia (fixo: livre para evento no fim de semana, não no passado; livre: ocupados e intervalos em ordem), link do evento, calendário pela rota do admin de segunda a domingo com quem reservou e espaço desativado, novo evento preenchido, conflito com justificativa e reenvio |
| `e2e/pedido-equipamento.spec.ts` | No navegador: aluno abre "Pedir equipamento de outro espaço", vê o prazo da resposta, pede, vê no resumo e na tela de sucesso (o corpo leva `solicitacoes`); e mantém a reserva pelo link do email |
| `admin/cadastros/cadastros.spec.ts` | Lista de espaços com filtro e descrição do horário, criar espaço com a duração sugerida, desativar com confirmação e a recusa da API, editar equipamento (aceita pedido e compartilhados), cursos (criar, renomear, desativar), datas bloqueadas (lista de hoje em diante com feriado, excluir; criar parte do dia com o total revogado), alunos (busca por POST sem RA/email na lista; detalhe, editar e anonimizar com confirmação forte), admins (lista por POST com principal e convite pendente; convidar, reenviar convite e desativar) |
| `admin/cadastros/estoque/estoque.spec.ts` | Utilizável e sinal de cada movimentação, resumo e lista do estoque, baixa "estava em manutenção" com o corpo enviado e a lista recarregada, ajuste vindo da devolução com a reserva já ligada e o erro da API, corrigir e remover o responsável do evento |
| `e2e/estoque.spec.ts` | No navegador: equipamento sem campo de quantidade na edição, manda para manutenção com o estado, vê a movimentação e os números (o evento com responsável está em `calendario-admin.spec.ts`) |
| `admin/historico/historico.spec.ts` | Valores legíveis (Sim/Não, datas, códigos, listas, `[oculto]`), mudanças na criação e na alteração, links só para registros com página, filtros da URL com valores inválidos ignorados, página com os filtros enviados à API e a tabela antes → depois, "Sistema ou aluno" e navegação dos filtros, histórico do registro que só consulta ao abrir (10 mais recentes) e o erro da API |
| `e2e/historico-admin.spec.ts` | No navegador: detalhe da reserva → "Mostrar o histórico" → "Ver tudo no histórico", mudanças campo a campo, filtro "Sistema ou aluno", limpar filtros e voltar à reserva pelo link; nada pessoal nas URLs |
| `e2e/cadastros-admin.spec.ts` | No navegador: Cadastros → Espaços → criar espaço; Equipamentos → marcar "Aceita pedido de outros espaços"; criar curso; criar bloqueio de parte do dia; buscar aluno por nome (fora da URL) e corrigir o email; convidar admin |
| `e2e/registro-posterior.spec.ts` | No navegador: "Registrar uso" pela lista, formulário, detalhe com o selo e "Registrado depois do uso por", RA só no corpo |
| `e2e/calendario-admin.spec.ts` | No navegador: calendário com quem reservou e o selo, "Reservar aqui", evento criado pela Nova reserva, e reserva para um aluno com equipamento de outro espaço (selo "Pelo admin", RA fora da URL) |
| `e2e/seguranca-do-front.spec.ts` | No navegador: o site carregado com a CSP do `frontend/deploy/nginx.conf` (lida do arquivo) não tem nada bloqueado, aplica a fonte e funciona no calendário, na reserva e no admin |
| `e2e/acessibilidade.spec.ts` | No navegador: axe (WCAG A e AA) em início, calendários da quadra e do laboratório, reservar, cadastro, minhas reservas, termos, confirmar, aceite dos termos da API e token gerado; e em entrar, painel, reservas, detalhe, nova reserva, calendário do admin, cadastros, espaços, equipamento, aluno, sistemas parceiros (lista, detalhe e cadastro), histórico e minha conta; reserva só pelo teclado com o foco visível |
| `e2e/sistemas-parceiros.spec.ts` | No navegador: Cadastros → Sistemas parceiros → cadastrar (corpo enviado, email fora das URLs, nenhum token na tela do admin); revogar o token de um sistema ativo; o responsável abre o link (token sai do endereço), lê os termos, só aceita com a caixa marcada e vê o token uma vez, sem nada guardado no navegador |
| `admin/cadastros/sistemas-parceiros/sistemas-parceiros.spec.ts` | Lista com a situação e o filtro na URL da API; cadastro com permissões e limite no corpo; erros por campo; no detalhe, link de renovação (`revogar_atual: false`), revogar (`true`) e desativar |
| `publico/aceite-parceiro/aceite-parceiro.spec.ts` | Token tirado do endereço, pedido e termos na tela, botão só com a caixa marcada, POST com `aceite_termos` e sem `Authorization`, token mostrado com a validade; link inválido; sem token |
| `e2e/reservas-admin.spec.ts` | No navegador: login, lista sem RA, filtro de confirmação na URL, busca fora da URL, detalhe com RA e email, revogação e volta à lista com os filtros |
| `e2e/acesso-admin.spec.ts` | No navegador: login com erro e certo voltando à página pedida, painel, sair e rota protegida; senha nova pelo link do email (token fora da URL, corpo enviado) |
| `publico/minhas-reservas/minhas-reservas.spec.ts` | POST com RA e email no corpo, situação de cada reserva (confirmação, revogação, devolução, aviso de cancelar pelo email), mensagem genérica, formato sem chamar a API, paginação repetindo o POST, limpar |
| `publico/link-reserva/link-reserva.spec.ts` | Confirmação só com o aceite (e token fora do endereço), reconfirmação sem termos, reserva que não pode mais ser confirmada, cancelamento, link inválido, sem token |
| `publico/situacao-reserva.spec.ts`, `publico/privacidade/privacidade.spec.ts` | Textos de confirmação, devolução e status; página de termos com o aviso de rascunho |
| `e2e/pos-reserva.spec.ts` | No navegador: minhas reservas (dados errados e certos, nada pessoal na URL), confirmar com os termos, cancelar, página de termos pelo rodapé |
| `publico/reservar/reservar.spec.ts` | Reserva com equipamentos (limite do disponível) e corpo enviado, RA vindo do calendário e esquecido depois, horário livre (intervalo da URL, "Alterar horário", equipamentos do intervalo, intervalo já ocupado), erro do RA com link de cadastro, 409 recarregando o horário, URL inválida |
| `publico/cadastro/cadastro.spec.ts` | Aviso de uso dos dados, `autocomplete` desligado, cadastro voltando para a reserva com o RA, confirmação sem volta, erros por campo, `voltar` só para o próprio site |
| `nucleo/api.interceptor.spec.ts` | `Accept` e `Bearer` só para a API (nunca para outros endereços), 401 com e sem sessão, 422 com os campos, 409 com os dados, 429, 5xx sem detalhes do servidor, falta de conexão |

---

## 9. Histórico do desenvolvimento

| Etapa | O que foi feito |
|---|---|
| Base | Migrations, models, enums de horário e status, cadastro de alunos, autenticação de admins (Sanctum), CRUD de equipamentos |
| Correções | Nomes de classes, ordem e colunas das migrations, chaves estrangeiras, bugs de update e de reserva duplicada |
| Infra | `RegraNegocioException`, fuso horário, limite de requisições, update só por PATCH |
| Item 1 — Espaços | CRUD completo, filtros por tipo, bloqueio de desativação |
| Item 2 — Exceções | Regras de negócio respondem 409/422 em vez de 500 |
| Cursos | Tabela de cursos, aluno com `curso_id`, seeder |
| Item 6 — Eventos e revogação | Revogação com justificativa e email; evento revoga conflito com justificativa |
| Item 3 — Equipamentos na reserva | Itens no payload, disponibilidade por data/horário, flag `compartilhavel`, filtros no admin |
| Item 4 — Regras de data | Fim de semana, 7 dias, horário de hoje, limite semanal por curso, `hora_inicio`/`hora_fim`, `config/reservas.php`, factories, `DemoSeeder` e testes permanentes |
| Repositório de disponibilidade | Consulta de disponibilidade centralizada em `DisponibilidadeEquipamentoRepository`, com índices; base para o campo `disponivel` do item 5 |
| Datas bloqueadas | Tabela, CRUD do admin, bloqueio de parte do dia, revogação automática com email, importação de feriados (Yasumi) |
| Dependências | Componentes do Symfony atualizados (7.4.23); `composer audit` sem alertas |
| Front-end: área pública de reserva (passo 11.2) | API: limite do curso por `POST` com o RA no corpo (só espaços com limite, 20/min), `GET` sem RA. Front: início com os espaços, calendário semanal, reserva (fixo e livre, com equipamentos), cadastro, cache por visita, RA só na memória |
| Front-end: base (passo 11.1) | Projeto Angular 22 em `frontend/` (sem zone.js, Tailwind v4, Vitest), paleta e Montserrat no próprio site, cliente da API com sessão no `sessionStorage` e erros padronizados, pt-BR, layouts público e admin, 404, rotas sob demanda, orçamento de tamanho, job do front no CI |
| Produção (passo 10) | Mensagens em pt_BR, CORS restrito, cabeçalhos de segurança, HTTPS, proxies, alertas por email, logs diários (14 dias), admin inicial pelo `.env`, `sistema:verificar-producao`, `DEPLOY.md`, `.env.production.example` e `HOSPEDAGEM.md` |
| CI (passo 9) | GitHub Actions: testes em SQLite, MySQL 8.0 e Postgres 16, Pint e `composer audit` |
| Cadastros: admins (passo 11.6d, 17/11/2026) | Lista com busca por POST, convidar, reenviar convite, editar, desativar/reativar. Com isso o 11.6 está completo |
| Estoque e responsável do evento (passo 11.9, 17/11/2026) | Movimentações com o estado do equipamento (entrada, manutenção, retorno, baixa, não houve devolução, ajuste), quantidade em manutenção fora do disponível, atalho a partir da devolução; responsável obrigatório em todo evento, só para admins, corrigível e removível a pedido |
| Acabamento (passo 11.8, 17/11/2026) | Limites das rotas públicas em duas camadas (IP + RA/link), orçamento do front 330/380 kB e contagem de consultas, axe e teclado nos testes do navegador, guia de deploy do front com Nginx e CSP (build sem CSS/script embutido), revisão de textos; também: admin desativado recusado a cada requisição, teste que varre as rotas do admin, fila pelo agendador, cabeçalho dos emails para o site |
| Histórico de auditoria (passo 11.7, 17/11/2026) | Página `/admin/historico` com filtros na URL e mudanças campo a campo; "Histórico deste registro" nos detalhes de reserva, aluno, espaço, equipamento e admin; sem rota nova na API |
| Cadastros: alunos (passo 11.6c, 17/11/2026) | API: busca de alunos e admins por POST e filtro `aluno_id` nas reservas. Front: lista de alunos com busca fora da URL, detalhe com edição, desativação, últimas reservas e anonimização com confirmação forte |
| Cadastros: cursos e datas bloqueadas (passo 11.6b, 17/11/2026) | Cursos numa página só; datas bloqueadas com lista, criar, editar motivo e excluir |
| Cadastros: espaços e equipamentos (passo 11.6a, 17/11/2026) | Página Cadastros no menu; lista e formulário de espaços e de equipamentos (compartilhamento e aceita pedido), desativar/reativar com confirmação |
| Pedidos com um email só e reenvio da confirmação (17/11/2026) | Marca de obrigatório, aprovar/recusar todos, um email por reserva quando todos os pedidos têm resposta; reenvio do email de confirmação por "Minhas reservas" invalidando os links anteriores |
| Reserva pelo admin para um aluno (passo 11.5e, 17/11/2026) | API: `POST /admin/reservas/aluno` (regras do site, confirmação, origem ADMIN, pedidos aprovados) e itens de outros espaços sem prazo. Front: página Nova reserva (aluno ou evento), "Reservar aqui" no calendário, selo "Pelo admin" |
| Ajuste do pedido de equipamento (16/11/2026) | Prazo de resposta gravado (24 h / curto 3 h / mínimo 2 h / presencial) e lógica invertida (reserva cancelada se o aluno não mantiver em 12 h); página "Manter a reserva" |
| Pedido de equipamento (passo 11.5d, 16/11/2026) | API: `aceita_solicitacao`, pedido no item da reserva, rota de itens solicitáveis, aprovar/recusar, vencimento 2 h antes, emails com o link de cancelar, filtro e exportação. Front: seção de pedido na reserva, situação em minhas reservas, resposta no detalhe do admin, selo, filtro e contador no painel |
| Registro de uso posterior (passo 11.5c, 16/11/2026) | API: `reservas.origem`, `POST /admin/reservas/registro-posterior`, email ao aluno, filtro e coluna de origem, origem no calendário do admin. Front: tela Registrar uso, selo e filtro, "Registrar uso aqui" no calendário e campos de uso compartilhados com o novo evento. Achado: email de revogação quebrava nas reservas de horário livre (corrigido) |
| Login com código (passo 11.4b, 16/11/2026) | API: `desafios_login`, `/login` em duas etapas, `/login/codigo`, `/login/reenviar`, email "Seu código de acesso" sem fila, `LOGIN_COM_CODIGO`, limpeza diária e conferência no `verificar-producao`. Front: segunda etapa na tela Entrar, com reenvio e contagem |
| Calendário do admin (passo 11.5b, 16/11/2026) | API: rota do admin com quem reservou e a confirmação, sem o limite do semestre, bloqueio do dia inteiro em `ocupados`. Front: calendário de segunda a domingo e criação de evento com conflito e justificativa; correção dos `<select>` com opções vindas da API |
| Reservas do admin (passo 11.5a, 16/11/2026) | API: `situacao_confirmacao` na reserva e lista/exportação também por POST (busca fora da URL). Front: lista com filtros na URL, totais que filtram, selos de status e confirmação, detalhe com linha do tempo, revogar, registrar devolução e exportar CSV |
| LGPD do calendário e emails de teste (13/11/2026) | Calendário público sem nome e curso de quem reservou (só admins); consulta mais leve; passo a passo para enviar emails reais em desenvolvimento (Gmail com senha de app, Mailtrap, Brevo) |
| Acesso do admin (passo 11.4) | Entrar, esqueci e redefinir a senha, aceitar convite, minha conta, sair; rotas do admin protegidas, retorno à página pedida e volta ao login quando a sessão acaba. Achado nos testes: `/admin` abria o layout das telas de acesso vazio (dois grupos de rotas com caminho vazio); corrigido com caminhos próprios |
| Telas pós-reserva (passo 11.3) | Minhas reservas (RA + email só na memória), confirmar com os termos e cancelar pelo link do email (token fora da barra de endereço), página de termos e privacidade, links no cabeçalho, rodapé e cadastro |
| Confirmação com aceite dos termos (passo 11.3a) | Toda reserva confirmada pelo email em 24 h (mesmo dia: 1 h) com aceite dos termos e versão gravada, reconfirmação 3 dias úteis antes com revogação na véspera, revogação a cada 15 min, `GET /api/termos` (rascunho), filtros novos de confirmação, email "Reserva registrada" removido; front com o prazo na tela de sucesso |
| Compartilhamento por espaço e calendário do semestre (11.2c e 11.2e) | API: tabela `equipamento_compartilhamentos`, `espacos_compartilhados` no admin (com histórico e 409), `calendario_ate` e `abre_em` na disponibilidade. Front: setas e seletor de semana até o fim do semestre; dias que ainda não abriram com os ocupados e "Abre para reserva em" |
| Escolha do horário mais amigável (passo 11.2d) | Barra do dia, horários livres como botões, início e fim numa lista própria que mostra todos os horários (`CampoHorario`, no lugar do `datalist`, que filtrava pelo texto), duração em botões, hora digitada de vários jeitos; testes no navegador do novo fluxo |
| Horário livre sem blocos (passo 11.2b) | API: arredondamento para 15 min, calendário LIVRE com janela e ocupados, `fim_minimo` de hoje, rota de versão da disponibilidade; demonstração com laboratórios e salas de horário livre. Front: horário montado pelo aluno com prévia do arredondamento, horários ocupados e livres, atualização automática sem piscar; testes no navegador do laboratório |
| Horários livres (passo 8) | Modo FIXO/LIVRE por espaço, reserva e evento com início/fim em blocos de 30 min, equipamentos pelo pico de uso, calendário em blocos, consulta de equipamentos por intervalo |
| Consulta do histórico (passo 7) | `GET /api/admin/auditorias` com filtros, morph map com nomes curtos |
| Admin principal | Flag `principal`, proteções contra desativação e edição por outros, comandos `admin:criar-principal` e `admin:transferir-principal` |
| Admins e senhas (itens 5 e 6) | `UserResource`, gestão de admins com convite, desativação, esqueci/redefinir/aceitar/trocar senha, email "senha alterada", email em minúsculas |
| Agendador | Feriados do ano seguinte, limpeza de tokens e de jobs falhos, horários escalonados, documentação de cron e supervisor, explicação de fila e jobs |
| Item 9 — Confirmação | Pedido 2 dias úteis antes (ou já na criação dentro da janela), prazo até o dia anterior, confirmação pela API, revogação automática, comandos agendados, filtro do admin |
| Item 8 — Cancelamento pelo aluno | Email "Reserva registrada" com link, tokens genéricos com hash, resumo e cancelamento pela API, email "Reserva cancelada", `FRONTEND_URL` |
| Minhas reservas (aluno) | Consulta por RA + email via POST, resposta genérica para dados errados, futuras/todas |
| Devolução de equipamentos | Situação por item, registro e correção, email em avaria/falta, filtro de pendências, coluna na exportação, model `ItemReserva` |
| Gestão de alunos e LGPD | Filtros, detalhe, edição, desativação com revogação das reservas futuras, anonimização com limpeza do histórico |
| Limpeza do código | Índices para o Postgres, contagem semanal e totais por status no `ReservaRepository`, paginação no controller base, nomes do `AlunoService`, código morto removido, Laravel Pint |
| Compatibilidade de bancos | Buscas com `whereLike` (ilike no Postgres), regra `NomeUnico`, datas comparadas sem `whereDate` |
| Auditoria | `criado_por`/`atualizado_por` nas tabelas principais, tabela `auditorias` com antes/depois, trait `Auditavel`, campos nas respostas do admin |
| Exportação de reservas | CSV com os filtros da listagem, atalhos de período, colunas opcionais, stream em lotes e proteção contra fórmulas; consulta movida para `ReservaRepository` |
| Item 7 — Listagem de reservas (admin) | Lista paginada com filtros, detalhe, totais por status e dados completos de aluno, espaço, equipamentos, criador e revogador |
| Passo 13 — API para sistemas parceiros (17/11/2026) | `ClienteApi` (Sanctum, permissões, limite), aceite dos termos da API por link ao responsável, token mostrado uma vez e renovação anual, `/api/v1` com controllers e resources próprios, separação total dos tokens de admin e parceiro, auditoria e origem `API`, telas no admin e página de aceite, OpenAPI pelo Scramble e guia para parceiros |
| Item 5 — Consulta de disponibilidade | Calendário público por espaço (até 31 dias), situação de cada horário, quem reservou, equipamentos por horário, limite semanal do curso por RA/curso; antecedência passa a 10 dias úteis |

---

## 10. Próximos passos

Na ordem combinada (revisado em 16/11/2026). O detalhe de cada passo (o que fará, decisões tomadas e recomendações em aberto) está no [`CHECKLIST.md`](CHECKLIST.md#️-plano-até-a-finalização-na-ordem), e a lista consolidada em "🗂️ Tudo o que falta fazer", no "Onde paramos".

**Pronto:** API (passos 1 a 10), área pública (11.1 a 11.3), acesso do admin (11.4) com senha + código por email (11.4b), reservas do admin (11.5a), calendário do admin e eventos (11.5b), registro de uso posterior (11.5c), pedido de equipamento de outro espaço com prazos e reserva mantida (11.5d e ajuste) e reserva pelo admin para um aluno (11.5e).

1. ✅ **11.5e — reservar pelo admin para um aluno** (feito em 17/11/2026).
2. ✅ **11.6 — cadastros do admin** (11.6a a 11.6d prontos em 17/11/2026): espaços, equipamentos (compartilhamento e "aceita solicitação"), cursos, alunos (com anonimização), datas bloqueadas e feriados, admins.
3. ✅ **11.7 — histórico de auditoria** no admin (17/11/2026).
4. ✅ **11.8 — acabamento** e **11.9 — estoque e responsável do evento** (17/11/2026).
5. **Passo 12 — grade de aulas (importação):** aprovado com ajustes em 13/11/2026; falta decidir a reimportação e saber o formato do arquivo da faculdade. Detalhes no passo 12 do CHECKLIST.
6. ✅ **Passo 13 — API para sistemas parceiros** (17/11/2026): cadastro com responsável, aceite dos termos da API, token de 12 meses com permissões, `/api/v1`, limite por sistema, auditoria por sistema, telas e OpenAPI + guia em `docs/`. A importação da grade pelo sistema acadêmico (`aulas:importar`) entra com o passo 12.
7. **Serviço de disparo de emails:** para testes, **Resend** (decisão de 17/11/2026; passo a passo em "Enviar emails de verdade"). **⏸️ Produção pendente** até a conversa com a faculdade: recomendado o SMTP da própria faculdade; alternativa, serviço transacional com o domínio dela (SPF, DKIM, DMARC). Ver "Enviar emails de verdade (teste local)".
8. **No deploy:** escolher a plataforma ([`HOSPEDAGEM.md`](HOSPEDAGEM.md)), definir o backup e o monitor externo, seguir o [`DEPLOY.md`](DEPLOY.md); termos e aviso de privacidade revisados pela faculdade.

### Front-end: identidade visual

Aprovada pelo usuário em 09/11/2026. Parte da identidade do front que um colega começou (`github.com/ericjunq/unifg-reservas`, pasta `frontend`; só o visual, não o código), com ajustes.

| Elemento | Definição |
|---|---|
| Azul principal | `#0D2C6B` (botões, títulos, cabeçalho) |
| Azul escuro | `#06163A` (rodapé, textos fortes) |
| Azul médio | `#144096` (hover, links) |
| Amarelo | `#F5B400` (destaques, item selecionado, detalhes). **Nunca como cor de texto sobre branco** (contraste baixo); texto sobre ele em azul principal |
| Neutros | escala `slate` do Tailwind; textos secundários no mínimo em `slate-600`, para manter o contraste |
| Estados | verde para sucesso, vermelho para erro, amarelo para aviso |
| Fonte | Montserrat, hospedada no próprio site, pesos 400, 600 e 800; títulos em 800 itálico |
| Detalhes gráficos | grade de pontinhos, tracinhos inclinados, faixa diagonal, rodapé azul com curva |
| Componentes | cartões brancos arredondados com sombra suave sobre fundo claro; botões em pílula |
| Ícones | Lucide (traço), em vez de emojis |

**Ajustes em relação à referência:**
- **paleta única:** a referência tinha dois amarelos e vários azuis;
- **foto real da quadra** no topo, com o título em texto HTML por cima. A imagem da referência parecia gerada por IA e tinha o texto embutido;
- **tom esportivo só na área pública** ligada à quadra. O admin usa a mesma paleta, de forma neutra e sem as decorações;
- **imagens otimizadas** (WebP/AVIF, uma versão por tamanho de tela) e acessibilidade (contraste, foco visível, textos alternativos).

O plano do front (passo 11) está no [`CHECKLIST.md`](CHECKLIST.md).

### Ideias registradas para depois

- **Estoque de equipamentos com movimentações (17/11/2026, ideia do usuário; 11.9 no CHECKLIST):** ajuste manual com tipo e motivo (entrada, baixa, manutenção, retorno, ajuste), quantidade em manutenção descontada do disponível, histórico das movimentações no detalhe do equipamento e atalho a partir da devolução com avaria. A quantidade total continua numa coluna, então a disponibilidade não fica mais lenta.

- **Importação de equipamentos por planilha (17/11/2026, ideia do usuário; passo 12c no CHECKLIST):** segundo import do sistema, ao lado da grade de aulas, com a mesma base (CSV/XLSX em fluxo, prévia, tudo ou nada, espaço pelo código). Atualiza o equipamento já existente (mesmo espaço e nome), cria o novo, recusa quantidade menor do que a já reservada em datas futuras e não desativa o que faltar no arquivo. Aguarda o formato da planilha da faculdade.

- **✅ Feito em 16/11/2026 (11.5d). Solicitação de equipamento pelo aluno (11.2f, aprovada em 13/11/2026):** por padrão um equipamento só aparece no próprio espaço; o admin escolhe com quais espaços ele é compartilhado (11.2c); para os outros, o aluno pode pedir o item na reserva, o admin aprova ou recusa e o aluno recebe email. Perguntas em aberto (itens que aceitam pedido, se o pedido segura a quantidade, prazo de resposta) e recomendações no passo 11 do CHECKLIST.
- **✅ Feito em 16/11/2026 (11.5c). Registro de uso posterior pelo admin (12b, aprovado em 13/11/2026):** só o admin registra uma reserva que está acontecendo ou já aconteceu (até 30 dias), sem confirmação e sem as regras de antecedência, conferindo conflitos; email ao aluno; **marcação e cor próprias de "registro posterior"** nas telas do admin e coluna "Origem" na exportação.
- **Grade de aulas (passo 12, aprovado em 13/11/2026 com ajustes):** importação CSV/XLSX com prévia; aula como ocupação semanal com vigência; aula tem prioridade (reserva de aluno no horário é revogada com email); erro na linha que tenta usar horário já ocupado; **reimportação em discussão** (recomendação: grade com nome, substituir só a escolhida, cortando a vigência dela no dia anterior ao início da nova, sem apagar). Detalhes no passo 12 do CHECKLIST.
- **Admin: reserva x reserva confirmada (para o 11.5, pedido de 13/11/2026):** selo com cor por situação da confirmação e filtro; calendário do admin com quem reservou.
- **Calendário até o fim do semestre (11.2e, aprovado em 13/11/2026):** semestres jan–jun e jul–dez, mínimo de 4 semanas, seletor de semana, limite também na API e "Abre para reserva em dd/mm"; a reserva continua limitada a 10 dias úteis.

- **✅ Feito em 16/11/2026 (11.4b). Login do admin com senha + código por email (ideia do usuário em 16/11/2026, opção A escolhida no mesmo dia):** recomendação do Claude, aceita: **senha + código de 6 dígitos por email** (dois fatores; validade de 10 min, uso único, até 5 tentativas, guardado com hash, resposta genérica). Alternativas: só o código (depende inteiramente do email e do serviço de envio, que está pendente) ou senha + aplicativo autenticador (TOTP). Ver o passo 11.4b do CHECKLIST.

- ~~**Reserva condicionada ao equipamento pedido (ideia de 16/11/2026, para depois):**~~ (absorvida em 16/11/2026: com a lógica invertida, a reserva já é cancelada se o aluno não a mantiver) uma caixa opcional no pedido de equipamento, "só quero o espaço se o equipamento for aprovado", que cancela a reserva sozinha se o pedido for recusado ou vencer. Por ora vale a opção A (a reserva continua e o email traz o botão de cancelar). Avaliação: resolve o mesmo que perguntar ao aluno depois (opção B, descartada por exigir um estado novo com prazo), mas libera o horário mais tarde.

- **✅ Feito em 17/11/2026. Reenviar o email de confirmação por "Minhas reservas" (ideia do usuário):** botão na reserva que aguarda confirmação ou reconfirmação, com RA + email; o email novo invalida os links do anterior; o prazo não muda; até 3 reenvios por etapa, 5 min entre eles.

- **Monitor externo da API:** um serviço gratuito (UptimeRobot ou Healthchecks.io) consulta `GET /up` a cada poucos minutos e avisa por outro canal (app, SMS, Telegram) quando a API não responde. Cobre o caso em que o próprio serviço de email cai e os alertas por email não saem. É configurado fora do código, na hora do deploy.
- **Backup do banco:** definido na hora do deploy, conforme a plataforma. Regra 3-2-1; banco gerenciado com backup automático **ou** `spatie/laravel-backup` com armazenamento externo (Backblaze B2, Cloudflare R2, S3); retenção de 7 diários, 4 semanais e 6 mensais; teste mensal de restauração; `.env` guardado à parte num gerenciador de senhas. LGPD: backup criptografado, acesso restrito, e refazer as anonimizações depois de uma restauração.

### Emails do sistema

| Email | Para quem | Quando | Situação |
|---|---|---|---|
| Pedido de equipamento aprovado / "Equipamento não liberado: confirme se quer manter a reserva" | Aluno | Admin responde o pedido ou ele vence no prazo (11.5d); recusa e vencimento trazem "Manter a reserva sem o equipamento" e o link de cancelar | ✅ Pronto |
| Registramos um uso de espaço em seu nome | Aluno | Admin registra um uso posterior pelo RA (11.5c) | ✅ Pronto |
| Reserva revogada | Aluno | Admin revoga, evento ocupa o horário ou data é bloqueada | ✅ Pronto |
| Pendência na devolução | Aluno | Item registrado como devolvido com avaria ou não devolvido | ✅ Pronto |
| Reserva cancelada | Aluno | Ao cancelar pelo link | ✅ Pronto |
| Confirme sua reserva (Confirmar + Cancelar) | Aluno | Ao reservar (prazo de 24 h; mesmo dia: 1 h). Confirmar exige aceitar os termos | ✅ Pronto (11.3a) |
| Sua reserva está chegando: confirme de novo | Aluno | 3 dias úteis antes, para quem confirmou há mais de 2 dias úteis (prazo: 00:00 da véspera) | ✅ Pronto (11.3a) |
| Reserva revogada por falta de confirmação | Aluno | Quando vence o prazo da confirmação ou da reconfirmação (verificado a cada 15 min; reaproveita o email de revogação) | ✅ Pronto |
| Seu código de acesso | Admin | Login com a senha certa e "Reenviar código" (11.4b) | ✅ Pronto (enviado na hora, sem fila) |
| Convite para acessar o sistema | Admin novo | Admin cadastra ou reenvia o convite | ✅ Pronto |
| Redefinição de senha | Admin | Pedido de "esqueci a senha" | ✅ Pronto |
| Sua senha foi alterada | Admin | Depois de redefinir, aceitar convite ou trocar a senha | ✅ Pronto |
| Alerta do sistema | Admins ativos e `ALERTAS_EMAIL` | Tarefa agendada ou job que falhou (máx. 1 por hora por problema) | ✅ Pronto (enviado na hora, sem fila) |

Todos, menos o alerta e o código de acesso, vão pela fila (`php artisan queue:work`). **Se o código de login chega e o email da reserva não**, é o worker que não está rodando: o código sai na hora e os outros ficam esperando na tabela `jobs` (diagnóstico de 17/11/2026). Os emails automáticos (reconfirmação, revogação por falta de confirmação, pedido de equipamento vencido) também dependem do agendador: em desenvolvimento, `php artisan schedule:work` num terminal à parte, que desde 17/11/2026 também envia a fila a cada minuto (`FILA_PELO_AGENDADOR`). O cabeçalho de todos os emails (nome do sistema) leva ao **site** (`FRONTEND_URL`), pelo layout em `resources/views/vendor/mail/{html,text}/message.blade.php` (o padrão do Laravel levava ao `APP_URL`, a API). O `EmailsTest` monta os 11 emails e confere assunto, conteúdo, que nenhum link aponta para a API ou para `localhost` e quais vão pela fila. Em desenvolvimento ficam no log (`MAIL_MAILER=log`); em produção é preciso configurar um servidor SMTP ou um serviço de envio (ex.: Amazon SES, Mailgun, Resend).

#### Enviar emails de verdade (teste local)

Para testar a confirmação recebendo o email numa caixa real (explicado ao usuário em 13/11/2026):

1. **Gmail com senha de app** (entrega de verdade): na conta Google, ativar a verificação em duas etapas e criar uma **senha de app** (Conta Google → Segurança → Senhas de app). No `.env`:
   ```env
   MAIL_MAILER=smtp
   MAIL_SCHEME=null
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=sua.conta@gmail.com
   MAIL_PASSWORD="senha de app de 16 letras"
   MAIL_FROM_ADDRESS=sua.conta@gmail.com
   FRONTEND_URL=http://localhost:4200
   ```
   **Senha de app** é uma senha separada, de 16 letras, que o Google gera para um programa (aqui, o Laravel) mandar email pela conta sem usar a senha principal; só existe com a verificação em duas etapas ligada e pode ser apagada a qualquer momento sem afetar a conta. O `FRONTEND_URL` não faz parte do bloco `MAIL_*` (fica no topo do `.env`); a API o usa para montar os links dos emails (`FRONTEND_URL/reservas/confirmar/<token>`).
   Se a página de senhas de app disser "não está disponível para sua conta" (aconteceu com o usuário em 13/11, mesmo tentando ativar), usar uma das alternativas abaixo.
   - **Resend** (entrega de verdade; **escolhido pelo usuário em 17/11/2026 para os testes**): criar a conta em resend.com entrando com o Google ou o GitHub, gerar uma chave em *API Keys* (permissão *Sending access*) e usar no `.env`:
     ```env
     MAIL_MAILER=smtp
     MAIL_SCHEME=null
     MAIL_HOST=smtp.resend.com
     MAIL_PORT=587
     MAIL_USERNAME=resend
     MAIL_PASSWORD="chave da API, começa com re_"
     MAIL_FROM_ADDRESS=onboarding@resend.dev
     ```
     Sem domínio próprio, o Resend **só entrega para o email da própria conta**: cadastre o aluno de teste (e o admin, para o código de login) com esse email. Grátis até 100 por dia. Os emails enviados aparecem no painel (*Emails*). Para entregar a qualquer pessoa, é preciso verificar um domínio (DNS), o que fica para a produção com a faculdade.
   - **Mailtrap Sandbox** (recomendado para testar; nada chega a ninguém, os emails aparecem no painel e os links podem ser clicados de lá): `MAIL_HOST=sandbox.smtp.mailtrap.io`, `MAIL_PORT=2525`, `MAIL_USERNAME` e `MAIL_PASSWORD` copiados de *Sandboxes → (caixa) → Integration → SMTP*; `MAIL_FROM_ADDRESS` pode ser qualquer endereço.
   - **Brevo** (entrega de verdade, grátis até 300 por dia): `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587`, `MAIL_USERNAME` = o login SMTP do painel (*SMTP & API*, algo como `xxxx@smtp-brevo.com`), `MAIL_PASSWORD` = uma chave SMTP gerada lá; o `MAIL_FROM_ADDRESS` precisa ser um remetente confirmado em *Senders*.
2. `php artisan config:clear` e **reiniciar** o `php artisan queue:work` (o worker guarda a configuração antiga enquanto roda). Sem o worker rodando, os emails ficam parados na fila.
   **Atenção:** o email que já estava na fila antes da troca continua saindo pelo envio antigo (o Laravel guarda, no job, qual envio usar), então ele vai para o log mesmo com o `.env` novo; basta fazer uma reserva nova. O Mailtrap Sandbox **não entrega** na caixa real (Gmail): o email só aparece no painel do Mailtrap (plano grátis: 50 emails por mês). Para recebê-lo também no Gmail, usar **Manual Forward** (reenviar um email do painel) ou **Auto Forward** (reenviar todos para endereços confirmados) na própria caixa do Sandbox.
3. Cadastrar um aluno com um email seu, reservar pelo front e abrir o link do email (o link aponta para `FRONTEND_URL`).

LGPD e segurança: a senha fica só no `.env` (fora do git); testar só com emails próprios; voltar a `MAIL_MAILER=log` quando terminar, se quiser. Limite do Gmail: cerca de 500 emails por dia, suficiente para teste e insuficiente para produção (lá, SMTP da faculdade ou serviço transacional com SPF e DKIM, ver `DEPLOY.md`).

### Melhorias anotadas para quando o volume justificar

- **Exportação muito grande:** o `lazy()` busca os lotes por OFFSET, e cada lote fica um pouco mais lento. Só pesa com dezenas de milhares de linhas num arquivo; a solução seria paginar por chave (keyset) na ordem de data/horário/id.

### Implementação final: API para terceiros autorizados (✅ feita no passo 13, 17/11/2026)

Feita como descrito em "Sistemas parceiros (passo 13)" (regras), nos endpoints `/api/v1` e em [`docs/API-PARCEIROS.md`](docs/API-PARCEIROS.md). As decisões abaixo eram os pontos em aberto antes da proposta: venceram Sanctum com *abilities*, cadastro pelo admin com aceite do responsável, `/api/v1`, limite por sistema, OpenAPI pelo Scramble e auditoria por sistema.

Pontos que estavam em aberto:

- **Autenticação de sistemas:** tokens do Sanctum com *abilities* por cliente (ex.: `reservas:ler`, `reservas:criar`) ou OAuth2 *client credentials* (Laravel Passport), se os parceiros exigirem um padrão.
- **Cadastro de clientes:** tabela de clientes de API, gerenciada pelos admins, com permissões e possibilidade de revogação.
- **Versionamento:** rotas em `/api/v1` para não quebrar os parceiros quando a API mudar.
- **Limites:** *rate limit* por cliente.
- **Documentação:** especificação OpenAPI (Swagger) gerada a partir das rotas.
- **Auditoria:** registrar qual cliente criou ou alterou cada reserva.
