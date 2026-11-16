import { formatarDataHora } from '../../publico/datas';
import { ReservaAdmin } from './modelos';

export interface Marco {
  rotulo: string;
  quando: string;
  detalhe?: string;
}

export function linhaDoTempo(reserva: ReservaAdmin): Marco[] {
  const marcos: (Marco | null)[] = [
    marco('Criada', reserva.criado_em, reserva.criado_por?.nome),
    reserva.tipo === 'ALUNO' && !reserva.confirmada_em
      ? marco('Prazo para confirmar', reserva.confirmar_ate)
      : null,
    marco(
      'Confirmada pelo aluno',
      reserva.confirmada_em,
      reserva.termos_versao ? `termos versão ${reserva.termos_versao}` : undefined,
    ),
    marco('Reconfirmação pedida', reserva.reconfirmacao_pedida_em),
    !reserva.reconfirmada_em && reserva.reconfirmar_ate
      ? marco('Prazo para reconfirmar', umMinutoAntes(reserva.reconfirmar_ate))
      : null,
    marco('Reconfirmada pelo aluno', reserva.reconfirmada_em),
    !reserva.mantida_em && reserva.manter_ate
      ? marco('Prazo para o aluno manter sem o equipamento', reserva.manter_ate)
      : null,
    marco('Mantida pelo aluno sem o equipamento', reserva.mantida_em),
    marco('Cancelada pelo aluno', reserva.cancelada_em),
    marco('Revogada', reserva.revogada_em, reserva.revogada_por?.nome),
  ];
  return marcos.filter((item): item is Marco => item !== null);
}

function marco(rotulo: string, quando: string | null | undefined, detalhe?: string): Marco | null {
  return quando ? { rotulo, quando: formatarDataHora(quando), detalhe } : null;
}

function umMinutoAntes(iso: string): string {
  return new Date(new Date(iso).getTime() - 60_000).toISOString();
}

export function jaComecou(reserva: Pick<ReservaAdmin, 'data_reserva' | 'hora_inicio'>): boolean {
  return new Date(`${reserva.data_reserva}T${reserva.hora_inicio}:00`).getTime() <= Date.now();
}
