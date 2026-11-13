import {
  deIso,
  ehDataIso,
  ehDiaUtil,
  ehHora,
  formatarDataHora,
  formatarDia,
  formatarDiaCurto,
  formatarDiaDaSemanaCurto,
  paraIso,
  segundaDaSemana,
  somarDias,
} from './datas';

describe('datas', () => {
  it('converte sem mudar o dia por causa do fuso', () => {
    expect(paraIso(deIso('2026-11-10'))).toBe('2026-11-10');
    expect(deIso('2026-11-10').getDate()).toBe(10);
  });

  it('acha a segunda-feira da semana', () => {
    expect(paraIso(segundaDaSemana(deIso('2026-11-12')))).toBe('2026-11-09');
    expect(paraIso(segundaDaSemana(deIso('2026-11-15')))).toBe('2026-11-09');
    expect(paraIso(segundaDaSemana(deIso('2026-11-09')))).toBe('2026-11-09');
  });

  it('soma dias atravessando o mês', () => {
    expect(paraIso(somarDias(deIso('2026-11-30'), 1))).toBe('2026-12-01');
  });

  it('reconhece dias úteis', () => {
    expect(ehDiaUtil('2026-11-13')).toBe(true);
    expect(ehDiaUtil('2026-11-14')).toBe(false);
    expect(ehDiaUtil('2026-11-15')).toBe(false);
  });

  it('valida datas e horas vindas da URL', () => {
    expect(ehDataIso('2026-11-10')).toBe(true);
    expect(ehDataIso('2026-02-30')).toBe(false);
    expect(ehDataIso('10/11/2026')).toBe(false);
    expect(ehDataIso(null)).toBe(false);
    expect(ehHora('14:30')).toBe(true);
    expect(ehHora('24:00')).toBe(false);
    expect(ehHora('14h')).toBe(false);
  });

  it('formata o dia em português', () => {
    expect(formatarDia('2026-11-10')).toBe('Terça-feira, 10/11');
    expect(formatarDiaCurto('2026-11-10')).toBe('10/11');
    expect(formatarDiaDaSemanaCurto('2026-11-10')).toBe('ter');
  });

  it('mostra data e hora no horário de Brasília', () => {
    expect(formatarDataHora('2026-11-10T13:00:00.000000Z')).toBe('10/11 às 10:00');
    expect(formatarDataHora('2026-11-11T02:30:00.000000Z')).toBe('10/11 às 23:30');
  });
});
