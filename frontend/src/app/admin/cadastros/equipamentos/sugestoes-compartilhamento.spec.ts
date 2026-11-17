import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../../ambientes/ambiente';
import { interceptorApi } from '../../../nucleo/api.interceptor';
import { SessaoService } from '../../../nucleo/sessao.service';
import { SugestaoCompartilhamento } from '../modelos';
import { SugestoesCompartilhamento } from './sugestoes-compartilhamento';

const url = ambiente.urlApi;

const projetor: SugestaoCompartilhamento = {
  equipamento: { id: 5, nome: 'Projetor' },
  espaco_de_origem: { id: 10, nome: 'Sala 10' },
  espaco_que_pede: { id: 2, nome: 'Lab 2' },
  pedidos: 4,
  ultimo_pedido_em: '2026-11-07T13:00:00-03:00',
};

const bola: SugestaoCompartilhamento = {
  ...projetor,
  equipamento: { id: 6, nome: 'Bola' },
  espaco_que_pede: { id: 3, nome: 'Quadra' },
  pedidos: 3,
};

describe('sugestões de compartilhamento', () => {
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

  function clicar(elemento: HTMLElement, texto: string) {
    [...elemento.querySelectorAll('button')]
      .find((botao) => botao.textContent?.trim().startsWith(texto))!
      .click();
  }

  it('lista, compartilha avisando quem usa o componente e dispensa', async () => {
    const fixture = TestBed.createComponent(SugestoesCompartilhamento);
    const compartilhados: unknown[] = [];
    fixture.componentInstance.compartilhado.subscribe((equipamento) =>
      compartilhados.push(equipamento),
    );
    await estabilizar(fixture);
    controle
      .expectOne(`${url}/admin/equipamentos/sugestoes-compartilhamento`)
      .flush({ data: [projetor, bola] });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('pedido 4 vezes pelo');
    expect(elemento.textContent).toContain('(Sala 10)');

    clicar(elemento, 'Compartilhar com Lab 2');
    const aceite = controle.expectOne(
      `${url}/admin/equipamentos/5/sugestoes-compartilhamento/2/aceitar`,
    );
    expect(aceite.request.method).toBe('POST');
    aceite.flush({
      data: { id: 5, nome: 'Projetor', espacos_compartilhados: [{ id: 2, nome: 'Lab 2' }] },
    });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Projetor agora é compartilhado com Lab 2.');
    expect(elemento.textContent).not.toContain('pedido 4 vezes');
    expect(compartilhados.length).toBe(1);

    clicar(elemento, 'Agora não');
    controle
      .expectOne(`${url}/admin/equipamentos/6/sugestoes-compartilhamento/3/dispensar`)
      .flush({ message: 'Sugestão dispensada.' });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Sugestão dispensada.');
    expect(elemento.querySelector('#titulo-sugestoes')).toBeNull();
  });

  it('no detalhe do equipamento, busca só as dele', async () => {
    const fixture = TestBed.createComponent(SugestoesCompartilhamento);
    fixture.componentRef.setInput('equipamentoId', 5);
    fixture.componentRef.setInput('mostrarEquipamento', false);
    await estabilizar(fixture);
    controle
      .expectOne(`${url}/admin/equipamentos/sugestoes-compartilhamento?equipamento_id=5`)
      .flush({ data: [projetor] });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.querySelector('a')).toBeNull();
    expect(elemento.textContent).toContain('Lab 2');
  });
});
