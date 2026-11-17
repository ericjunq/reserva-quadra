import { Location } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { ResumoDoAceiteApi } from '../modelos';
import { AceiteParceiro } from './aceite-parceiro';

const url = ambiente.urlApi;

const resumo: ResumoDoAceiteApi = {
  sistema: 'App da Atlética',
  organizacao: 'Atlética de Computação',
  responsavel_nome: 'Bruno Lima',
  finalidade: 'Reservar a quadra para os treinos.',
  permissoes: [{ valor: 'reservas:criar', nome: 'Reservar para um aluno pelo RA' }],
  renovacao: false,
  link_expira_em: '2026-11-24T13:00:00-03:00',
  termos: {
    versao: '2026-11',
    rascunho: true,
    secoes: [{ titulo: 'Dados pessoais', paragrafos: ['Envie só o RA de quem pediu.'] }],
  },
};

describe('aceite dos termos da API pelo responsável', () => {
  let controle: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
  });

  afterEach(() => controle.verify());

  async function estabilizar(fixture: { detectChanges(): void; whenStable(): Promise<unknown> }) {
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  }

  it('tira o token do endereço, mostra o pedido e os termos, e só aceita com a caixa marcada', async () => {
    const trocar = vi.spyOn(TestBed.inject(Location), 'replaceState');
    const fixture = TestBed.createComponent(AceiteParceiro);
    fixture.componentRef.setInput('token', 'abc123');
    await estabilizar(fixture);
    expect(trocar).toHaveBeenCalledWith('/parceiros/aceite');
    controle.expectOne(`${url}/parceiros/aceite/abc123`).flush({ data: resumo });
    await estabilizar(fixture);

    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('Pedido de acesso');
    expect(elemento.textContent).toContain('Reservar para um aluno pelo RA');
    expect(elemento.textContent).toContain('Envie só o RA de quem pediu.');
    expect(elemento.textContent).toContain('24/11 às 13:00');
    const botao = elemento.querySelector<HTMLButtonElement>('button[type=submit]')!;
    expect(botao.disabled).toBe(true);

    elemento.querySelector<HTMLInputElement>('input[type=checkbox]')!.click();
    await estabilizar(fixture);
    expect(botao.disabled).toBe(false);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));

    const aceite = controle.expectOne(`${url}/parceiros/aceite/abc123`);
    expect(aceite.request.method).toBe('POST');
    expect(aceite.request.body).toEqual({ aceite_termos: true });
    expect(aceite.request.headers.has('Authorization')).toBe(false);
    aceite.flush({
      data: {
        token: '7|segredo-do-parceiro',
        expira_em: '2027-11-17T13:00:00-03:00',
        permissoes: ['reservas:criar'],
      },
    });
    await estabilizar(fixture);

    expect(elemento.textContent).toContain('Ele aparece só agora');
    expect(elemento.querySelector<HTMLInputElement>('#token-gerado')!.value).toBe(
      '7|segredo-do-parceiro',
    );
    expect(elemento.textContent).toContain('17/11/2027');
  });

  it('link inválido mostra a mensagem da API', async () => {
    const fixture = TestBed.createComponent(AceiteParceiro);
    fixture.componentRef.setInput('token', 'velho');
    await estabilizar(fixture);
    controle.expectOne(`${url}/parceiros/aceite/velho`).flush(
      {
        message:
          'Link inválido ou expirado. Peça um novo link a um administrador do sistema de reservas.',
      },
      { status: 404, statusText: 'não encontrado' },
    );
    await estabilizar(fixture);
    expect(fixture.nativeElement.textContent).toContain('Link inválido ou expirado.');
  });

  it('sem token pede para abrir pelo email', async () => {
    const fixture = TestBed.createComponent(AceiteParceiro);
    await estabilizar(fixture);
    expect(fixture.nativeElement.textContent).toContain('Abra esta página pelo botão');
  });
});
