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
  reservado_por: { tipo: 'ALUNO' | 'ADMIN'; nome: string | null; curso: string | null };
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
