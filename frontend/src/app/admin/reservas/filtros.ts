import { deIso, ehDataIso } from '../../publico/datas';
import { SituacaoDevolucao, SituacaoSolicitacao, StatusReserva } from '../../publico/modelos';
import {
  FiltrosReservas,
  OrigemReserva,
  PeriodoAdmin,
  SituacaoConfirmacao,
  TipoReserva,
} from './modelos';

export interface ParametrosDaUrl {
  periodo?: string;
  de?: string;
  ate?: string;
  espaco?: string;
  curso?: string;
  status?: string;
  tipo?: string;
  confirmacao?: string;
  devolucao?: string;
  origem?: string;
  solicitacao?: string;
}

export const PERIODOS: { valor: PeriodoAdmin; nome: string }[] = [
  { valor: 'HOJE', nome: 'Hoje' },
  { valor: 'SEMANA_ATUAL', nome: 'Esta semana' },
  { valor: 'PROXIMA_SEMANA', nome: 'Próxima semana' },
  { valor: 'MES_ATUAL', nome: 'Este mês' },
  { valor: 'MES_ANTERIOR', nome: 'Mês anterior' },
  { valor: 'ANO_ATUAL', nome: 'Este ano' },
];

export const STATUS: StatusReserva[] = ['RESERVADO', 'CANCELADO', 'REVOGADO'];

export const CONFIRMACOES: SituacaoConfirmacao[] = [
  'AGUARDANDO_CONFIRMACAO',
  'CONFIRMADA',
  'AGUARDANDO_RECONFIRMACAO',
  'RECONFIRMADA',
];

export const DEVOLUCOES: SituacaoDevolucao[] = [
  'PENDENTE',
  'DEVOLVIDO',
  'DEVOLVIDO_COM_AVARIA',
  'NAO_DEVOLVIDO',
];

const TIPOS: TipoReserva[] = ['ALUNO', 'EVENTO'];

export const ORIGENS: OrigemReserva[] = ['SITE', 'ADMIN', 'REGISTRO_POSTERIOR'];

export const SOLICITACOES: SituacaoSolicitacao[] = [
  'AGUARDANDO',
  'APROVADA',
  'RECUSADA',
  'VENCIDA',
];

export const MAXIMO_DIAS_EXPORTACAO = 366;

export function filtrosDaUrl(parametros: ParametrosDaUrl): FiltrosReservas {
  const periodo = umDe(
    PERIODOS.map((p) => p.valor),
    parametros.periodo,
  );
  const de = ehDataIso(parametros.de) ? parametros.de : undefined;
  const ate =
    ehDataIso(parametros.ate) && (!de || parametros.ate >= de) ? parametros.ate : undefined;

  return limpar({
    periodo,
    de: periodo ? undefined : de,
    ate: periodo ? undefined : ate,
    espaco_id: idPositivo(parametros.espaco),
    curso_id: idPositivo(parametros.curso),
    status: umDe(STATUS, parametros.status),
    tipo: umDe(TIPOS, parametros.tipo),
    confirmacao: umDe(CONFIRMACOES, parametros.confirmacao),
    devolucao: umDe(DEVOLUCOES, parametros.devolucao),
    origem: umDe(ORIGENS, parametros.origem),
    solicitacao: umDe(SOLICITACOES, parametros.solicitacao),
  });
}

export function diasDoPeriodo(filtros: FiltrosReservas): number | null {
  if (!filtros.de || !filtros.ate) {
    return null;
  }
  return Math.round((deIso(filtros.ate).getTime() - deIso(filtros.de).getTime()) / 86_400_000) + 1;
}

function umDe<T extends string>(opcoes: readonly T[], valor: string | undefined): T | undefined {
  return opcoes.find((opcao) => opcao === valor);
}

function idPositivo(valor: string | undefined): number | undefined {
  const numero = Number(valor);
  return Number.isInteger(numero) && numero > 0 ? numero : undefined;
}

function limpar(filtros: FiltrosReservas): FiltrosReservas {
  return Object.fromEntries(
    Object.entries(filtros).filter(([, valor]) => valor !== undefined),
  ) as FiltrosReservas;
}
