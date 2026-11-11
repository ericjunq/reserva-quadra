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
- A quadra usa **horários fixos** de 1h30: `19:00–20:30` e `20:30–22:00`.
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
php artisan schedule:work  # roda o agendador em dev (pedido de confirmação às 08:00, revogação às 00:05)
php artisan reservas:pedir-confirmacao          # dispara o pedido de confirmação na hora
php artisan reservas:revogar-nao-confirmadas    # revoga as não confirmadas de hoje na hora
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

- O endereço da API vem de `frontend/src/ambientes/`: `ambiente.development.ts` (`http://localhost:8000/api`, usado pelo `npm start` e pelos testes) e `ambiente.ts` (produção; `/api` até o deploy do front ser definido no subpasso 11.8).
- O `FRONTEND_URL` do `.env` da API já é `http://localhost:4200`, então o CORS libera o front local.
- **Rede que bloqueia o `registry.npmjs.org`** (aconteceu no PC Ubuntu): `npm config set registry https://registry.yarnpkg.com/` (espelho com os mesmos pacotes). Fica no `~/.npmrc` do PC, fora do repositório; o `package-lock.json` continua apontando para o registro padrão.
- A coleta de dados de uso do Angular CLI fica **desligada** no projeto (`"analytics": false` no `angular.json`), em linha com a decisão de não usar rastreamento.
- **Para ver o front:** um terminal com `php artisan serve` (raiz) e outro com `cd frontend && npm start`; abrir http://localhost:4200. Até o 11.2, só a base visual aparece (nenhuma tela chama a API ainda).

### Preparar um PC novo

O que foi preciso no PC Ubuntu (09/11/2026), sem `sudo` e sem `curl`:

1. **PHP 8.4 + Composer:** binários do php.new (herd-lite) em `~/.config/herd-lite/bin`, baixados com `wget`, e o caminho no `~/.bashrc` (`PATH` e `PHP_INI_SCAN_DIR`).
2. **Node LTS:** tarball oficial do nodejs.org (com o checksum conferido) extraído em `~/.local/node`, e `~/.local/node/bin` no `PATH`. O Angular 22 aceita Node 22.22+, 24.15+ ou 26.
3. Abrir um terminal novo (ou `source ~/.bashrc`) e rodar o "Primeira vez" acima; no front, `cd frontend && npm install`.
4. **Git:** `git config user.name` e `user.email` iguais aos dos commits anteriores.
5. **Push por HTTPS:** o GitHub não aceita a senha da conta no terminal. Usar um **token de acesso pessoal** (*Settings → Developer settings → Personal access tokens → Fine-grained*), só para este repositório, com **Contents** e **Workflows** em *Read and write* (Workflows porque os commits podem mexer no `.github/workflows`). Para não colar o token a cada push: `git config --global credential.helper store` (guarda em `~/.git-credentials`; aceitável num PC pessoal).

### Fazer login como admin

```http
POST /api/login
{ "email": "admin@email.com", "password": "Admin" }
```

Use o `token` devolvido no cabeçalho `Authorization: Bearer <token>`. O token vale 8 horas.

### Variáveis de ambiente do negócio

Ficam em [`config/reservas.php`](config/reservas.php) e podem ser alteradas pelo `.env`:

| Variável | Padrão | O que controla |
|---|---|---|
| `RESERVA_ANTECEDENCIA_MAXIMA_DIAS_UTEIS` | 10 | Até quantos dias úteis à frente o aluno pode reservar |
| `RESERVA_BLOCO_MINUTOS` | 30 | Tamanho do bloco dos horários livres (início, fim e duração são múltiplos dele) |
| `RESERVA_MINUTOS_ANTES_DO_FIM` | 20 | Até quantos minutos antes do fim um horário de hoje pode ser reservado |
| `RESERVA_CONFIRMACAO_DIAS_UTEIS_ANTES` | 2 | Quantos dias **úteis** antes da reserva sai o pedido de confirmação |
| `RESERVA_HORARIO_PEDIDO_CONFIRMACAO` | 08:00 | Horário diário do comando que envia os pedidos |
| `RESERVA_HORARIO_REVOGACAO_NAO_CONFIRMADAS` | 00:05 | Horário diário do comando que revoga as não confirmadas |
| `RESERVA_FERIADOS_FACULTATIVOS` | false | Se a importação automática de feriados inclui Carnaval, Quarta de Cinzas e Corpus Christi |
| `RESERVA_TOKENS_RETENCAO_DIAS` | 30 | Depois de quantos dias os links de email usados ou vencidos são apagados |
| `RESERVA_JOBS_FALHOS_RETENCAO_HORAS` | 168 | Depois de quantas horas os jobs que falharam são apagados (7 dias) |
| `APP_TIMEZONE` | America/Sao_Paulo | Fuso usado nas regras de "hoje" e no agendador |
| `FRONTEND_URL` | http://localhost:4200 | Endereço do front Angular; base dos links enviados por email (ex.: cancelamento) |
| `AUTH_REDEFINICAO_EXPIRA_MINUTOS` | 60 | Validade do link de redefinição de senha |
| `AUTH_CONVITE_EXPIRA_MINUTOS` | 2880 | Validade do link de convite de admin (2 dias) |
| `CORS_ALLOWED_ORIGINS` | `FRONTEND_URL` | Endereços (separados por vírgula) que podem chamar a API pelo navegador |
| `CORS_MAX_AGE` | 86400 | Por quantos segundos o navegador guarda a liberação do CORS (menos requisições `OPTIONS`) |
| `TRUSTED_PROXIES` | vazio | IPs do proxy/balanceador na frente do servidor (ou `*`), para o Laravel enxergar o HTTPS e o IP real |
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
| `reservas:revogar-nao-confirmadas` | Todo dia, 00:05 | Revoga as reservas de hoje que não foram confirmadas |
| `reservas:limpar-tokens` | Todo dia, 03:10 | Apaga links de email usados ou vencidos há mais de 30 dias |
| `sanctum:prune-expired --hours=24` | Todo dia, 03:20 | Apaga tokens de login vencidos |
| `queue:prune-failed --hours=168` | Todo dia, 03:30 | Apaga jobs que falharam há mais de 7 dias |
| `reservas:pedir-confirmacao` | Todo dia, 08:00 | Envia os pedidos de confirmação |
| `feriados:importar {ano seguinte}` | 1º de dezembro, 03:00 | Importa os feriados do próximo ano (sem os facultativos, salvo `RESERVA_FERIADOS_FACULTATIVOS=true`) |

Em desenvolvimento: `php artisan schedule:work` (roda o agendador) e `php artisan schedule:list` (mostra as tarefas e a próxima execução).

### Em produção (agendador e fila)

O agendador precisa de **um** cron chamando o Laravel a cada minuto:

```cron
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

O worker da fila precisa ficar sempre rodando e voltar sozinho se cair. Exemplo com **supervisor** (`/etc/supervisor/conf.d/reservas-worker.conf`):

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

**Front-end (`frontend/src`):**

| Pasta | Papel |
|---|---|
| `ambientes/` | Endereço da API por ambiente (desenvolvimento e produção) |
| `app/nucleo/` | Peças usadas pelo app inteiro: `SessaoService` (token do admin no `sessionStorage`), `interceptorApi` (cabeçalhos e erros) e `erro-api.ts` (formato único de erro) |
| `app/publico/` | Área do aluno: `LayoutPublico` (identidade esportiva), páginas e `publico.routes.ts`; `ApiPublicaService` (chamadas da área pública), `RaEmMemoriaService`, `modelos.ts` (formato das respostas), `datas.ts` e `textos.ts` |
| `app/publico/inicio/` | `/`: espaços ativos em cartões, com filtro por tipo, horário e limite por curso |
| `app/publico/calendario/` | `/espacos/:id?semana=&dia=`: horário fixo em 5 colunas (dias empilhados no celular); horário livre com a escolha do dia (botões com "N livres") e a grade compacta do dia escolhido, mais as reservas do dia; quem reservou, motivo dos dias fechados e, na quadra, o limite do curso pelo RA |
| `app/publico/reservar/` | `/espacos/:id/reservar?data=&horario=` (fixo) ou `?data=&inicio=&fim=` (livre): RA, fim do intervalo (livre), equipamentos, resumo lateral e a tela de sucesso com o próximo email |
| `app/publico/cadastro/` | `/cadastro?voltar=`: cadastro do aluno com o aviso de uso dos dados; volta para a reserva com o RA preenchido |
| `app/admin/` | Área do admin: `LayoutAdmin` (neutro), páginas e `admin.routes.ts` |
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
  - **Horário livre:** o aluno clica num bloco livre e, na reserva, escolhe até que horas; as opções param no primeiro bloco ocupado e na duração máxima do espaço. Os equipamentos do intervalo vêm de `/equipamentos/disponibilidade`.
  - **Situações novas** da API (ex.: "aula", do passo 12) aparecem como "Indisponível", com o motivo, sem precisar mudar o front.
  - **Cadastro:** o `voltar` só aceita caminhos do próprio site (começando com `/`, sem `//` nem `\`), para o link não poder mandar o aluno para outro site.
  - **Calendário do horário livre:** o aluno escolhe o dia (botões com "N livres", "Lotado" ou "Fechado"; o primeiro dia com horário livre vem selecionado) e vê só aquele dia em grade compacta, com os horários de início livres como link e a lista das reservas do dia. O dia vai na URL (`?dia=`).
  - **Tamanho:** cada tela é um pacote sob demanda (início 2,4 kB, calendário 4,2 kB, reserva 5 kB, cadastro 2,5 kB comprimidos); formulários do Angular (17 kB) e ícones (só os usados) também ficam fora do pacote inicial. O pacote inicial (~300 kB, 80 kB comprimido) é quase todo do Angular (núcleo, router) e do rxjs; o orçamento é 350 kB (aviso) e 500 kB (erro).
- **Sessão do admin:** token e validade (`expires_at` do login) no `sessionStorage`, que some ao fechar a aba. Sessão vencida ou conteúdo inválido é descartado ao abrir e a cada uso.
- **Datas e números em pt-BR** (`LOCALE_ID`), `<html lang="pt-BR">` e `Referrer-Policy: no-referrer` no `index.html`, para os tokens dos links de email não vazarem para outros sites.
- **Acessibilidade:** link "Pular para o conteúdo" nos dois layouts e foco visível em todos os elementos (contorno azul).
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
| `equipamentos` | `espaco_id`, `nome` (único dentro do espaço), `quantidade_total`, `ativo`, `compartilhavel` |
| `reservas` | `data_reserva`, `horario` (enum), `hora_inicio`, `hora_fim`, `espaco_id`, `aluno_id`, `status`, `tipo` (ALUNO, EVENTO), `descricao`, `justificativa_revogacao`, `revogada_em`, `revogada_por`, `cancelada_em`, `confirmacao_enviada_em`, `confirmada_em` |
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
| POST | `/api/alunos` | Cadastro de aluno |
| GET | `/api/espacos?tipo=QUADRA` | Espaços ativos (filtro opcional) |
| GET | `/api/espacos/{id}/equipamentos` | Equipamentos reserváveis no espaço (os dele + os compartilháveis) |
| GET | `/api/espacos/{id}/equipamentos/disponibilidade?data=&hora_inicio=&hora_fim=` | Disponível de cada equipamento num intervalo (usado nos espaços LIVRE) |
| GET | `/api/espacos/{id}/disponibilidade?de=&ate=` | Calendário do espaço: situação de cada horário e equipamentos disponíveis (opcional `curso_id`) |
| POST | `/api/espacos/{id}/disponibilidade/curso` | O mesmo calendário com o limite semanal do curso do aluno (`registro_academico`, `de`, `ate` no corpo); só em espaços com limite por curso |
| POST | `/api/reservas` | Cria uma reserva |
| POST | `/api/minhas-reservas` | O aluno consulta as próprias reservas (RA + email no corpo) |
| GET | `/api/reservas/cancelamento/{token}` | Resumo da reserva do link do email (`pode_cancelar`) |
| POST | `/api/reservas/cancelamento/{token}` | Cancela a reserva do link do email |
| GET | `/api/reservas/confirmacao/{token}` | Resumo da reserva do link de confirmação (`pode_confirmar`, `prazo_confirmacao`) |
| POST | `/api/reservas/confirmacao/{token}` | Confirma a reserva |
| POST | `/api/login` | Login do admin (responde com `token`, `expires_at` e `user`) |
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
- Cada dia tem `reservavel` e `motivo` (data passada, fim de semana, fora da antecedência ou bloqueio do dia inteiro).
- Cada horário tem `situacao`: `LIVRE`, `RESERVADO`, `EVENTO`, `BLOQUEADO`, `INDISPONIVEL` (o dia não aceita reservas) ou `ENCERRADO` (horário de hoje passou do limite). Também traz `motivo`, `reserva` (com `reservado_por`: nome e curso do aluno, ou nome do admin; RA e email nunca aparecem) e `equipamentos` (`total` e `disponivel` naquele horário, incluindo os compartilháveis).
- **Espaços LIVRE:** `espaco` traz `modo_horario`, `abre_as`, `fecha_as`, `duracao_maxima_minutos` e `bloco_minutos`; cada dia lista os **blocos** da janela (ex.: 07:00–07:30, 07:30–08:00…) com a mesma `situacao`/`reserva`, `horario: null` e **sem** `equipamentos` (para o intervalo escolhido, usar `/equipamentos/disponibilidade`).


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

### Admin (exigem `Authorization: Bearer <token>`)

| Método | Rota | Descrição |
|---|---|---|
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
| GET/POST/PATCH/DELETE | `/api/admin/equipamentos` | CRUD de equipamentos (`?espaco_id=`, `?nome=`, `?ativo=`) |
| GET/POST/PATCH/DELETE | `/api/admin/cursos` | CRUD de cursos |
| GET | `/api/admin/alunos` | Lista de alunos (`?busca=` nome/RA/email, `?curso_id=`, `?ativo=`) |
| GET | `/api/admin/alunos/{id}` | Detalhe do aluno, com o total de reservas futuras |
| PATCH | `/api/admin/alunos/{id}` | Edita nome, RA, email, curso e `ativo` (desativar revoga as reservas futuras) |
| DELETE | `/api/admin/alunos/{id}` | Desativa o aluno e revoga as reservas futuras (responde com `reservas_revogadas`) |
| POST | `/api/admin/alunos/{id}/anonimizar` | Anonimiza os dados pessoais (LGPD); exige `{"confirmar": true}` |
| GET/POST/PATCH/DELETE | `/api/admin/datas-bloqueadas` | Bloqueios (`?de=`, `?ate=`, `?espaco_id=`); PATCH altera só o motivo |
| GET | `/api/admin/reservas` | Lista reservas com filtros (ver abaixo) |
| GET | `/api/admin/reservas/exportar` | Exporta as reservas filtradas em CSV |
| GET | `/api/admin/reservas/exportar/colunas` | Colunas obrigatórias e opcionais da exportação |
| GET | `/api/admin/reservas/{id}` | Detalhe de uma reserva |
| POST | `/api/admin/reservas/eventos` | Reserva de evento da faculdade |
| POST | `/api/admin/reservas/{id}/revogar` | Revoga uma reserva (`justificativa` obrigatória) |
| POST | `/api/admin/reservas/{id}/devolucao` | Registra (ou corrige) a devolução dos equipamentos da reserva |

**Listagem de reservas:** `GET /api/admin/reservas?de=2026-11-01&ate=2026-11-30&curso_id=2&status=RESERVADO`

- Filtros opcionais e combináveis: `periodo` (atalho: `HOJE`, `SEMANA_ATUAL`, `PROXIMA_SEMANA`, `MES_ATUAL`, `MES_ANTERIOR`, `ANO_ATUAL`; não combina com `de`/`ate`), `de`, `ate`, `espaco_id`, `tipo_espaco`, `curso_id`, `busca` (nome ou RA do aluno, busca parcial), `status`, `tipo`, `horario`, `devolucao` (situação de algum item: só reservas ativas que já começaram; sem `de`/`ate`, não aplica o padrão de hoje em diante, para achar pendências antigas), `confirmacao` (`AGUARDANDO`, `CONFIRMADA` ou `NAO_SOLICITADA`; só reservas de aluno).
- Sem `de` e `ate`, lista de hoje em diante. `ordem=asc|desc` (padrão `asc`, por data e horário). `per_page` até 100 (padrão 15).
- Cada reserva traz `aluno` (nome, RA, email e curso), `espaco`, `equipamentos`, `criado_por` (eventos), `revogada_por` e a justificativa (revogadas).
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
  - múltiplos do bloco (30 min), dentro da janela, fim depois do início, duração de 30 min até a máxima do espaço (422);
  - mandar `horario` é recusado;
  - as outras regras valem igual: dia útil, antecedência, hoje até 20 min antes do **fim escolhido**, bloqueios (inclusive de parte do dia), conflito por sobreposição (14:00–15:00 e 15:00–16:00 não conflitam), limite semanal (conta reservas, não horas), confirmação, cancelamento e devolução.
- **Eventos** em espaço LIVRE: blocos de 30 min, podendo sair da janela e sem duração máxima.
- **Configuração:** o admin define no cadastro/edição do espaço (no LIVRE, os três campos são obrigatórios, alinhados ao bloco, fechamento depois da abertura, duração máxima múltipla do bloco e que caiba na janela). **Trocar o modo** com reservas futuras é recusado (409). Mudar a janela ou a duração não afeta as reservas já feitas.

### Equipamentos na reserva

| Regra | Erro |
|---|---|
| Equipamento ativo, do próprio espaço **ou** compartilhável | 422 |
| Sem equipamento repetido; quantidade ≥ 1 e ≤ estoque total | 422 |
| Quantidade ≤ **disponível** naquela data/horário | 409 (lista o que faltou e quanto há) |

**Disponível** = `quantidade_total` − **pico de uso** no intervalo: o maior total reservado (status `RESERVADO`, **em qualquer espaço**) em um mesmo momento. Ex.: 2 projetores das 14:00 às 15:00 e outros 2 das 15:00 às 16:00 dão pico 2 no intervalo 14:00–16:00 (e não 4). Nos horários fixos o resultado é o mesmo da soma.
O cálculo fica todo em `DisponibilidadeEquipamentoRepository`: uma única consulta agrupada por equipamento, independente de quantos itens forem pedidos.
Exemplo: 2 microscópios compartilháveis e 3 labs no mesmo horário → o terceiro recebe 409.

### Eventos (admin)

- Podem ser em fim de semana e não têm limite de antecedência.
- Se o horário estiver ocupado: sem `justificativa` → 409 com `reserva_conflitante_id`; com `justificativa` → a reserva do aluno é revogada, o aluno recebe email e o evento é criado, tudo na mesma transação.
- Podem reservar equipamentos (os da reserva revogada já ficam liberados).

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
- trava as **linhas dos equipamentos** pedidos (em ordem de id, para evitar deadlock), porque os compartilháveis podem ser disputados por espaços diferentes;
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

### Cancelamento pelo aluno (link do email)

- Ao reservar, o aluno recebe **"Reserva registrada"** (ou, dentro da janela de confirmação, **"Confirme sua reserva"**, ver abaixo) com os detalhes e o botão **Cancelar reserva**, que leva a `FRONTEND_URL/reservas/cancelar/{token}` (página do front Angular).
- O front chama `GET /api/reservas/cancelamento/{token}` para mostrar o resumo (sem RA, email ou nome) e `POST /api/reservas/cancelamento/{token}` para cancelar. **Só o POST cancela**: leitores de email e antivírus abrem links sozinhos.
- O token tem 64 caracteres aleatórios; no banco fica só o **hash SHA-256**. Vale **até o início da reserva** e só pode ser usado **uma vez**. Token inválido, vencido ou usado: `404` com "Link inválido ou expirado.".
- Cancelar: só reservas `RESERVADO` (409) que não começaram (422). Grava `CANCELADO` e `cancelada_em`, libera horário e equipamentos e envia **"Reserva cancelada"**. Na auditoria fica sem admin (foi o aluno).
- Eventos não recebem esses emails nem token.
- Até o front existir, o link do email não abre página nenhuma; em desenvolvimento os emails ficam em `storage/logs/laravel.log`, e o fluxo é testado pela API (Postman/curl) e pelos testes automáticos.

### Confirmação da reserva (item 9)

- **Pedido:** o comando `reservas:pedir-confirmacao` (todo dia às 08:00) envia **"Confirme sua reserva"** para as reservas de aluno ativas que acontecem até **2 dias úteis** à frente e ainda não receberam o pedido. Ex.: reserva de segunda → pedido na quinta. Grava `confirmacao_enviada_em`.
- **Prazo:** até as **23:59 do dia anterior** à reserva (dia corrido). Ex.: reserva de segunda → pedido na quinta, prazo até domingo 23:59.
- O email tem **Confirmar** (`FRONTEND_URL/reservas/confirmar/{token}`, token `CONFIRMAR` que vence no prazo) e **Cancelar** (token `CANCELAR` novo, vence no início). O front chama `GET`/`POST /api/reservas/confirmacao/{token}`; só o POST confirma (grava `confirmada_em`, token de uso único).
- **Revogação automática:** o comando `reservas:revogar-nao-confirmadas` (todo dia às 00:05) revoga as reservas **do dia** que receberam o pedido e não foram confirmadas, com a justificativa "Reserva não confirmada no prazo." e o email de revogação. Na auditoria fica sem admin. O horário fica livre no próprio dia (reserva para hoje é aceita até 20 minutos antes do fim).
- **Na criação**, depende da data:
  - reserva **para hoje**: nasce **confirmada** (o prazo já teria passado) e recebe "Reserva registrada";
  - reserva **dentro da janela** (o pedido já teria saído): o email da criação já é **"Confirme sua reserva"**, um email só, com os dois botões. Isso também confirma que quem reservou é o dono do email;
  - reserva **fora da janela**: recebe "Reserva registrada", com a data em que chegará o pedido de confirmação.
- **Eventos** não precisam de confirmação.
- O `ReservaResource` mostra `confirmacao_enviada_em` e `confirmada_em`.

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
- **Desativar admin:** ele não faz mais login e perde todas as sessões na hora. Ninguém desativa a si mesmo, e sempre sobra pelo menos um admin ativo (verificado com trava). Reativar é `PATCH` com `ativo: true`.
- **Login** de admin inativo ou com senha errada: a mesma resposta, "Credenciais inválidas".
- **Auditoria:** convites, edições e desativações de admins ficam no histórico; senha e `remember_token` aparecem só como `[oculto]`.
- **Admin principal** (um só; conta de testes e manutenção): **ninguém o desativa** (409) e **só ele mesmo edita os próprios dados** (403 para os outros). Fora isso, tem os mesmos poderes dos outros admins. A flag `principal` não muda pela API: o seeder cria o `admin@email.com` como principal em desenvolvimento; em produção, `php artisan admin:criar-principal` (pergunta nome, email e senha, que não aparece na tela) e `php artisan admin:transferir-principal {email}` (passa o papel para outro admin ativo que já definiu a senha).

### Alunos (admin)

- **Desativar** (DELETE ou PATCH com `ativo: false`) revoga as reservas ativas que ainda não terminaram, com a justificativa "Cadastro do aluno desativado." e email para o aluno. Reativar é PATCH com `ativo: true`.
- Aluno inativo não reserva (RA recusado com 422) e não pode ser usado na consulta de disponibilidade.
- **Mudança de curso:** o limite semanal conta pelo curso atual do aluno, então as reservas dele passam a contar para o curso novo.
- O RA continua único; ao editar, a validação ignora o próprio aluno.

### LGPD

- **Rotas públicas** nunca mostram RA nem email; o calendário mostra só nome e curso de quem reservou.
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
| Equipamento pertence a um espaço + flag `compartilhavel` | Por padrão a bola da quadra fica restrita à quadra; o microscópio pode ser marcado para uso em salas |
| Disponibilidade **calculada** a partir das reservas, num repositório | Ela muda por data e horário; um número fixo no equipamento não representa isso. Uma tabela de ocupação teria que ser atualizada em cada operação e poderia ficar errada. O repositório concentra a consulta (uma ida ao banco, com índices) |
| Repositórios **só para consultas complexas** | Entidades com operações simples usam o Eloquent direto nos services; repositório onde a query é complexa ou reaproveitada |
| Data da reserva gravada como `Y-m-d` | Permite comparar a coluna diretamente e usar índices (o `whereDate` impede o uso de índice) |
| Espaço exclusivo por horário | Regra atual da quadra. Está isolada em `verificarConflitoDeEspaco()` e pode ser removida se um dia o espaço for compartilhado |
| Reserva guarda `hora_inicio` e `hora_fim`; conflito por **sobreposição** | Funciona igual nos horários fixos e livres |
| Modo de horário **por espaço** (FIXO/LIVRE) | A quadra continua fixa; laboratórios e salas podem ter horário livre |
| Bloco de **30 min global** (configurável) | Simplifica calendário e cálculo de pico; por espaço só faria sentido com espaços muito diferentes |
| Duração mínima de 1 bloco; **máxima por espaço** | Cada espaço decide quanto tempo uma reserva pode ocupar |
| Equipamentos pelo **pico de uso** | Somar reservas que se sobrepõem ao intervalo superestima o uso quando elas não acontecem ao mesmo tempo |
| Calendário LIVRE em blocos, **sem equipamentos por bloco** | A resposta ficaria enorme (blocos × equipamentos × dias); os equipamentos vêm da consulta por intervalo |
| Eventos podem sair da janela e passar da duração máxima | Decisão do admin (ex.: palestra até 23:00) |
| Limite semanal continua contando **reservas**, não horas | Mantém a regra combinada para a quadra |
| Trocar o modo com reservas futuras é recusado | Reserva fixa em espaço livre (ou o contrário) ficaria incoerente |
| Horários fixos iguais para todos os espaços | Horários fixos por espaço ficam como opcional |
| Regras de entrada em `app/Rules` (uma linha no request) | Fáceis de ligar e desligar quando a regra mudar |
| Números do negócio em `config/reservas.php` | Mudar 10 dias úteis, 20 minutos ou 2 dias sem mexer no código |
| Antecedência em **dias úteis** | Com dias corridos, quem reservava na sexta só alcançava 5 dias úteis; contando dias úteis, a janela é a mesma qualquer dia da semana. Feriados contam como dia útil na contagem e aparecem no calendário como bloqueados: a janela fica previsível e não muda quando um bloqueio é cadastrado. Prazo de 10 dias úteis |
| Disponibilidade como **calendário** (até 31 dias) | O front navega por semanas/meses; reservas continuam limitadas a 10 dias úteis, e os dias fora da janela vêm com o motivo |
| Consulta mostra **quem reservou** (nome e curso) | Facilita o mapeamento do uso. Por ser pública, não mostra RA nem email (LGPD) |
| Consulta com RA ou curso **não filtra** as reservas | Mostra o espaço inteiro e apenas aponta o limite e a disponibilidade do curso |
| Mensagem do limite semanal sem o nome do curso | Quem digitasse um RA alheio descobria o curso dele quando o limite estourava (achado no 11.2, aprovado pelo usuário em 10/11/2026) |
| Orçamento do front: aviso 350 kB, erro 500 kB | A base do Angular (núcleo, router, rxjs) já ocupa ~275 kB do pacote inicial; o limite de 300 kB seria ultrapassado sem código nosso (aprovado em 10/11/2026) |
| Horário livre escolhido **por dia** no calendário | Ideia do usuário: 30 blocos por dia em 5 colunas deixavam a tela enorme; agora o aluno escolhe o dia e vê só ele |
| RA do calendário por **POST** (`/disponibilidade/curso`), fora do `GET` | Ideia do usuário (10/11/2026): o aluno digita o RA e o sistema descobre o curso. Por POST para o RA não ir para URL e logs; 20 por minuto (pedido do usuário; o limite é por IP e o Wi-Fi da faculdade compartilha IP); só em espaços com limite por curso, porque o curso só importa na quadra (atléticas); sem o nome do curso na resposta |
| Eventos podem revogar reservas de alunos | Eventos da faculdade têm prioridade; justificativa obrigatória e aluno avisado |
| Reservas não confirmadas são revogadas automaticamente | Libera horários de quem não vai usar |
| Pedido de confirmação **2 dias úteis** antes | Em dias corridos, reserva de segunda pediria confirmação no sábado, quando muita gente não abre o email da faculdade |
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
| Reserva dentro da janela pede a confirmação **já na criação** | Um email só; também confirma que quem reservou é o dono do email (reserva com RA de outra pessoa cai sozinha) |
| Reserva **para hoje** nasce confirmada | O prazo (dia anterior) já teria passado; um prazo de poucas horas exigiria revogação de hora em hora para um caso raro |
| Email de confirmação também tem **Cancelar** | Quem não vai mais libera o horário na hora, sem esperar a revogação |
| Email da criação renomeado para "Reserva registrada" | "Confirmada" confundia com a confirmação do item 9 |
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

Os testes ficam em `tests/Feature`, um arquivo por assunto, e usam as factories de `database/factories`. A data dos testes é fixada em **segunda-feira, 09/11/2026, 10:00** (`tests/TestCase.php`), para não dependerem do dia em que rodam. O limite de requisições por minuto fica desligado nos testes.

| Arquivo | O que cobre |
|---|---|
| `AlunoTest` | Cadastro, RA duplicado, curso inativo, listagem só para admin |
| `AdminTest` | Login e /me com resource, email sem maiúsculas, inativo sem login, convite (email, 2 dias, link só com token), email único, filtros, edição, desativação derrubando sessões e reativação, não desativar a si mesmo, sempre um ativo, reenvio de convite, senha nunca no histórico |
| `HorarioLivreTest` | Configuração do espaço LIVRE e validações, troca de modo com reservas, reserva com início/fim e validações do intervalo, espaço fixo recusa início/fim, conflito por sobreposição, hoje até 20 min antes do fim, bloqueio parcial, evento fora da janela, equipamentos pelo pico, redução de estoque pelo pico, calendário em blocos, consulta de equipamentos por intervalo, link de cancelamento no início escolhido |
| `AuditoriaConsultaTest` | Histórico de um registro em ordem, nome curto no banco, filtros por admin e sem admin, por ação e período, senha oculta e aluno anonimizado, validações, paginação |
| `AdminPrincipalTest` | Principal não desativado nem editado por outros, edita a si mesmo, desativa os outros, flag não muda pela API, seeder, comandos de criação (senha fraca, duplicado) e de transferência |
| `SenhaTest` | Esqueci a senha (link só com token, 60 min), resposta igual para inexistente/inativo, redefinição derrubando sessões com email de aviso, uso único e vencimento, token/email errados, regras da senha, aceite de convite em até 2 dias, link de redefinição não serve no convite, troca logado mantendo a sessão atual |
| `AgendadorTest` | Horários e `withoutOverlapping` de todas as tarefas, feriados do ano seguinte sem facultativos, retenção de 7 dias dos jobs falhos, limpeza só dos tokens antigos |
| `ConfirmacaoTest` | Reserva para hoje confirmada, dentro da janela pede confirmação na criação (prazo e links), fora da janela avisa a data do pedido, pedido 2 dias úteis antes e sem duplicar, o que o pedido ignora, confirmação pelo link, vencimento no fim do dia anterior, cancelar pelo email de confirmação, revogação só das não confirmadas do dia, fluxo completo pelos comandos, filtro do admin, agendamento |
| `CancelamentoTest` | Email de criação com link do front, só o hash no banco e validade até o início, resumo sem dados pessoais, cancelamento liberando horário e equipamentos com email, uso único, token inválido e vencido, reserva revogada, evento sem email, criação sem expor o aluno |
| `MinhasReservasTest` | Futuras e todas, ordem, espaço/equipamentos/justificativa, sem dados pessoais nem auditoria, email sem maiúsculas, resposta igual para dados errados, inativo x anonimizado, só POST, validações, paginação |
| `DevolucaoTest` | Itens pendentes, devolução completa, avaria/falta com email, estoque e aluno não afetados, correção com histórico, coerência de quantidades, item fora da reserva, validações, reserva ativa e iniciada, evento sem email, filtro de pendências, coluna na exportação |
| `AlunoAdminTest` | Filtros, detalhe, edição, desativação com revogação e email, reativação, aluno inativo sem reservar, mudança de curso no limite semanal, anonimização (confirmação, dados e histórico limpos, sem email, bloqueio de alterações, RA liberado) |
| `EspacoTest` | CRUD, PATCH, filtros, bloqueio de desativação com reservas futuras, paginação padronizada |
| `EquipamentoTest` | Nome único por espaço, filtros, compartilháveis, redução de estoque |
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
| `DisponibilidadeTest` | Situações dos horários, quem reservou sem RA/email, bloqueios, motivos do calendário, equipamentos por horário, dados do curso por `curso_id`, consulta com RA só por POST (sem id/nome do curso, só em espaço com limite, RA inexistente ou inativo com a mesma mensagem, espaço inativo), `GET` ignorando o RA, validações |
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
| `publico/calendario/calendario.spec.ts` | Horário livre: botões dos dias com o resumo, dia escolhido por padrão e pela URL, só os blocos livres como link, reservas do dia agrupadas. Semana de segunda a sexta com links de reserva, quem reservou, dias fechados, situação desconhecida, RA só em espaço com limite, consulta do limite por POST e RA só na memória, RA inexistente, formato do RA, sem voltar para semanas passadas |
| `publico/reservar/reservar.spec.ts` | Reserva com equipamentos (limite do disponível) e corpo enviado, RA vindo do calendário e esquecido depois, horário livre (opções de fim e equipamentos do intervalo), erro do RA com link de cadastro, 409 recarregando o horário, URL inválida |
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
| Item 5 — Consulta de disponibilidade | Calendário público por espaço (até 31 dias), situação de cada horário, quem reservou, equipamentos por horário, limite semanal do curso por RA/curso; antecedência passa a 10 dias úteis |

---

## 10. Próximos passos

Na ordem combinada. O detalhe de cada passo (o que fará, decisões tomadas e recomendações em aberto) está no [`CHECKLIST.md`](CHECKLIST.md#️-plano-até-a-finalização-na-ordem).

1. **Front-end em Angular** (pasta `frontend/`; não será com Blade): base (11.1) e área pública de reserva (11.2) prontas; **proposta do 11.3 enviada** (minhas reservas, cancelar e confirmar pelo link do email, página de privacidade).
2. **Grade de aulas (importação):** o admin importa a grade da faculdade (espaços e horários de aula) e esses horários deixam de aceitar reservas de alunos. Ideia do usuário em 10/11/2026; detalhes e recomendações iniciais no passo 12 do [`CHECKLIST.md`](CHECKLIST.md).
3. **API para terceiros autorizados**, com as devidas restrições de segurança (passo 13). O sistema acadêmico da faculdade pode ser um dos clientes, enviando a grade de aulas.
4. **No deploy:** escolher a plataforma ([`HOSPEDAGEM.md`](HOSPEDAGEM.md)), definir o backup e o monitor externo, seguir o [`DEPLOY.md`](DEPLOY.md).

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

- **Monitor externo da API:** um serviço gratuito (UptimeRobot ou Healthchecks.io) consulta `GET /up` a cada poucos minutos e avisa por outro canal (app, SMS, Telegram) quando a API não responde. Cobre o caso em que o próprio serviço de email cai e os alertas por email não saem. É configurado fora do código, na hora do deploy.
- **Backup do banco:** definido na hora do deploy, conforme a plataforma. Regra 3-2-1; banco gerenciado com backup automático **ou** `spatie/laravel-backup` com armazenamento externo (Backblaze B2, Cloudflare R2, S3); retenção de 7 diários, 4 semanais e 6 mensais; teste mensal de restauração; `.env` guardado à parte num gerenciador de senhas. LGPD: backup criptografado, acesso restrito, e refazer as anonimizações depois de uma restauração.

### Emails do sistema

| Email | Para quem | Quando | Situação |
|---|---|---|---|
| Reserva revogada | Aluno | Admin revoga, evento ocupa o horário ou data é bloqueada | ✅ Pronto |
| Pendência na devolução | Aluno | Item registrado como devolvido com avaria ou não devolvido | ✅ Pronto |
| Reserva registrada (com link de cancelamento) | Aluno | Ao reservar (fora da janela de confirmação ou para hoje) | ✅ Pronto |
| Reserva cancelada | Aluno | Ao cancelar pelo link | ✅ Pronto |
| Confirme sua reserva (Confirmar + Cancelar) | Aluno | 2 dias úteis antes, ou já na criação se a reserva cair dentro da janela | ✅ Pronto |
| Reserva revogada por falta de confirmação | Aluno | No dia da reserva, às 00:05, se não confirmou (reaproveita o email de revogação) | ✅ Pronto |
| Convite para acessar o sistema | Admin novo | Admin cadastra ou reenvia o convite | ✅ Pronto |
| Redefinição de senha | Admin | Pedido de "esqueci a senha" | ✅ Pronto |
| Sua senha foi alterada | Admin | Depois de redefinir, aceitar convite ou trocar a senha | ✅ Pronto |
| Alerta do sistema | Admins ativos e `ALERTAS_EMAIL` | Tarefa agendada ou job que falhou (máx. 1 por hora por problema) | ✅ Pronto (enviado na hora, sem fila) |

Todos, menos o alerta, vão pela fila (`php artisan queue:work`). Em desenvolvimento ficam no log (`MAIL_MAILER=log`); em produção é preciso configurar um servidor SMTP ou um serviço de envio (ex.: Amazon SES, Mailgun, Resend).

### Melhorias anotadas para quando o volume justificar

- **Exportação muito grande:** o `lazy()` busca os lotes por OFFSET, e cada lote fica um pouco mais lento. Só pesa com dezenas de milhares de linhas num arquivo; a solução seria paginar por chave (keyset) na ordem de data/horário/id.

### Implementação final: API para terceiros autorizados

Permitir que sistemas de terceiros autorizados (ex.: portal da faculdade, app das atléticas, outras unidades da rede) consumam a API.

Pontos a decidir quando chegar a hora:

- **Autenticação de sistemas:** tokens do Sanctum com *abilities* por cliente (ex.: `reservas:ler`, `reservas:criar`) ou OAuth2 *client credentials* (Laravel Passport), se os parceiros exigirem um padrão.
- **Cadastro de clientes:** tabela de clientes de API, gerenciada pelos admins, com permissões e possibilidade de revogação.
- **Versionamento:** rotas em `/api/v1` para não quebrar os parceiros quando a API mudar.
- **Limites:** *rate limit* por cliente.
- **Documentação:** especificação OpenAPI (Swagger) gerada a partir das rotas.
- **Auditoria:** registrar qual cliente criou ou alterou cada reserva.
