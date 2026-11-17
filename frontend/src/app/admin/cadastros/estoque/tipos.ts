import { TipoMovimentacao } from '../modelos';

export const TIPOS_DE_MOVIMENTACAO: { valor: TipoMovimentacao; nome: string; ajuda: string }[] = [
  { valor: 'ENTRADA', nome: 'Entrada', ajuda: 'Compra, doação ou item que voltou a ser usado.' },
  { valor: 'MANUTENCAO', nome: 'Foi para manutenção', ajuda: 'Sai do disponível até voltar.' },
  { valor: 'RETORNO', nome: 'Voltou da manutenção', ajuda: 'Volta a ficar disponível.' },
  { valor: 'BAIXA', nome: 'Baixa', ajuda: 'Quebrou sem conserto, foi descartado ou perdido.' },
  {
    valor: 'NAO_DEVOLVIDO',
    nome: 'Não houve devolução',
    ajuda: 'Baixa ligada à reserva ou ao evento que não devolveu.',
  },
  {
    valor: 'AJUSTE',
    nome: 'Ajuste de inventário',
    ajuda: 'Correção depois de uma conferência. Use um número negativo para tirar.',
  },
];

export const NOMES_DAS_MOVIMENTACOES = Object.fromEntries(
  TIPOS_DE_MOVIMENTACAO.map((tipo) => [tipo.valor, tipo.nome]),
) as Record<TipoMovimentacao, string>;

export function utilizavel(total: number, emManutencao: number | undefined): number {
  return Math.max(total - (emManutencao ?? 0), 0);
}

export function descreverQuantidade(tipo: TipoMovimentacao, quantidade: number): string {
  if (tipo === 'AJUSTE') {
    return quantidade > 0 ? `+${quantidade}` : `${quantidade}`;
  }
  return ['ENTRADA', 'RETORNO'].includes(tipo) ? `+${quantidade}` : `−${quantidade}`;
}
