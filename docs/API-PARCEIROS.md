# API de reservas para sistemas parceiros (v1)

Guia para quem desenvolve um sistema autorizado pela instituição a usar o sistema de reservas: portal acadêmico, aplicativo de atlética, sistema de outra unidade etc.

A especificação técnica completa (OpenAPI 3.1) está em [`api-v1.openapi.json`](api-v1.openapi.json). Ela pode ser aberta no [Swagger Editor](https://editor.swagger.io), no Postman ou no Insomnia.

> Os termos de uso da API ainda são um rascunho, a validar com a instituição. A versão em vigor aparece na página de aceite e em `GET /api/parceiros/termos`.

## 1. Como conseguir o acesso

1. Peça o acesso à instituição. Informe o nome do sistema, a organização, a pessoa responsável (nome e email), a **finalidade** e o que o sistema precisa fazer (veja as permissões na seção 3).
2. Um administrador cadastra o sistema. A pessoa responsável recebe um email com o link **"Ler e aceitar os termos"**, que vale 7 dias.
3. Na página do link, a pessoa responsável confere o cadastro, lê os termos de uso da API e aceita.
4. Logo depois do aceite, o **token aparece uma única vez**, na própria página. A instituição não guarda o token em texto e nenhum administrador consegue vê-lo. Copie o token e guarde num cofre de senhas ou numa variável de ambiente do servidor.

Se o token se perder ou vazar, avise a instituição. Um administrador revoga o token e envia um link novo.

## 2. Autenticação

Envie o token em toda requisição, no cabeçalho `Authorization`:

```http
GET /api/v1/espacos HTTP/1.1
Host: reservas.exemplo.edu
Authorization: Bearer 12|AbCdEf...
Accept: application/json
```

- O endereço base é `https://<domínio informado pela instituição>/api/v1`. Use sempre HTTPS.
- O token vale **12 meses**. Cerca de 30 dias antes do vencimento, a pessoa responsável recebe um link para aceitar os termos de novo e gerar um token novo. O token atual continua funcionando até vencer, para dar tempo de trocar.
- O token só funciona na `/api/v1`. As outras rotas do sistema respondem `403`.
- Nunca coloque o token em código público, no navegador, num aplicativo instalado no celular dos usuários ou em logs. A API deve ser chamada pelo **servidor** do seu sistema.

## 3. Permissões

Cada token tem só as permissões cadastradas para o sistema. Sem a permissão necessária, a API responde `403`, com a permissão que faltou em `dados.permissoes_necessarias`.

| Permissão | O que libera |
|---|---|
| `espacos:ler` | Ver os espaços, o calendário de horários e os equipamentos |
| `reservas:criar` | Reservar para um aluno pelo RA |
| `reservas:ler` | Ver as reservas que o **próprio sistema** criou |
| `reservas:cancelar` | Cancelar as reservas que o **próprio sistema** criou |

## 4. Rotas

| Método e rota | Permissão | Para quê |
|---|---|---|
| `GET /espacos?tipo=QUADRA` | `espacos:ler` | Espaços ativos (`tipo` é opcional: `QUADRA`, `LABORATORIO`, `SALA`) |
| `GET /espacos/{id}/disponibilidade?de=AAAA-MM-DD&ate=AAAA-MM-DD` | `espacos:ler` | Calendário do espaço (até 31 dias por consulta). Horários reservados aparecem só como ocupados, sem dizer quem reservou |
| `GET /espacos/{id}/equipamentos` | `espacos:ler` | Equipamentos que podem ser usados no espaço |
| `GET /espacos/{id}/equipamentos/disponibilidade?data=...&hora_inicio=...&hora_fim=...` | `espacos:ler` | Quantidade livre de cada equipamento num intervalo (`HH:MM`; nos espaços de horário fixo, use o início e o fim do horário, ex.: `19:00` e `20:30`) |
| `POST /reservas` | `reservas:criar` | Reservar para um aluno |
| `GET /reservas?de=...&ate=...&status=RESERVADO` | `reservas:ler` | Reservas criadas pelo seu sistema, paginadas (`page`, `per_page` até 100) |
| `GET /reservas/{id}` | `reservas:ler` | Uma reserva criada pelo seu sistema |
| `POST /reservas/{id}/cancelar` | `reservas:cancelar` | Cancelar uma reserva criada pelo seu sistema, antes de ela começar |

### Reservar para um aluno

```http
POST /api/v1/reservas
Content-Type: application/json

{
  "registro_academico": "2024000001",
  "espaco_id": 1,
  "data_reserva": "2026-11-10",
  "horario": "PRIMEIRO_HORARIO",
  "equipamentos": [{ "id": 3, "quantidade": 2 }]
}
```

- Em espaços de horário fixo, envie `horario` (`PRIMEIRO_HORARIO` ou `SEGUNDO_HORARIO`). Em espaços de horário livre, envie `hora_inicio` e `hora_fim` (`HH:MM`).
- Valem **as mesmas regras do site**: o aluno precisa estar cadastrado e ativo, só em dias úteis, até 10 dias úteis de antecedência, sem conflito de horário, com limite semanal por curso em alguns espaços e equipamentos disponíveis.
- O aluno **recebe o email de confirmação** com os termos de uso do sistema de reservas. Se não confirmar no prazo, a reserva é cancelada automaticamente, como no site. Acompanhe pelo campo `situacao_confirmacao`.
- A resposta (`201`) traz a reserva **sem dados pessoais do aluno**. Guarde o `id` para consultar ou cancelar depois.

Exemplo de resposta:

```json
{
  "data": {
    "id": 42,
    "data_reserva": "2026-11-10",
    "horario": "PRIMEIRO_HORARIO",
    "hora_inicio": "19:00",
    "hora_fim": "20:30",
    "status": "RESERVADO",
    "situacao_confirmacao": "AGUARDANDO_CONFIRMACAO",
    "confirmar_ate": "2026-11-10T10:00:00-03:00",
    "confirmada_em": null,
    "cancelada_em": null,
    "revogada_em": null,
    "espaco": { "id": 1, "nome": "Quadra Poliesportiva" },
    "equipamentos": [{ "id": 3, "nome": "Bola de vôlei", "quantidade": 2, "situacao_do_pedido": null }],
    "criada_em": "2026-11-09T10:00:00-03:00"
  }
}
```

Valores de `status`: `RESERVADO`, `CANCELADO` e `REVOGADO` (cancelada pela instituição ou por falta de confirmação). Valores de `situacao_confirmacao`: `AGUARDANDO_CONFIRMACAO`, `CONFIRMADA`, `AGUARDANDO_RECONFIRMACAO` e `RECONFIRMADA`.

## 5. Limites

- Cada sistema tem um limite de requisições por minuto, combinado no cadastro (padrão: 60).
- Pedidos com o mesmo RA também têm limite próprio, como no site.
- Acima do limite, a resposta é `429`, com o cabeçalho `Retry-After` dizendo em quantos segundos tentar de novo.

## 6. Erros

Todas as respostas de erro são JSON com `message` e, quando ajuda, `errors` (campos) ou `dados`.

| Código | Quando |
|---|---|
| `401` | Sem token, token vencido ou revogado, ou sistema desativado |
| `403` | Token sem a permissão da rota (`dados.permissoes_necessarias`) ou rota fora da `/api/v1` |
| `404` | Espaço inexistente ou inativo, ou reserva que não foi criada pelo seu sistema |
| `409` | Conflito: horário já reservado, reserva que não está mais ativa |
| `422` | Dados inválidos (`errors` traz a mensagem de cada campo) ou regra de negócio, como o limite semanal |
| `429` | Limite de requisições atingido |

## 7. Dados pessoais (LGPD)

- A instituição é a controladora dos dados do sistema de reservas. A pessoa responsável aceitou os termos em nome do sistema e responde pelo uso que ele faz da API.
- Envie só o RA de um aluno que **pediu** a reserva pelo seu sistema. A reserva só vale depois que o próprio aluno confirma pelo email dele.
- A API nunca devolve nome, email nem curso de alunos, nem reservas de outros sistemas ou do site.
- Guarde no seu sistema só o necessário para a finalidade cadastrada e informe os seus usuários de que a reserva é feita no sistema de reservas da instituição.
- Não registre o token nem o RA em logs.
- Toda ação feita pela API fica no histórico do sistema de reservas com o nome do sistema parceiro.

## 8. Versões

- Mudanças que não quebram o seu sistema entram na própria v1: campos novos nas respostas, rotas novas e campos opcionais novos. **Ignore os campos que você não conhece.**
- Mudanças que quebrariam (renomear ou remover campo, tornar obrigatório um campo novo, mudar o significado de um valor) viram uma versão nova (`/api/v2`). A v1 continua no ar por um prazo avisado por email à pessoa responsável.
- Valores novos podem aparecer em campos de situação (ex.: um tipo novo de ocupação no calendário). Trate valores desconhecidos sem quebrar.

## 9. Para quem mantém o sistema de reservas

- O arquivo `api-v1.openapi.json` é gerado a partir do código pelo pacote `dedoc/scramble`, instalado só em desenvolvimento. Para atualizar depois de mexer na v1, rode:

  ```bash
  ATUALIZAR_OPENAPI=1 php artisan test --filter=DocumentacaoApiTest
  ```

  O teste gera o arquivo no SQLite em memória, porque os tipos das colunas mudam de banco para banco. Sem a variável, ele só confere o arquivo e falha se estiver desatualizado. No CI, a conferência roda no job do SQLite.
- Em desenvolvimento, a documentação navegável fica em `http://localhost:8000/docs/api`. Ela só abre com `APP_ENV=local` e não existe na produção.
