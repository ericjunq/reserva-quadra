# Checklist do sistema

Lista simples do que o sistema já faz e do que falta. Atualizada a cada etapa concluída.

Modelo de dados e decisões de modelagem: [`DATABASE.md`](DATABASE.md).

Última atualização: 06/11/2026

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
- [x] Receber email quando a reserva é revogada

### Regras da reserva do aluno
- [x] Só em dias úteis (sem fim de semana)
- [x] No máximo 10 dias úteis à frente
- [x] Horário de hoje aceito até 20 minutos antes de terminar
- [x] Uma reserva por espaço em cada horário
- [x] Limite semanal por curso em espaços com limite (ex.: quadra)
- [x] Não reserva em data ou horário bloqueado (mostra o motivo)
- [x] Equipamentos só se houver quantidade disponível naquele horário
- [x] Limite de requisições por minuto contra abuso

### Admins (com login)
- [x] Login, logout e logout de todos os dispositivos (token vale 8h)
- [x] Cadastrar, editar, listar e desativar espaços
- [x] Cadastrar, editar, listar e desativar equipamentos (com filtros)
- [x] Marcar equipamento como compartilhável entre espaços
- [x] Cadastrar, editar, listar e desativar cursos
- [x] Listar alunos
- [x] Listar reservas com filtros (data, espaço, tipo de espaço, curso, nome/RA do aluno, status, tipo, horário) e totais por status
- [x] Ver o detalhe de uma reserva (aluno, curso, equipamentos, quem criou, quem revogou)
- [x] Criar eventos (inclusive no fim de semana e em feriados)
- [x] Revogar uma reserva de aluno com justificativa
- [x] Criar evento por cima de uma reserva de aluno, revogando-a com justificativa
- [x] Bloquear datas: dia inteiro ou parte do dia, todos os espaços ou um só
- [x] Ao bloquear, revogar automaticamente as reservas atingidas e avisar os alunos
- [x] Importar feriados nacionais do ano (`php artisan feriados:importar`)

### Proteções
- [x] Não deixa desativar espaço ou equipamento com reservas futuras
- [x] Não deixa reduzir a quantidade de um equipamento abaixo do que já está reservado
- [x] Duas pessoas reservando ao mesmo tempo não geram reserva duplicada
- [x] Erros de regra de negócio com mensagem clara (409/422)
- [x] Dependências sem vulnerabilidades conhecidas (`composer audit`)
- [x] 84 testes automáticos passando

---

## ⬜ O que falta (na ordem combinada)

- [ ] **Exportação de reservas** em CSV (abre no Excel), com os mesmos filtros da listagem
- [ ] **Auditoria:** registrar quem criou e quem alterou cada registro
- [ ] **Admin editar e desativar alunos**
- [ ] **Aluno ver as próprias reservas** informando RA + email
- [ ] **Item 8 — Cancelamento pelo aluno:** email ao reservar, com link para cancelar
- [ ] **Item 9 — Confirmação:** email 2 dias antes; reserva não confirmada é revogada sozinha
- [ ] **Agendador de tarefas:** roda a confirmação, limpa tokens vencidos e importa feriados todo ano
- [ ] **Item 10 — `/me` com dados do admin formatados**
- [ ] **Item 10 — Admin cadastrar outros admins** (convite por email)
- [ ] **Recuperação de senha do admin** por email
- [ ] **Horários livres:** espaços com horário flexível (ex.: 7h–22h) além dos horários fixos
- [ ] **Front-end em Angular** (projeto separado; não será com Blade)
- [ ] **Final — API para terceiros autorizados** (portal da faculdade, atléticas, outras unidades)

## ✉️ Emails

- [x] Reserva revogada (pelo admin, por evento ou por data bloqueada)
- [ ] Reserva criada, com link de cancelamento (item 8)
- [ ] Reserva cancelada (item 8)
- [ ] Pedido de confirmação 2 dias antes (item 9)
- [ ] Reserva revogada por falta de confirmação (item 9)
- [ ] Convite de novo admin (item 10)
- [ ] Recuperação de senha do admin

## ❔ O que não faz e ainda não está no plano

- Nada no momento.
