import { ModoHorario, TipoEspaco } from '../../publico/modelos';

export interface Pagina<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

export interface EspacoCadastro {
  id: number;
  nome: string;
  tipo: TipoEspaco;
  ativo: boolean;
  limite_semanal_por_curso: number | null;
  modo_horario: ModoHorario;
  abre_as: string | null;
  fecha_as: string | null;
  duracao_maxima_minutos: number | null;
}

export type DadosDoEspaco = Omit<EspacoCadastro, 'id' | 'ativo'>;

export interface EquipamentoCadastro {
  id: number;
  espaco_id: number;
  nome: string;
  quantidade_total: number;
  ativo: boolean;
  aceita_solicitacao: boolean;
  espacos_compartilhados?: { id: number; nome: string }[];
}

export interface CursoCadastro {
  id: number;
  nome: string;
  ativo: boolean;
}

export interface DataBloqueadaCadastro {
  id: number;
  data_inicio: string;
  data_fim: string;
  dia_inteiro: boolean;
  hora_inicio: string | null;
  hora_fim: string | null;
  espaco: { id: number; nome: string } | null;
  motivo: string;
  origem: 'MANUAL' | 'FERIADO';
}

export interface DadosDoBloqueio {
  data_inicio: string;
  data_fim: string;
  hora_inicio?: string;
  hora_fim?: string;
  espaco_id?: number;
  motivo: string;
}

export interface DadosDoEquipamento {
  espaco_id?: number;
  nome: string;
  quantidade_total: number;
  aceita_solicitacao: boolean;
  espacos_compartilhados: number[];
}
