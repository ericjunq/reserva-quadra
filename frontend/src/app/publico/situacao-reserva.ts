import { formatarDataHora, paraIso } from './datas';
import { ReservaDoAluno, SituacaoDevolucao, StatusReserva } from './modelos';

const NOMES_DOS_STATUS: Record<StatusReserva, string> = {
  RESERVADO: 'Reservada',
  CANCELADO: 'Cancelada',
  REVOGADO: 'Revogada',
};

const NOMES_DAS_DEVOLUCOES: Record<SituacaoDevolucao, string> = {
  PENDENTE: 'devolução pendente',
  DEVOLVIDO: 'devolvido',
  DEVOLVIDO_COM_AVARIA: 'devolvido com avaria',
  NAO_DEVOLVIDO: 'não devolvido',
};

export function nomeDoStatus(status: StatusReserva): string {
  return NOMES_DOS_STATUS[status] ?? status;
}

export function descreverConfirmacao(reserva: ReservaDoAluno): string | null {
  if (reserva.status !== 'RESERVADO' || reserva.tipo !== 'ALUNO') {
    return null;
  }
  if (!reserva.confirmada_em) {
    return reserva.confirmar_ate
      ? `Aguardando a sua confirmação pelo email até ${formatarDataHora(reserva.confirmar_ate)}`
      : 'Aguardando a sua confirmação pelo email';
  }
  if (reserva.reconfirmacao_pedida_em && !reserva.reconfirmada_em) {
    return reserva.reconfirmar_ate
      ? `Confirme de novo pelo email até ${formatarDataHora(umMinutoAntes(reserva.reconfirmar_ate))}`
      : 'Confirme de novo pelo email';
  }
  return reserva.reconfirmada_em ? 'Confirmada de novo' : 'Confirmada';
}

export function descreverDevolucao(
  reserva: ReservaDoAluno,
  situacao: SituacaoDevolucao | null,
): string | null {
  if (!situacao || reserva.status !== 'RESERVADO') {
    return null;
  }
  if (situacao === 'PENDENTE' && reserva.data_reserva >= paraIso(new Date())) {
    return null;
  }
  return NOMES_DAS_DEVOLUCOES[situacao];
}

function umMinutoAntes(iso: string): string {
  return new Date(new Date(iso).getTime() - 60_000).toISOString();
}
