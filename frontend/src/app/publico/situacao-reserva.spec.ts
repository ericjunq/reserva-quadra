import { ReservaDoAluno } from './modelos';
import { descreverConfirmacao, descreverDevolucao, nomeDoStatus } from './situacao-reserva';

function reserva(dados: Partial<ReservaDoAluno> = {}): ReservaDoAluno {
  return {
    id: 1,
    data_reserva: '2026-11-12',
    hora_inicio: '19:00',
    hora_fim: '20:30',
    status: 'RESERVADO',
    tipo: 'ALUNO',
    confirmar_ate: '2026-11-11T13:00:00.000000Z',
    confirmada_em: null,
    reconfirmacao_pedida_em: null,
    reconfirmar_ate: null,
    reconfirmada_em: null,
    ...dados,
  };
}

describe('situação da reserva', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 10, 10, 10));
  });

  afterEach(() => vi.useRealTimers());

  it('descreve cada etapa da confirmação', () => {
    expect(descreverConfirmacao(reserva())).toBe(
      'Aguardando a sua confirmação pelo email até 11/11 às 10:00',
    );
    expect(descreverConfirmacao(reserva({ confirmada_em: 'x' }))).toBe('Confirmada');
    expect(
      descreverConfirmacao(
        reserva({
          confirmada_em: 'x',
          reconfirmacao_pedida_em: 'x',
          reconfirmar_ate: '2026-11-15T03:00:00.000000Z',
        }),
      ),
    ).toBe('Confirme de novo pelo email até 14/11 às 23:59');
    expect(
      descreverConfirmacao(
        reserva({ confirmada_em: 'x', reconfirmacao_pedida_em: 'x', reconfirmada_em: 'x' }),
      ),
    ).toBe('Confirmada de novo');
    expect(descreverConfirmacao(reserva({ status: 'CANCELADO' }))).toBeNull();
  });

  it('mostra a devolução só depois da data, e pendências antigas', () => {
    expect(descreverDevolucao(reserva(), 'PENDENTE')).toBeNull();
    expect(descreverDevolucao(reserva({ data_reserva: '2026-11-09' }), 'PENDENTE')).toBe(
      'devolução pendente',
    );
    expect(descreverDevolucao(reserva(), 'DEVOLVIDO_COM_AVARIA')).toBe('devolvido com avaria');
    expect(descreverDevolucao(reserva({ status: 'REVOGADO' }), 'NAO_DEVOLVIDO')).toBeNull();
  });

  it('dá nome aos status', () => {
    expect(nomeDoStatus('RESERVADO')).toBe('Reservada');
    expect(nomeDoStatus('REVOGADO')).toBe('Revogada');
  });
});
