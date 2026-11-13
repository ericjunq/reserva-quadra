import { Location } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { ReservaDoAluno, Termos } from '../modelos';
import { LinkReserva, ModoDoLink } from './link-reserva';

const url = ambiente.urlApi;
const token = 'a'.repeat(64);

const reserva: ReservaDoAluno = {
  id: 7,
  data_reserva: '2026-11-12',
  hora_inicio: '19:00',
  hora_fim: '20:30',
  status: 'RESERVADO',
  tipo: 'ALUNO',
  espaco: { id: 1, nome: 'Quadra Poliesportiva', tipo: 'QUADRA' },
  equipamentos: [],
  confirmar_ate: '2026-11-11T13:00:00.000000Z',
  confirmada_em: null,
  reconfirmacao_pedida_em: null,
  reconfirmar_ate: null,
  reconfirmada_em: null,
};

const termos: Termos = {
  versao: '2026-11',
  rascunho: true,
  termos_de_uso: [
    { titulo: 'Como funciona a reserva', paragrafos: ['Toda reserva precisa ser confirmada.'] },
  ],
  privacidade: [{ titulo: 'Quais dados e para quê', paragrafos: ['Nome, RA, email e curso.'] }],
};

describe('LinkReserva', () => {
  let controle: HttpTestingController;
  let fixture: ComponentFixture<LinkReserva>;
  let pagina: HTMLElement;
  let trocarEndereco: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [LinkReserva],
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
    trocarEndereco = vi.spyOn(TestBed.inject(Location), 'replaceState');
  });

  afterEach(() => controle.verify());

  async function atualizar() {
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  }

  async function abrir(modo: ModoDoLink, comToken = true) {
    fixture = TestBed.createComponent(LinkReserva);
    fixture.componentRef.setInput('modo', modo);
    if (comToken) {
      fixture.componentRef.setInput('token', token);
    }
    pagina = fixture.nativeElement;
    await atualizar();
  }

  async function responder(endereco: string, corpo: object, status = 200) {
    let pedido!: ReturnType<HttpTestingController['expectOne']>;
    await vi.waitFor(() => {
      fixture.detectChanges();
      pedido = controle.expectOne(endereco);
    });
    const enviado = pedido.request.body;
    pedido.flush(corpo, { status, statusText: status === 200 ? 'OK' : 'erro' });
    await atualizar();
    return enviado;
  }

  const botao = (texto: string) =>
    [...pagina.querySelectorAll<HTMLButtonElement>('button')].find(
      (item) => item.textContent?.trim() === texto,
    )!;

  it('confirma só depois de aceitar os termos e tira o token do endereço', async () => {
    await abrir('confirmar');
    expect(trocarEndereco).toHaveBeenCalledWith('/reservas/confirmar');

    await responder(`${url}/reservas/confirmacao/${token}`, {
      data: reserva,
      etapa: 'CONFIRMACAO',
      pode_confirmar: true,
      prazo_confirmacao: '2026-11-11T13:00:00.000000Z',
      exige_aceite_termos: true,
      termos_versao: '2026-11',
    });
    await responder(`${url}/termos`, { data: termos });

    expect(pagina.textContent).toContain('Quadra Poliesportiva');
    expect(pagina.textContent).toContain('até 11/11 às 10:00');
    expect(pagina.textContent).toContain('Toda reserva precisa ser confirmada.');
    expect(pagina.textContent).toContain('Texto provisório');
    expect(botao('Confirmar reserva').disabled).toBe(true);

    const caixa = pagina.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
    caixa.click();
    await atualizar();
    expect(botao('Confirmar reserva').disabled).toBe(false);

    botao('Confirmar reserva').click();
    const corpo = await responder(`${url}/reservas/confirmacao/${token}`, {
      data: { ...reserva, confirmada_em: '2026-11-10T13:00:00Z' },
    });
    expect(corpo).toEqual({ aceite_termos: true });
    expect(pagina.textContent).toContain('Reserva confirmada!');
    expect(pagina.textContent).toContain('cancele pelo link "Cancelar reserva" do email');
    expect(botao('Cancelar reserva')).toBeUndefined();
  });

  it('na reconfirmação, confirma sem pedir os termos de novo', async () => {
    await abrir('confirmar');
    await responder(`${url}/reservas/confirmacao/${token}`, {
      data: { ...reserva, confirmada_em: '2026-11-02T12:00:00Z' },
      etapa: 'RECONFIRMACAO',
      pode_confirmar: true,
      prazo_confirmacao: '2026-11-15T03:00:00.000000Z',
      exige_aceite_termos: false,
      termos_versao: '2026-11',
    });
    await responder(`${url}/termos`, { data: termos });

    expect(pagina.textContent).toContain('Sua reserva está chegando');
    expect(pagina.textContent).toContain('até 14/11 às 23:59');
    expect(pagina.querySelector('input[type="checkbox"]')).toBeNull();

    botao('Confirmar reserva').click();
    const corpo = await responder(`${url}/reservas/confirmacao/${token}`, { data: reserva });
    expect(corpo).toEqual({ aceite_termos: false });
  });

  it('avisa quando a reserva não pode mais ser confirmada', async () => {
    await abrir('confirmar');
    await responder(`${url}/reservas/confirmacao/${token}`, {
      data: reserva,
      etapa: 'CONFIRMACAO',
      pode_confirmar: false,
      prazo_confirmacao: null,
      exige_aceite_termos: true,
      termos_versao: '2026-11',
    });
    await responder(`${url}/termos`, { data: termos });

    expect(pagina.textContent).toContain('não pode mais ser confirmada');
    expect(botao('Confirmar reserva')).toBeUndefined();
  });

  it('cancela pelo POST e mostra o resultado', async () => {
    await abrir('cancelar');
    expect(trocarEndereco).toHaveBeenCalledWith('/reservas/cancelar');
    await responder(`${url}/reservas/cancelamento/${token}`, {
      data: reserva,
      pode_cancelar: true,
    });

    botao('Cancelar reserva').click();
    await responder(`${url}/reservas/cancelamento/${token}`, {
      data: { ...reserva, status: 'CANCELADO' },
    });

    expect(pagina.textContent).toContain('Reserva cancelada');
  });

  it('mostra a mensagem da API para link inválido', async () => {
    await abrir('cancelar');
    await responder(
      `${url}/reservas/cancelamento/${token}`,
      { message: 'Link inválido ou expirado.' },
      404,
    );

    expect(pagina.textContent).toContain('Link inválido ou expirado.');
  });

  it('sem token, explica que é preciso abrir pelo email', async () => {
    await abrir('cancelar', false);

    expect(pagina.textContent).toContain('Abra de novo pelo botão do email.');
    expect(trocarEndereco).not.toHaveBeenCalled();
  });
});
