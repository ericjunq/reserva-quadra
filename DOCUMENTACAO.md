# Sistema de Reservas de Espaços — Documentação de Desenvolvimento

API (backend) em Laravel para reservar os espaços da faculdade (quadra, laboratórios e salas) e os equipamentos desses espaços.

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

**Stack:** PHP 8.3+, Laravel 13, Laravel Sanctum, SQLite em desenvolvimento.

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
php artisan test           # roda os testes
php artisan feriados:importar 2027                 # importa os feriados nacionais como datas bloqueadas
php artisan feriados:importar 2027 --facultativos  # inclui Carnaval, Quarta de Cinzas e Corpus Christi
```

Depois de alterar migrations, rode `php artisan migrate:fresh --seed` (**apaga os dados locais**).

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
| `RESERVA_MINUTOS_ANTES_DO_FIM` | 20 | Até quantos minutos antes do fim um horário de hoje pode ser reservado |
| `RESERVA_CONFIRMACAO_DIAS_ANTES` | 2 | Quantos dias antes o pedido de confirmação é enviado (item 9) |
| `APP_TIMEZONE` | America/Sao_Paulo | Fuso usado nas regras de "hoje" e no agendador |

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

**Convenções:**

- Erros de **entrada** (campo inválido) → `422` no request.
- Erros de **regra de negócio** que dependem do estado do banco (horário ocupado, estoque, limite semanal) → `RegraNegocioException`: `409` para conflitos, `422` para as demais.
- Update só por **PATCH** (atualização parcial).
- Exclusão de espaços, equipamentos e cursos = **desativação** (`ativo = false`), para não perder o histórico.
- Sem comentários no código: as explicações ficam no `implementacoes-com-claude-code.txt` e neste documento.

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
| `alunos` | `nome_completo`, `registro_academico` (RA, único, 10 dígitos), `email`, `curso_id` |
| `espacos` | `nome` (único), `tipo` (QUADRA, LABORATORIO, SALA), `ativo`, `limite_semanal_por_curso` (vazio = sem limite) |
| `equipamentos` | `espaco_id`, `nome` (único dentro do espaço), `quantidade_total`, `ativo`, `compartilhavel` |
| `reservas` | `data_reserva`, `horario` (enum), `hora_inicio`, `hora_fim`, `espaco_id`, `aluno_id`, `status`, `tipo` (ALUNO, EVENTO), `descricao`, `justificativa_revogacao`, `revogada_em`, `revogada_por`, `confirmacao_enviada_em`, `confirmada_em` |
| `item_reservas` | `reserva_id`, `equipamento_id`, `quantidade` |
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
| GET | `/api/espacos/{id}/disponibilidade?de=&ate=` | Calendário do espaço: situação de cada horário e equipamentos disponíveis |
| POST | `/api/reservas` | Cria uma reserva |
| POST | `/api/login` | Login do admin |

`POST /api/alunos` e `POST /api/reservas` aceitam no máximo 10 requisições por minuto. O login aceita 5. A consulta de disponibilidade aceita 60.

**Consulta de disponibilidade:**

`GET /api/espacos/1/disponibilidade?de=2026-11-09&ate=2026-11-13`

- `de` é obrigatório. `ate` é opcional (sem ele, a consulta é de um dia) e o período pode ter até 31 dias, para o front navegar pelo calendário.
- Opcional: `registro_academico` **ou** `curso_id`. Com eles, a resposta mostra todas as reservas igualmente e acrescenta os dados do curso: quantas reservas ele já tem em cada semana, quantas restam, `do_curso` em cada reserva e `disponivel_para_o_curso` em cada horário.
- `reservas_ate`: último dia que aceita reservas de alunos (10 dias úteis à frente).
- Cada dia tem `reservavel` e `motivo` (data passada, fim de semana, fora da antecedência ou bloqueio do dia inteiro).
- Cada horário tem `situacao`: `LIVRE`, `RESERVADO`, `EVENTO`, `BLOQUEADO`, `INDISPONIVEL` (o dia não aceita reservas) ou `ENCERRADO` (horário de hoje passou do limite). Também traz `motivo`, `reserva` (com `reservado_por`: nome e curso do aluno, ou nome do admin; RA e email nunca aparecem) e `equipamentos` (`total` e `disponivel` naquele horário, incluindo os compartilháveis).


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

### Admin (exigem `Authorization: Bearer <token>`)

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/logout` · `/api/logout-geral` | Encerra o token atual ou todos os tokens |
| GET | `/api/me` | Admin logado |
| GET/POST/PATCH/DELETE | `/api/admin/espacos` | CRUD de espaços (`?tipo=`) |
| GET/POST/PATCH/DELETE | `/api/admin/equipamentos` | CRUD de equipamentos (`?espaco_id=`, `?nome=`, `?ativo=`) |
| GET/POST/PATCH/DELETE | `/api/admin/cursos` | CRUD de cursos |
| GET | `/api/admin/alunos` | Lista de alunos |
| GET/POST/PATCH/DELETE | `/api/admin/datas-bloqueadas` | Bloqueios (`?de=`, `?ate=`, `?espaco_id=`); PATCH altera só o motivo |
| GET | `/api/admin/reservas` | Lista reservas com filtros (ver abaixo) |
| GET | `/api/admin/reservas/exportar` | Exporta as reservas filtradas em CSV |
| GET | `/api/admin/reservas/exportar/colunas` | Colunas obrigatórias e opcionais da exportação |
| GET | `/api/admin/reservas/{id}` | Detalhe de uma reserva |
| POST | `/api/admin/reservas/eventos` | Reserva de evento da faculdade |
| POST | `/api/admin/reservas/{id}/revogar` | Revoga uma reserva (`justificativa` obrigatória) |

**Listagem de reservas:** `GET /api/admin/reservas?de=2026-11-01&ate=2026-11-30&curso_id=2&status=RESERVADO`

- Filtros opcionais e combináveis: `periodo` (atalho: `HOJE`, `SEMANA_ATUAL`, `PROXIMA_SEMANA`, `MES_ATUAL`, `MES_ANTERIOR`, `ANO_ATUAL`; não combina com `de`/`ate`), `de`, `ate`, `espaco_id`, `tipo_espaco`, `curso_id`, `busca` (nome ou RA do aluno, busca parcial), `status`, `tipo`, `horario`.
- Sem `de` e `ate`, lista de hoje em diante. `ordem=asc|desc` (padrão `asc`, por data e horário). `per_page` até 100 (padrão 15).
- Cada reserva traz `aluno` (nome, RA, email e curso), `espaco`, `equipamentos`, `criado_por` (eventos), `revogada_por` e a justificativa (revogadas).
- `totais_por_status` traz a contagem de cada status com os mesmos filtros, menos o de status.

**Exportação de reservas:** `GET /api/admin/reservas/exportar?periodo=MES_ANTERIOR&colunas[]=curso&colunas[]=equipamentos`

- Aceita os mesmos filtros da listagem (inclusive `periodo` e `ordem`). Período de no máximo 366 dias.
- Gera um CSV que abre direto no Excel em português (separador `;`, UTF-8 com BOM, datas em dd/mm/aaaa), com nome conforme o período (ex.: `reservas_2026-10-01_a_2026-10-31.csv`).
- **Colunas obrigatórias** (sempre saem): ID, data, horário, espaço, tipo, status e responsável (aluno ou admin do evento).
- **Colunas opcionais** (`colunas[]=...`, ou `colunas[]=todas`): `tipo_espaco`, `registro_academico`, `email`, `curso`, `equipamentos`, `descricao`, `criada_em`, `revogacao` (revogada por, em e justificativa). Relações só são carregadas quando a coluna é pedida.
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
| Espaço com `limite_semanal_por_curso` (quadra = 3): no máximo N reservas do mesmo curso de **segunda a sexta** | 409 |
| Data/horário não pode estar numa **data bloqueada** (mensagem traz o motivo) | 422 |

### Equipamentos na reserva

| Regra | Erro |
|---|---|
| Equipamento ativo, do próprio espaço **ou** compartilhável | 422 |
| Sem equipamento repetido; quantidade ≥ 1 e ≤ estoque total | 422 |
| Quantidade ≤ **disponível** naquela data/horário | 409 (lista o que faltou e quanto há) |

**Disponível** = `quantidade_total` − soma do que está reservado (status `RESERVADO`) em horários que se sobrepõem, **em qualquer espaço**.
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

### Compatibilidade com bancos de dados

O desenvolvimento usa SQLite, mas o código foi ajustado para rodar igual em **MySQL (5.7+)** e **Postgres**:

- Buscas por texto usam `whereLike(..., caseSensitive: false)`: vira `like` no MySQL/SQLite e `ilike` no Postgres, sem diferenciar maiúsculas em nenhum deles.
- Nomes únicos (espaços, cursos e equipamentos no mesmo espaço) são validados com a regra `NomeUnico`, que compara em minúsculas (`LOWER`). Assim "Quadra" e "quadra" são o mesmo nome em qualquer banco (o MySQL já fazia isso; o Postgres e o SQLite, não).
- Datas são comparadas direto na coluna (sem `whereDate`), o que funciona nos três e usa os índices.
- Não há SQL específico de um banco; as consultas escritas à mão usam só `SUM`, `COUNT` e `GROUP BY`.
- O SQLite ignora `lockForUpdate` (ele trava o banco inteiro na escrita). No MySQL e no Postgres, as travas por linha passam a valer de fato.

**Pendente:** os testes rodam em SQLite em memória. Ao escolher o banco de produção, rodar `php artisan test` também nele (localmente ou em CI). No SQLite, `LOWER` só converte letras sem acento, então "Ética" e "ética" passam como nomes diferentes; no MySQL e no Postgres, não.

---

## 7. Decisões tomadas

| Decisão | Motivo |
|---|---|
| Aluno sem login, só com RA | Simplicidade de uso. Risco: alguém usar o RA de outra pessoa. Mitigação prevista: email com link de cancelamento a cada reserva (item 8) |
| Listagem de alunos só para admin | Evita expor nome, RA e email publicamente |
| Listagem de reservas sem filtro de data mostra **de hoje em diante** | É o que o admin mais consulta; o histórico vem com `de`/`ate` |
| Totais por status **ignoram o filtro de status** | O painel mostra quantas estão ativas, revogadas e canceladas mesmo quando a lista exibe só uma delas |
| Consulta com filtros de reservas em `ReservaRepository` | Começou no service; foi para o repositório quando a exportação passou a reaproveitá-la |
| Exportação em **CSV**, não `.xlsx` | Abre no Excel sem biblioteca nova; `.xlsx` fica para quando alguém pedir |
| Colunas obrigatórias + opcionais na exportação | O arquivo traz só o necessário: menos tamanho e menos consultas (relações só carregam se a coluna for pedida) |
| Auditoria com colunas **e** tabela de histórico | As colunas mostram o último a alterar; o histórico mostra o que mudou em cada alteração (antes/depois) |
| Auditoria por trait nos eventos do Eloquent | Nenhum service precisa lembrar de preencher; vale para qualquer alteração feita pelo model |
| `reservas.user_id` renomeado para `criado_por` | Mesmo nome de coluna em todas as tabelas auditadas |
| Código compatível com SQLite, MySQL e Postgres | O banco de produção ainda não foi escolhido; buscas e nomes únicos se comportam igual nos três |
| Proteção contra fórmulas no CSV | Nome ou descrição começando com `=` poderia virar fórmula no Excel (*CSV injection*) |
| Cursos em **tabela**, não enum | Permitir reaproveitar o sistema em outras instituições da rede |
| Equipamento pertence a um espaço + flag `compartilhavel` | Por padrão a bola da quadra fica restrita à quadra; o microscópio pode ser marcado para uso em salas |
| Disponibilidade **calculada** a partir das reservas, num repositório | Ela muda por data e horário; um número fixo no equipamento não representa isso. Uma tabela de ocupação teria que ser atualizada em cada operação e poderia ficar errada. O repositório concentra a consulta (uma ida ao banco, com índices) |
| Repositórios **só para consultas complexas** | Entidades com operações simples usam o Eloquent direto nos services; repositório onde a query é complexa ou reaproveitada |
| Data da reserva gravada como `Y-m-d` | Permite comparar a coluna diretamente e usar índices (o `whereDate` impede o uso de índice) |
| Espaço exclusivo por horário | Regra atual da quadra. Está isolada em `verificarConflitoDeEspaco()` e pode ser removida se um dia o espaço for compartilhado |
| Reserva guarda `hora_inicio` e `hora_fim`; conflito por **sobreposição** | Preparação para horários livres (ex.: 7h–22h com duração máxima por espaço). A quadra continua com horários fixos |
| Regras de entrada em `app/Rules` (uma linha no request) | Fáceis de ligar e desligar quando a regra mudar |
| Números do negócio em `config/reservas.php` | Mudar 10 dias úteis, 20 minutos ou 2 dias sem mexer no código |
| Antecedência em **dias úteis** | Com dias corridos, quem reservava na sexta só alcançava 5 dias úteis; contando dias úteis, a janela é a mesma qualquer dia da semana. Feriados contam como dia útil na contagem e aparecem no calendário como bloqueados: a janela fica previsível e não muda quando um bloqueio é cadastrado. Prazo de 10 dias úteis |
| Disponibilidade como **calendário** (até 31 dias) | O front navega por semanas/meses; reservas continuam limitadas a 10 dias úteis, e os dias fora da janela vêm com o motivo |
| Consulta mostra **quem reservou** (nome e curso) | Facilita o mapeamento do uso. Por ser pública, não mostra RA nem email (LGPD) |
| Consulta com RA ou curso **não filtra** as reservas | Mostra o espaço inteiro e apenas aponta o limite e a disponibilidade do curso |
| Eventos podem revogar reservas de alunos | Eventos da faculdade têm prioridade; justificativa obrigatória e aluno avisado |
| Reservas não confirmadas serão revogadas automaticamente | Libera horários de quem não vai usar (item 9) |
| Datas bloqueadas em tabela própria | Uma fonte só para feriados (importados pela Yasumi), recesso e manutenção (cadastrados pelo admin) |
| Bloqueios valem só para alunos | Eventos da faculdade podem acontecer em feriados e recessos |
| Bloqueio de parte do dia | Manutenções pontuais sem fechar o dia inteiro; reaproveita a verificação por sobreposição |
| Bloqueio só edita o motivo | Mudar o período levantaria a dúvida de devolver ou não reservas já revogadas; excluir e criar de novo é explícito |
| Sexta-feira Santa importada por padrão | É feriado nacional por lei, embora a Yasumi a classifique como "observância" |
| Campo `data_reserva` (e não `data`) | Com um campo `data`, o Laravel não envolvia a resposta em `{"data": ...}` |
| Update só por PATCH; exclusão = desativação | Atualização parcial e preservação do histórico |

---

## 8. Testes

```bash
php artisan test
```

Os testes ficam em `tests/Feature`, um arquivo por assunto, e usam as factories de `database/factories`. A data dos testes é fixada em **segunda-feira, 09/11/2026, 10:00** (`tests/TestCase.php`), para não dependerem do dia em que rodam. O limite de requisições por minuto fica desligado nos testes.

| Arquivo | O que cobre |
|---|---|
| `AlunoTest` | Cadastro, RA duplicado, curso inativo, listagem só para admin |
| `EspacoTest` | CRUD, PATCH, filtros, bloqueio de desativação com reservas futuras |
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
| `DisponibilidadeTest` | Situações dos horários, quem reservou sem RA/email, bloqueios, motivos do calendário, equipamentos por horário, dados do curso por RA/curso, validações |
| `DisponibilidadeEquipamentoRepositoryTest` | Disponível por data/horário, soma entre espaços, revogadas, sobreposição, maior uso futuro |

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
| Compatibilidade de bancos | Buscas com `whereLike` (ilike no Postgres), regra `NomeUnico`, datas comparadas sem `whereDate` |
| Auditoria | `criado_por`/`atualizado_por` nas tabelas principais, tabela `auditorias` com antes/depois, trait `Auditavel`, campos nas respostas do admin |
| Exportação de reservas | CSV com os filtros da listagem, atalhos de período, colunas opcionais, stream em lotes e proteção contra fórmulas; consulta movida para `ReservaRepository` |
| Item 7 — Listagem de reservas (admin) | Lista paginada com filtros, detalhe, totais por status e dados completos de aluno, espaço, equipamentos, criador e revogador |
| Item 5 — Consulta de disponibilidade | Calendário público por espaço (até 31 dias), situação de cada horário, quem reservou, equipamentos por horário, limite semanal do curso por RA/curso; antecedência passa a 10 dias úteis |

---

## 10. Próximos passos

Na ordem combinada:

1. **Gestão de alunos (admin):** editar e desativar alunos. Desativar em vez de apagar, para manter o histórico de reservas.
2. **Consulta das próprias reservas pelo aluno:** rota pública que exige **RA + email cadastrado** (só o RA não basta: ele não é secreto e revelaria onde e quando a pessoa estará).
3. **Item 8 — Cancelamento pelo aluno:** email na criação da reserva com link assinado de cancelamento.
4. **Item 9 — Confirmação:** comando diário que envia o pedido de confirmação 2 dias antes e revoga automaticamente as não confirmadas; scheduler (que também roda o `sanctum:prune-expired` e importa os feriados do ano seguinte).
5. **Item 10 — Admins:** `UserResource` no `/me`; um admin cadastra outros admins (convite por email para definir a senha).
6. **Recuperação de senha do admin:** link por email para redefinir a senha.
7. **Horários livres:** configuração por espaço (`modo_horario` FIXO/LIVRE, janela de funcionamento, duração máxima, blocos de 30 min) e cálculo de equipamentos pelo **pico de uso** no intervalo.
8. **Front-end em Angular:** projeto separado consumindo esta API (não será feito com Blade).

### Emails do sistema

| Email | Para quem | Quando | Situação |
|---|---|---|---|
| Reserva revogada | Aluno | Admin revoga, evento ocupa o horário ou data é bloqueada | ✅ Pronto |
| Reserva criada (com link de cancelamento) | Aluno | Ao reservar | Item 8 |
| Reserva cancelada | Aluno | Ao cancelar pelo link | Item 8 |
| Pedido de confirmação | Aluno | 2 dias antes da reserva | Item 9 |
| Reserva revogada por falta de confirmação | Aluno | Prazo de confirmação vencido (reaproveita o email de revogação) | Item 9 |
| Convite de novo admin | Admin novo | Admin cadastra outro admin | Item 10 |
| Recuperação de senha | Admin | Pedido de "esqueci a senha" | Passo 9 |

Todos vão pela fila (`php artisan queue:work`). Em desenvolvimento ficam no log (`MAIL_MAILER=log`); em produção é preciso configurar um servidor SMTP ou um serviço de envio (ex.: Amazon SES, Mailgun, Resend).

### Implementação final: API para terceiros autorizados

Permitir que sistemas de terceiros autorizados (ex.: portal da faculdade, app das atléticas, outras unidades da rede) consumam a API.

Pontos a decidir quando chegar a hora:

- **Autenticação de sistemas:** tokens do Sanctum com *abilities* por cliente (ex.: `reservas:ler`, `reservas:criar`) ou OAuth2 *client credentials* (Laravel Passport), se os parceiros exigirem um padrão.
- **Cadastro de clientes:** tabela de clientes de API, gerenciada pelos admins, com permissões e possibilidade de revogação.
- **Versionamento:** rotas em `/api/v1` para não quebrar os parceiros quando a API mudar.
- **Limites:** *rate limit* por cliente.
- **Documentação:** especificação OpenAPI (Swagger) gerada a partir das rotas.
- **Auditoria:** registrar qual cliente criou ou alterou cada reserva.
