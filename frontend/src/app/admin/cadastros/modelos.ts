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
  quantidade_em_manutencao?: number;
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

export interface AlunoCadastro {
  id: number;
  nome_completo: string;
  registro_academico: string;
  email: string;
  curso: { id: number; nome: string } | null;
  ativo: boolean;
  anonimizado_em: string | null;
}

export interface DadosDoAluno {
  nome_completo: string;
  registro_academico: string;
  email: string;
  curso_id: number | null;
}

export interface AdminCadastro {
  id: number;
  nome: string;
  email: string;
  ativo: boolean;
  principal: boolean;
  convite_aceito_em: string | null;
}

export interface DadosDoEquipamento {
  espaco_id?: number;
  nome: string;
  quantidade_total?: number;
  aceita_solicitacao: boolean;
  espacos_compartilhados: number[];
}

export type TipoMovimentacao =
  'ENTRADA' | 'BAIXA' | 'NAO_DEVOLVIDO' | 'MANUTENCAO' | 'RETORNO' | 'AJUSTE';

export interface MovimentacaoEstoque {
  id: number;
  tipo: TipoMovimentacao;
  quantidade: number;
  estado: string;
  reserva: { id: number; tipo: 'ALUNO' | 'EVENTO'; data_reserva: string } | null;
  feito_por: { id: number; nome: string } | null;
  quantidade_total_depois: number;
  em_manutencao_depois: number;
  em: string;
}

export interface DadosDaMovimentacao {
  tipo: TipoMovimentacao;
  quantidade: number;
  estado: string;
  reserva_id?: number;
  da_manutencao?: boolean;
}

export type PermissaoApi = 'espacos:ler' | 'reservas:criar' | 'reservas:ler' | 'reservas:cancelar';

export type SituacaoClienteApi = 'AGUARDANDO_ACEITE' | 'ATIVO' | 'VENCIDO' | 'DESATIVADO';

export interface OpcaoDePermissao {
  valor: PermissaoApi;
  nome: string;
}

export interface ClienteApiCadastro {
  id: number;
  nome: string;
  organizacao: string;
  responsavel: { nome: string; email: string };
  finalidade: string;
  permissoes: PermissaoApi[];
  limite_por_minuto: number;
  ativo: boolean;
  situacao: SituacaoClienteApi;
  termos_versao: string | null;
  termos_aceitos_em: string | null;
  acesso_expira_em: string | null;
  link_aceite: { expira_em: string } | null;
}

export interface DadosDoClienteApi {
  nome?: string;
  organizacao?: string;
  responsavel_nome?: string;
  responsavel_email?: string;
  finalidade?: string;
  permissoes?: PermissaoApi[];
  limite_por_minuto?: number;
  ativo?: boolean;
}

export interface SugestaoCompartilhamento {
  equipamento: { id: number; nome: string };
  espaco_de_origem: { id: number; nome: string };
  espaco_que_pede: { id: number; nome: string };
  pedidos: number;
  ultimo_pedido_em: string;
}
