import { horariosLivres, minutos } from '../../publico/intervalo';
import { DiaNoCalendario } from '../../publico/modelos';
import { CalendarioAdmin, DiaAdmin, ItemDoDia, OcupacaoAdmin, TipoDoItem } from './modelos';

const TIPOS: Record<string, TipoDoItem> = {
  RESERVADO: 'RESERVA',
  EVENTO: 'EVENTO',
  BLOQUEADO: 'BLOQUEIO',
};

export interface Referencia {
  hoje: string;
  agora: string;
  registroDesde: string;
}

export function itensDoDia(
  dia: DiaAdmin,
  espaco: CalendarioAdmin['espaco'],
  referencia: Referencia,
): ItemDoDia[] {
  const { hoje } = referencia;
  const futuro = dia.data >= hoje;
  const podeRegistrar = (inicio: string) =>
    dia.data >= referencia.registroDesde &&
    dia.data <= hoje &&
    (dia.data < hoje || inicio <= referencia.agora);

  if (espaco.modo_horario !== 'LIVRE') {
    return (dia.horarios ?? []).map((horario) => {
      const item = deOcupacao(horario);
      return {
        ...item,
        horario: horario.horario,
        podeCriarEvento: item.tipo === 'LIVRE' && futuro && horario.situacao !== 'ENCERRADO',
        podeRegistrarUso: item.tipo === 'LIVRE' && podeRegistrar(horario.inicio),
      };
    });
  }

  const podeHoje = dia.data > hoje || !!dia.fim_minimo;
  const livres = horariosLivres(dia as DiaNoCalendario, {
    abre: espaco.abre_as ?? '00:00',
    fecha: espaco.fecha_as ?? '23:59',
    duracaoMaxima: espaco.duracao_maxima_minutos ?? 0,
    bloco: espaco.bloco_minutos ?? 15,
  }).map((livre): ItemDoDia => ({
    ...livre,
    tipo: 'LIVRE',
    horario: null,
    reserva: null,
    motivo: null,
    podeCriarEvento: futuro && podeHoje,
    podeRegistrarUso: podeRegistrar(livre.inicio),
  }));

  return [...(dia.ocupados ?? []).map(deOcupacao), ...livres].sort(
    (a, b) => minutos(a.inicio) - minutos(b.inicio),
  );
}

function deOcupacao(ocupacao: OcupacaoAdmin): ItemDoDia {
  return {
    inicio: ocupacao.inicio,
    fim: ocupacao.fim,
    tipo: TIPOS[ocupacao.situacao] ?? 'LIVRE',
    horario: null,
    reserva: ocupacao.reserva,
    motivo: ocupacao.situacao === 'BLOQUEADO' ? ocupacao.motivo : null,
    podeCriarEvento: false,
    podeRegistrarUso: false,
  };
}

export function linkDoHorario(
  espacoId: number,
  data: string,
  item: ItemDoDia,
): Record<string, string | number> {
  return item.horario
    ? { espaco: espacoId, data, horario: item.horario }
    : { espaco: espacoId, data, inicio: item.inicio, fim: item.fim };
}
