# Checklist do sistema

Estado do sistema, plano completo até a finalização e as regras de trabalho combinadas. Atualizado a cada etapa concluída.

- Regras de negócio, endpoints e como rodar: [`DOCUMENTACAO.md`](DOCUMENTACAO.md)
- Modelo de dados e decisões de modelagem: [`DATABASE.md`](DATABASE.md)
- Registro arquivo por arquivo de cada implementação: [`implementacoes-com-claude-code.txt`](implementacoes-com-claude-code.txt)

Última atualização: 09/11/2026 · 226 testes passando

---

## 📍 Onde paramos

- **Último passo concluído:** passo 9, CI no GitHub Actions. **Primeira execução verde em 09/11/2026** (commit `43a0e74`): os 226 testes passaram em SQLite, **MySQL 8.0** e **Postgres 16**, além do Pint e do `composer audit`.
- **Próximo passo:** passo 10, preparação para produção. **Proposta enviada em 09/11/2026, aguardando aprovação** (resumo no passo 10 do plano, abaixo).
- **O usuário precisa:** dar push dos commits de documentação e rodar `migrate:fresh --seed` local, se ainda não rodou depois do passo 8.
- **Conceitos já explicados ao usuário:** não precisa de branches separadas (o CI roda na `main`; branches + PR só para bloquear merge, quando houver mais pessoas); repositório público não gasta minutos do Actions. Antes: fila e jobs; por que email não deve diferenciar maiúsculas; opção A do link do front (Angular, sem Blade); prazo da confirmação até a véspera (alternativa avaliada e descartada).
- **Código sem nenhum comentário** desde 09/11/2026, inclusive os padrões do Laravel nos `config/`. Manter assim (regra no `CLAUDE.md`).

## 🧭 Como continuar (para uma nova conversa com o Claude)

Leia este arquivo e o `CLAUDE.md` antes de qualquer coisa. Juntos, eles substituem a memória da conversa anterior. Comece pela seção "📍 Onde paramos" e mantenha-a atualizada a cada proposta, decisão, explicação e etapa concluída (regras em "Continuidade entre conversas", no `CLAUDE.md`).

### Regras de trabalho e padrões de código
Estão no [`CLAUDE.md`](CLAUDE.md#regras-de-desenvolvimento-combinadas-com-o-usuário), tópico "Regras de desenvolvimento combinadas com o usuário": comunicação, processo (proposta antes de implementar), código, compatibilidade entre bancos, desempenho, LGPD, testes e git. O Claude Code lê esse arquivo sozinho em toda conversa.

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
- **Confirmação:** pedido 2 dias úteis antes; prazo até as 23:59 do dia anterior (mantido depois de avaliar a alternativa "1 dia útil depois do email"); reserva para hoje nasce confirmada; reserva dentro da janela pede confirmação já na criação.
- O calendário público mostra **quem reservou** (nome e curso, nunca RA ou email).
- **Admins:** email sempre em minúsculas; um **admin principal** (não pode ser desativado nem editado por outros; criado/transferido só por comando); sem perfis de permissão.
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
- [x] Em espaços com horário livre, escolher início e fim (blocos de 30 min, dentro da janela e até a duração máxima)
- [x] Consultar as próprias reservas informando RA + email (futuras ou todas)
- [x] Receber email "Reserva registrada" e cancelar pelo link (até o início da reserva)
- [x] Confirmar a reserva pelo link do email (pedido 2 dias úteis antes; prazo até o dia anterior)
- [x] Receber email quando a reserva é criada, cancelada ou revogada, e quando a devolução tem pendência

### Regras da reserva do aluno
- [x] Só em dias úteis (sem fim de semana)
- [x] No máximo 10 dias úteis à frente
- [x] Horário de hoje aceito até 20 minutos antes de terminar
- [x] Uma reserva por espaço em cada horário
- [x] Limite semanal por curso em espaços com limite (ex.: quadra)
- [x] Não reserva em data ou horário bloqueado (mostra o motivo)
- [x] Equipamentos só se houver quantidade disponível naquele horário
- [x] Aluno inativo não consegue reservar
- [x] Reserva não confirmada no prazo é revogada sozinha (comando agendado)
- [x] Tarefas automáticas: feriados do ano seguinte, limpeza de links de email (30 dias) e de jobs falhos (7 dias), tokens de login vencidos
- [x] Limite de requisições por minuto contra abuso

### Admins (com login)
- [x] Login, logout e logout de todos os dispositivos (token vale 8h)
- [x] Convidar outros admins por email (link para definir a senha, válido por 2 dias) e reenviar o convite
- [x] Listar, editar, desativar e reativar admins (desativado perde as sessões; ninguém desativa a si mesmo)
- [x] Trocar a própria senha logado e recuperar a senha por email ("esqueci minha senha")
- [x] Admin principal (conta de manutenção): não pode ser desativado nem editado por outros; criado e transferido por comando no servidor
- [x] Cadastrar, editar, listar e desativar espaços, equipamentos e cursos
- [x] Configurar o espaço como horário fixo ou livre (janela de funcionamento e duração máxima)
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
- [x] Admin consulta o histórico com filtros (por registro, por admin, ações sem admin, ação e período)
- [x] Código compatível com SQLite, MySQL e Postgres
- [x] Código formatado com o Laravel Pint (`pint.json`)
- [x] CI no GitHub Actions: testes em SQLite, MySQL e Postgres, Pint e `composer audit` a cada push
- [x] Dependências sem vulnerabilidades conhecidas (`composer audit`)
- [x] Equipamentos calculados pelo pico de uso no intervalo
- [x] 226 testes automáticos passando

---

## 🗺️ Plano até a finalização (na ordem)

Cada passo abaixo já tem a ideia da proposta. Antes de implementar, enviar a proposta detalhada e esperar a aprovação. As **recomendações** são sugestões e ainda precisam de aprovação.

### 1. ✅ Aluno consulta as próprias reservas — *concluído em 09/11/2026*
- `POST /api/minhas-reservas` com `registro_academico` e `email`. É POST para o RA e o email não irem na URL nem nos logs.
- RA e email precisam ser do mesmo aluno (email comparado sem diferenciar maiúsculas). Dados errados recebem sempre a mesma resposta genérica, que não revela se o RA existe. Limite de 10 consultas por minuto.
- Aluno inativo pode consultar. Aluno anonimizado não aparece.
- Devolve a lista paginada das reservas dele: espaço, data, horário, status, equipamentos com a devolução e a justificativa da revogação. Filtro `periodo`: `FUTURAS` (padrão) ou `TODAS`.
- Arquivos: `ConsultarMinhasReservasRequest`, um método novo no `ReservaRepository`, `MinhasReservasController`, `MinhasReservasTest`.

### 2. ✅ Item 8 — Email na criação com link de cancelamento — *concluído em 09/11/2026*
- Ao reservar, o aluno recebe "Reserva registrada" (antes "Reserva confirmada") com um link para cancelar. Isso também protege contra o uso do RA por outra pessoa.
- **Token aleatório:** só o hash dele fica no banco, e o token puro vai só no email.
- **Link no front:** `FRONTEND_URL/reservas/cancelar/{token}`. O front chama:
  - `GET /api/reservas/cancelamento/{token}`: resumo da reserva;
  - `POST /api/reservas/cancelamento/{token}`: cancela. **Só o POST cancela**, porque leitores de email abrem links sozinhos.
- Só reservas `RESERVADO`, até o horário de início.
- Status `CANCELADO` com `cancelada_em`. Horário e equipamentos ficam livres, e o aluno recebe "Reserva cancelada".
- Eventos não recebem esses emails.
- Decisões tomadas: cancelar **até o início**; link para o **front Angular** (`FRONTEND_URL`, opção A, sem Blade); **token genérico** em `tokens_reserva` (`CANCELAR`/`CONFIRMAR`); resumo do GET **sem o nome** do aluno.

### 3. ✅ Item 9 — Confirmação e revogação automática — *concluído em 09/11/2026*
- Pedido de confirmação **2 dias úteis antes** (comando `reservas:pedir-confirmacao`, 08:00); prazo até as **23:59 do dia anterior** (dia corrido); revogação das não confirmadas no dia da reserva (comando `reservas:revogar-nao-confirmadas`, 00:05).
- Na criação: reserva **para hoje** nasce confirmada; reserva **dentro da janela** já recebe "Confirme sua reserva" (um email só); fora da janela recebe "Reserva registrada" com a data do pedido.
- Email de confirmação com **Confirmar** e **Cancelar**; `GET`/`POST /api/reservas/confirmacao/{token}`; filtro `confirmacao` na listagem do admin. Eventos não precisam de confirmação.

### 4. ✅ Agendador de tarefas — *concluído em 09/11/2026*
- `routes/console.php`: revogação (00:05), limpeza de links de email com mais de 30 dias (03:10), tokens de login vencidos (03:20), jobs falhos com mais de 7 dias (03:30), pedido de confirmação (08:00) e feriados do ano seguinte (1º/12, 03:00, sem facultativos por padrão). Todas com `withoutOverlapping`.
- Produção documentada no DOCUMENTACAO: cron do `schedule:run` e worker da fila com supervisor. Aviso de falha por email fica para o passo 10.

### 5. ✅ Item 10 — Admins — *concluído em 09/11/2026 (junto com o passo 6)*
- `UserResource` no login e no `/me`; `GET/POST/PATCH/DELETE /api/admin/admins` e reenvio de convite. Convite por email com link para definir a senha (**2 dias**). Desativado não faz login e perde as sessões; ninguém desativa a si mesmo; sempre sobra um ativo.
- Email do admin **sempre em minúsculas** (decisão após explicação ao usuário). Sem perfis de permissão.
- **Admin principal** (extra): um só, não pode ser desativado nem editado por outros admins; única diferença de poder. Comandos `admin:criar-principal` e `admin:transferir-principal`.

### 6. ✅ Recuperação e troca de senha — *concluído em 09/11/2026*
- "Esqueci a senha" (resposta sempre igual, link de **60 min** só com o token), redefinição, aceite de convite e troca logado (`PATCH /api/me/senha`). Senha 8+ com letras e números. Email "Sua senha foi alterada". Tabelas de token separadas para convite e redefinição.

### 7. ✅ Consulta do histórico de auditoria — *concluído em 09/11/2026*
- `GET /api/admin/auditorias`: uma rota só, com filtros `tipo`, `registro_id`, `admin_id`, `sem_admin`, `acao`, `periodo`/`de`/`ate`; mais recente primeiro; `campos_alterados`, `antes`, `depois`, `feito_por`.
- Morph map: `auditavel_type` com nomes curtos. Histórico guardado sem prazo; exportação do histórico ficou como opcional.

### 8. ✅ Horários livres — *concluído em 09/11/2026*
- `espacos`: `modo_horario` (FIXO/LIVRE), `abre_as`, `fecha_as`, `duracao_maxima_minutos`. Bloco global de 30 min (`RESERVA_BLOCO_MINUTOS`). Trocar o modo com reservas futuras é recusado.
- Reserva LIVRE com `hora_inicio`/`hora_fim` (alinhados, dentro da janela, até a duração máxima); eventos podem sair da janela. Limite semanal conta reservas.
- Equipamentos pelo **pico de uso** (inclusive na redução de estoque). Calendário LIVRE em blocos, sem equipamentos por bloco; `GET /api/espacos/{id}/equipamentos/disponibilidade` para um intervalo.

### 9. ✅ CI (GitHub Actions) — *concluído em 09/11/2026; primeira execução verde nos três bancos*
- `.github/workflows/ci.yml`: push e PR na `main`; job de Pint (`--test`) + `composer audit`; testes em matriz SQLite, MySQL 8.0 e Postgres 16 (serviços do Actions), PHP 8.3, cache do Composer.
- Sem branch protection por enquanto (trabalho direto na `main`); explicado no DOCUMENTACAO como ativar.

### 10. ⬜ Preparação para produção — *proposta enviada em 09/11/2026, aguardando aprovação*
- **Código:**
  - CORS só para o front (`CORS_ALLOWED_ORIGINS`) e cabeçalhos de segurança (nosniff, frame, referrer, HSTS com HTTPS);
  - HTTPS forçado em produção e `TRUSTED_PROXIES`;
  - mensagens de validação em **pt_BR**;
  - alerta por email de tarefa agendada que falhou e de job que falhou (`ALERTAS_EMAIL`);
  - logs diários com 14 dias, nível `warning`;
  - em produção, o seeder só cria cursos (admin de teste e demonstração só em dev);
  - comando `sistema:verificar-producao`, com ✅/❌ da configuração;
  - `APP_NAME` "Sistema de Reservas".
- **`DEPLOY.md`:** requisitos, `.env.production.example`, passos de implantação, primeira vez (principal, feriados, verificação), Nginx com HTTPS, cron, supervisor, backup do banco por cron com cópia externa e checklist de LGPD.
- **Decisões em aberto, com recomendação:**
  1. traduções pt_BR geradas com o laravel-lang (só dev), com os arquivos no projeto e sem comentários: sim;
  2. alertas para um email configurável, e não para todos os admins: sim;
  3. seeder de admin só em dev, principal por comando: sim;
  4. backup documentado com dump por cron agora, pacote só quando a hospedagem for escolhida: sim;
  5. onde vai hospedar? Se não souber, o guia é para VPS Linux com Nginx.
- **Antes:** CORS, fila, cron e supervisor já estavam previstos aqui; cron e supervisor já estão documentados no DOCUMENTACAO (passo 4).

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
- [ ] **Horários fixos diferentes por espaço** (hoje 19:00 e 20:30 valem para todos)
- [ ] **Exportar o histórico de auditoria** em CSV (reaproveitar o exportador de reservas)
- [ ] **Retenção do histórico de auditoria** (ex.: apagar depois de N anos), se o volume pedir

---

## ✉️ Emails

- [x] Reserva revogada (pelo admin, por evento, por data bloqueada ou por aluno desativado)
- [x] Pendência na devolução de equipamentos (avaria ou não devolvido)
- [x] Reserva registrada, com link de cancelamento
- [x] Reserva cancelada pelo aluno
- [x] Confirme sua reserva (2 dias úteis antes, ou já na criação dentro da janela)
- [x] Reserva revogada por falta de confirmação
- [x] Convite para acessar o sistema (admin novo)
- [x] Redefinição de senha do admin
- [x] Sua senha foi alterada
