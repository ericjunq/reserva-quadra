import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Component } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { RaEmMemoriaService } from '../ra-em-memoria.service';
import { caminhoInterno } from '../../compartilhado/caminho-interno';
import { Cadastro } from './cadastro';

const url = ambiente.urlApi;

@Component({ template: '' })
class Destino {}

describe('Cadastro', () => {
  let controle: HttpTestingController;
  let fixture: ComponentFixture<Cadastro>;
  let pagina: HTMLElement;

  beforeEach(async () => {
    TestBed.configureTestingModule({
      imports: [Cadastro],
      providers: [
        provideRouter([{ path: 'espacos/:id/reservar', component: Destino }]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
  });

  afterEach(() => controle.verify());

  async function abrir(voltar?: string) {
    fixture = TestBed.createComponent(Cadastro);
    if (voltar) {
      fixture.componentRef.setInput('voltar', voltar);
    }
    pagina = fixture.nativeElement;
    await vi.waitFor(() => {
      fixture.detectChanges();
      controle.expectOne(`${url}/cursos`).flush({
        data: [
          { id: 2, nome: 'Direito', ativo: true },
          { id: 3, nome: 'Enfermagem', ativo: true },
        ],
      });
    });
    await fixture.whenStable();
  }

  function preencher(id: string, valor: string) {
    const campo = pagina.querySelector<HTMLInputElement | HTMLSelectElement>(`#${id}`)!;
    campo.value = valor;
    campo.dispatchEvent(new Event(campo instanceof HTMLSelectElement ? 'change' : 'input'));
  }

  async function enviar(resposta: object, status: number) {
    pagina.querySelector('form')!.dispatchEvent(new Event('submit'));
    let corpo: unknown;
    await vi.waitFor(() => {
      fixture.detectChanges();
      const requisicao = controle.expectOne(`${url}/alunos`);
      corpo = requisicao.request.body;
      requisicao.flush(resposta, { status, statusText: status < 300 ? 'OK' : 'erro' });
    });
    await fixture.whenStable();
    return corpo;
  }

  function preencherTudo() {
    preencher('nome', 'Ana Souza');
    preencher('ra', '2024000001');
    preencher('email', 'ana@email.com');
    const select = pagina.querySelector<HTMLSelectElement>('#curso')!;
    select.selectedIndex = 1;
    select.dispatchEvent(new Event('change'));
  }

  it('explica para que servem os dados e não deixa o navegador lembrar deles', async () => {
    await abrir();

    expect(pagina.textContent).toContain('só para registrar suas reservas');
    for (const id of ['nome', 'ra', 'email']) {
      expect(pagina.querySelector(`#${id}`)!.getAttribute('autocomplete')).toBe('off');
    }
  });

  it('cadastra e volta para a reserva com o RA já preenchido', async () => {
    await abrir('/espacos/1/reservar?data=2026-11-12&horario=PRIMEIRO_HORARIO');
    preencherTudo();

    const corpo = await enviar({ data: {} }, 201);

    expect(corpo).toEqual({
      nome_completo: 'Ana Souza',
      registro_academico: '2024000001',
      email: 'ana@email.com',
      curso_id: 2,
    });
    expect(TestBed.inject(Router).url).toBe(
      '/espacos/1/reservar?data=2026-11-12&horario=PRIMEIRO_HORARIO',
    );
    expect(TestBed.inject(RaEmMemoriaService).ra()).toBe('2024000001');
  });

  it('sem página de volta, mostra a confirmação do cadastro', async () => {
    await abrir();
    preencherTudo();
    await enviar({ data: {} }, 201);

    expect(pagina.textContent).toContain('Cadastro feito!');
  });

  it('mostra o erro de cada campo', async () => {
    await abrir();
    preencherTudo();
    await enviar(
      {
        message: 'O campo registro acadêmico já está sendo utilizado.',
        errors: {
          registro_academico: ['O campo registro acadêmico já está sendo utilizado.'],
          email: ['O campo email deve ser um endereço de e-mail válido.'],
        },
      },
      422,
    );

    expect(pagina.querySelector('#ra')!.getAttribute('aria-invalid')).toBe('true');
    expect(pagina.textContent).toContain('já está sendo utilizado');
    expect(pagina.textContent).toContain('endereço de e-mail válido');
  });

  it('só volta para endereços do próprio site', () => {
    expect(caminhoInterno('/espacos/1/reservar?data=2026-11-12')).toBe(
      '/espacos/1/reservar?data=2026-11-12',
    );
    expect(caminhoInterno('https://site-falso.com')).toBeNull();
    expect(caminhoInterno('//site-falso.com')).toBeNull();
    expect(caminhoInterno('/\\site-falso.com')).toBeNull();
    expect(caminhoInterno(undefined)).toBeNull();
  });
});
