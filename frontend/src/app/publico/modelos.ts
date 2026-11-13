export type TipoEspaco = 'QUADRA' | 'LABORATORIO' | 'SALA';
export type ModoHorario = 'FIXO' | 'LIVRE';
export type Horario = 'PRIMEIRO_HORARIO' | 'SEGUNDO_HORARIO';

export interface Espaco {
  id: number;
  nome: string;
  tipo: TipoEspaco;
  limite_semanal_por_curso: number | null;
  modo_horario: ModoHorario;
  abre_as: string | null;
  fecha_as: string | null;
  duracao_maxima_minutos: number | null;
}

export interface Curso {
  id: number;
  nome: string;
}

export interface EquipamentoDisponivel {
  id: number;
  nome: string;
  total: number;
  disponivel: number;
}

export interface ReservaNoCalendario {
  id: number;
  tipo: 'ALUNO' | 'EVENTO';
  descricao: string | null;
  do_curso?: boolean;
}

export interface HorarioNoCalendario {
  horario: Horario | null;
  inicio: string;
  fim: string;
  situacao: string;
  motivo: string | null;
  reserva: ReservaNoCalendario | null;
  equipamentos?: EquipamentoDisponivel[];
  disponivel_para_o_curso?: boolean;
}

export interface Ocupacao {
  inicio: string;
  fim: string;
  situacao: string;
  motivo: string | null;
  reserva: ReservaNoCalendario | null;
}

export interface DiaNoCalendario {
  data: string;
  reservavel: boolean;
  motivo: string | null;
  horarios?: HorarioNoCalendario[];
  ocupados?: Ocupacao[];
  fim_minimo?: string | null;
  abre_em?: string | null;
}

export interface SemanaDoCurso {
  inicio: string;
  fim: string;
  reservas: number;
  restantes: number;
}

export interface Calendario {
  espaco: {
    id: number;
    nome: string;
    tipo: TipoEspaco;
    modo_horario: ModoHorario;
    abre_as: string | null;
    fecha_as: string | null;
    duracao_maxima_minutos: number | null;
    bloco_minutos: number | null;
  };
  de: string;
  ate: string;
  reservas_ate: string;
  calendario_ate: string;
  curso: { limite_semanal: number; semanas: SemanaDoCurso[] } | null;
  dias: DiaNoCalendario[];
}

export interface NovaReserva {
  registro_academico: string;
  espaco_id: number;
  data_reserva: string;
  horario?: Horario;
  hora_inicio?: string;
  hora_fim?: string;
  equipamentos?: { id: number; quantidade: number }[];
}

export interface ReservaCriada {
  id: number;
  data_reserva: string;
  hora_inicio: string;
  hora_fim: string;
  confirmar_ate: string | null;
  confirmada_em: string | null;
}

export interface NovoAluno {
  nome_completo: string;
  registro_academico: string;
  email: string;
  curso_id: number;
}

export type StatusReserva = 'RESERVADO' | 'CANCELADO' | 'REVOGADO';
export type SituacaoDevolucao = 'PENDENTE' | 'DEVOLVIDO' | 'DEVOLVIDO_COM_AVARIA' | 'NAO_DEVOLVIDO';

export interface ReservaDoAluno {
  id: number;
  data_reserva: string;
  hora_inicio: string;
  hora_fim: string;
  status: StatusReserva;
  tipo: 'ALUNO' | 'EVENTO';
  espaco?: { id: number; nome: string; tipo: TipoEspaco };
  equipamentos?: {
    id: number;
    nome: string;
    quantidade: number;
    devolucao: { situacao: SituacaoDevolucao | null; observacao: string | null };
  }[];
  justificativa_revogacao?: string | null;
  confirmar_ate: string | null;
  confirmada_em: string | null;
  reconfirmacao_pedida_em: string | null;
  reconfirmar_ate: string | null;
  reconfirmada_em: string | null;
}

export interface PaginaDeReservas {
  data: ReservaDoAluno[];
  meta: { current_page: number; last_page: number; total: number };
}

export type PeriodoMinhasReservas = 'FUTURAS' | 'TODAS';

export interface ResumoDoCancelamento {
  data: ReservaDoAluno;
  pode_cancelar: boolean;
}

export interface ResumoDaConfirmacao {
  data: ReservaDoAluno;
  etapa: 'CONFIRMACAO' | 'RECONFIRMACAO';
  pode_confirmar: boolean;
  prazo_confirmacao: string | null;
  exige_aceite_termos: boolean;
  termos_versao: string;
}

export interface SecaoDosTermos {
  titulo: string;
  paragrafos: string[];
}

export interface Termos {
  versao: string;
  rascunho: boolean;
  termos_de_uso: SecaoDosTermos[];
  privacidade: SecaoDosTermos[];
}
