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

export function rotuloDoHorario(data: string, inicio: string, fim: string): string {
  return `Reservar ${formatarDia(data)}, das ${inicio} às ${fim}`;
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
          reservado_por: { tipo: 'ALUNO', nome: 'Ana Souza', curso: 'Direito' },
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
                    reservado_por: { tipo: 'ALUNO', nome: 'Carla Dias', curso: 'Enfermagem' },
                  },
                },
              ]
            : [],
      };
    }),
  };
}

export interface Chamada {
  metodo: string;
  url: string;
  corpo: unknown;
}

export const tokenDoEmail = 'b'.repeat(64);

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

const termos = {
  versao: '2026-11',
  rascunho: true,
  termos_de_uso: [
    { titulo: 'Como funciona a reserva', paragrafos: ['Toda reserva precisa ser confirmada.'] },
  ],
  privacidade: [{ titulo: 'Quais dados e para quê', paragrafos: ['Nome, RA, email e curso.'] }],
};

export async function prepararApi(page: Page): Promise<Chamada[]> {
  const chamadas: Chamada[] = [];

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
    if (caminho === '/termos') {
      return json({ data: termos });
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
    if (caminho === '/alunos' && requisicao.method() === 'POST') {
      return json({ data: {} }, 201);
    }
    return json({ message: 'Rota não simulada no teste.' }, 404);
  });

  return chamadas;
}
