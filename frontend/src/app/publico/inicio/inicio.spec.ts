import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { Espaco } from '../modelos';
import { Inicio } from './inicio';

const espaco = (dados: Partial<Espaco>): Espaco => ({
  id: 1,
  nome: 'Quadra',
  tipo: 'QUADRA',
  limite_semanal_por_curso: null,
  modo_horario: 'FIXO',
  abre_as: null,
  fecha_as: null,
  duracao_maxima_minutos: null,
  ...dados,
});

describe('Inicio', () => {
  let controle: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Inicio],
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
  });

  async function abrir(espacos: Espaco[]) {
    const fixture = TestBed.createComponent(Inicio);
    controle.expectOne(`${ambiente.urlApi}/espacos`).flush({ data: espacos });
    await fixture.whenStable();
    return { fixture, pagina: fixture.nativeElement as HTMLElement };
  }

  it('lista os espaços com horário, limite e link para o calendário', async () => {
    const { pagina } = await abrir([
      espaco({ id: 1, nome: 'Quadra Poliesportiva', limite_semanal_por_curso: 3 }),
      espaco({
        id: 4,
        nome: 'Laboratório de Informática',
        tipo: 'LABORATORIO',
        modo_horario: 'LIVRE',
        abre_as: '07:00',
        fecha_as: '22:00',
        duracao_maxima_minutos: 180,
      }),
    ]);

    const cartoes = pagina.querySelectorAll('ul.grid > li');
    expect(cartoes.length).toBe(2);
    expect(cartoes[0].textContent).toContain('Até 3 reservas por curso na semana');
    expect(cartoes[0].textContent).toContain('Horários fixos');
    expect(cartoes[1].textContent).toContain('das 07:00 às 22:00, até 3h por reserva');
    expect(cartoes[1].querySelector('a')?.getAttribute('href')).toBe('/espacos/4');
  });

  it('filtra por tipo', async () => {
    const { fixture, pagina } = await abrir([
      espaco({ id: 1, nome: 'Quadra Poliesportiva' }),
      espaco({ id: 5, nome: 'Sala 101', tipo: 'SALA' }),
    ]);

    const botaoSala = [...pagina.querySelectorAll('button')].find(
      (botao) => botao.textContent?.trim() === 'Sala',
    )!;
    botaoSala.click();
    await fixture.whenStable();

    const nomes = [...pagina.querySelectorAll('ul.grid h3')].map((h3) => h3.textContent);
    expect(nomes).toEqual(['Sala 101']);
    expect(botaoSala.getAttribute('aria-pressed')).toBe('true');
  });

  it('mostra o erro e permite tentar de novo', async () => {
    const fixture = TestBed.createComponent(Inicio);
    controle
      .expectOne(`${ambiente.urlApi}/espacos`)
      .flush(null, { status: 500, statusText: 'erro' });
    await fixture.whenStable();
    const pagina = fixture.nativeElement as HTMLElement;

    expect(pagina.querySelector('[role="alert"]')?.textContent).toContain('erro no servidor');

    pagina.querySelector<HTMLButtonElement>('[role="alert"] button')!.click();
    controle.expectOne(`${ambiente.urlApi}/espacos`).flush({ data: [] });
    await fixture.whenStable();

    expect(pagina.textContent).toContain('Nenhum espaço disponível');
  });
});
