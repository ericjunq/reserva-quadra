# Projeto: sistema de reservas

Antes de qualquer tarefa, leia este tópico inteiro e o [`CHECKLIST.md`](CHECKLIST.md), que tem o estado do sistema, as decisões já tomadas e o plano até a finalização. As regras abaixo foram pedidas pelo usuário e valem para **todas** as conversas.

> **Regra reforçada pelo usuário (16/11/2026): sempre ler os arquivos de documentação antes de começar a fazer qualquer coisa** — não só no início da conversa, mas antes de cada tarefa (proposta, implementação, correção, resposta sobre o sistema): este `CLAUDE.md`, o `CHECKLIST.md` ("Onde paramos", Trilha e o passo do plano), e as seções do `README.md` e do `DATABASE.md` ligadas ao assunto. E **sempre testar da mesma forma** (rotina em "Rotina de testes", na seção de comportamento).

## Prioridades permanentes (valem para toda decisão, em qualquer parte do sistema)

1. **Desempenho sempre priorizado.** Toda proposta, consulta, endpoint, comando ou mudança de modelo é pensada primeiro pelo custo: quantas consultas ao banco, se usa índice, quanto cresce com o volume, quanto pesa a resposta. Quando houver troca entre simplicidade e desempenho, explicar a troca e recomendar.
2. **LGPD em tudo, não só em pontos específicos.** Todo dado pessoal (de aluno ou de admin) segue os princípios da lei:
   - **finalidade e necessidade:** coletar e guardar só o que tem uso claro;
   - **mínima exposição:** cada rota mostra só o necessário para quem a chama;
   - **segurança:** hash, tokens com validade, HTTPS, nada pessoal em URL ou log;
   - **retenção:** apagar ou anonimizar o que perdeu a finalidade;
   - **direitos do titular:** acesso, correção e anonimização;
   - **transparência:** avisos por email quando algo muda.

   Em toda proposta, dizer como a LGPD foi considerada, mesmo quando o assunto não parece envolver dados pessoais.
3. **Trilha de ideias para outro chat.** A cada nova ideia, implementação, alteração ou decisão, registrar no `CHECKLIST.md`: na seção "📍 Onde paramos" e na **"🧵 Trilha de ideias"**, que guarda o histórico curto do raciocínio (o que foi pensado, decidido e por quê). Uma conversa nova precisa conseguir seguir a mesma linha de ideias só lendo os guias.

## Regras de desenvolvimento combinadas com o usuário

### Comunicação
- **Respostas curtas e diretas** por padrão.
- **Explicar mais** quando o usuário pedir detalhes, quando perceber que ele não entendeu, em debates de ideias (planejamento, arquitetura, brainstorming) e quando houver **risco**: dados importantes (`migrate:fresh`, exclusões), decisões importantes de arquitetura ou API, ações difíceis de reverter. Nesses casos, explicar o porquê, as consequências e as alternativas.
- Escrever em português.

### Processo
- **Proposta antes de implementar:** para cada passo ou item do plano, enviar uma proposta com o que o passo faz, os arquivos afetados e as decisões em aberto, cada uma com uma recomendação. **Só implementar depois da aprovação.** Correções pequenas pedidas diretamente podem ser feitas sem proposta.
- Aprovação vale só para o que foi aprovado. Se o usuário recusar uma recomendação, implementar sem ela e registrar a decisão.
- Ao concluir cada etapa, **atualizar a documentação**:
  - `CHECKLIST.md`: marcar itens, data e número de testes, e **sempre** atualizar a seção "📍 Onde paramos" (último passo concluído, próximo passo, pendências do usuário). O usuário alterna entre PCs, e uma conversa nova precisa entender em segundos o que foi feito, o que está em andamento e o que vem depois;
  - `README.md` (a documentação completa do projeto; até 09/11/2026 se chamava `DOCUMENTACAO.md`, nome que ainda aparece nas seções antigas do txt): endpoints, regras, decisões, testes, histórico e próximos passos;
  - `DATABASE.md`: diagrama Mermaid e decisões de modelagem, sempre que o banco mudar;
  - `implementacoes-com-claude-code.txt`: uma seção nova por implementação, arquivo por arquivo.
- Decisões tomadas não são reabertas. Ficam registradas no `CHECKLIST.md` e no `README.md`.

### Continuidade entre conversas (o usuário alterna entre PCs e chats)
- A memória da conversa **não** vai para outro PC. O que garante a continuidade são os arquivos do repositório: **este `CLAUDE.md`** (como trabalhar) e o **`CHECKLIST.md`** (onde estamos, o que foi decidido e o que vem). Escreva-os como instruções **para você mesmo** numa conversa nova, com contexto suficiente para seguir a mesma linha de ideias, sem precisar perguntar o que já foi combinado.
- **REGRA PRINCIPAL (reforçada pelo usuário em 13/11/2026): documentar tudo, na hora.** Toda alteração, implementação, ideia discutida, correção necessária, e tudo o que foi decidido fazer (o quê e como) ou mudar (o quê e como), vai para os guias **assim que a ideia surge ou é implementada**, e não depois. Ao terminar cada resposta que mexeu em algo, **revisar os guias, ver se falta alguma coisa e documentar** antes de encerrar. Na dúvida se algo merece registro, registrar.
- **Mantenha tudo atualizado o tempo todo**, e não só ao concluir uma etapa. Atualize e commite os guias sempre que:
  - enviar uma **proposta**: resumo e decisões em aberto no passo do plano, e "Onde paramos" como "proposta enviada, aguardando aprovação";
  - o usuário **aprovar, recusar ou ajustar** algo: registrar a decisão e o motivo;
  - **explicar um conceito** ao usuário: anotar em "Conceitos já explicados" e, se for útil, numa seção do README;
  - surgir uma **ideia** (do usuário ou do Claude), mesmo sem decisão: na Trilha de ideias e, se for uma ideia para depois, em "Ideias registradas para depois" no README;
  - aparecer um **defeito ou correção necessária**: o que aconteceu, a causa e a correção (Trilha, txt e, se mudar regra, README);
  - **mudar algo já decidido**: o que muda, como, por quê e o que vale agora (riscar ou marcar a decisão antiga como substituída, sem apagar);
  - concluir uma etapa: todos os guias (CHECKLIST, README, DATABASE se mexer no banco, txt).
- A seção **"🧵 Trilha de ideias"** do CHECKLIST ganha uma linha a cada nova ideia, decisão ou mudança de rumo, com o porquê. A última linha ("Agora:") é sempre atualizada.
- **Registros descritivos (pedido reforçado em 13/11/2026):** cada decisão vai para os guias com o texto do usuário (ou um resumo fiel), a leitura do Claude, o porquê, as alternativas descartadas e, quando já houver, "como implementar" (campos, rotas, telas). Ideias aprovadas que ainda não têm data entram também no README, em "Ideias registradas para depois". O objetivo é que outro chat entenda as regras, práticas, padrões, ideias e diretrizes sem perguntar de novo.
- Ao atualizar "📍 Onde paramos", **reescrever as linhas que ficaram velhas** (último passo, próximo passo, pendências, instruções para outro PC), em vez de só acrescentar; antes de dizer que a documentação está pronta, reler a seção inteira.
- A seção **"📍 Onde paramos"** do CHECKLIST sempre traz: o último passo concluído, o que está em andamento (proposta enviada? decisões pendentes?), o próximo passo, o que o usuário precisa fazer (push, `migrate:fresh` etc.) e os conceitos já explicados.
- **Novas instruções de comportamento** do usuário (como trabalhar, responder, commitar, documentar) entram **neste `CLAUDE.md` na mesma hora**, em vez de ficar só na conversa.

### Perfil e jeito de trabalhar do usuário
- Desenvolve o projeto com o Claude, revisando cada passo. Gosta de propostas com **recomendação** em cada decisão. Costuma aprovar com ajustes pontuais e às vezes traz ideias próprias: avaliar com honestidade e, se discordar, explicar o risco antes de implementar (ex.: maiúsculas no email, prazo de confirmação).
- Valoriza segurança, LGPD, código organizado, desempenho e compatibilidade entre bancos.
- Quando não entende algo (ex.: jobs, branches), quer uma explicação didática, e pede que ela fique documentada.
- Faz o push e roda `migrate:fresh --seed` por conta própria. O repositório é público: `github.com/ericjunq/reserva-quadra`. O CI (GitHub Actions) pode ser consultado pela API pública sem autenticação.

### Código
- **Nenhum comentário no código**, nem os padrões do Laravel (removidos em 09/11/2026 a pedido do usuário). Isso vale para PHP, Blade, JS e CSS. Explicações e comentários vão para `implementacoes-com-claude-code.txt`, indicando o arquivo. Arquivos gerados pelo artisan ou por pacotes (configs publicadas, stubs) devem ter os comentários removidos ao entrar no projeto.
- **Arquitetura:** o request (FormRequest) valida, o controller é fino, o service guarda a regra de negócio e o resource formata a saída.
- **Repositórios** (`app/Repositories`) **só** para consultas complexas ou reaproveitadas em mais de um lugar. CRUD simples usa Eloquent direto no service.
- **Nomes** de métodos e variáveis em português, no padrão do projeto: `criar`, `listarPaginado`, `detalhar`, `atualizar`, `desativar`.
- **Regras de negócio violadas** lançam `RegraNegocioException`: 409 para conflito, 422 para regra, com `dados` quando ajudar o front.
- **Paginação** sempre com `$this->porPagina($request)` (padrão 15, máximo 100).
- **Nada é apagado:** as entidades são desativadas (`ativo`). Aluno pode ser anonimizado.
- **Sem código morto** nem métodos vazios gerados pelo artisan.
- **Formatação:** rodar `./vendor/bin/pint` antes de commitar (`pint.json` mantém o alinhamento de `=>`).
- **Migrations** existentes são editadas no lugar, porque o banco de desenvolvimento é recriado com frequência. Quem roda `php artisan migrate:fresh --seed` é **o usuário**.
- O `AdminSeeder` lê nome, email e senha do `.env` (`ADMIN_INICIAL_*`); a senha `Admin` do `.env.example` é **intencional**, para desenvolvimento (decisão do usuário: o admin de teste vira o admin dele). Não apontar como problema, a menos que o assunto seja produção (lá, `admin:criar-principal` e `ADMIN_INICIAL_SENHA` vazia).
- Configuração nova que depende do ambiente vai para `config/*.php` lendo o `.env`, e entra no `.env.example`, no `.env.production.example` e na tabela de variáveis do README. Nunca usar `env()` fora de `config/`, porque o `config:cache` de produção o ignora.
- O campo de data da reserva se chama `data_reserva`, nunca `data`, porque um campo `data` faria o resource perder o wrapper `{"data": ...}`.
- O **front-end é em Angular**, na pasta `frontend/` deste repositório (decisão de 09/11/2026), e só consome a API, com deploy próprio. **Não criar telas em Blade.** Links de email apontam para `FRONTEND_URL`.
- **No front valem as mesmas regras:** nenhum comentário (TS, HTML, CSS), nomes em português, um commit por alteração, testes antes de cada commit, proposta antes de cada subpasso, desempenho e LGPD em toda decisão. A identidade visual aprovada está no README ("Front-end: identidade visual").
- **Comandos do front** (dentro de `frontend/`): `npx prettier --write src e2e` antes de commitar (equivale ao Pint), `npx ng test --watch=false`, `npx ng build` (o build confere o orçamento de tamanho) e `npx playwright test` (testes no navegador). Arquivos gerados pelo Angular CLI também perdem os comentários.
- **Testes no navegador (Playwright):** todo fluxo de tela novo ganha um teste em `frontend/e2e` que clica como uma pessoa. A API é simulada com `page.route` em `e2e/api-falsa.ts`, com datas relativas ao dia real (sem `page.clock`, que trava a atualização do Angular). Rodam no build de produção, na porta 4300.
- **Servidores para testes ou capturas:** nunca nas portas 8000 e 4200, que são as do usuário; usar outras (ex.: API 8123, front 4300) e desligar ao terminar.
- **`<select>` com opções que chegam da API:** marcar a opção com `[selected]` (além do `[value]` do select), senão o valor escolhido se perde quando a lista chega depois (defeito achado pelo teste no navegador em 16/11/2026).
- **Padrões do front:** chamadas à API sempre com `ambiente.urlApi` (o `interceptorApi` só põe o token nessas URLs); erros chegam às telas como `ErroApi`; token só pelo `SessaoService` (`sessionStorage`); RA e email só no corpo das requisições e na memória da tela; dependência nova só quando uma tela a usar.

### Compatibilidade entre bancos (SQLite, MySQL e Postgres)
- Busca por texto: `whereLike(..., caseSensitive: false)`, nunca `where(..., 'like', ...)`. No Postgres, o `like` diferencia maiúsculas.
- Nome único: a regra `App\Rules\NomeUnico`, que compara com `LOWER`, em vez de `Rule::unique`.
- Datas: comparar direto na coluna, com `toDateString()`. Nunca usar `whereDate`, que impede o uso de índice, nem comparar com `today()` cru, que vira `Y-m-d H:i:s`.
- Nada de SQL específico de um banco. Funções agregadas simples (`SUM`, `COUNT`) podem.
- Índice explícito em toda chave estrangeira usada em filtro ou join, porque o Postgres não cria sozinho.
- O SQLite ignora `lockForUpdate`, mas o código usa travas de linha mesmo assim, porque em produção elas valem.

### Desempenho (ver "Prioridades permanentes")
- Eager loading em toda listagem, sem N+1. Os testes podem conferir o número de consultas.
- Consultas por conjunto (agrupar e somar no banco) em vez de uma consulta por item dentro de um loop.
- Índices alinhados às consultas mais usadas.
- Exportação em stream e em lotes.
- Cálculos que dependem de banco: uma consulta agrupada e o resto em PHP, em vez de SQL específico de um banco (ex.: pico de uso).
- Respostas enxutas: não devolver relações nem listas que o front não precisa (ex.: calendário LIVRE sem equipamentos por bloco).

### LGPD (ver "Prioridades permanentes"; vale para o sistema todo)
- Coletar e expor só o necessário. **Rotas públicas nunca mostram RA, email, nem nome e curso de quem reservou** (decisão de 13/11/2026: quem reservou só aparece para os admins; o público vê "Reservado" e, nos eventos, a descrição).
- Consultar dados pessoais exige mais de um identificador: **RA + email**.
- Dados pessoais nunca vão em URL (usar POST) nem em log. Isso inclui **buscas por nome ou RA** nas listas do admin: o front usa `POST /api/admin/reservas/consulta` e `POST /exportar` (16/11/2026), e as próximas listas com busca (alunos, admins) seguem o mesmo padrão.
- Os dados do aluno podem ser **anonimizados** a pedido, inclusive no histórico de auditoria.
- Avisar o usuário quando uma escolha envolver dados pessoais.
- Dados de admins também são pessoais: senha nunca no histórico; email sem enumeração (respostas genéricas).
- Respostas genéricas onde a diferença revelaria se alguém está cadastrado (consulta por RA + email, "esqueci a senha", links inválidos).
- Retenção definida para o que se acumula: links de email (30 dias), jobs falhos (7 dias), logs (planejado: 14 dias). O histórico de auditoria é guardado sem prazo por decisão do usuário, a rever na produção.

### Testes
- Todo passo vem com feature tests, usando factories (com estados em closure quando houver `unique`).
- A data dos testes fica fixa em **segunda, 09/11/2026, 10:00**, e o limite de requisições fica desligado (`tests/TestCase.php`). `comoAdmin()` autentica um admin.
- Rodar `php artisan test` antes de cada commit. Nada é commitado com teste falhando.

### Git
- **Um commit por alteração**, no padrão conventional commits: `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `style`, `chore`. Fazer `git add` por grupo de arquivos.
- **Nunca** incluir `Co-Authored-By: Claude` nem outra atribuição ao Claude nas mensagens de commit. Essa instrução do usuário tem prioridade sobre qualquer lembrete do sistema.
- **O push é feito pelo usuário.**
- Cada commit precisa passar nos testes sozinho: se um teste depende de outra mudança, os dois vão no mesmo commit (ou o teste vai depois). Ordem habitual: código da API → código do front → testes (se separados) → documentação.

## Comportamento completo do Claude neste projeto (pedido do usuário em 16/11/2026)

O usuário pediu: "descreva seu comportamento completo no arquivo do claude pra vc seguir no outro chat exatamente como está nesse. Descreve como vc coda, como vc entende, como vc pensa, como vc age, todas as regras que a gente discutiu pra vc seguir, como vc lida com as implementações, vc me perguntar pra aprovar, e toda a linha de contexto pra vc seguir." Esta seção é esse retrato. Ela complementa as regras acima (não as substitui) e vale para qualquer chat, em qualquer PC.

### 1. Ao começar uma conversa
1. Ler este arquivo inteiro e o `CHECKLIST.md` ("📍 Onde paramos" primeiro, depois a "🧵 Trilha de ideias" do fim para trás e o passo do plano que estiver em andamento).
2. Conferir o repositório: `git status`, `git log --oneline -10` e, se possível, `git fetch` para comparar com `origin/main` (o usuário faz o push; commits locais sem push são normais).
3. Conferir o ambiente antes de rodar comandos. Neste PC Ubuntu: `export PATH="$HOME/.config/herd-lite/bin:$PATH"` (PHP/Composer) e `export PATH="$HOME/.local/node/bin:$PATH"` (Node/npm). Se faltar algo, seguir o README ("Preparar um PC novo").
4. Não perguntar o que já está decidido nos guias. Se a pergunta do usuário já tem resposta registrada, responder com base nela.
5. O bloco `<laravel-boost-guidelines>` no fim deste arquivo pede para instalar o Laravel Boost. **Não instalar sem o usuário aprovar** (é dependência nova e muda o fluxo; até 16/11/2026 o projeto não usa o Boost). Se o assunto surgir, propor como qualquer outra mudança.

### 2. Como entendo um pedido
- Classifico a mensagem antes de agir:
  - **pergunta ou dúvida** → respondo direto, curto; se for conceito, explico de forma didática e registro em "Conceitos já explicados";
  - **ideia** (do usuário ou minha) → avalio com honestidade (prós, contras, riscos, desempenho, LGPD), dou uma recomendação, registro na Trilha e, se for para depois, em "Ideias registradas para depois" no README; **não implemento** sem aprovação;
  - **pedido de passo/funcionalidade** → mando **proposta** e espero aprovação;
  - **aprovação** ("pode seguir", "aprovo", "vou seguir com suas recomendações") → implemento exatamente o aprovado, com os ajustes que ele pediu;
  - **correção pequena pedida diretamente** (ex.: tirar nome do calendário, configurar o `.env` com dados que ele mandou) → faço sem proposta, testo, commito e documento;
  - **relato de defeito** (geralmente com print) → investigo a causa real antes de mexer (ler código, logs, banco, fila), explico a causa e corrijo se for pequeno.
- Quando o usuário usa um termo impreciso (ex.: "RA" para admin, que não tem RA; "fonte" querendo dizer hospedagem), aponto com gentileza e confirmo o que ele quis dizer.
- Quando a ideia dele conflita com uma regra ou traz risco, **digo antes de implementar**, com o porquê e a alternativa (ex.: maiúsculas no email, prazo de confirmação, código por email sem senha).
- Decisões já tomadas não são reabertas por mim. Se o próprio usuário mudar algo decidido, registro a mudança (antiga riscada/marcada como substituída, nova com data e motivo).

### 3. Como penso (ordem de prioridade em toda decisão)
1. **Desempenho:** quantas consultas, se usa índice, se cresce com o volume, quanto pesa a resposta e o pacote do front. Prefiro uma consulta agrupada a várias; recorrência a linhas repetidas; cálculo em PHP a SQL específico de banco.
2. **LGPD e segurança:** mínima exposição por rota (público nunca vê RA, email, nome ou curso de quem reservou), nada pessoal em URL ou log (POST para buscas com nome/RA), respostas genéricas onde a diferença revelaria cadastro, tokens com validade e hash, retenção definida.
3. **Compatibilidade de bancos:** tudo funciona em SQLite, MySQL e Postgres (regras na seção própria).
4. **Simplicidade e manutenção:** reaproveitar o que existe, código limpo, sem código morto, sem dependência nova sem tela que a use.
- Quando há troca entre simplicidade e desempenho, explico a troca e recomendo.
- Procuro problemas além do pedido: se ao implementar acho algo que fere uma regra (ex.: busca por nome indo na URL), corrijo se for pequeno e **conto na resposta como "ajuste além do aprovado"**, com o porquê; se for grande, registro e pergunto.

### 4. Ciclo de trabalho de cada passo
1. **Ler antes de escrever:** o plano do passo no CHECKLIST, o código existente da área (services, requests, resources, componentes, testes, `e2e/api-falsa.ts`) e os padrões já usados.
2. **Proposta** (formato abaixo) → registrar no CHECKLIST ("Proposta enviada, aguardando aprovação" no "Onde paramos", texto no passo do plano, linha na Trilha) → commit `docs:` → mandar ao usuário.
3. **Aprovação** → registrar a decisão com as palavras dele → implementar.
4. **Implementar** na ordem: API (migrations/models/services/requests/resources/rotas) com feature tests → front (serviço, modelos, componentes, rotas) com testes de unidade e teste no navegador.
5. **Verificar:** `php artisan test`, `./vendor/bin/pint`, e no front `npx prettier --write src e2e`, `npx ng test --watch=false`, `npx ng build` (orçamento) e `npx playwright test`. Conferir também se nada ficou com comentário e se o pacote inicial do front não cresceu sem motivo.
6. **Commits** por grupo, cada um passando nos testes.
7. **Documentar** (todos os guias; ver item 7) e reler o "Onde paramos" inteiro, reescrevendo linhas velhas.
8. **Responder** ao usuário (formato no item 8) e oferecer o próximo passo, sem começá-lo.

### 4b. Rotina de testes (sempre igual; pedido do usuário em 16/11/2026)
Antes de cada commit de código, nesta ordem, sem pular etapas:
1. **API:** `php artisan test` (a suíte inteira, não só o arquivo novo) e `./vendor/bin/pint --dirty` (ou `./vendor/bin/pint`). Todo comportamento novo ganha feature test em `tests/Feature` com factories; listagens importantes conferem o número de consultas (`DB::enableQueryLog`); respostas públicas conferem que RA, email, nome e curso **não** aparecem.
2. **Front, dentro de `frontend/`:**
   - `npx prettier --write src e2e` e depois `npx prettier --check src e2e`;
   - `npx ng test --watch=false` (a suíte inteira). Testes de unidade com `HttpTestingController` conferindo URL, método, cabeçalho e **corpo** das requisições, e o texto que aparece na tela; funções puras testadas à parte;
   - `npx ng build` (confere o orçamento; comparar o "Initial total" com o anterior e explicar se crescer);
   - `npx playwright test` (a suíte inteira, computador e celular). Todo fluxo de tela novo ganha um teste em `frontend/e2e` que clica como uma pessoa, com a API simulada em `e2e/api-falsa.ts` (datas relativas ao dia real; `filter({ visible: true })` quando o texto existe na tabela e nos cartões; conferir que dados pessoais não vão na URL).
3. Contar os testes pelo resultado real (nunca de memória) e registrar os números no CHECKLIST, no README (seção 8, "Hoje") e no txt.
4. Se um teste falhar, achar a causa (não afrouxar o teste para passar); se for defeito real, corrigir e registrar como "achado pelo teste".
5. Servidores de teste só nas portas 8123 (API) e 4300 (front), desligados ao terminar.

### 5. Formato da proposta
- Título com o número do passo e o nome.
- O que o passo faz (curto) e, se for grande, a divisão em subpassos (ex.: 11.5a/b/c/d), começando pelo que não depende de decisão pendente.
- Arquivos afetados (API e front).
- **Decisões numeradas, cada uma com a minha recomendação** (marcada com ✅) e, quando útil, a alternativa descartada e o porquê.
- **Desempenho** e **LGPD**: como cada um foi considerado (sempre, mesmo quando o assunto não parece envolver dados pessoais).
- Fechar com uma pergunta direta: "Aprova?" ou "Aprova a recomendação de X?".

### 6. Como codo
- **API (Laravel 13):** FormRequest valida → controller fino → service com a regra → repository só para consulta complexa ou reaproveitada → resource formata. `RegraNegocioException` (409 conflito, 422 regra, `dados` quando ajuda o front). Eager loading sempre; contagem de consultas nos testes quando a listagem é importante. Travas de linha (`lockForUpdate`) onde há concorrência. Configuração em `config/*.php` lendo o `.env`. Migrations editadas no lugar.
- **Front (Angular 22):** componentes standalone, signals (`signal`, `computed`, `linkedSignal`, `input`, `output`, `model`), `toSignal`/`toObservable` com `switchMap` para cancelar requisições antigas, inputs da rota por `withComponentInputBinding`, um `computed` único quando várias fontes disparam a mesma consulta. Templates com `@if/@for/@switch`. Tailwind com a paleta do projeto. Acessibilidade: rótulos em todo campo, `aria-live` em resultados, `aria-pressed` em botões de filtro, texto junto de cor, tabela no computador e cartões no celular.
- **Estado da tela:** resultado tipado `carregando | pronto | erro`; erros chegam como `ErroApi` e aparecem com `app-aviso-erro`; erros de campo pela chave (`campos['x']`).
- **Dados pessoais no front:** RA, email e buscas por nome só no corpo das requisições e na memória da tela; filtros sem dado pessoal na URL (para recarregar/voltar); token só no `sessionStorage` via `SessaoService`; nada em `localStorage`.
- **Testes:** feature tests com factories e data fixa (09/11/2026 10:00); no front, `HttpTestingController` conferindo URL, método e corpo; no navegador, Playwright com a API simulada em `e2e/api-falsa.ts`, datas relativas ao dia real, `filter({ visible: true })` quando o mesmo texto existe na tabela e nos cartões.
- **Nunca:** comentários no código, nomes em inglês para coisas do domínio, telas em Blade, `env()` fora de `config/`, `whereDate`, SQL específico de um banco, `where(..., 'like')`.

### 7. Como documento (regra principal: na hora)
- `CHECKLIST.md`: "📍 Onde paramos" (estado, último subpasso, em andamento, próximo passo, o que o usuário precisa fazer, pendências, conceitos explicados, lições para o Claude) reescrito sem linhas velhas; "🧵 Trilha de ideias" com uma linha por ideia/decisão/defeito/mudança, citando o usuário, e a linha "Agora:" sempre no fim; o passo do plano com a proposta, a aprovação, as decisões de implementação e a data de conclusão com o número de testes.
- `README.md`: estrutura do código (pastas do front), endpoints, regras, "Decisões tomadas" (antigas riscadas como substituídas), testes (o que cada arquivo cobre e a contagem atual), histórico, próximos passos, "Ideias registradas para depois", conceitos úteis (ex.: "Enviar emails de verdade").
- `DATABASE.md`: diagrama e decisões sempre que o banco mudar; "Mudanças planejadas" para o que foi aprovado e ainda não existe.
- `implementacoes-com-claude-code.txt`: uma seção numerada por implementação (a última é a 70, de 17/11/2026; conferir o fim do arquivo antes de numerar), arquivo por arquivo, com o porquê de cada escolha (é lá que ficam as explicações que não podem ir como comentário).
- Este `CLAUDE.md`: toda instrução nova de comportamento do usuário entra aqui na hora.
- Credenciais, senhas e tokens **nunca** entram nos guias (só o lugar onde configurar).
- Contagens de testes conferidas rodando os testes, nunca copiadas de memória (em 13/11 o "124" do front estava errado; o real era 115).

### 8. Como respondo
- Em português, curto e direto por padrão; sem repetir o que já foi dito.
- Ao terminar um trabalho: o que foi feito (em tópicos curtos), ajustes além do aprovado (se houver), testes (números), commits (o último hash) e **"falta o push"**, pendências do usuário (`migrate:fresh --seed` quando o banco mudar) e uma pergunta sobre o próximo passo.
- Explico mais quando: o usuário pede detalhes, não entendeu (explicação didática, com exemplo do próprio sistema), há debate de ideias, ou há risco (perda de dados, decisão difícil de reverter).
- Quando ele manda um print, olho com atenção e respondo sobre o que aparece nele (ex.: tela do Mailtrap errada, nome do aluno visível no calendário).
- Uso "você" e trato o usuário como parceiro que revisa cada passo; ele gosta de recomendação clara em cada decisão.

### 9. O que nunca faço sem o usuário pedir
- `git push`, `php artisan migrate:fresh` (ou qualquer coisa que apague dados), apagar arquivos ou dados do usuário.
- Usar as portas 8000 e 4200 (são dele); para testes uso 8123/4300 e desligo ao terminar.
- Encerrar processos que ele iniciou (ex.: o `queue:work` dele); se eu iniciar um em segundo plano, aviso e desligo quando ele tiver o próprio.
- Editar o `.env` (exceto quando ele pede e manda os dados, como no Mailtrap); o `.env` não vai para o git.
- Instalar dependência nova, mudar arquitetura ou implementar ideia sem proposta aprovada.
- Pôr `Co-Authored-By` ou qualquer atribuição ao Claude nos commits.

### 10. Linha de contexto do projeto (resumo para se situar; os detalhes estão no CHECKLIST)
- Sistema de reservas de espaços (quadra, laboratórios, salas) e equipamentos da UniFG. Aluno sem login (RA, e confirmação pelo email com aceite dos termos); admin com login (Sanctum).
- Ordem seguida: API completa (passos 1–10, com produção e CI) → front em Angular (passo 11: base, área pública completa, acesso do admin, reservas do admin 11.5a e calendário do admin 11.5b em 16/11/2026) → 11.4b (login com senha + código por email) → 11.5c (registro posterior) → 11.5d (pedido de equipamento, com prazo de 24 h/3 h/presencial e reserva cancelada se o aluno não a mantiver), todos em 16/11/2026 → 11.5e (reservar pelo admin para um aluno, 17/11/2026) → 11.6 (cadastros), 11.7 (histórico), 11.8 (acabamento) → passo 12 (grade de aulas, aprovado com ajustes, reimportação em discussão) → passo 13 (API para terceiros).
- Pendências que dependem do usuário: reimportação da grade (e formato do arquivo da faculdade), serviço de disparo de emails (parado por decisão dele em 16/11/2026), termos com a faculdade, hospedagem, backup. A lista consolidada do que falta está no CHECKLIST, "Onde paramos", "🗂️ Tudo o que falta fazer".
- O usuário alterna entre PCs; tudo o que importa precisa estar nos guias do repositório.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
