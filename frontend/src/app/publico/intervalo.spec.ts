import { DiaNoCalendario } from './modelos';
import {
  arredondar,
  avaliarIntervalo,
  duracaoLegivel,
  duracoesPossiveis,
  finsPossiveis,
  horariosLivres,
  iniciosLivres,
  inicioNaPosicao,
  Janela,
  marcasDaBarra,
  normalizarHora,
  trechosDaBarra,
} from './intervalo';

const janela: Janela = { abre: '07:00', fecha: '22:00', duracaoMaxima: 180, bloco: 15 };

function dia(dados: Partial<DiaNoCalendario> = {}): DiaNoCalendario {
  return {
    data: '2026-11-12',
    reservavel: true,
    motivo: null,
    fim_minimo: null,
    ocupados: [
      {
        inicio: '14:00',
        fim: '15:30',
        situacao: 'RESERVADO',
        motivo: null,
        reserva: {
          id: 1,
          tipo: 'ALUNO',
          descricao: null,
          reservado_por: { tipo: 'ALUNO', nome: 'Ana Souza', curso: 'Direito' },
        },
      },
    ],
    ...dados,
  };
}

describe('intervalo', () => {
  it('arredonda para o múltiplo de 15 minutos mais próximo, como a API', () => {
    expect(arredondar('14:07', 15)).toBe('14:00');
    expect(arredondar('14:08', 15)).toBe('14:15');
    expect(arredondar('15:38', 15)).toBe('15:45');
    expect(arredondar('23:58', 15)).toBe('23:45');
    expect(arredondar('14h', 15)).toBeNull();
  });

  it('aceita um intervalo livre e devolve os horários arredondados', () => {
    expect(avaliarIntervalo(dia(), janela, '09:07', '11:38')).toEqual({
      inicio: '09:00',
      fim: '11:45',
      erro: null,
    });
  });

  it('explica cada motivo de recusa', () => {
    const erro = (inicio: string, fim: string, dados: Partial<DiaNoCalendario> = {}) =>
      avaliarIntervalo(dia(dados), janela, inicio, fim).erro;

    expect(erro('', '10:00')).toBe('Informe o início e o fim.');
    expect(erro('10:00', '10:05')).toBe('O fim precisa ser depois do início.');
    expect(erro('06:30', '08:00')).toBe('O espaço funciona das 07:00 às 22:00.');
    expect(erro('08:00', '11:15')).toBe('Cada reserva pode ter no máximo 3h.');
    expect(erro('13:00', '14:15')).toBe('Esse horário bate com 14:00–15:30 (Ana Souza (Direito)).');
    expect(erro('15:30', '16:00')).toBeNull();
    expect(erro('08:00', '09:00', { fim_minimo: '10:30' })).toBe(
      'Hoje, só dá para reservar horários que terminem a partir das 10:30.',
    );
    expect(erro('08:00', '09:00', { reservavel: false, motivo: 'Feriado.' })).toBe('Feriado.');
  });

  it('lista os horários livres do dia', () => {
    expect(horariosLivres(dia(), janela)).toEqual([
      { inicio: '07:00', fim: '14:00' },
      { inicio: '15:30', fim: '22:00' },
    ]);
    expect(horariosLivres(dia({ fim_minimo: '16:30' }), janela)).toEqual([
      { inicio: '16:15', fim: '22:00' },
    ]);
  });

  it('escreve durações de um jeito legível', () => {
    expect(duracaoLegivel(180)).toBe('3h');
    expect(duracaoLegivel(90)).toBe('1h30');
    expect(duracaoLegivel(45)).toBe('45 min');
  });
});

describe('escolha do horário livre', () => {
  const comHoje = dia({ fim_minimo: '10:15' });

  it('lista os inícios livres de 15 em 15 minutos, sem os ocupados', () => {
    const inicios = iniciosLivres(dia(), janela);
    expect(inicios[0]).toBe('07:00');
    expect(inicios).toContain('13:45');
    expect(inicios).not.toContain('14:00');
    expect(inicios).not.toContain('15:15');
    expect(inicios).toContain('15:30');
    expect(inicios.at(-1)).toBe('21:45');
    expect(iniciosLivres(dia({ reservavel: false }), janela)).toEqual([]);
  });

  it('hoje, começa pelo primeiro início que ainda termina a tempo', () => {
    expect(iniciosLivres(comHoje, janela)[0]).toBe('10:00');
  });

  it('oferece os fins até o próximo ocupado ou a duração máxima', () => {
    expect(finsPossiveis(dia(), janela, '13:00')).toEqual(['13:15', '13:30', '13:45', '14:00']);
    expect(finsPossiveis(dia(), janela, '16:00').at(-1)).toBe('19:00');
    expect(finsPossiveis(dia(), janela, '21:30')).toEqual(['21:45', '22:00']);
    expect(finsPossiveis(dia(), janela, '14:30')).toEqual([]);
    expect(finsPossiveis(comHoje, janela, '10:00')).toEqual(
      expect.arrayContaining(['10:15', '10:30']),
    );
  });

  it('mostra só as durações que cabem, de meia em meia hora', () => {
    expect(duracoesPossiveis(dia(), janela, '13:00')).toEqual([30, 60]);
    expect(duracoesPossiveis(dia(), janela, '16:00')).toEqual([30, 60, 90, 120, 150, 180]);
    expect(duracoesPossiveis(dia(), janela, '13:45')).toEqual([15]);
    expect(duracoesPossiveis(dia(), janela, '14:30')).toEqual([]);
  });

  it('posiciona os trechos na barra do dia, em porcentagem da janela', () => {
    const [trecho] = trechosDaBarra([{ inicio: '14:00', fim: '15:30' }], janela);
    expect(trecho.esquerda).toBeCloseTo(46.67, 1);
    expect(trecho.largura).toBe(10);
    expect(trechosDaBarra([{ inicio: '05:00', fim: '06:00' }], janela)).toEqual([]);
  });

  it('transforma o toque na barra no início livre mais próximo', () => {
    expect(inicioNaPosicao(dia(), janela, 0)).toBe('07:00');
    expect(inicioNaPosicao(dia(), janela, 0.5)).toBeNull();
    expect(inicioNaPosicao(dia(), janela, 0.6)).toBe('16:00');
    expect(inicioNaPosicao(dia(), janela, 0.4596)).toBe('13:45');
  });

  it('marca as horas da barra de 3 em 3', () => {
    expect(marcasDaBarra(janela).map((marca) => marca.hora)).toEqual([
      '07:00',
      '10:00',
      '13:00',
      '16:00',
      '19:00',
      '22:00',
    ]);
  });
});

describe('hora digitada', () => {
  it('aceita os jeitos comuns de digitar uma hora', () => {
    expect(normalizarHora('14:30')).toBe('14:30');
    expect(normalizarHora('9:05')).toBe('09:05');
    expect(normalizarHora('9')).toBe('09:00');
    expect(normalizarHora('14h')).toBe('14:00');
    expect(normalizarHora('14h30')).toBe('14:30');
    expect(normalizarHora('1430')).toBe('14:30');
    expect(normalizarHora('930')).toBe('09:30');
    expect(normalizarHora('abc')).toBe('abc');
  });

  it('avalia o intervalo com a hora digitada de outro jeito', () => {
    expect(avaliarIntervalo(dia(), janela, '9h', '1030').erro).toBeNull();
  });
});
