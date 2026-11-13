import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { ReservaDoAluno } from '../modelos';
import { MinhasReservas } from './minhas-reservas';

const url = ambiente.urlApi;

function reserva(dados: Partial<ReservaDoAluno> = {}): ReservaDoAluno {
  return {
    id: 1,
    data_reserva: '2026-11-12',
    hora_inicio: '19:00',
    hora_fim: '20:30',
    status: 'RESERVADO',
    tipo: 'ALUNO',
    espaco: { id: 1, nome: 'Quadra Poliesportiva', tipo: 'QUADRA' },
    equipamentos: [
      {
        id: 1,
        nome: 'Bola de futsal',
        quantidade: 2,
        devolucao: { situacao: 'PENDENTE', observacao: null },
      },
    ],
    confirmar_ate: '2026-11-11T13:00:00.000000Z',
    confirmada_em: null,
    reconfirmacao_pedida_em: null,
    reconfirmar_ate: null,
    reconfirmada_em: null,
    ...dados,
  };
}

describe('MinhasReservas', () => {
  let controle: HttpTestingController;
  let fixture: ComponentFixture<MinhasReservas>;
  let pagina: HTMLElement;

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 10, 10, 10));
    TestBed.configureTestingModule({
      imports: [MinhasReservas],
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
    vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    fixture = TestBed.createComponent(MinhasReservas);
    pagina = fixture.nativeElement;
    fixture.detectChanges();
  });

  afterEach(() => {
    controle.verify();
    vi.useRealTimers();
  });

  function preencher(id: string, valor: string) {
    const campo = pagina.querySelector<HTMLInputElement>(`#${id}`)!;
    campo.value = valor;
    campo.dispatchEvent(new Event('input'));
  }

  async function consultar() {
    pagina.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();
    await fixture.whenStable();
  }

  async function responder(corpo: object, status = 200) {
    let requisicao!: ReturnType<HttpTestingController['expectOne']>;
    await vi.waitFor(() => {
      fixture.detectChanges();
      requisicao = controle.expectOne((pedido) => pedido.url === `${url}/minhas-reservas`);
    });
    const enviado = {
      corpo: requisicao.request.body,
      pagina: requisicao.request.params.get('page'),
    };
    requisicao.flush(corpo, { status, statusText: status === 200 ? 'OK' : 'erro' });
    fixture.detectChanges();
    await fixture.whenStable();
    return enviado;
  }

  it('consulta por RA e email no corpo do POST e mostra a situação de cada reserva', async () => {
    preencher('ra', '2024000001');
    preencher('email', 'maria@email.com');
    await consultar();

    const enviado = await responder({
      data: [
        reserva(),
        reserva({
          id: 2,
          data_reserva: '2026-11-05',
          status: 'REVOGADO',
          justificativa_revogacao: 'Reserva não confirmada no prazo.',
          equipamentos: [],
        }),
        reserva({
          id: 3,
          data_reserva: '2026-11-02',
          confirmada_em: '2026-10-30T12:00:00Z',
          equipamentos: [
            {
              id: 1,
              nome: 'Bola de vôlei',
              quantidade: 1,
              devolucao: { situacao: 'NAO_DEVOLVIDO', observacao: null },
            },
          ],
        }),
      ],
      meta: { current_page: 1, last_page: 1, total: 3 },
    });

    expect(enviado).toEqual({
      corpo: { registro_academico: '2024000001', email: 'maria@email.com', periodo: 'FUTURAS' },
      pagina: '1',
    });
    const itens = pagina.querySelectorAll('li.rounded-2xl');
    expect(itens.length).toBe(3);
    expect(itens[0].textContent).toContain('Quadra Poliesportiva');
    expect(itens[0].textContent).toContain(
      'Aguardando a sua confirmação pelo email até 11/11 às 10:00',
    );
    expect(itens[0].textContent).toContain('Para cancelar, use o link');
    expect(itens[0].textContent).not.toContain('devolução pendente');
    expect(itens[1].textContent).toContain('Revogada');
    expect(itens[1].textContent).toContain('Motivo: Reserva não confirmada no prazo.');
    expect(itens[1].textContent).not.toContain('Para cancelar');
    expect(itens[2].textContent).toContain('Confirmada');
    expect(itens[2].textContent).toContain('não devolvido');
  });

  it('mostra a mensagem genérica da API quando os dados não batem', async () => {
    preencher('ra', '2024000001');
    preencher('email', 'outra@email.com');
    await consultar();
    await responder({ message: 'Não encontramos reservas para esses dados.' }, 404);

    expect(pagina.textContent).toContain('Não encontramos reservas para esses dados.');
  });

  it('confere o formato sem chamar a API', async () => {
    preencher('ra', '123');
    await consultar();
    expect(pagina.textContent).toContain('O RA tem 10 números.');

    preencher('ra', '2024000001');
    preencher('email', 'sem-arroba');
    await consultar();
    expect(pagina.textContent).toContain('Informe o email do seu cadastro.');
  });

  it('pagina repetindo o POST, com só o número da página na URL', async () => {
    const navegar = TestBed.inject(Router).navigate as ReturnType<typeof vi.fn>;
    preencher('ra', '2024000001');
    preencher('email', 'maria@email.com');
    await consultar();
    await responder({ data: [reserva()], meta: { current_page: 1, last_page: 3, total: 31 } });

    expect(pagina.textContent).toContain('Página 1 de 3');
    [...pagina.querySelectorAll<HTMLButtonElement>('button')]
      .find((botao) => botao.textContent?.trim() === 'Próxima')!
      .click();
    expect(navegar).toHaveBeenLastCalledWith([], { queryParams: { pagina: 2 } });

    fixture.componentRef.setInput('pagina', '2');
    const enviado = await responder({
      data: [reserva({ id: 9 })],
      meta: { current_page: 2, last_page: 3, total: 31 },
    });
    expect(enviado.pagina).toBe('2');
    expect(enviado.corpo).toEqual({
      registro_academico: '2024000001',
      email: 'maria@email.com',
      periodo: 'FUTURAS',
    });
  });

  it('esquece os dados ao limpar', async () => {
    preencher('ra', '2024000001');
    preencher('email', 'maria@email.com');
    await consultar();
    await responder({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
    expect(pagina.textContent).toContain('Nenhuma reserva para mostrar.');

    [...pagina.querySelectorAll<HTMLButtonElement>('button')]
      .find((botao) => botao.textContent?.trim() === 'Limpar')!
      .click();
    fixture.detectChanges();
    await fixture.whenStable();

    expect(pagina.querySelector<HTMLInputElement>('#ra')!.value).toBe('');
    expect(pagina.textContent).not.toContain('Nenhuma reserva para mostrar.');
  });
});
