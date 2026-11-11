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
    situacao: ocupado ? 'RESERVADO' : 'LIVRE',
    motivo: null,
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
    curso: null,
    dias: dias.map((data) => {
      const reservavel = data > datas.hoje && data <= datas.reservasAte && ehDiaUtil(data);
      return {
        data,
        reservavel,
        motivo: reservavel ? null : 'Fora do período de reservas.',
        horarios: [
          horarioFixo(data, 'PRIMEIRO_HORARIO', '19:00', '20:30'),
          horarioFixo(data, 'SEGUNDO_HORARIO', '20:30', '22:00'),
        ],
      };
    }),
  };
}

export interface Chamada {
  metodo: string;
  url: string;
  corpo: unknown;
}

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
    if (caminho === '/espacos/1/disponibilidade') {
      return json({ data: calendarioFixo(url.searchParams.get('de')!) });
    }
    if (caminho === '/reservas' && requisicao.method() === 'POST') {
      const corpo = requisicao.postDataJSON();
      return json(
        {
          data: {
            id: 50,
            data_reserva: corpo.data_reserva,
            hora_inicio: '20:30',
            hora_fim: '22:00',
            confirmacao_enviada_em: null,
            confirmada_em: null,
          },
        },
        201,
      );
    }
    if (caminho === '/alunos' && requisicao.method() === 'POST') {
      return json({ data: {} }, 201);
    }
    return json({ message: 'Rota não simulada no teste.' }, 404);
  });

  return chamadas;
}
