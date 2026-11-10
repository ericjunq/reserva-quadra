import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { Calendario as DadosCalendario, DiaNoCalendario, Espaco } from '../modelos';
import { RaEmMemoriaService } from '../ra-em-memoria.service';
import { Calendario } from './calendario';

const url = ambiente.urlApi;

const quadra: Espaco = {
  id: 1,
  nome: 'Quadra Poliesportiva',
  tipo: 'QUADRA',
  limite_semanal_por_curso: 3,
  modo_horario: 'FIXO',
  abre_as: null,
  fecha_as: null,
  duracao_maxima_minutos: null,
};

const sala: Espaco = {
  ...quadra,
  id: 5,
  nome: 'Sala 101',
  tipo: 'SALA',
  limite_semanal_por_curso: null,
};

function dia(data: string, dados: Partial<DiaNoCalendario> = {}): DiaNoCalendario {
  return {
    data,
    reservavel: true,
    motivo: null,
    horarios: [
      {
        horario: 'PRIMEIRO_HORARIO',
        inicio: '19:00',
        fim: '20:30',
        situacao: 'LIVRE',
        motivo: null,
        reserva: null,
      },
      {
        horario: 'SEGUNDO_HORARIO',
        inicio: '20:30',
        fim: '22:00',
        situacao: 'LIVRE',
        motivo: null,
        reserva: null,
      },
    ],
    ...dados,
  };
}

function calendario(dados: Partial<DadosCalendario> = {}): DadosCalendario {
  return {
    espaco: { ...quadra, bloco_minutos: null },
    de: '2026-11-09',
    ate: '2026-11-13',
    reservas_ate: '2026-11-24',
    curso: null,
    dias: ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'].map((data) =>
      dia(data),
    ),
    ...dados,
  };
}

describe('Calendario', () => {
  let controle: HttpTestingController;
  let fixture: ComponentFixture<Calendario>;

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 10, 10, 10));
    TestBed.configureTestingModule({
      imports: [Calendario],
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    controle.verify();
    vi.useRealTimers();
  });

  async function responder(endereco: string, corpo: object, status = 200) {
    await vi.waitFor(() => {
      fixture.detectChanges();
      controle
        .expectOne(endereco)
        .flush(corpo, { status, statusText: status === 200 ? 'OK' : 'erro' });
    });
    await fixture.whenStable();
  }

  async function abrir(espaco: Espaco, dados: DadosCalendario = calendario()) {
    fixture = TestBed.createComponent(Calendario);
    fixture.componentRef.setInput('id', String(espaco.id));
    await responder(`${url}/espacos`, { data: [quadra, sala] });
    await responder(`${url}/espacos/${espaco.id}/disponibilidade?de=2026-11-09&ate=2026-11-13`, {
      data: dados,
    });
    return fixture.nativeElement as HTMLElement;
  }

  it('mostra a semana de segunda a sexta com os horários livres para reservar', async () => {
    const pagina = await abrir(quadra);

    expect(pagina.querySelector('h1')?.textContent).toContain('Quadra Poliesportiva');
    expect(pagina.querySelectorAll('article').length).toBe(5);
    expect(pagina.textContent).toContain('Semana de 09/11 a 13/11');

    const link = pagina.querySelector<HTMLAnchorElement>('article a')!;
    expect(link.getAttribute('href')).toBe(
      '/espacos/1/reservar?data=2026-11-09&horario=PRIMEIRO_HORARIO',
    );
    expect(link.getAttribute('aria-label')).toBe(
      'Reservar Segunda-feira, 09/11, das 19:00 às 20:30',
    );
  });

  it('mostra quem reservou, o motivo dos dias fechados e situações desconhecidas', async () => {
    const dados = calendario();
    dados.dias[0] = dia('2026-11-09', { reservavel: false, motivo: 'Data passada.' });
    dados.dias[1].horarios[0] = {
      ...dados.dias[1].horarios[0],
      situacao: 'RESERVADO',
      reserva: {
        id: 7,
        tipo: 'ALUNO',
        descricao: null,
        reservado_por: { tipo: 'ALUNO', nome: 'Ana Souza', curso: 'Direito' },
      },
    };
    dados.dias[2].horarios[0] = {
      ...dados.dias[2].horarios[0],
      situacao: 'AULA',
      motivo: 'Aula de Anatomia',
    };
    const pagina = await abrir(quadra, dados);
    const dias = pagina.querySelectorAll('article');

    expect(dias[0].textContent).toContain('Data passada.');
    expect(dias[0].querySelector('a')).toBeNull();
    expect(dias[1].textContent).toContain('Reservado');
    expect(dias[1].textContent).toContain('Ana Souza');
    expect(dias[1].textContent).toContain('Direito');
    expect(dias[2].textContent).toContain('Indisponível');
    expect(dias[2].textContent).toContain('Aula de Anatomia');
    expect(pagina.textContent).not.toMatch(/\d{10}/);
  });

  it('só pede o RA em espaços com limite por curso', async () => {
    const pagina = await abrir(sala, calendario({ espaco: { ...sala, bloco_minutos: null } }));

    expect(pagina.querySelector('#ra-calendario')).toBeNull();
  });

  it('consulta o limite do curso pelo RA, no corpo de um POST, e lembra o RA só na memória', async () => {
    const pagina = await abrir(quadra);
    const campo = pagina.querySelector<HTMLInputElement>('#ra-calendario')!;
    expect(campo.getAttribute('autocomplete')).toBe('off');

    campo.value = '2024000001';
    campo.dispatchEvent(new Event('input'));
    pagina.querySelector('form')!.dispatchEvent(new Event('submit'));

    const dados = calendario({
      curso: {
        limite_semanal: 3,
        semanas: [{ inicio: '2026-11-09', fim: '2026-11-13', reservas: 2, restantes: 1 }],
      },
    });
    dados.dias[1].horarios[1] = { ...dados.dias[1].horarios[1], disponivel_para_o_curso: false };

    await vi.waitFor(() => {
      fixture.detectChanges();
      const requisicao = controle.expectOne(`${url}/espacos/1/disponibilidade/curso`);
      expect(requisicao.request.method).toBe('POST');
      expect(requisicao.request.body).toEqual({
        de: '2026-11-09',
        ate: '2026-11-13',
        registro_academico: '2024000001',
      });
      requisicao.flush({ data: dados });
    });
    await fixture.whenStable();

    expect(pagina.textContent).toContain('O seu curso ainda pode fazer 1 reserva nesta semana.');
    expect(pagina.textContent).toContain('Limite do seu curso atingido');
    expect(TestBed.inject(RaEmMemoriaService).ra()).toBe('2024000001');
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
  });

  it('avisa quando o RA não existe e volta ao calendário sem RA', async () => {
    const pagina = await abrir(quadra);
    const campo = pagina.querySelector<HTMLInputElement>('#ra-calendario')!;
    campo.value = '2099999999';
    campo.dispatchEvent(new Event('input'));
    pagina.querySelector('form')!.dispatchEvent(new Event('submit'));

    await responder(
      `${url}/espacos/1/disponibilidade/curso`,
      {
        message: 'Não encontramos um aluno ativo com este RA.',
        errors: { registro_academico: ['Não encontramos um aluno ativo com este RA.'] },
      },
      422,
    );
    fixture.detectChanges();
    await fixture.whenStable();

    expect(pagina.textContent).toContain('Não encontramos um aluno ativo com este RA.');
    expect(pagina.querySelector('a[href="/cadastro"]')).not.toBeNull();
    expect(TestBed.inject(RaEmMemoriaService).ra()).toBeNull();
    expect(pagina.querySelectorAll('article').length).toBe(5);
  });

  it('recusa RA com formato errado sem chamar a API', async () => {
    const pagina = await abrir(quadra);
    const campo = pagina.querySelector<HTMLInputElement>('#ra-calendario')!;
    campo.value = '123';
    campo.dispatchEvent(new Event('input'));
    pagina.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();
    await fixture.whenStable();

    expect(pagina.textContent).toContain('O RA tem 10 números.');
  });

  it('não volta para semanas passadas', async () => {
    const pagina = await abrir(quadra);

    const [anterior, proxima] = [
      pagina.querySelector<HTMLButtonElement>('[aria-label="Semana anterior"]')!,
      pagina.querySelector<HTMLButtonElement>('[aria-label="Próxima semana"]')!,
    ];
    expect(anterior.disabled).toBe(true);
    expect(proxima.disabled).toBe(false);
  });
});
