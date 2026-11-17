import { Page, Route } from '@playwright/test';
import {
  deIso,
  ehDiaUtil,
  formatarDia,
  formatarDiaCurto,
  paraIso,
  segundaDaSemana,
  somarDias,
} from '../src/app/publico/datas';

const hoje = new Date();
const segundaAtual = segundaDaSemana(hoje);
const segundaSeguinte = somarDias(segundaAtual, 7);

export const datas = {
  hoje: paraIso(hoje),
  ontem: paraIso(somarDias(hoje, -1)),
  segundaAtual: paraIso(segundaAtual),
  sextaAtual: paraIso(somarDias(segundaAtual, 4)),
  segundaSeguinte: paraIso(segundaSeguinte),
  sextaSeguinte: paraIso(somarDias(segundaSeguinte, 4)),
  tercaSeguinte: paraIso(somarDias(segundaSeguinte, 1)),
  quartaSeguinte: paraIso(somarDias(segundaSeguinte, 2)),
  reservasAte: paraIso(somarDias(hoje, 14)),
  calendarioAte: paraIso(somarDias(hoje, 60)),
};

export function tituloDaSemana(segunda: string): string {
  return `Semana de ${formatarDiaCurto(segunda)} a ${formatarDiaCurto(paraIso(somarDias(deIso(segunda), 4)))}`;
}

export function rotuloDoHorario(data: string, inicio: string, fim: string): RegExp {
  return new RegExp(`^${inicio}–${fim} Livre · reservar ?, ${formatarDia(data)}$`);
}

export const espacos = [
  {
    id: 1,
    nome: 'Quadra Poliesportiva',
    tipo: 'QUADRA',
    limite_semanal_por_curso: 3,
    modo_horario: 'FIXO',
    abre_as: null,
    fecha_as: null,
    duracao_maxima_minutos: null,
  },
  {
    id: 4,
    nome: 'Laboratório de Informática',
    tipo: 'LABORATORIO',
    limite_semanal_por_curso: null,
    modo_horario: 'LIVRE',
    abre_as: '07:00',
    fecha_as: '22:00',
    duracao_maxima_minutos: 180,
  },
];

function horarioFixo(data: string, horario: string, inicio: string, fim: string) {
  const ocupado = data === datas.tercaSeguinte && horario === 'PRIMEIRO_HORARIO';
  return {
    horario,
    inicio,
    fim,
    situacao: ocupado ? 'RESERVADO' : data > datas.reservasAte ? 'INDISPONIVEL' : 'LIVRE',
    motivo: data > datas.reservasAte ? 'Ainda não abriu.' : null,
    reserva: ocupado
      ? {
          id: 9,
          tipo: 'ALUNO',
          descricao: null,
        }
      : null,
    equipamentos: [{ id: 1, nome: 'Bola de futsal', total: 4, disponivel: 4 }],
  };
}

function calendarioFixo(de: string) {
  const dias = [0, 1, 2, 3, 4].map((deslocamento) => paraIso(somarDias(deIso(de), deslocamento)));
  return {
    espaco: { ...espacos[0], bloco_minutos: null },
    de,
    ate: dias[4],
    reservas_ate: datas.reservasAte,
    calendario_ate: datas.calendarioAte,
    curso: null,
    dias: dias.map((data) => {
      const reservavel = data > datas.hoje && data <= datas.reservasAte && ehDiaUtil(data);
      const abreEm = data > datas.reservasAte ? paraIso(somarDias(deIso(data), -14)) : null;
      return {
        data,
        reservavel,
        motivo: reservavel
          ? null
          : abreEm
            ? `Abre para reserva em ${formatarDiaCurto(abreEm)}.`
            : 'Fora do período de reservas.',
        abre_em: abreEm,
        horarios: [
          horarioFixo(data, 'PRIMEIRO_HORARIO', '19:00', '20:30'),
          horarioFixo(data, 'SEGUNDO_HORARIO', '20:30', '22:00'),
        ],
      };
    }),
  };
}

function calendarioLivre(de: string) {
  const dias = [0, 1, 2, 3, 4].map((deslocamento) => paraIso(somarDias(deIso(de), deslocamento)));
  return {
    espaco: { ...espacos[1], bloco_minutos: 15 },
    de,
    ate: dias[4],
    reservas_ate: datas.reservasAte,
    calendario_ate: datas.calendarioAte,
    curso: null,
    dias: dias.map((data) => {
      const reservavel = data > datas.hoje && data <= datas.reservasAte && ehDiaUtil(data);
      const abreEm = data > datas.reservasAte ? paraIso(somarDias(deIso(data), -14)) : null;
      return {
        data,
        reservavel,
        motivo: reservavel
          ? null
          : abreEm
            ? `Abre para reserva em ${formatarDiaCurto(abreEm)}.`
            : 'Fora do período de reservas.',
        abre_em: abreEm,
        fim_minimo: null,
        ocupados:
          data === datas.tercaSeguinte
            ? [
                {
                  inicio: '14:00',
                  fim: '15:30',
                  situacao: 'RESERVADO',
                  motivo: null,
                  reserva: {
                    id: 12,
                    tipo: 'ALUNO',
                    descricao: null,
                  },
                },
              ]
            : [],
      };
    }),
  };
}

function calendarioAdmin(de: string) {
  const dias = [0, 1, 2, 3, 4, 5, 6].map((deslocamento) =>
    paraIso(somarDias(deIso(de), deslocamento)),
  );
  return {
    espaco: { ...espacos[1], bloco_minutos: 15 },
    de,
    ate: dias[6],
    dias: dias.map((data) => ({
      data,
      reservavel: data > datas.hoje && ehDiaUtil(data),
      motivo: ehDiaUtil(data) ? null : 'Fim de semana.',
      fim_minimo: null,
      ocupados:
        data === datas.tercaSeguinte
          ? [
              {
                inicio: '14:00',
                fim: '15:30',
                situacao: 'RESERVADO',
                motivo: null,
                reserva: {
                  id: 77,
                  tipo: 'ALUNO',
                  descricao: null,
                  reservado_por: { tipo: 'ALUNO', nome: 'Carla Dias', curso: 'Enfermagem' },
                  situacao_confirmacao: 'AGUARDANDO_CONFIRMACAO',
                },
              },
            ]
          : [],
    })),
  };
}

export interface Chamada {
  metodo: string;
  url: string;
  corpo: unknown;
}

export const tokenDoEmail = 'b'.repeat(64);

export const codigoDoEmail = '123456';

const adminFalso = { id: 1, nome: 'Eric', email: 'eric@fac.edu', principal: true };

const reservaDoAluno = {
  id: 77,
  data_reserva: datas.tercaSeguinte,
  hora_inicio: '14:00',
  hora_fim: '15:30',
  status: 'RESERVADO',
  tipo: 'ALUNO',
  espaco: { id: 4, nome: 'Laboratório de Informática', tipo: 'LABORATORIO' },
  equipamentos: [],
  confirmar_ate: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
  confirmada_em: null,
  reconfirmacao_pedida_em: null,
  reconfirmar_ate: null,
  reconfirmada_em: null,
};

const reservaDoAdmin = {
  ...reservaDoAluno,
  origem: 'SITE',
  descricao: null,
  aluno: {
    id: 3,
    nome_completo: 'Carla Dias',
    registro_academico: '2024000009',
    email: 'carla@email.com',
    curso: { id: 2, nome: 'Enfermagem' },
  },
  situacao_confirmacao: 'AGUARDANDO_CONFIRMACAO',
  termos_aceitos_em: null,
  termos_versao: null,
  criado_por: null,
  atualizado_por: null,
  criado_em: new Date().toISOString(),
  atualizado_em: null,
};

const historicoFalso = [
  {
    id: 12,
    tipo: 'RESERVA',
    registro_id: 77,
    acao: 'ATUALIZACAO',
    campos_alterados: ['status', 'justificativa_revogacao'],
    antes: { status: 'RESERVADO', justificativa_revogacao: null },
    depois: { status: 'REVOGADO', justificativa_revogacao: 'Manutenção' },
    feito_por: { id: 1, nome: 'Eric' },
    em: new Date(Date.now() - 60 * 60 * 1000).toISOString(),
  },
  {
    id: 11,
    tipo: 'RESERVA',
    registro_id: 77,
    acao: 'CRIACAO',
    campos_alterados: ['data_reserva', 'status'],
    antes: null,
    depois: { data_reserva: datas.tercaSeguinte, status: 'RESERVADO' },
    feito_por: null,
    em: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
  },
  {
    id: 10,
    tipo: 'CURSO',
    registro_id: 2,
    acao: 'CRIACAO',
    campos_alterados: ['nome', 'ativo'],
    antes: null,
    depois: { nome: 'Direito', ativo: true },
    feito_por: { id: 1, nome: 'Eric' },
    em: new Date(Date.now() - 3 * 60 * 60 * 1000).toISOString(),
  },
];

const termos = {
  versao: '2026-11',
  rascunho: true,
  termos_de_uso: [
    { titulo: 'Como funciona a reserva', paragrafos: ['Toda reserva precisa ser confirmada.'] },
  ],
  privacidade: [{ titulo: 'Quais dados e para quê', paragrafos: ['Nome, RA, email e curso.'] }],
};

export async function entrarComoAdmin(page: Page): Promise<void> {
  await page.goto('/admin/entrar');
  await page.getByLabel('Email').fill('eric@fac.edu');
  await page.getByLabel('Senha').fill('Senha1234');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await page.getByLabel('Código').fill(codigoDoEmail);
  await page.getByRole('button', { name: 'Entrar' }).click();
}

export async function prepararApi(page: Page): Promise<Chamada[]> {
  const chamadas: Chamada[] = [];
  let eventoCriado: Record<string, unknown> | null = null;
  let usoRegistrado: Record<string, unknown> | null = null;
  let reservaPeloAdmin: Record<string, unknown> | null = null;
  let espacoCriado: Record<string, unknown> | null = null;
  const cursosDoAdmin: Record<string, unknown>[] = [{ id: 2, nome: 'Direito', ativo: true }];
  const adminsConvidados: Record<string, unknown>[] = [];
  const movimentacoesDoAdmin: Record<string, unknown>[] = [
    {
      id: 1,
      tipo: 'ENTRADA',
      quantidade: 2,
      estado: 'Cadastro do equipamento.',
      reserva: null,
      feito_por: { id: 1, nome: 'Eric' },
      quantidade_total_depois: 2,
      em_manutencao_depois: 0,
      em: new Date(Date.now() - 86_400_000).toISOString(),
    },
  ];
  let alunoDoAdmin: Record<string, unknown> = {
    id: 3,
    nome_completo: 'Carla Dias',
    registro_academico: '2024000009',
    email: 'carla@email.com',
    curso: { id: 2, nome: 'Direito' },
    ativo: true,
    anonimizado_em: null,
  };
  const daquiAUmAno = new Date(Date.now() + 365 * 86_400_000).toISOString();
  const sistemasDoAdmin: Record<string, unknown>[] = [
    {
      id: 3,
      nome: 'App da Atlética',
      organizacao: 'Atlética de Computação',
      responsavel: { nome: 'Bruno Lima', email: 'bruno@atletica.org' },
      finalidade: 'Reservar a quadra para os treinos.',
      permissoes: ['espacos:ler', 'reservas:criar'],
      limite_por_minuto: 60,
      ativo: true,
      situacao: 'ATIVO',
      termos_versao: '2026-11',
      termos_aceitos_em: new Date(Date.now() - 86_400_000).toISOString(),
      acesso_expira_em: daquiAUmAno,
      link_aceite: null,
    },
  ];
  let equipamentoDoAdmin: Record<string, unknown> = {
    id: 5,
    espaco_id: 4,
    nome: 'Microscópio',
    quantidade_total: 2,
    ativo: true,
    aceita_solicitacao: false,
    espacos_compartilhados: [],
  };

  await page.route('**/api/**', async (route: Route) => {
    const requisicao = route.request();
    const url = new URL(requisicao.url());
    chamadas.push({
      metodo: requisicao.method(),
      url: url.pathname + url.search,
      corpo: requisicao.postDataJSON?.() ?? null,
    });
    const caminho = url.pathname.replace('/api', '');
    const json = (dados: unknown, status = 200) =>
      route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(dados) });

    if (caminho === '/espacos') {
      return json({ data: espacos });
    }
    if (caminho === '/cursos') {
      return json({ data: [{ id: 2, nome: 'Direito', ativo: true }] });
    }
    if (caminho === '/disponibilidade/versao') {
      return json({ data: { versao: 'v1' } });
    }
    if (caminho === '/espacos/1/disponibilidade') {
      return json({ data: calendarioFixo(url.searchParams.get('de')!) });
    }
    if (caminho === '/espacos/4/disponibilidade') {
      return json({ data: calendarioLivre(url.searchParams.get('de')!) });
    }
    if (caminho === '/espacos/1/equipamentos/solicitaveis') {
      return json({
        data: [
          {
            id: 5,
            nome: 'Microscópio',
            espaco: 'Laboratório de Informática',
            total: 2,
            disponivel: 2,
          },
        ],
        responder_ate: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString(),
      });
    }
    if (caminho === `/reservas/manter/${tokenDoEmail}`) {
      if (requisicao.method() === 'POST') {
        return json({ data: { ...reservaDoAluno, mantida_em: new Date().toISOString() } });
      }
      return json({
        data: {
          ...reservaDoAluno,
          confirmada_em: new Date().toISOString(),
          manter_ate: new Date(Date.now() + 12 * 60 * 60 * 1000).toISOString(),
          mantida_em: null,
        },
        pode_manter: true,
      });
    }
    if (caminho === '/espacos/4/equipamentos/disponibilidade') {
      return json({ data: [{ id: 5, nome: 'Microscópio', total: 2, disponivel: 2 }] });
    }
    if (caminho === '/reservas' && requisicao.method() === 'POST') {
      const corpo = requisicao.postDataJSON();
      return json(
        {
          data: {
            id: 50,
            data_reserva: corpo.data_reserva,
            hora_inicio: corpo.hora_inicio ?? '20:30',
            hora_fim: corpo.hora_fim ?? '22:00',
            confirmar_ate: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
            confirmada_em: null,
          },
        },
        201,
      );
    }
    if (caminho === '/login') {
      const corpo = requisicao.postDataJSON();
      if (corpo.email !== 'eric@fac.edu' || corpo.password !== 'Senha1234') {
        return json({ message: 'Credenciais inválidas' }, 401);
      }
      return json({
        etapa: 'CODIGO',
        desafio: 'd'.repeat(64),
        expira_em: new Date(Date.now() + 10 * 60 * 1000).toISOString(),
      });
    }
    if (caminho === '/login/codigo') {
      if (requisicao.postDataJSON().codigo !== codigoDoEmail) {
        return json({ message: 'Código inválido ou vencido. Entre de novo se precisar.' }, 401);
      }
      return json({
        token: 'token-admin',
        expires_at: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
        user: adminFalso,
      });
    }
    if (caminho === '/me') {
      return json({ data: adminFalso });
    }
    if (caminho === '/logout' || caminho === '/logout-geral') {
      return json({ message: 'Até logo.' });
    }
    if (caminho === '/redefinir-senha') {
      return json({ message: 'Senha redefinida. Faça login com a nova senha.' });
    }
    if (caminho === '/termos') {
      return json({ data: termos });
    }
    if (caminho === '/minhas-reservas/reenviar-confirmacao') {
      return json({
        message:
          'Enviamos o email de confirmação de novo. Os links do email anterior não valem mais.',
      });
    }
    if (caminho === '/minhas-reservas') {
      const corpo = requisicao.postDataJSON();
      if (corpo.email !== 'carla@email.com') {
        return json({ message: 'Não encontramos reservas para esses dados.' }, 404);
      }
      return json({
        data: [reservaDoAluno],
        meta: { current_page: 1, last_page: 1, total: 1 },
      });
    }
    if (caminho === `/reservas/confirmacao/${tokenDoEmail}`) {
      if (requisicao.method() === 'POST') {
        return json({ data: { ...reservaDoAluno, confirmada_em: new Date().toISOString() } });
      }
      return json({
        data: reservaDoAluno,
        etapa: 'CONFIRMACAO',
        pode_confirmar: true,
        prazo_confirmacao: reservaDoAluno.confirmar_ate,
        exige_aceite_termos: true,
        termos_versao: '2026-11',
      });
    }
    if (caminho === `/reservas/cancelamento/${tokenDoEmail}`) {
      if (requisicao.method() === 'POST') {
        return json({ data: { ...reservaDoAluno, status: 'CANCELADO' } });
      }
      return json({ data: reservaDoAluno, pode_cancelar: true });
    }
    if (caminho === '/admin/espacos') {
      if (requisicao.method() === 'POST') {
        espacoCriado = { ...requisicao.postDataJSON(), id: 9, ativo: true };
        return json({ data: espacoCriado }, 201);
      }
      return json({
        data: [
          ...espacos.map((espaco) => ({ ...espaco, ativo: true })),
          ...(espacoCriado ? [espacoCriado] : []),
        ],
        meta: { current_page: 1, last_page: 1, total: espacos.length, per_page: 15 },
      });
    }
    if (caminho === '/admin/espacos/9' && espacoCriado) {
      return json({ data: espacoCriado });
    }
    if (caminho === '/admin/alunos/consulta') {
      const corpo = requisicao.postDataJSON();
      const lista = corpo.busca && !'Carla Dias'.includes(corpo.busca) ? [] : [alunoDoAdmin];
      return json({
        data: lista,
        meta: { current_page: 1, last_page: 1, total: lista.length, per_page: 15 },
      });
    }
    if (caminho === '/admin/alunos/3') {
      if (requisicao.method() === 'PATCH') {
        alunoDoAdmin = { ...alunoDoAdmin, ...requisicao.postDataJSON() };
        return json({ data: alunoDoAdmin, reservas_revogadas: 0 });
      }
      return json({ data: alunoDoAdmin });
    }
    if (caminho === '/admin/auditorias') {
      const filtros = url.searchParams;
      const lista = historicoFalso.filter(
        (entrada) =>
          (!filtros.get('tipo') || entrada.tipo === filtros.get('tipo')) &&
          (!filtros.get('registro_id') ||
            entrada.registro_id === Number(filtros.get('registro_id'))) &&
          (filtros.get('sem_admin') !== '1' || entrada.feito_por === null),
      );
      return json({
        data: lista,
        meta: { current_page: 1, last_page: 1, total: lista.length, per_page: 15 },
      });
    }
    if (caminho === '/admin/admins/consulta') {
      return json({
        data: [
          { ...adminFalso, ativo: true, convite_aceito_em: '2026-11-01T10:00:00Z' },
          ...adminsConvidados,
        ],
        meta: { current_page: 1, last_page: 1, total: 1 + adminsConvidados.length, per_page: 15 },
      });
    }
    if (caminho === '/admin/admins' && requisicao.method() === 'POST') {
      const corpo = requisicao.postDataJSON();
      adminsConvidados.push({
        id: 6,
        ...corpo,
        ativo: true,
        principal: false,
        convite_aceito_em: null,
      });
      return json({ data: adminsConvidados.at(-1) }, 201);
    }
    if (caminho === '/admin/admins/6') {
      return json({ data: adminsConvidados[0] });
    }
    if (caminho === '/admin/clientes-api/permissoes') {
      return json({
        data: [
          { valor: 'espacos:ler', nome: 'Ver espaços, calendário e equipamentos' },
          { valor: 'reservas:criar', nome: 'Reservar para um aluno pelo RA' },
          { valor: 'reservas:ler', nome: 'Ver as reservas criadas pelo sistema' },
          { valor: 'reservas:cancelar', nome: 'Cancelar as reservas criadas pelo sistema' },
        ],
      });
    }
    if (caminho === '/admin/clientes-api' && requisicao.method() === 'POST') {
      const corpo = requisicao.postDataJSON();
      sistemasDoAdmin.push({
        id: 8,
        nome: corpo.nome,
        organizacao: corpo.organizacao,
        responsavel: { nome: corpo.responsavel_nome, email: corpo.responsavel_email },
        finalidade: corpo.finalidade,
        permissoes: corpo.permissoes,
        limite_por_minuto: corpo.limite_por_minuto,
        ativo: true,
        situacao: 'AGUARDANDO_ACEITE',
        termos_versao: null,
        termos_aceitos_em: null,
        acesso_expira_em: null,
        link_aceite: { expira_em: new Date(Date.now() + 7 * 86_400_000).toISOString() },
      });
      return json({ data: sistemasDoAdmin.at(-1) }, 201);
    }
    if (caminho === '/admin/clientes-api') {
      return json({
        data: sistemasDoAdmin,
        meta: { current_page: 1, last_page: 1, total: sistemasDoAdmin.length, per_page: 15 },
      });
    }
    const sistema = /^\/admin\/clientes-api\/(\d+)(\/enviar-aceite)?$/.exec(caminho);
    if (sistema) {
      const indice = sistemasDoAdmin.findIndex((item) => item.id === Number(sistema[1]));
      if (indice < 0) {
        return json({ message: 'Não encontrado.' }, 404);
      }
      if (sistema[2]) {
        const revogar = requisicao.postDataJSON().revogar_atual === true;
        sistemasDoAdmin[indice] = {
          ...sistemasDoAdmin[indice],
          ...(revogar ? { situacao: 'AGUARDANDO_ACEITE', acesso_expira_em: null } : {}),
          link_aceite: { expira_em: new Date(Date.now() + 7 * 86_400_000).toISOString() },
        };
      } else if (requisicao.method() === 'DELETE') {
        sistemasDoAdmin[indice] = {
          ...sistemasDoAdmin[indice],
          ativo: false,
          situacao: 'DESATIVADO',
          acesso_expira_em: null,
          link_aceite: null,
        };
      }
      return json({ data: sistemasDoAdmin[indice] });
    }
    if (caminho === `/parceiros/aceite/${tokenDoEmail}`) {
      if (requisicao.method() === 'POST') {
        return json({
          data: {
            token: '12|tokenDoParceiroGeradoUmaVezSo',
            expira_em: daquiAUmAno,
            permissoes: ['espacos:ler', 'reservas:criar'],
          },
        });
      }
      return json({
        data: {
          sistema: 'App da Atlética',
          organizacao: 'Atlética de Computação',
          responsavel_nome: 'Bruno Lima',
          finalidade: 'Reservar a quadra para os treinos.',
          permissoes: [
            { valor: 'espacos:ler', nome: 'Ver espaços, calendário e equipamentos' },
            { valor: 'reservas:criar', nome: 'Reservar para um aluno pelo RA' },
          ],
          renovacao: false,
          link_expira_em: new Date(Date.now() + 7 * 86_400_000).toISOString(),
          termos: {
            versao: '2026-11',
            rascunho: true,
            secoes: [
              {
                titulo: 'Dados pessoais',
                paragrafos: ['Envie à API só o RA de um aluno que pediu a reserva.'],
              },
            ],
          },
        },
      });
    }
    if (caminho === '/admin/cursos') {
      if (requisicao.method() === 'POST') {
        cursosDoAdmin.push({ id: 3, nome: requisicao.postDataJSON().nome, ativo: true });
        return json({ data: cursosDoAdmin.at(-1) }, 201);
      }
      return json({
        data: cursosDoAdmin,
        meta: { current_page: 1, last_page: 1, total: cursosDoAdmin.length, per_page: 15 },
      });
    }
    if (caminho === '/admin/datas-bloqueadas') {
      if (requisicao.method() === 'POST') {
        return json({ data: { id: 8, ...requisicao.postDataJSON() }, reservas_revogadas: 1 }, 201);
      }
      return json({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 } });
    }
    if (caminho === '/admin/equipamentos') {
      return json({
        data: [equipamentoDoAdmin],
        meta: { current_page: 1, last_page: 1, total: 1, per_page: 15 },
      });
    }
    if (caminho === '/admin/equipamentos/5/movimentacoes') {
      if (requisicao.method() === 'POST') {
        const corpo = requisicao.postDataJSON();
        const total = equipamentoDoAdmin.quantidade_total as number;
        const manutencao = (equipamentoDoAdmin.quantidade_em_manutencao as number | undefined) ?? 0;
        equipamentoDoAdmin = {
          ...equipamentoDoAdmin,
          quantidade_em_manutencao:
            corpo.tipo === 'MANUTENCAO' ? manutencao + corpo.quantidade : manutencao,
        };
        movimentacoesDoAdmin.unshift({
          id: movimentacoesDoAdmin.length + 1,
          tipo: corpo.tipo,
          quantidade: corpo.quantidade,
          estado: corpo.estado,
          reserva: null,
          feito_por: { id: 1, nome: 'Eric' },
          quantidade_total_depois: total,
          em_manutencao_depois: equipamentoDoAdmin.quantidade_em_manutencao,
          em: new Date().toISOString(),
        });
        return json({ data: movimentacoesDoAdmin[0], equipamento: equipamentoDoAdmin }, 201);
      }
      return json({
        data: movimentacoesDoAdmin,
        meta: { current_page: 1, last_page: 1, total: movimentacoesDoAdmin.length, per_page: 15 },
      });
    }
    if (caminho === '/admin/equipamentos/5') {
      if (requisicao.method() === 'PATCH') {
        equipamentoDoAdmin = { ...equipamentoDoAdmin, ...requisicao.postDataJSON() };
      }
      return json({
        data: {
          ...equipamentoDoAdmin,
          espacos_compartilhados: (equipamentoDoAdmin.espacos_compartilhados as number[]).map(
            (id) => ({
              id,
              nome: espacos.find((espaco) => espaco.id === id)?.nome ?? '',
            }),
          ),
        },
      });
    }
    if (caminho === '/admin/espacos/4/disponibilidade') {
      return json({ data: calendarioAdmin(url.searchParams.get('de')!) });
    }
    if (caminho === '/admin/reservas/eventos') {
      const corpo = requisicao.postDataJSON();
      eventoCriado = {
        ...reservaDoAdmin,
        id: 31,
        tipo: 'EVENTO',
        aluno: null,
        descricao: corpo.descricao,
        responsavel: { nome: corpo.responsavel_nome, contato: corpo.responsavel_contato ?? null },
        data_reserva: corpo.data_reserva,
        hora_inicio: corpo.hora_inicio,
        hora_fim: corpo.hora_fim,
        situacao_confirmacao: null,
        confirmar_ate: null,
        criado_por: { id: 1, nome: 'Eric' },
      };
      return json({ data: eventoCriado }, 201);
    }
    if (caminho === '/admin/espacos/4/equipamentos/solicitaveis') {
      return json({
        data: [
          { id: 5, nome: 'Microscópio', espaco: 'Laboratório de Química', total: 2, disponivel: 2 },
        ],
      });
    }
    if (caminho === '/admin/reservas/aluno') {
      const corpo = requisicao.postDataJSON();
      reservaPeloAdmin = {
        ...reservaDoAdmin,
        id: 42,
        origem: 'ADMIN',
        data_reserva: corpo.data_reserva,
        hora_inicio: corpo.hora_inicio,
        hora_fim: corpo.hora_fim,
        criado_por: { id: 1, nome: 'Eric' },
      };
      return json({ data: reservaPeloAdmin }, 201);
    }
    if (caminho === '/admin/reservas/42' && reservaPeloAdmin) {
      return json({ data: reservaPeloAdmin });
    }
    if (caminho === '/admin/reservas/registro-posterior') {
      const corpo = requisicao.postDataJSON();
      usoRegistrado = {
        ...reservaDoAdmin,
        id: 41,
        origem: 'REGISTRO_POSTERIOR',
        data_reserva: corpo.data_reserva,
        hora_inicio: corpo.hora_inicio,
        hora_fim: corpo.hora_fim,
        situacao_confirmacao: null,
        confirmar_ate: null,
        criado_por: { id: 1, nome: 'Eric' },
      };
      return json({ data: usoRegistrado, aviso: null }, 201);
    }
    if (caminho === '/admin/reservas/41' && usoRegistrado) {
      return json({ data: usoRegistrado });
    }
    if (caminho === '/admin/reservas/31' && eventoCriado) {
      return json({ data: eventoCriado });
    }
    if (caminho === '/admin/reservas/consulta') {
      const corpo = requisicao.postDataJSON();
      const lista =
        (corpo.busca && !'Carla Dias'.includes(corpo.busca)) || corpo.solicitacao
          ? []
          : [reservaDoAdmin];
      return json({
        data: lista,
        meta: { current_page: 1, last_page: 1, total: lista.length, per_page: 15 },
        totais_por_status: { RESERVADO: lista.length, CANCELADO: 0, REVOGADO: 0 },
      });
    }
    if (caminho === '/admin/reservas/77') {
      return json({ data: reservaDoAdmin });
    }
    if (caminho === '/admin/reservas/77/revogar') {
      return json({
        data: {
          ...reservaDoAdmin,
          status: 'REVOGADO',
          justificativa_revogacao: requisicao.postDataJSON().justificativa,
          revogada_em: new Date().toISOString(),
          revogada_por: { id: 1, nome: 'Eric' },
        },
      });
    }
    if (caminho === '/alunos' && requisicao.method() === 'POST') {
      return json({ data: {} }, 201);
    }
    return json({ message: 'Rota não simulada no teste.' }, 404);
  });

  return chamadas;
}
