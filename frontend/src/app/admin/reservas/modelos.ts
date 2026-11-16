import {
  SituacaoDevolucao,
  SituacaoSolicitacao,
  Solicitacao,
  StatusReserva,
  TipoEspaco,
} from '../../publico/modelos';

export type SituacaoConfirmacao =
  'AGUARDANDO_CONFIRMACAO' | 'CONFIRMADA' | 'AGUARDANDO_RECONFIRMACAO' | 'RECONFIRMADA';

export type PeriodoAdmin =
  'HOJE' | 'SEMANA_ATUAL' | 'PROXIMA_SEMANA' | 'MES_ATUAL' | 'MES_ANTERIOR' | 'ANO_ATUAL';

export type TipoReserva = 'ALUNO' | 'EVENTO';

export type OrigemReserva = 'SITE' | 'ADMIN' | 'REGISTRO_POSTERIOR';

export interface Pessoa {
  id: number;
  nome: string;
}

export interface EquipamentoDaReserva {
  id: number;
  nome: string;
  quantidade: number;
  devolucao: {
    situacao: SituacaoDevolucao | null;
    quantidade_devolvida: number | null;
    observacao: string | null;
    devolvido_em: string | null;
  };
  solicitacao?: Solicitacao | null;
}

export interface ReservaAdmin {
  id: number;
  data_reserva: string;
  hora_inicio: string;
  hora_fim: string;
  status: StatusReserva;
  tipo: TipoReserva;
  origem: OrigemReserva;
  espaco?: { id: number; nome: string; tipo: TipoEspaco };
  aluno?: {
    id: number;
    nome_completo: string;
    registro_academico: string;
    email: string;
    curso: { id: number; nome: string } | null;
  } | null;
  descricao: string | null;
  equipamentos?: EquipamentoDaReserva[];
  justificativa_revogacao?: string | null;
  revogada_em?: string | null;
  revogada_por?: Pessoa | null;
  cancelada_em?: string | null;
  situacao_confirmacao: SituacaoConfirmacao | null;
  confirmar_ate: string | null;
  confirmada_em: string | null;
  termos_aceitos_em: string | null;
  termos_versao: string | null;
  reconfirmacao_pedida_em: string | null;
  reconfirmar_ate: string | null;
  reconfirmada_em: string | null;
  manter_ate?: string | null;
  mantida_em?: string | null;
  criado_por?: Pessoa | null;
  atualizado_por?: Pessoa | null;
  criado_em?: string | null;
  atualizado_em?: string | null;
}

export interface FiltrosReservas {
  periodo?: PeriodoAdmin;
  de?: string;
  ate?: string;
  espaco_id?: number;
  curso_id?: number;
  status?: StatusReserva;
  tipo?: TipoReserva;
  confirmacao?: SituacaoConfirmacao;
  devolucao?: SituacaoDevolucao;
  origem?: OrigemReserva;
  solicitacao?: SituacaoSolicitacao;
  busca?: string;
}

export interface PaginaReservasAdmin {
  data: ReservaAdmin[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
  totais_por_status: Record<StatusReserva, number>;
}

export interface ItemDevolucao {
  equipamento_id: number;
  quantidade_devolvida: number;
  situacao: Exclude<SituacaoDevolucao, 'PENDENTE'>;
  observacao: string | null;
}

export interface NovoUsoPosterior {
  espaco_id: number;
  data_reserva: string;
  horario?: string;
  hora_inicio?: string;
  hora_fim?: string;
  registro_academico?: string;
  descricao?: string;
  equipamentos?: { id: number; quantidade: number }[];
}

export interface UsoRegistrado {
  reserva: ReservaAdmin;
  aviso: string | null;
}

export interface ColunaExportacao {
  chave: string;
  titulo: string;
}

export interface ColunasExportacao {
  obrigatorias: ColunaExportacao[];
  opcionais: ColunaExportacao[];
}

export interface ArquivoExportado {
  conteudo: Blob;
  nome: string;
}
