# Projeto: sistema de reservas

Antes de qualquer tarefa, leia este tópico inteiro e o [`CHECKLIST.md`](CHECKLIST.md), que tem o estado do sistema, as decisões já tomadas e o plano até a finalização. As regras abaixo foram pedidas pelo usuário e valem para **todas** as conversas.

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
  - `DOCUMENTACAO.md`: endpoints, regras, decisões, testes, histórico e próximos passos;
  - `DATABASE.md`: diagrama Mermaid e decisões de modelagem, sempre que o banco mudar;
  - `implementacoes-com-claude-code.txt`: uma seção nova por implementação, arquivo por arquivo.
- Decisões tomadas não são reabertas. Ficam registradas no `CHECKLIST.md` e no `DOCUMENTACAO.md`.

### Continuidade entre conversas (o usuário alterna entre PCs e chats)
- A memória da conversa **não** vai para outro PC. O que garante a continuidade são os arquivos do repositório: **este `CLAUDE.md`** (como trabalhar) e o **`CHECKLIST.md`** (onde estamos, o que foi decidido e o que vem). Escreva-os como instruções **para você mesmo** numa conversa nova, com contexto suficiente para seguir a mesma linha de ideias, sem precisar perguntar o que já foi combinado.
- **Mantenha tudo atualizado o tempo todo**, e não só ao concluir uma etapa. Atualize e commite os guias sempre que:
  - enviar uma **proposta**: resumo e decisões em aberto no passo do plano, e "Onde paramos" como "proposta enviada, aguardando aprovação";
  - o usuário **aprovar, recusar ou ajustar** algo: registrar a decisão e o motivo;
  - **explicar um conceito** ao usuário: anotar em "Conceitos já explicados" e, se for útil, numa seção do DOCUMENTACAO;
  - concluir uma etapa: todos os guias (CHECKLIST, DOCUMENTACAO, DATABASE se mexer no banco, txt).
- A seção **"🧵 Trilha de ideias"** do CHECKLIST ganha uma linha a cada nova ideia, decisão ou mudança de rumo, com o porquê. A última linha ("Agora:") é sempre atualizada.
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
- Configuração nova que depende do ambiente vai para `config/*.php` lendo o `.env`, e entra no `.env.example`, no `.env.production.example` e na tabela de variáveis do DOCUMENTACAO. Nunca usar `env()` fora de `config/`, porque o `config:cache` de produção o ignora.
- O campo de data da reserva se chama `data_reserva`, nunca `data`, porque um campo `data` faria o resource perder o wrapper `{"data": ...}`.
- O **front-end será em Angular**, num projeto separado (proposta: pasta `frontend/` neste repositório, a confirmar no passo 11). **Não criar telas em Blade.** Links de email apontam para `FRONTEND_URL`.
- **No front valem as mesmas regras:** nenhum comentário (TS, HTML, CSS), nomes em português, um commit por alteração, testes antes de cada commit, proposta antes de cada subpasso, desempenho e LGPD em toda decisão. A identidade visual aprovada está no DOCUMENTACAO ("Front-end: identidade visual").

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
- Coletar e expor só o necessário. **Rotas públicas nunca mostram RA nem email.**
- Consultar dados pessoais exige mais de um identificador: **RA + email**.
- Dados pessoais nunca vão em URL (usar POST) nem em log.
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
