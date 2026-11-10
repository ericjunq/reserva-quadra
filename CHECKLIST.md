# Checklist do sistema

Estado do sistema, plano completo até a finalização e as regras de trabalho combinadas. Atualizado a cada etapa concluída.

- Regras de negócio, endpoints e como rodar: [`README.md`](README.md)
- Modelo de dados e decisões de modelagem: [`DATABASE.md`](DATABASE.md)
- Registro arquivo por arquivo de cada implementação: [`implementacoes-com-claude-code.txt`](implementacoes-com-claude-code.txt)

Última atualização: 10/11/2026 · 239 testes da API + 22 do front passando

---

## 📍 Onde paramos

- **Último passo concluído:** **passo 10, preparação para produção** (09/11/2026, 239 testes passando). Mensagens em pt_BR, CORS restrito, cabeçalhos de segurança, HTTPS e proxies, alertas por email, logs diários (14 dias), admin inicial pelo `.env`, `sistema:verificar-producao`, `DEPLOY.md`, `.env.production.example` e `HOSPEDAGEM.md` (recomendações de plataforma para o usuário negociar com a TI da faculdade).
- **Último subpasso concluído:** **11.1, base do front Angular** (09/11/2026, 22 testes do front + 239 da API). Projeto em `frontend/` (Angular 22 sem zone.js, Tailwind v4, Vitest), paleta e Montserrat no próprio site, cliente da API (sessão no `sessionStorage`, erros padronizados), pt-BR, layouts público e admin, 404, rotas sob demanda, orçamento de tamanho, job do front no CI.
- **Em andamento:** **proposta do 11.2 enviada em 09/11/2026**: decisões 5, 6 e 7 aprovadas em 10/11; 1, 2, 4 e 5 aprovadas; **3 (ideia do usuário: RA puxa o curso) com recomendação de fazer por POST, aguardando ok** (no passo 11 do plano). Destaque: a decisão 3 propõe uma mudança pequena na API (tirar o RA do GET de disponibilidade).
- **Próximo passo:** com a aprovação, implementar o 11.2. O Angular CDK entra quando uma tela usar diálogo.
- **Pendente para o deploy (decisão do usuário):** escolher a plataforma (ver `HOSPEDAGEM.md`), definir o backup e o monitor externo.
- **O usuário precisa:** responder à **proposta do 11.2** (7 decisões). Push em dia: o CI de 10/11/2026 (`f9c16ac`) passou nos **5 jobs**, inclusive o `Front-end`. Para ver o front: `php artisan serve` na raiz e `cd frontend && npm start` (http://localhost:4200). **No outro PC**: `git pull`, copiar as linhas novas do `.env.example` para o `.env` (`ADMIN_INICIAL_*`, `ALERTAS_EMAIL`, `APP_LOCALE=pt_BR`, `LOG_STACK=daily`, `APP_NAME`) e rodar `cd frontend && npm install`.
- **Para retomar o passo 11 em outro PC (instruções para o Claude):**
  1. Ler este arquivo inteiro e o `CLAUDE.md`. A proposta do 11.2, com as 7 decisões, está no passo 11 do plano (abaixo); a identidade visual está no README, seção "Front-end: identidade visual"; a estrutura do front, em "Estrutura do código".
  2. Conferir com `git log` se o último commit local é o mesmo do GitHub.
  3. Conferir o ambiente (`php -v`, `node -v`, `npm -v`; Angular 22 aceita Node 22.22+, 24.15+ ou 26) e rodar `npm install` em `frontend/` se faltar o `node_modules`. Se o PC não tiver PHP ou Node, seguir o README, "Preparar um PC novo".
  4. Perguntar ao usuário só o que falta: **o ok da decisão 3 do 11.2 na versão por POST** (as outras já foram aprovadas). Não reabrir as decisões 1 a 8 do passo 11, já aprovadas.
  5. Com o ok, a mudança na API (rota POST com o RA no corpo; `registro_academico` sai do `GET`) vem antes das telas, com os testes da API e a documentação.
- **Ambiente do PC Ubuntu (preparado em 09/11/2026):** sem `sudo` e sem `curl`, então tudo ficou na pasta do usuário: PHP 8.4 + Composer em `~/.config/herd-lite/bin` (binários do php.new baixados com `wget`) e Node 26 LTS + npm em `~/.local/node` (tarball oficial, checksum conferido); os dois entram no `PATH` pelo `~/.bashrc`. `.env` criado do `.env.example`, banco SQLite criado e semeado, 239 testes e Pint ok. A rede deste PC bloqueia o `registry.npmjs.org` (o usuário confirmou no terminal dele), mas o espelho oficial `registry.yarnpkg.com` (mesmos pacotes) responde: o `~/.npmrc` **deste PC** aponta para ele (fora do repositório; o outro PC e o CI usam o registro padrão). Git deste repositório configurado com `ericjunq`, como nos commits anteriores.
- **Conceitos já explicados ao usuário:** não precisa de branches separadas (o CI roda na `main`; branches + PR só para bloquear merge, quando houver mais pessoas); repositório público não gasta minutos do Actions. Antes: fila e jobs; por que email não deve diferenciar maiúsculas; opção A do link do front (Angular, sem Blade); prazo da confirmação até a véspera (alternativa avaliada e descartada). Em 09/11 (passo 10): **alertas** (o que falha em silêncio hoje; alerta sem dados pessoais, enviado sem passar pela fila, no máximo 1 por hora por problema; monitor externo do `/up` para quando o próprio email cair) e **backup** (regra 3-2-1; banco gerenciado > `spatie/laravel-backup` com armazenamento externo > script manual; `.env` separado num gerenciador de senhas; retenção 7 diários/4 semanais/6 mensais; teste mensal de restauração; LGPD: criptografia, acesso restrito e refazer as anonimizações depois de uma restauração). Também em 09/11: **plataformas de hospedagem** (Laravel Cloud × VPS em São Paulo × VPS + Forge × servidor da faculdade; perguntar antes à TI; registrado em `HOSPEDAGEM.md`); **análise visual do front de referência** (pontos fortes e ajustes); e, na proposta do passo 11, **onde guardar o token** (`sessionStorage` some ao fechar a aba; `localStorage` fica exposto por mais tempo a um ataque XSS; cookie do Sanctum é o mais seguro, mas exige o mesmo domínio e mudanças na API) e **por que não usar o Google Fonts** (o Google recebe o IP de cada visitante, que é dado pessoal, e o site fica mais lento). No PC Ubuntu (09/11): **"fonte" é o arquivo da tipografia**, não a hospedagem do sistema (o usuário tinha entendido assim); **pasta fora do projeto** (o Claude alcança outras pastas com permissão; a separação que importa é a de execução e deploy); **push por HTTPS** (o GitHub pede um token de acesso pessoal no lugar da senha, com Contents e Workflows; `credential.helper store` para não repetir); **como subir o front** (`php artisan serve` + `cd frontend && npm start`, http://localhost:4200; até o 11.2 só aparece o visual). Em 10/11, as decisões 1 a 4 do 11.2: **calendário responsivo** (a mesma semana em colunas no computador e empilhada no celular), **query params** (só data e horário, nada pessoal), **enumeração de RA** pelo `exists` na consulta de disponibilidade e **por que não lembrar o RA** em computador compartilhado. Tudo isso também está no README ("Front-end (Angular)" e "Preparar um PC novo").
- **Código sem nenhum comentário** desde 09/11/2026, inclusive os padrões do Laravel nos `config/`. Manter assim (regra no `CLAUDE.md`).

## 🧵 Trilha de ideias (histórico curto do raciocínio, mais recente no fim)

Registrar aqui cada nova ideia, decisão ou mudança de rumo, com o porquê, em uma ou duas linhas. Serve para uma conversa nova seguir a mesma linha de pensamento.

- **Base (05–06/11):** API Laravel só backend; aluno sem login (RA), admin com Sanctum. Disponibilidade de equipamentos **calculada** (o usuário quis uma coluna "disponível"; foi explicado que ela não representa data e horário). Repositório só para consulta complexa.
- **Regras de data:** antecedência passou de 7 dias para **10 dias úteis**; limite de 3 por curso na quadra; reservas para hoje até 20 min antes do fim. Datas bloqueadas e feriados (Yasumi) revogam reservas com email.
- **Qualidade (06/11):** auditoria (colunas + histórico antes/depois); compatibilidade SQLite/MySQL/Postgres; limpeza (Pint, código morto); **nenhum comentário no código**; commits separados sem coautoria; guias para continuar em outro PC.
- **LGPD virou padrão (06/11):** gestão de alunos com desativação e **anonimização** (inclusive no histórico); consulta das próprias reservas exige **RA + email via POST**.
- **Devolução de equipamentos:** registro por item no próprio `item_reservas` (model pivot). O usuário **recusou** bloquear o aluno com item não devolvido; o controle fica no registro, no filtro e no email.
- **Links por email (09/11):** token aleatório com hash, uso único e validade, em tabela genérica (`CANCELAR`/`CONFIRMAR`). Link aponta para o **front Angular** (opção A, sem Blade).
- **Confirmação:** pedido **2 dias úteis** antes; prazo até a **véspera** (dia corrido). Ideia do usuário: reserva dentro da janela pede confirmação **já na criação**. Alternativa "24h depois do email" foi avaliada e descartada (ganho pequeno, casos estranhos).
- **Admins e senhas:** convite (2 dias) e redefinição (60 min) em tabelas de token separadas. O usuário pediu email com maiúsculas diferenciadas; foi explicado o risco (contas duplicadas, login falhando, bancos diferentes) e ficou **minúsculas**. Ideia do usuário: **admin principal** que não pode ser desativado nem editado por outros.
- **Histórico de auditoria:** uma rota só, com filtros; morph map com nomes curtos para o histórico não depender do nome das classes.
- **Horários livres:** modo FIXO/LIVRE por espaço; blocos de 30 min; equipamentos pelo **pico de uso** (calculado em PHP, para manter a compatibilidade entre bancos e o desempenho); calendário LIVRE sem equipamentos por bloco, para a resposta não ficar enorme.
- **CI:** GitHub Actions com os 3 bancos. Primeira execução verde, sem ajustes. O usuário trabalha só na `main` (sem branch protection por enquanto).
- **Prioridades reforçadas pelo usuário (09/11):** **desempenho sempre priorizado** e **LGPD no sistema inteiro**, não só em partes; a trilha de ideias é atualizada a cada ideia, implementação, alteração ou decisão.
- **Alertas e backup explicados (09/11):** alertas sem dados pessoais, fora da fila, com limite de repetição, mais monitor externo do `/up`. Backup 3-2-1, com retenção e teste de restauração; LGPD no backup (criptografia; refazer anonimizações após restaurar). Recomendação: banco gerenciado, senão `spatie/laravel-backup` com armazenamento externo. Pergunta em aberto: incluir o spatie já no passo 10 ou esperar a hospedagem.
- **Respostas do usuário (09/11):** alertas para os admins ativos e para ele (`ALERTAS_EMAIL`); monitor externo depois (documentado); backup na hora do deploy; plataforma ainda não escolhida. **Recomendação de plataforma dada:** primeiro perguntar à TI da faculdade se há exigência de servidor próprio ou de dados no Brasil. Se não houver e o orçamento em dólar for ok: **Laravel Cloud** (menos manutenção: fila, agendador e banco gerenciado com backup). Se precisar de dados no Brasil ou de custo menor: **VPS na região de São Paulo** (com o guia do DEPLOY.md, ou com o Laravel Forge para automatizar). Front Angular em hospedagem estática (Cloudflare Pages, Netlify ou Vercel).
- **Decisão 1 do passo 10 (09/11):** traduções pt_BR aprovadas. Na decisão 3, o usuário avisou que o admin de teste vai virar o admin dele. Foi explicado que a senha `Admin` não pode ir para produção e recomendado criar o admin dele como principal pelo comando, com senha forte digitada no servidor.
- **Decisão 3 do passo 10 (09/11):** o usuário preferiu o admin inicial **pelo `.env`** (`ADMIN_INICIAL_*`), mantido enquanto o projeto está em desenvolvimento; depois ele pode remover e criar o admin manualmente. Ficou assim: sem senha no `.env`, o seeder não cria admin; o verificador acusa ❌ se `ADMIN_INICIAL_SENHA` estiver preenchida em produção; o `DEPLOY.md` recomenda `admin:criar-principal`. Ele também pediu as recomendações de plataforma num arquivo para negociar com a TI: `HOSPEDAGEM.md`.
- **Passo 10 implementado (09/11):** o laravel-lang exigia a extensão `bcmath` (ausente no PHP local), então foi instalado só para gerar `lang/pt_BR` e removido (sem dependência). CORS com uma origem só devolve sempre essa origem e o navegador bloqueia as outras. Alertas sem a mensagem do erro (pode ter dados pessoais), enviados sem fila, 1 por hora por problema, e abandonam o envio no primeiro erro de SMTP (desempenho).
- **Front de referência (09/11):** o usuário mostrou o front que um amigo começou em React + Tailwind (`github.com/ericjunq/unifg-reservas`, pasta `frontend`: Home, Reserva, Cadastro). Análise: a identidade visual é boa (azul e amarelo da UniFG, Montserrat, títulos em itálico, tela de reserva com calendário e resumo lateral), mas há problemas: imagens de 2,7 MB e 2,2 MB; hero com texto dentro da imagem e cara de gerado por IA; telas só da quadra; dados fixos que não batem com a API (horários, fins de semana, materiais, cursos); campo de telefone que a API não coleta; email que falta; acessibilidade e cores inconsistentes. Recomendação: manter a identidade e o layout, refazer em Angular (com Tailwind) ligado à API.
- **Esclarecimento do usuário (09/11):** do front de referência, interessa **só a identidade visual** (cores, fonte, estilo), não o código nem as telas. Feedback dado só sobre o visual.
- **Base visual aprovada (09/11):** o usuário aprovou seguir a identidade de referência com os ajustes (paleta única, foto real, ícones de traço, admin neutro). Documentada no README.
- **Proposta do passo 11 (09/11):** Angular + Tailwind + CDK, em subpassos 11.1 a 11.8. Recomendações: pasta `frontend/` neste repositório; token em `sessionStorage`; sem biblioteca de componentes pesada; signals; fonte hospedada no site (LGPD); página de privacidade; sem rastreamento.
- **Documentação renomeada (09/11):** a pedido do usuário, o `DOCUMENTACAO.md` virou o `README.md` (é o que o GitHub mostra na página do repositório) e o README padrão do Laravel foi apagado.
- **Ambiente no PC Ubuntu (09/11):** PHP, Composer e Node instalados sem `sudo`, na pasta do usuário; testes verdes. Falta confirmar o acesso ao registro do npm.
- **Decisões do passo 11 (09/11):** o usuário preferia o front **fora** do repositório (back e front separados, conversando só pela API), mas ficou com a pasta `frontend/` na raiz por não saber até onde o Claude alcança. Foi explicado que a separação que importa é a de execução e deploy (o front só consome a API), não a pasta; dá para mover para um repositório próprio depois sem perder o histórico (`git subtree split`). Token: o usuário pediu recomendação; como back e front ficarão em domínios separados, **`sessionStorage` + `Bearer`** (o cookie do Sanctum exige o mesmo domínio). Tailwind, signals, sem rastreamento e página de privacidade com **todos os dados coletados**: aprovados. Foto: depois. Fonte: explicado que é o arquivo da tipografia (Montserrat), servido junto com o site em vez de vir dos servidores do Google.
- **11.1 implementado (09/11):** projeto gerado com `ng new` (Angular 22.2: sem zone.js, Vitest e Tailwind já vêm prontos) e limpo de comentários. Ícones e CDK ficaram para quando uma tela usar (sem dependência sem uso). Um teste mostrou que o login recusado também responde 401 ("Credenciais inválidas"): o front só troca a mensagem do 401 quando havia sessão, e mostra a da API nos outros casos. O 5xx nunca mostra o texto do servidor (pode ter detalhes internos). O token nunca vai para URLs fora da API. Orçamento do pacote inicial: 300 kB aviso / 450 kB erro (hoje 247 kB, 67 kB comprimido).
- **Proposta do 11.2 (09/11):** o usuário percebeu que só dá para testar o front quando as telas chamarem a API; o 11.2 é o primeiro com chamadas. Ao revisar o endpoint de disponibilidade, apareceu um ponto de LGPD antigo: ele aceita o RA na query string (GET) e a validação `exists` revela se o RA está cadastrado. Recomendação: retirar o parâmetro e o front usar o `curso_id`.
- **Coleta de uso do Angular CLI (09/11):** ao rodar `npm start`, o CLI perguntou se podia coletar dados de uso; o usuário recusou e o `"analytics": false` ficou no `angular.json` (commitado, coerente com "sem rastreamento").
- **CI verde com o front (10/11):** o usuário deu push; os 5 jobs passaram (Pint/audit, SQLite, MySQL, Postgres e Front-end).
- **Resposta do usuário ao 11.2 (10/11):** aprovou 5 (com a condição de não haver inconsistência ou trava: a escolha não reserva nada até o POST), 6 e 7. Pediu explicação de 1 a 4. Explicado: (1) "lista" é a **mesma semana** com os dias empilhados na tela estreita do celular, não meses; (2) sim, *query params*, só data e horário; (3) RA na URL vai para logs e histórico, e a validação `exists` responde diferente para RA cadastrado (dá para descobrir quem tem cadastro testando RAs); o `curso_id` dá a mesma informação sem dado pessoal; (4) guardar o RA no navegador deixaria o RA do aluno anterior no computador do laboratório.
- **Decisões 1, 2, 4 e 5 do 11.2 aprovadas (10/11).** Na 3, o usuário propôs que o RA puxe o curso. Avaliado como bom para o aluno; recomendado com o RA no corpo de um POST (fora da URL e dos logs), limite de 10/min e sem o nome do curso na resposta.
- **Agora:** aguardando o ok da versão da decisão 3 com POST; depois, implementar o 11.2 (começando pela API).

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
- [x] Mensagens de validação em português (pt_BR)
- [x] CORS só para o front, cabeçalhos de segurança, HTTPS em produção e proxies confiáveis
- [x] Alertas por email aos admins e ao `ALERTAS_EMAIL` quando uma tarefa agendada ou um job falha
- [x] Logs diários guardados por 14 dias
- [x] `php artisan sistema:verificar-producao` e guia de implantação (`DEPLOY.md`)
- [x] 239 testes automáticos passando
- [x] Front Angular: base pronta (paleta, fonte, cliente da API, layouts, rotas sob demanda) com 22 testes e job no CI

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
- Produção documentada no README: cron do `schedule:run` e worker da fila com supervisor. Aviso de falha por email fica para o passo 10.

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
- Sem branch protection por enquanto (trabalho direto na `main`); explicado no README como ativar.

### 10. ✅ Preparação para produção — *concluído em 09/11/2026 (239 testes)*
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
  1. traduções pt_BR geradas com o laravel-lang (só dev), com os arquivos no projeto e sem comentários: **aprovado pelo usuário (09/11)**;
  2. alertas: **decisão do usuário (09/11): para todos os admins ativos** (são eles que cuidam da gerência) **e também para `ALERTAS_EMAIL`** (o email dele ou da TI), sem dados pessoais;
  3. seeder de admin: **decisão do usuário (09/11): dados do admin inicial no `.env`** (`ADMIN_INICIAL_*`), enquanto estiver em desenvolvimento. Histórico da discussão: O usuário disse (09/11) que o admin de teste vai virar o admin dele. Recomendação enviada: em produção, o admin dele é criado como **principal** pelo `admin:criar-principal` (mesmo nome e email, senha forte digitada na hora, nunca no código); o `AdminSeeder` com a senha `Admin` continua só em dev. Alternativa: o seeder ler nome, email e senha do `.env` em produção (descartada por deixar a senha num arquivo);
  4. backup: **decisão do usuário (09/11): fica para a hora do deploy**, quando a plataforma for escolhida (as opções explicadas estão em "Conceitos já explicados"). O `DEPLOY.md` traz a seção de backup como pendente, com as opções;
  5. onde vai hospedar? **O usuário ainda vai decidir** e pediu recomendação (dada em 09/11, ver Trilha de ideias). Até lá, o guia é genérico, com o caminho de VPS Linux com Nginx;
  6. **monitor externo do `/up`** (UptimeRobot ou Healthchecks.io): **fica para depois**, por decisão do usuário. A ideia está documentada nos opcionais e no README.
- **Desempenho (reenviado em 09/11 com as prioridades permanentes):** `config/route/view/event:cache`, `--optimize-autoloader`, OPcache, fila com worker e logs em `warning`; o verificador confere os caches. Redis para cache e sessões fica documentado como evolução.
- **LGPD:** HTTPS e HSTS, CORS restrito, `APP_DEBUG` desligado, **alertas sem dados pessoais** (só a tarefa, a data e o tipo do erro), logs por 14 dias e sem dados pessoais, backups criptografados com acesso restrito e prazo de guarda, sem dados de teste em produção.
- **Antes:** CORS, fila, cron e supervisor já estavam previstos aqui; cron e supervisor já estão documentados no README (passo 4).

### 11. 🔄 Front-end em Angular — *aprovado em 09/11/2026, em implementação*
- **Base visual (decisão do usuário, 09/11):** a identidade do front de referência (`github.com/ericjunq/unifg-reservas`, pasta `frontend`), **com os ajustes**: paleta única, foto real no topo com o título em texto, ícones de traço em vez de emojis, tom esportivo só na área pública e admin neutro. Do front de referência vale **só o visual**, não o código. Detalhes em README, "Front-end: identidade visual".
- **Stack proposta:** Angular (versão estável mais recente, componentes standalone, signals, sem zone.js), Tailwind CSS v4, Angular CDK (diálogos, foco e teclado acessíveis), ícones Lucide, Montserrat hospedada no próprio site, testes unitários com o runner padrão do Angular e Playwright nos fluxos principais.
- **Telas que o front precisa ter** (links dos emails já usados pela API): `/reservas/cancelar/:token`, `/reservas/confirmar/:token`, `/aceitar-convite/:token`, `/redefinir-senha/:token`.
- **Subpassos (cada um com proposta curta, testes e commit):**
  - ✅ 11.1 Base (*concluído em 09/11/2026, 22 testes do front*): projeto, Tailwind com a paleta, fontes, layouts público e admin, cliente HTTP (URL da API, token, erros 401/409/422/429), rotas com carregamento sob demanda, datas em pt-BR, testes e CI do front. Ícones e CDK adiados para quando uma tela usar.
  - 11.2 Público, reserva — *proposta enviada em 09/11/2026, aguardando aprovação*:
    - **Telas:** início com os espaços ativos e filtro por tipo (`/`); calendário do espaço (`/espacos/:id`); reserva com RA, equipamentos e resumo lateral (`/espacos/:id/reservar`); cadastro do aluno (`/cadastro`); tela de sucesso explicando os emails (registrada / confirme).
    - **Arquivos:** `publico/espacos/` (service + tipos), `publico/inicio/` (lista), `publico/calendario/` (FIXO em grade, LIVRE em blocos), `publico/reservar/`, `publico/cadastro/`, `compartilhado/` (botão, campo com erro, aviso de erro), specs de cada um, `publico.routes.ts`; dependência `@lucide/angular`.
    - **Decisões em aberto, com recomendação:**
      1. calendário por **semana (seg–sex), navegável**, uma requisição por semana guardada em memória; no celular, lista por dia. Alternativa: mês inteiro (resposta bem maior nos espaços LIVRE);
      2. data e horário escolhidos **na URL** (`?data=&horario=` ou `?data=&inicio=&fim=`): não são dados pessoais, e voltar ou recarregar não perde a escolha. RA e email nunca na URL;
      3. **retirar o `registro_academico` do `GET /api/espacos/{id}/disponibilidade`** (mudança pequena na API) e o front usar só o `curso_id` para mostrar quantas reservas o curso ainda tem. Hoje o RA vai na URL (fica em log de servidor e histórico do navegador) e o `exists` da validação revela se um RA está cadastrado, o que contraria as regras de LGPD do projeto;
      4. **não lembrar o RA** no navegador (computadores compartilhados); o aluno digita a cada reserva;
      5. RA não cadastrado: mensagem com link "Fazer meu cadastro", que volta para a reserva com a escolha preservada (`/cadastro?voltar=` só com o caminho interno): **aprovado (10/11)**, com a condição de não gerar inconsistência nem problema de trava. Explicado: escolher um horário **não segura** nada no banco; a reserva só nasce no `POST /api/reservas`, que continua com as travas de linha da API. Se outra pessoa reservar no meio do cadastro, a reserva recebe 409 e o calendário recarrega (decisão 6);
      6. sem polling: o calendário recarrega ao abrir, ao trocar de semana e quando a reserva recebe 409 (horário ocupado por outra pessoa), com aviso: **aprovado (10/11)**;
      7. testes unitários agora; **Playwright fica para o 11.8** (precisa baixar navegadores, e a rede deste PC bloqueia alguns downloads): **aprovado (10/11)**.
    - **Decisões 1, 2 e 4: aprovadas (10/11)** depois da explicação. **5** confirmada (sem inconsistência: nada fica reservado até o POST).
    - **Decisão 3 (ideia do usuário, 10/11):** em vez de trocar o RA pelo curso, o aluno informa o RA e o sistema descobre o curso sozinho. Avaliação: dá para fazer sem os dois problemas, se o RA sair da URL. **Recomendação enviada, aguardando ok:**
      - `POST /api/espacos/{id}/disponibilidade/curso` (nome a confirmar) com `registro_academico`, `de` e `ate` no **corpo**: devolve o mesmo calendário com os dados do curso (restantes por semana, `do_curso`, `disponivel_para_o_curso`), **sem o nome do curso** na resposta;
      - limite de **10 por minuto** (igual ao `POST /api/reservas`), em vez de 60;
      - o `GET` continua público e aceita só `curso_id`; o `registro_academico` sai dele;
      - no front, o RA digitado no calendário fica **só na memória** e já vem preenchido no formulário de reserva (sem digitar duas vezes; some ao recarregar a página, como na decisão 4);
      - o que sobra: a resposta ainda revela se o RA existe, mas isso o `POST /api/reservas` já revela hoje (o aluno precisa saber que tem de se cadastrar); com o POST e o limite de 10 por minuto, o risco fica igual ao que já existe.
    - **Desempenho:** uma requisição por semana (com cache em memória); equipamentos do FIXO vêm do próprio calendário; no LIVRE, a disponibilidade dos equipamentos só é pedida depois de escolher o intervalo; espaços carregados uma vez por visita; cursos só na tela de cadastro; cada tela no seu pacote.
    - **LGPD:** RA e email só no corpo do POST; formulário limpo depois do sucesso; calendário mostra só nome e curso (como a API); aviso curto no cadastro sobre o que é coletado e para quê (o link para a página de privacidade entra no 11.3); nenhum dado pessoal em `localStorage` ou URL.
  - 11.3 Público, pós-reserva: minhas reservas (RA + email), cancelar e confirmar pelo link, página de privacidade.
  - 11.4 Admin, acesso: login, esqueci e redefinir senha, aceitar convite, trocar senha, sair.
  - 11.5 Admin, reservas: lista com filtros e totais, detalhe, revogar, eventos, devolução, exportação.
  - 11.6 Admin, cadastros: espaços, equipamentos, cursos, alunos (com anonimização), datas bloqueadas, admins.
  - 11.7 Admin, histórico de auditoria.
  - 11.8 Acabamento: acessibilidade, orçamento de desempenho, guia de deploy do front.
- **Decisões (09/11/2026):**
  1. código na **pasta `frontend/` deste repositório**: aprovado. O usuário preferia um repositório separado; ficou na raiz, com a opção de separar depois (`git subtree split`). Back e front continuam independentes: o front só consome a API e tem deploy próprio;
  2. token do admin em **`sessionStorage`** com `Authorization: Bearer`, saída automática no 401: recomendação aceita (o usuário pediu a recomendação). Motivo: API e front em domínios separados; o cookie do Sanctum exigiria o mesmo domínio e fica como evolução;
  3. **Tailwind + componentes próprios + Angular CDK** (o CDK só entra quando houver diálogo): aprovado;
  4. **signals e services**, sem NgRx: aprovado;
  5. fonte **Montserrat servida junto com o site** (pacote `@fontsource`, só o alfabeto latino e 3 pesos), sem Google Fonts: explicado ao usuário e mantido;
  6. foto real da quadra: **depois**; até lá, fundo com as cores e os detalhes gráficos;
  7. **página de privacidade** listando **todos os dados coletados**, para quê, por quanto tempo e como pedir a anonimização: aprovado (texto a validar com a faculdade);
  8. **sem ferramentas de rastreamento**: aprovado.
- **Desempenho:** área pública e admin em pacotes separados, carregados sob demanda (o aluno não baixa o admin); orçamento de tamanho no build; imagens WebP/AVIF com versões por tamanho de tela; filtros com espera ao digitar; paginação e exportação feitas pela API.
- **LGPD:** RA e email só no corpo das requisições e só na memória da tela (nada em URL, `localStorage` ou log); tokens dos links trocados logo na abertura da página e o front com `Referrer-Policy: no-referrer`; mensagens genéricas iguais às da API; sem rastreamento; página de privacidade.
- **Regras do projeto valem no front:** nenhum comentário no código, nomes em português, um commit por alteração, testes antes de cada commit, proposta antes de cada subpasso.

### 12. ⬜ Final — API para terceiros autorizados
- Tabela de **clientes de API** gerenciada pelos admins, com tokens Sanctum por cliente e permissões por *ability* (ex.: `reservas:ler`, `reservas:criar`), que podem ser revogadas.
- Rotas versionadas em `/api/v1`, limite de requisições por cliente, documentação OpenAPI (Swagger) e auditoria indicando qual cliente fez cada alteração.

### Opcionais (sem data)
- [ ] **Registro de retirada** dos equipamentos (hoje só a devolução é registrada)
- [ ] **Exportação muito grande:** trocar o `lazy()` (OFFSET) por paginação por chave quando o volume passar de dezenas de milhares de linhas
- [ ] **Perfis de permissão** para admins (ver passo 5)
- [ ] **Horários fixos diferentes por espaço** (hoje 19:00 e 20:30 valem para todos)
- [ ] **Monitor externo da API** (UptimeRobot ou Healthchecks.io consultando `/up`): avisa por outro canal (app, SMS, Telegram) quando a API cai ou quando o próprio email está fora do ar, caso em que os alertas por email não saem. Gratuito; configura-se fora do código, na hora do deploy
- [ ] **Backup do banco**: decidir na hora do deploy, conforme a plataforma (banco gerenciado com backup automático, ou `spatie/laravel-backup` com armazenamento externo)
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
- [x] Alerta do sistema (tarefa agendada ou job que falhou), para admins ativos e `ALERTAS_EMAIL`
