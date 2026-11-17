import { PeriodoAdmin } from '../reservas/modelos';

export type TipoAuditavel =
  | 'RESERVA'
  | 'ALUNO'
  | 'ESPACO'
  | 'EQUIPAMENTO'
  | 'CURSO'
  | 'DATA_BLOQUEADA'
  | 'ITEM_RESERVA'
  | 'ADMIN';

export type AcaoAuditoria = 'CRIACAO' | 'ATUALIZACAO' | 'EXCLUSAO';

export interface Auditoria {
  id: number;
  tipo: TipoAuditavel;
  registro_id: number;
  acao: AcaoAuditoria;
  campos_alterados: string[];
  antes: Record<string, unknown> | null;
  depois: Record<string, unknown> | null;
  feito_por: { id: number; nome: string } | null;
  em: string;
}

export interface FiltrosHistorico {
  tipo?: TipoAuditavel;
  registro_id?: number;
  acao?: AcaoAuditoria;
  admin_id?: number;
  sem_admin?: boolean;
  periodo?: PeriodoAdmin;
  de?: string;
  ate?: string;
}

export interface Mudanca {
  campo: string;
  rotulo: string;
  antes: string;
  depois: string;
}
