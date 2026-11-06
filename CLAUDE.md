# Projeto: sistema de reservas

Antes de qualquer tarefa, leia este tópico inteiro e o [`CHECKLIST.md`](CHECKLIST.md), que tem o estado do sistema, as decisões já tomadas e o plano até a finalização. As regras abaixo foram pedidas pelo usuário e valem para **todas** as conversas.

## Regras de desenvolvimento combinadas com o usuário

### Comunicação
- **Respostas curtas e diretas** por padrão.
- **Explicar mais** quando o usuário pedir detalhes, quando perceber que ele não entendeu, em debates de ideias (planejamento, arquitetura, brainstorming) e quando houver **risco**: dados importantes (`migrate:fresh`, exclusões), decisões importantes de arquitetura ou API, ações difíceis de reverter. Nesses casos, explicar o porquê, as consequências e as alternativas.
- Escrever em português.

### Processo
- **Proposta antes de implementar:** para cada passo ou item do plano, enviar uma proposta com o que o passo faz, os arquivos afetados e as decisões em aberto, cada uma com uma recomendação. **Só implementar depois da aprovação.** Correções pequenas pedidas diretamente podem ser feitas sem proposta.
- Aprovação vale só para o que foi aprovado. Se o usuário recusar uma recomendação, implementar sem ela e registrar a decisão.
- Ao concluir cada etapa, **atualizar a documentação**:
  - `CHECKLIST.md`: marcar itens, data e número de testes;
  - `DOCUMENTACAO.md`: endpoints, regras, decisões, testes, histórico e próximos passos;
  - `DATABASE.md`: diagrama Mermaid e decisões de modelagem, sempre que o banco mudar;
  - `implementacoes-com-claude-code.txt`: uma seção nova por implementação, arquivo por arquivo.
- Decisões tomadas não são reabertas. Ficam registradas no `CHECKLIST.md` e no `DOCUMENTACAO.md`.

### Código
- **Sem comentários no código.** Explicações e comentários vão para `implementacoes-com-claude-code.txt`, indicando o arquivo. Os docblocks padrão do Laravel que já existem podem ficar.
- **Arquitetura:** o request (FormRequest) valida, o controller é fino, o service guarda a regra de negócio e o resource formata a saída.
- **Repositórios** (`app/Repositories`) **só** para consultas complexas ou reaproveitadas em mais de um lugar. CRUD simples usa Eloquent direto no service.
- **Nomes** de métodos e variáveis em português, no padrão do projeto: `criar`, `listarPaginado`, `detalhar`, `atualizar`, `desativar`.
- **Regras de negócio violadas** lançam `RegraNegocioException`: 409 para conflito, 422 para regra, com `dados` quando ajudar o front.
- **Paginação** sempre com `$this->porPagina($request)` (padrão 15, máximo 100).
- **Nada é apagado:** as entidades são desativadas (`ativo`). Aluno pode ser anonimizado.
- **Sem código morto** nem métodos vazios gerados pelo artisan.
- **Formatação:** rodar `./vendor/bin/pint` antes de commitar (`pint.json` mantém o alinhamento de `=>`).
- **Migrations** existentes são editadas no lugar, porque o banco de desenvolvimento é recriado com frequência. Quem roda `php artisan migrate:fresh --seed` é **o usuário**.
- A senha `Admin` do `AdminSeeder` é **intencional**, para desenvolvimento. Não apontar como problema, a menos que o assunto seja produção.
- O campo de data da reserva se chama `data_reserva`, nunca `data`, porque um campo `data` faria o resource perder o wrapper `{"data": ...}`.
- O **front-end será em Angular**, num projeto separado. **Não criar telas em Blade.** Links de email apontam para `FRONTEND_URL`.

### Compatibilidade entre bancos (SQLite, MySQL e Postgres)
- Busca por texto: `whereLike(..., caseSensitive: false)`, nunca `where(..., 'like', ...)`. No Postgres, o `like` diferencia maiúsculas.
- Nome único: a regra `App\Rules\NomeUnico`, que compara com `LOWER`, em vez de `Rule::unique`.
- Datas: comparar direto na coluna, com `toDateString()`. Nunca usar `whereDate`, que impede o uso de índice, nem comparar com `today()` cru, que vira `Y-m-d H:i:s`.
- Nada de SQL específico de um banco. Funções agregadas simples (`SUM`, `COUNT`) podem.
- Índice explícito em toda chave estrangeira usada em filtro ou join, porque o Postgres não cria sozinho.
- O SQLite ignora `lockForUpdate`, mas o código usa travas de linha mesmo assim, porque em produção elas valem.

### Desempenho
- Eager loading em toda listagem, sem N+1. Os testes podem conferir o número de consultas.
- Consultas por conjunto (agrupar e somar no banco) em vez de uma consulta por item dentro de um loop.
- Índices alinhados às consultas mais usadas.
- Exportação em stream e em lotes.

### LGPD
- Coletar e expor só o necessário. **Rotas públicas nunca mostram RA nem email.**
- Consultar dados pessoais exige mais de um identificador: **RA + email**.
- Dados pessoais nunca vão em URL (usar POST) nem em log.
- Os dados do aluno podem ser **anonimizados** a pedido, inclusive no histórico de auditoria.
- Avisar o usuário quando uma escolha envolver dados pessoais.

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
