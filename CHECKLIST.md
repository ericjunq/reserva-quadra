# Checklist do sistema

Estado do sistema, plano completo até a finalização e as regras de trabalho combinadas. Atualizado a cada etapa concluída.

- Regras de negócio, endpoints e como rodar: [`DOCUMENTACAO.md`](DOCUMENTACAO.md)
- Modelo de dados e decisões de modelagem: [`DATABASE.md`](DATABASE.md)
- Registro arquivo por arquivo de cada implementação: [`implementacoes-com-claude-code.txt`](implementacoes-com-claude-code.txt)

Última atualização: 06/11/2026 · 137 testes passando

---

## 🧭 Como continuar (para uma nova conversa com o Claude)

Leia este arquivo inteiro antes de qualquer coisa. Ele substitui a memória da conversa anterior.

### Regras de trabalho
1. **Proposta antes de implementar:** para cada passo do plano, enviar a proposta (o que faz, arquivos, decisões em aberto com recomendação) e **esperar a aprovação**. Correções pequenas pedidas diretamente podem ser feitas sem proposta.
2. **Respostas curtas e diretas** por padrão. Explicar mais quando o usuário pedir, quando ele não entender, em debates de ideias e quando houver risco (dados importantes, decisões difíceis de reverter).
3. **Sem comentários no código.** As explicações vão para `implementacoes-com-claude-code.txt`, com uma seção nova por implementação, arquivo por arquivo.
4. **Commits separados por alteração**, no padrão conventional commits (`feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `style`, `chore`). **Sem** a linha `Co-Authored-By` do Claude. O usuário faz o push.
5. **Ao concluir cada etapa, atualizar:** este CHECKLIST (marcar itens, data e número de testes), o `DOCUMENTACAO.md`, o `DATABASE.md` (se mexer no banco) e o txt.
6. **O usuário roda** `php artisan migrate:fresh --seed`. As migrations existentes são editadas no lugar, porque o banco de desenvolvimento é recriado com frequência.
7. A senha `Admin` do `AdminSeeder` é intencional, só para desenvolvimento. Não apontar como problema, a menos que o assunto seja produção.

### Padrões de código (valem para todo código novo)
- **Organização:** o request valida, o controller é fino, o service guarda a regra e o resource formata a saída. Repositório (`app/Repositories`) **só** para consulta complexa ou reaproveitada. Métodos em português (`criar`, `listarPaginado`, `atualizar`, `desativar`). Paginação com `$this->porPagina($request)`. Nada de código morto. Rodar `./vendor/bin/pint` antes de commitar.
- **Compatibilidade SQLite/MySQL/Postgres:**
  - busca com `whereLike(..., caseSensitive: false)`;
  - nome único com a regra `NomeUnico`;
  - data comparada direto na coluna (`toDateString()`, sem `whereDate`);
  - nada de SQL específico de um banco;
  - índice explícito em chave estrangeira usada em filtro ou join.
- **Desempenho:** eager loading (sem N+1), consultas por conjunto em vez de uma por item dentro de um loop, índices alinhados às consultas, exportação em stream.
- **LGPD:**
  - expor só o necessário: rota pública nunca mostra RA nem email;
  - consultar dados pessoais exige RA + email;
  - dados pessoais podem ser anonimizados, inclusive no histórico;
  - nada de dados pessoais em URL (usar POST) nem em log;
  - avisar o usuário quando uma decisão envolver dados pessoais.
- **Testes:** feature tests com factories. A data dos testes fica fixa em segunda, 09/11/2026, 10:00 (`tests/TestCase.php`). Todo passo vem com testes.

### Decisões já tomadas (não reabrir)
- Aluno **não faz login**: se identifica pelo RA. Admin entra com Sanctum (token de 8h).
- Reserva de aluno: só em **dia útil**, até **10 dias úteis** à frente (feriados contam na contagem). O horário de hoje aceita reserva até **20 minutos antes do fim**.
- Horários fixos: 19:00–20:30 e 20:30–22:00. O modelo já guarda `hora_inicio` e `hora_fim`, preparando os horários livres.
- **Limite semanal por curso** em espaços com limite (quadra = 3, contado de segunda a sexta). O limite conta pelo curso **atual** do aluno.
- **Eventos** (admin) podem cair em fim de semana e feriado. Se ocuparem o horário de um aluno, revogam a reserva dele com justificativa e email.
- **Datas bloqueadas** valem só para alunos. As reservas atingidas são revogadas, com email.
- Equipamento pertence a um espaço, e a flag `compartilhavel` libera o uso em outros espaços. A disponibilidade é **calculada**, nunca guardada numa coluna.
- **Cursos** ficam em tabela, para reaproveitar o sistema em outras instituições.
- Nada é apagado: as entidades são **desativadas**. Aluno desativado tem as reservas futuras revogadas, com email. Aluno pode ser **anonimizado** (LGPD).
- **Devolução** de equipamentos é registrada por item. Ela **não** mexe no estoque e **não** bloqueia o aluno. Avaria ou falta dispara email.
- O calendário público mostra **quem reservou** (nome e curso, nunca RA ou email).
- O **front-end** será em **Angular**, num projeto separado. **Não usar Blade** para telas.
- O banco de produção ainda não foi escolhido (MySQL ou Postgres). O código precisa funcionar nos dois.

---

## ✅ O que já faz

### Alunos (sem login)
- [x] Se cadastrar com RA, nome, email e curso
- [x] Ver os cursos ativos para o cadastro
- [x] Ver os espaços ativos (com filtro por tipo)
- [x] Ver os equipamentos que pode pedir em um espaço (os do espaço + compartilháveis)
- [x] Ver o calendário de um espaço (até 31 dias): horários livres, reservados, eventos e bloqueios, quem reservou e equipamentos disponíveis
- [x] Informar o RA ou o curso no calendário para ver quantas reservas o curso ainda tem na semana
- [x] Reservar um espaço em um horário, com ou sem equipamentos
- [x] Receber email quando a reserva é revogada ou quando a devolução tem pendência

### Regras da reserva do aluno
- [x] Só em dias úteis (sem fim de semana)
- [x] No máximo 10 dias úteis à frente
- [x] Horário de hoje aceito até 20 minutos antes de terminar
- [x] Uma reserva por espaço em cada horário
- [x] Limite semanal por curso em espaços com limite (ex.: quadra)
- [x] Não reserva em data ou horário bloqueado (mostra o motivo)
- [x] Equipamentos só se houver quantidade disponível naquele horário
- [x] Aluno inativo não consegue reservar
- [x] Limite de requisições por minuto contra abuso

### Admins (com login)
- [x] Login, logout e logout de todos os dispositivos (token vale 8h)
- [x] Cadastrar, editar, listar e desativar espaços, equipamentos e cursos
- [x] Marcar equipamento como compartilhável entre espaços
- [x] Listar alunos com filtros (nome/RA/email, curso, ativo) e ver o detalhe
- [x] Editar, desativar e reativar alunos (desativar revoga as reservas futuras e avisa o aluno)
- [x] Anonimizar aluno a pedido (LGPD), inclusive no histórico
- [x] Listar reservas com filtros (período, espaço, tipo de espaço, curso, nome/RA, status, tipo, horário, devolução) e totais por status
- [x] Ver o detalhe de uma reserva (aluno, curso, equipamentos, devolução, quem criou, quem revogou)
- [x] Exportar reservas em CSV (abre no Excel), com os mesmos filtros e colunas opcionais
- [x] Registrar e corrigir a devolução de cada equipamento (devolvido, com avaria, não devolvido)
- [x] Criar eventos, inclusive por cima de uma reserva de aluno (revogando-a com justificativa)
- [x] Revogar uma reserva de aluno com justificativa
- [x] Bloquear datas (dia inteiro ou parte do dia, todos os espaços ou um só), revogando as reservas atingidas
- [x] Importar feriados nacionais do ano (`php artisan feriados:importar 2027 [--facultativos]`)

### Proteções e qualidade
- [x] Não deixa desativar espaço ou equipamento com reservas futuras
- [x] Não deixa reduzir a quantidade de um equipamento abaixo do que já está reservado
- [x] Duas pessoas reservando ao mesmo tempo não geram reserva duplicada (travas de linha)
- [x] Erros de regra de negócio com mensagem clara (409/422)
- [x] Auditoria: quem criou e quem alterou cada registro, e histórico com o antes e o depois
- [x] Código compatível com SQLite, MySQL e Postgres
- [x] Código formatado com o Laravel Pint (`pint.json`)
- [x] Dependências sem vulnerabilidades conhecidas (`composer audit`)
- [x] 137 testes automáticos passando

---

## 🗺️ Plano até a finalização (na ordem)

Cada passo abaixo já tem a ideia da proposta. Antes de implementar, enviar a proposta detalhada e esperar a aprovação. As **recomendações** são sugestões e ainda precisam de aprovação.

### 1. ⬜ Aluno consulta as próprias reservas — *proposta enviada, aguardando aprovação*
- `POST /api/minhas-reservas` com `registro_academico` e `email`. É POST para o RA e o email não irem na URL nem nos logs.
- RA e email precisam ser do mesmo aluno (email comparado sem diferenciar maiúsculas). Dados errados recebem sempre a mesma resposta genérica, que não revela se o RA existe. Limite de 10 consultas por minuto.
- Aluno inativo pode consultar. Aluno anonimizado não aparece.
- Devolve a lista paginada das reservas dele: espaço, data, horário, status, equipamentos com a devolução e a justificativa da revogação. Filtro `periodo`: `FUTURAS` (padrão) ou `TODAS`.
- Arquivos: `ConsultarMinhasReservasRequest`, um método novo no `ReservaRepository`, `MinhasReservasController`, `MinhasReservasTest`.

### 2. ⬜ Item 8 — Email na criação com link de cancelamento — *proposta enviada, aguardando aprovação*
- Ao reservar, o aluno recebe "Reserva confirmada" com um link para cancelar. Isso também protege contra o uso do RA por outra pessoa.
- **Token aleatório:** só o hash dele fica no banco, e o token puro vai só no email.
- **Link no front:** `FRONTEND_URL/reservas/cancelar/{token}`. O front chama:
  - `GET /api/reservas/cancelamento/{token}`: resumo da reserva;
  - `POST /api/reservas/cancelamento/{token}`: cancela. **Só o POST cancela**, porque leitores de email abrem links sozinhos.
- Só reservas `RESERVADO`, até o horário de início.
- Status `CANCELADO` com `cancelada_em`. Horário e equipamentos ficam livres, e o aluno recebe "Reserva cancelada".
- Eventos não recebem esses emails.
- Decisões em aberto, com recomendação:
  - prazo para cancelar **até o início**;
  - o link aponta para o front mesmo antes de o Angular existir (sem Blade);
  - **token genérico**, por finalidade (`CANCELAR`/`CONFIRMAR`), para reaproveitar no item 9. Ex.: tabela `tokens_reserva` (reserva_id, finalidade, token_hash, expira_em, usado_em).

### 3. ⬜ Item 9 — Confirmação 2 dias antes e revogação automática
- Comando diário `reservas:pedir-confirmacao`: envia o email "Confirme sua reserva" para as reservas de aluno que acontecem daqui a `RESERVA_CONFIRMACAO_DIAS_ANTES` (2) dias, com o link de confirmação (token do passo 2). Grava `confirmacao_enviada_em`, e as colunas `confirmacao_enviada_em`/`confirmada_em` já existem.
- `POST /api/reservas/confirmacao/{token}` grava `confirmada_em`.
- Comando `reservas:revogar-nao-confirmadas`: revoga as que não forem confirmadas no prazo, com a justificativa "Reserva não confirmada" e email (reaproveita o email de revogação).
- Decisões em aberto, com recomendação:
  - **prazo para confirmar:** até 24h antes do início;
  - **reserva criada já dentro da janela de 2 dias:** já nasce confirmada, para não exigir uma confirmação logo depois de reservar;
  - eventos não precisam de confirmação.
- Arquivos: 2 comandos, 2 emails, um service de confirmação, a rota e os testes, com a data simulada.

### 4. ⬜ Agendador de tarefas (scheduler)
- Em `routes/console.php`:
  - pedido de confirmação e revogação das não confirmadas, todo dia;
  - `sanctum:prune-expired`, todo dia;
  - `feriados:importar` do ano seguinte, todo 1º de dezembro;
  - `queue:prune-failed`.
- Produção precisa de um cron chamando `php artisan schedule:run` a cada minuto e de um worker de fila (`queue:work`, com supervisor). Documentar isso.

### 5. ⬜ Item 10 — Admins
- `UserResource` no `/me` e no login, para não devolver o model `User` cru.
- `POST /api/admin/admins`: um admin cadastra outro. O novo admin recebe um **convite por email** com um link para definir a senha (reaproveita o mecanismo de redefinição do passo 6).
- `GET /api/admin/admins` (lista), `PATCH` (nome/email) e `DELETE` (desativar: coluna `ativo` em `users`; admin desativado não faz login e perde os tokens). Um admin não pode desativar a si mesmo, e precisa sobrar pelo menos 1 admin ativo.
- Decisão em aberto: perfis de permissão (ex.: admin geral × funcionário que só registra devoluções). Recomendação: **não agora**; deixar anotado para quando houver necessidade.

### 6. ⬜ Recuperação de senha do admin
- `POST /api/esqueci-senha` com o email: a resposta é **sempre a mesma**, exista o email ou não. Limite de requisições.
- Email com o link `FRONTEND_URL/redefinir-senha?token=...` (Password broker do Laravel; a tabela `password_reset_tokens` já existe; o token expira em 60 minutos).
- `POST /api/redefinir-senha` (token, email, senha nova com confirmação e regras mínimas). Ao redefinir, **revoga todos os tokens de acesso** daquele admin.

### 7. ⬜ Rota de consulta do histórico de auditoria
- `GET /api/admin/auditorias` com os filtros: tipo do registro (espaço, reserva, aluno...), id do registro, admin, ação e período. Paginada.
- Também `GET /api/admin/{tipo}/{id}/historico`, ou um campo no detalhe de cada entidade (decidir na proposta).

### 8. ⬜ Horários livres (modo LIVRE por espaço)
- `espacos` ganha `modo_horario` (FIXO/LIVRE), `abre_as`, `fecha_as`, `duracao_maxima_minutos` e o tamanho do bloco (30 min).
- No modo LIVRE, a reserva informa `hora_inicio`/`hora_fim` (múltiplos do bloco, dentro da janela, até a duração máxima). A verificação de conflito por sobreposição já existe.
- **Equipamentos pelo pico de uso:** a disponibilidade no intervalo é o **menor** saldo entre os blocos de 30 minutos, e não a soma das reservas que se sobrepõem.
- O calendário de disponibilidade passa a mostrar blocos nesses espaços. A quadra continua FIXO.
- Revisar: tolerância de 20 minutos, limite semanal e bloqueios de parte do dia (devem continuar funcionando, por sobreposição).

### 9. ⬜ Testes no banco de produção (CI)
- Pipeline no GitHub Actions rodando `php artisan test` em **MySQL e Postgres** (services do Actions), além do Pint e do `composer audit`.

### 10. ⬜ Preparação para produção
- `.env` de produção: `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL`, `FRONTEND_URL`, banco, SMTP (SES, Mailgun, Resend...), fila e cache.
- **CORS** liberado só para o domínio do front Angular (`config/cors.php`).
- HTTPS, worker de fila com supervisor, cron do scheduler, backup do banco, logs sem dados pessoais (nada de `MAIL_MAILER=log`) e senha forte no admin inicial (o seeder de demonstração não roda fora de `local`).

### 11. ⬜ Front-end em Angular (projeto separado)
- Público: cadastro de aluno, calendário de disponibilidade, reserva com equipamentos, minhas reservas (RA + email) e as páginas de cancelar e confirmar (links dos emails).
- Admin: login e redefinição de senha, painel de reservas (filtros, totais, exportação), devoluções pendentes, espaços, equipamentos, cursos, alunos, datas bloqueadas, eventos, admins e histórico.
- Consome esta API. Os links dos emails usam `FRONTEND_URL`.

### 12. ⬜ Final — API para terceiros autorizados
- Tabela de **clientes de API** gerenciada pelos admins, com tokens Sanctum por cliente e permissões por *ability* (ex.: `reservas:ler`, `reservas:criar`), que podem ser revogadas.
- Rotas versionadas em `/api/v1`, limite de requisições por cliente, documentação OpenAPI (Swagger) e auditoria indicando qual cliente fez cada alteração.

### Opcionais (sem data)
- [ ] **Registro de retirada** dos equipamentos (hoje só a devolução é registrada)
- [ ] **Exportação muito grande:** trocar o `lazy()` (OFFSET) por paginação por chave quando o volume passar de dezenas de milhares de linhas
- [ ] **Perfis de permissão** para admins (ver passo 5)

---

## ✉️ Emails

- [x] Reserva revogada (pelo admin, por evento, por data bloqueada ou por aluno desativado)
- [x] Pendência na devolução de equipamentos (avaria ou não devolvido)
- [ ] Reserva criada, com link de cancelamento (passo 2)
- [ ] Reserva cancelada (passo 2)
- [ ] Pedido de confirmação 2 dias antes (passo 3)
- [ ] Reserva revogada por falta de confirmação (passo 3)
- [ ] Convite de novo admin (passo 5)
- [ ] Recuperação de senha do admin (passo 6)
