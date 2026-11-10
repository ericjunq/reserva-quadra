import { Espaco, TipoEspaco } from './modelos';

export const NOMES_DOS_TIPOS: Record<TipoEspaco, string> = {
  QUADRA: 'Quadra',
  LABORATORIO: 'Laboratório',
  SALA: 'Sala',
};

export function descreverHorario(espaco: Espaco): string {
  if (espaco.modo_horario === 'LIVRE') {
    const horas = (espaco.duracao_maxima_minutos ?? 0) / 60;
    const duracao = Number.isInteger(horas) ? `${horas}h` : `${horas.toLocaleString('pt-BR')}h`;
    return `Horário livre, das ${espaco.abre_as} às ${espaco.fecha_as}, até ${duracao} por reserva`;
  }
  return 'Horários fixos: 19:00–20:30 e 20:30–22:00';
}
