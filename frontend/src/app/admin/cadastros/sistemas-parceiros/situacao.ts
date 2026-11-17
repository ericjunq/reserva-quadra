import { SituacaoClienteApi } from '../modelos';

export const NOMES_DAS_SITUACOES: Record<SituacaoClienteApi, string> = {
  AGUARDANDO_ACEITE: 'Aguardando aceite',
  ATIVO: 'Ativo',
  VENCIDO: 'Acesso vencido',
  DESATIVADO: 'Desativado',
};

export const CORES_DAS_SITUACOES: Record<SituacaoClienteApi, string> = {
  AGUARDANDO_ACEITE: 'bg-amber-100 text-amber-900',
  ATIVO: 'bg-emerald-100 text-emerald-900',
  VENCIDO: 'bg-red-100 text-red-900',
  DESATIVADO: 'bg-slate-100 text-slate-800',
};
