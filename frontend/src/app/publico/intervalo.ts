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

export function normalizarHora(valor: string): string {
  const texto = valor.trim().toLowerCase().replace('h', ':');
  const soNumeros = texto.replace(/\D/g, '');
  if (/^\d{1,2}:?$/.test(texto)) {
    return `${soNumeros.padStart(2, '0')}:00`;
  }
  if (/^\d{3,4}$/.test(texto)) {
    return `${soNumeros.slice(0, -2).padStart(2, '0')}:${soNumeros.slice(-2)}`;
  }
  const partes = /^(\d{1,2}):(\d{1,2})$/.exec(texto);
  return partes ? `${partes[1].padStart(2, '0')}:${partes[2].padStart(2, '0')}` : valor;
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
  return 'Reservado';
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
  const inicio = arredondar(normalizarHora(inicioDigitado), janela.bloco);
  const fim = arredondar(normalizarHora(fimDigitado), janela.bloco);
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

export interface TrechoDaBarra {
  inicio: string;
  fim: string;
  esquerda: number;
  largura: number;
}

function livreQueContem(
  dia: DiaNoCalendario,
  janela: Janela,
  inicio: string,
): { inicio: string; fim: string } | null {
  return (
    horariosLivres(dia, janela).find(
      (livre) =>
        minutos(livre.inicio) <= minutos(inicio) &&
        minutos(inicio) + janela.bloco <= minutos(livre.fim),
    ) ?? null
  );
}

export function iniciosLivres(dia: DiaNoCalendario, janela: Janela): string[] {
  if (!dia.reservavel) {
    return [];
  }
  const inicios: string[] = [];
  for (const livre of horariosLivres(dia, janela)) {
    const primeiro = Math.ceil(minutos(livre.inicio) / janela.bloco) * janela.bloco;
    for (let atual = primeiro; atual + janela.bloco <= minutos(livre.fim); atual += janela.bloco) {
      inicios.push(hora(atual));
    }
  }
  return inicios;
}

export function finsPossiveis(dia: DiaNoCalendario, janela: Janela, inicio: string): string[] {
  const livre = dia.reservavel ? livreQueContem(dia, janela, inicio) : null;
  if (!livre) {
    return [];
  }
  const limite = Math.min(minutos(livre.fim), minutos(inicio) + janela.duracaoMaxima);
  const minimo = dia.fim_minimo ? minutos(dia.fim_minimo) : 0;
  const fins: string[] = [];
  for (let atual = minutos(inicio) + janela.bloco; atual <= limite; atual += janela.bloco) {
    if (atual >= minimo) {
      fins.push(hora(atual));
    }
  }
  return fins;
}

export function duracoesPossiveis(
  dia: DiaNoCalendario,
  janela: Janela,
  inicio: string,
  passo = 30,
): number[] {
  const duracoes = finsPossiveis(dia, janela, inicio).map((fim) => minutos(fim) - minutos(inicio));
  const redondas = duracoes.filter((duracao) => duracao % passo === 0);
  return redondas.length ? redondas : duracoes.slice(-1);
}

export function trechosDaBarra(
  intervalos: { inicio: string; fim: string }[],
  janela: Janela,
): TrechoDaBarra[] {
  const abre = minutos(janela.abre);
  const total = minutos(janela.fecha) - abre;
  if (total <= 0) {
    return [];
  }
  return intervalos
    .map((intervalo) => ({
      inicio: intervalo.inicio,
      fim: intervalo.fim,
      de: Math.max(minutos(intervalo.inicio), abre),
      ate: Math.min(minutos(intervalo.fim), minutos(janela.fecha)),
    }))
    .filter((trecho) => trecho.ate > trecho.de)
    .map((trecho) => ({
      inicio: trecho.inicio,
      fim: trecho.fim,
      esquerda: ((trecho.de - abre) / total) * 100,
      largura: ((trecho.ate - trecho.de) / total) * 100,
    }));
}

export function inicioNaPosicao(
  dia: DiaNoCalendario,
  janela: Janela,
  fracao: number,
): string | null {
  const abre = minutos(janela.abre);
  const tocado = abre + Math.min(Math.max(fracao, 0), 1) * (minutos(janela.fecha) - abre);
  const livre = horariosLivres(dia, janela).find(
    (intervalo) => minutos(intervalo.inicio) <= tocado && tocado < minutos(intervalo.fim),
  );
  if (!dia.reservavel || !livre) {
    return null;
  }
  const doTrecho = iniciosLivres(dia, janela).filter(
    (inicio) => minutos(inicio) >= minutos(livre.inicio) && minutos(inicio) < minutos(livre.fim),
  );
  return doTrecho.filter((inicio) => minutos(inicio) <= tocado).pop() ?? doTrecho[0] ?? null;
}

export function marcasDaBarra(janela: Janela, passo = 180): { hora: string; posicao: number }[] {
  const abre = minutos(janela.abre);
  const fecha = minutos(janela.fecha);
  const marcas: { hora: string; posicao: number }[] = [];
  for (let atual = Math.ceil(abre / 60) * 60; atual <= fecha; atual += passo) {
    marcas.push({ hora: hora(atual), posicao: ((atual - abre) / (fecha - abre)) * 100 });
  }
  return marcas;
}
