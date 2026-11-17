import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../../ambientes/ambiente';
import { interceptorApi } from '../../../nucleo/api.interceptor';
import { SessaoService } from '../../../nucleo/sessao.service';
import { ReservaAdmin } from '../modelos';
import { RegistrarDevolucao } from './registrar-devolucao';
import { RegistrarRetirada, janelaDaRetirada } from './registrar-retirada';

const url = ambiente.urlApi;

const reserva = {
  id: 7,
  data_reserva: '2026-11-09',
  hora_inicio: '19:00',
  hora_fim: '20:30',
  status: 'RESERVADO',
  equipamentos: [
    {
      id: 2,
      nome: 'Bola',
      quantidade: 3,
      retirada: null,
      devolucao: {
        situacao: 'PENDENTE',
        quantidade_devolvida: null,
        observacao: null,
        devolvido_em: null,
      },
      solicitacao: null,
    },
    {
      id: 5,
      nome: 'Projetor',
      quantidade: 1,
      retirada: null,
      devolucao: {
        situacao: 'PENDENTE',
        quantidade_devolvida: null,
        observacao: null,
        devolvido_em: null,
      },
      solicitacao: {
        situacao: 'RECUSADA',
        justificativa: 'Sem',
        respondida_em: null,
        responder_ate: null,
        obrigatorio: false,
      },
    },
  ],
} as unknown as ReservaAdmin;

describe('retirada de equipamento', () => {
  it('a janela abre 30 minutos antes e fecha no fim', () => {
    const em = (hora: string) => new Date(`2026-11-09T${hora}:00`).getTime();
    expect(janelaDaRetirada(reserva, em('18:29'))).toBe('antes');
    expect(janelaDaRetirada(reserva, em('18:30'))).toBe('aberta');
    expect(janelaDaRetirada(reserva, em('20:30'))).toBe('aberta');
    expect(janelaDaRetirada(reserva, em('20:31'))).toBe('encerrada');
    expect(janelaDaRetirada({ ...reserva, status: 'CANCELADO' }, em('19:00'))).toBe('fora');
  });

  describe('telas', () => {
    let controle: HttpTestingController;

    beforeEach(() => {
      sessionStorage.clear();
      TestBed.configureTestingModule({
        providers: [
          provideRouter([]),
          provideHttpClient(withInterceptors([interceptorApi])),
          provideHttpClientTesting(),
        ],
      });
      controle = TestBed.inject(HttpTestingController);
      TestBed.inject(SessaoService).iniciar('tok', new Date(Date.now() + 3_600_000).toISOString());
    });

    afterEach(() => {
      controle.verify();
      sessionStorage.clear();
    });

    async function estabilizar(fixture: { detectChanges(): void; whenStable(): Promise<unknown> }) {
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();
    }

    it('registra a retirada só dos itens entregues e mostra o motivo de cada item recusado', async () => {
      const fixture = TestBed.createComponent(RegistrarRetirada);
      fixture.componentRef.setInput('reserva', reserva);
      await estabilizar(fixture);
      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).toContain('Bola');
      expect(elemento.textContent).not.toContain('Projetor');

      const campo = elemento.querySelector<HTMLInputElement>('#retirada-2')!;
      campo.value = '2';
      campo.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      elemento.querySelector('form')!.dispatchEvent(new Event('submit'));

      const pedido = controle.expectOne(`${url}/admin/reservas/7/retirada`);
      expect(pedido.request.method).toBe('POST');
      expect(pedido.request.body).toEqual({
        itens: [{ equipamento_id: 2, quantidade_retirada: 2 }],
      });
      pedido.flush(
        {
          message: 'Alguns itens da retirada são inválidos.',
          dados: {
            itens_invalidos: [
              { equipamento_id: 2, motivo: 'Já foram registradas 3 unidade(s) devolvidas.' },
            ],
          },
        },
        { status: 422, statusText: 'inválido' },
      );
      await estabilizar(fixture);
      expect(elemento.textContent).toContain('Já foram registradas 3 unidade(s) devolvidas.');
    });

    it('a devolução parte da quantidade retirada', async () => {
      const fixture = TestBed.createComponent(RegistrarDevolucao);
      fixture.componentRef.setInput('reserva', {
        ...reserva,
        equipamentos: [
          {
            ...reserva.equipamentos![0],
            retirada: { quantidade: 2, retirado_em: '2026-11-09T21:50:00Z' },
          },
        ],
      });
      await estabilizar(fixture);
      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).toContain('2 retirado(s)');
      expect(elemento.querySelector<HTMLInputElement>('#quantidade-2')!.value).toBe('2');
    });
  });
});
