import { DiaNoCalendario, Ocupacao } from './modelos';

export interface Janela {
  abre: string;
  fecha: string;
  duracaoMaxima: number;
  bloco: number;
}

export interface Avaliacao {
  inicio: string;
  fim: string;
  erro: string | null;
}

export function minutos(hora: string): number {
  const [h, m] = hora.split(':').map(Number);
  return h * 60 + m;
}

export function hora(total: number): string {
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

export function arredondar(valor: string, bloco: number): string | null {
  if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(valor)) {
    return null;
  }
  return hora(Math.min(Math.round(minutos(valor) / bloco) * bloco, 24 * 60 - bloco));
}

export function descreverOcupacao(ocupacao: Ocupacao): string {
  if (ocupacao.situacao === 'BLOQUEADO') {
    return ocupacao.motivo ? `Bloqueado: ${ocupacao.motivo}` : 'Bloqueado';
  }
  if (ocupacao.situacao === 'EVENTO') {
    return ocupacao.reserva?.descricao || 'Evento da faculdade';
  }
  const quem = ocupacao.reserva?.reservado_por;
  if (!quem?.nome) {
    return 'Reservado';
  }
  return quem.curso ? `${quem.nome} (${quem.curso})` : quem.nome;
}

export function horariosLivres(
  dia: DiaNoCalendario,
  janela: Janela,
): { inicio: string; fim: string }[] {
  const inicioDoDia = Math.max(
    minutos(janela.abre),
    dia.fim_minimo ? minutos(dia.fim_minimo) - janela.bloco : 0,
  );
  const ocupados = [...(dia.ocupados ?? [])].sort((a, b) => minutos(a.inicio) - minutos(b.inicio));
  const livres: { inicio: string; fim: string }[] = [];
  let cursor = inicioDoDia;
  for (const ocupacao of ocupados) {
    if (minutos(ocupacao.inicio) - cursor >= janela.bloco) {
      livres.push({ inicio: hora(cursor), fim: ocupacao.inicio });
    }
    cursor = Math.max(cursor, minutos(ocupacao.fim));
  }
  if (minutos(janela.fecha) - cursor >= janela.bloco) {
    livres.push({ inicio: hora(cursor), fim: janela.fecha });
  }
  return livres;
}

export function avaliarIntervalo(
  dia: DiaNoCalendario,
  janela: Janela,
  inicioDigitado: string,
  fimDigitado: string,
): Avaliacao {
  const inicio = arredondar(inicioDigitado, janela.bloco);
  const fim = arredondar(fimDigitado, janela.bloco);
  const resultado = (erro: string | null): Avaliacao => ({
    inicio: inicio ?? '',
    fim: fim ?? '',
    erro,
  });

  if (!inicio || !fim) {
    return resultado('Informe o início e o fim.');
  }
  if (!dia.reservavel) {
    return resultado(dia.motivo ?? 'Este dia não aceita reservas.');
  }
  if (minutos(fim) <= minutos(inicio)) {
    return resultado('O fim precisa ser depois do início.');
  }
  if (minutos(inicio) < minutos(janela.abre) || minutos(fim) > minutos(janela.fecha)) {
    return resultado(`O espaço funciona das ${janela.abre} às ${janela.fecha}.`);
  }
  if (minutos(fim) - minutos(inicio) > janela.duracaoMaxima) {
    return resultado(`Cada reserva pode ter no máximo ${duracaoLegivel(janela.duracaoMaxima)}.`);
  }
  if (dia.fim_minimo && minutos(fim) < minutos(dia.fim_minimo)) {
    return resultado(
      `Hoje, só dá para reservar horários que terminem a partir das ${dia.fim_minimo}.`,
    );
  }
  const conflito = (dia.ocupados ?? []).find(
    (ocupacao) =>
      minutos(ocupacao.inicio) < minutos(fim) && minutos(ocupacao.fim) > minutos(inicio),
  );
  if (conflito) {
    return resultado(
      `Esse horário bate com ${conflito.inicio}–${conflito.fim} (${descreverOcupacao(conflito)}).`,
    );
  }
  return resultado(null);
}

export function duracaoLegivel(total: number): string {
  const horas = Math.floor(total / 60);
  const resto = total % 60;
  if (!horas) {
    return `${resto} min`;
  }
  return resto ? `${horas}h${String(resto).padStart(2, '0')}` : `${horas}h`;
}
