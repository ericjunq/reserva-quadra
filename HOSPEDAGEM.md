# Hospedagem do Sistema de Reservas: opções e recomendação

Material de apoio para decidir, com a TI da faculdade, onde o sistema vai rodar.

## O que o sistema precisa

| Parte | O que é | Necessidade |
|---|---|---|
| **API** | Laravel 13 (PHP 8.3) | Servidor com PHP, HTTPS e domínio próprio (ex.: `api.reservas...`) |
| **Banco de dados** | MySQL 8 ou Postgres 16 | Os dois já são testados automaticamente. Precisa de backup diário |
| **Fila** | Processo que envia os emails em segundo plano | Precisa ficar sempre rodando (supervisor ou equivalente) |
| **Agendador** | Tarefas diárias (pedir confirmação, revogar reservas, limpezas) | Um cron chamando o sistema a cada minuto |
| **Email** | Envio de avisos a alunos e admins | Um SMTP (o da faculdade ou um serviço transacional) |
| **Front-end** | Angular (projeto separado) | Hospedagem de site estático com HTTPS |

**Volume esperado:** baixo a médio (alunos e admins de uma instituição). Um servidor pequeno atende com folga.

**Dados pessoais tratados:** nome, RA, email e curso dos alunos; nome e email dos admins. Por isso a LGPD pesa na escolha.

## Perguntas para a TI da faculdade

1. Existe exigência de que os sistemas rodem **nos servidores da faculdade**?
2. Existe política de que os **dados de alunos fiquem no Brasil**?
3. A faculdade tem **SMTP** que o sistema possa usar? Com qual remetente (ex.: `reservas@...`)?
4. Quem cuida do **domínio** e pode criar os endereços da API e do front (ex.: `api.reservas.faculdade.edu.br` e `reservas.faculdade.edu.br`)?
5. Existe **orçamento** para hospedagem? Em real ou em dólar? Quem paga?
6. Quem fica responsável pelo servidor depois da entrega (atualizações, backup, acesso)?
7. A faculdade tem um **encarregado de dados (DPO)** que precise aprovar o sistema? Qual o prazo de guarda desejado para o histórico de auditoria?

As respostas 1 e 2 decidem quase tudo. Se houver exigência, a escolha está feita. Se não houver, valem as opções abaixo.

## Opções

| Opção | Para quem | A favor | Contra |
|---|---|---|---|
| **Laravel Cloud** (plataforma oficial do Laravel) | Quem quer **cuidar o mínimo de servidor** | Fila, agendador, banco gerenciado com backup e HTTPS já resolvidos. Implantação a cada push | Cobrança em dólar, por uso. Os dados ficam fora do Brasil |
| **VPS em São Paulo** (ex.: Hostinger VPS, AWS Lightsail, outros com região no Brasil) | Quem quer **dados no Brasil** ou **custo menor** | Mais barato, controle total, menor latência para os alunos | Alguém cuida do servidor (atualizações, segurança, backup). O [`DEPLOY.md`](DEPLOY.md) cobre esse caminho |
| **VPS + Laravel Forge** | Meio-termo | O Forge configura e mantém o servidor (Nginx, PHP, fila, cron, HTTPS) | Assinatura do Forge, além do VPS |
| **Servidor da faculdade** | Se a TI exigir | Dados dentro da instituição | Depende da infraestrutura e da agenda da TI |

**Front-end Angular:** qualquer opção acima pode ser combinada com hospedagem estática gratuita ou barata (Cloudflare Pages, Netlify ou Vercel). Basta liberar o endereço do front no CORS da API.

Os preços mudam com frequência. Confira no site de cada um na hora de decidir.

## Recomendação

1. **Primeiro, as perguntas 1 e 2 à TI.**
2. **Sem exigência de dados no Brasil e com orçamento em dólar:** **Laravel Cloud**. É a opção com menos manutenção, porque fila, agendador, banco e backup vêm prontos.
3. **Com exigência de dados no Brasil ou orçamento apertado:** **VPS na região de São Paulo**. Para não precisar configurar o servidor na mão, adicionar o **Laravel Forge**.
4. **Front:** hospedagem estática (Cloudflare Pages, Netlify ou Vercel).

## LGPD na escolha

- **Localização dos dados:** a LGPD permite guardar dados fora do Brasil, desde que o provedor ofereça proteção adequada (os grandes provedores têm cláusulas para isso). Mesmo assim, algumas instituições preferem o Brasil por política interna. Daí a pergunta 2.
- **Contrato:** o provedor atua como **operador** dos dados. Vale guardar os termos de proteção de dados dele.
- **Backup:** criptografado, com acesso restrito e prazo de guarda definido.
- **Acesso:** só quem administra o sistema tem acesso ao servidor e ao banco.

O sistema já cumpre a parte dele: HTTPS obrigatório, dados pessoais fora das URLs e dos logs, logs apagados em 14 dias, links de email com validade, anonimização de alunos a pedido e alertas sem dados pessoais.
