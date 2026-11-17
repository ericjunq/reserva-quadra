import { Calendario, Horario } from '../../publico/modelos';
import { OrigemReserva, SituacaoConfirmacao } from '../reservas/modelos';

export interface ReservaNoCalendarioAdmin {
  id: number;
  tipo: 'ALUNO' | 'EVENTO';
  descricao: string | null;
  reservado_por: { tipo: 'ALUNO' | 'ADMIN'; nome: string | null; curso: string | null };
  situacao_confirmacao: SituacaoConfirmacao | null;
  origem: OrigemReserva;
}

export interface OcupacaoAdmin {
  inicio: string;
  fim: string;
  situacao: string;
  motivo: string | null;
  reserva: ReservaNoCalendarioAdmin | null;
}

export interface HorarioAdmin extends OcupacaoAdmin {
  horario: Horario | null;
}

export interface DiaAdmin {
  data: string;
  reservavel: boolean;
  motivo: string | null;
  horarios?: HorarioAdmin[];
  ocupados?: OcupacaoAdmin[];
  fim_minimo?: string | null;
}

export interface CalendarioAdmin {
  espaco: Calendario['espaco'];
  de: string;
  ate: string;
  dias: DiaAdmin[];
}

export interface EspacoAdmin {
  id: number;
  nome: string;
  ativo: boolean;
}

export type TipoDoItem = 'RESERVA' | 'EVENTO' | 'BLOQUEIO' | 'LIVRE';

export interface ItemDoDia {
  inicio: string;
  fim: string;
  tipo: TipoDoItem;
  horario: Horario | null;
  reserva: ReservaNoCalendarioAdmin | null;
  motivo: string | null;
  podeCriarEvento: boolean;
  podeRegistrarUso: boolean;
}

export interface NovaReservaDeAluno {
  espaco_id: number;
  data_reserva: string;
  horario?: Horario;
  hora_inicio?: string;
  hora_fim?: string;
  registro_academico: string;
  equipamentos?: { id: number; quantidade: number }[];
  solicitacoes?: { id: number; quantidade: number }[];
}

export interface NovoEvento {
  espaco_id: number;
  data_reserva: string;
  horario?: Horario;
  hora_inicio?: string;
  hora_fim?: string;
  descricao: string;
  responsavel_nome: string;
  responsavel_contato?: string;
  equipamentos?: { id: number; quantidade: number }[];
  justificativa?: string;
}
