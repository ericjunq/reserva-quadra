import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { Calendario, DiaNoCalendario, Espaco, HorarioNoCalendario } from '../modelos';
import { RaEmMemoriaService } from '../ra-em-memoria.service';
import { AtualizacaoDisponibilidadeService } from '../atualizacao-disponibilidade.service';
import { Reservar } from './reservar';

const url = ambiente.urlApi;
const semana = `${url}/espacos/ID/disponibilidade?de=2026-11-09&ate=2026-11-13`;

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

const laboratorio: Espaco = {
  id: 4,
  nome: 'Laboratório de Informática',
  tipo: 'LABORATORIO',
  limite_semanal_por_curso: null,
  modo_horario: 'LIVRE',
  abre_as: '14:00',
  fecha_as: '17:00',
  duracao_maxima_minutos: 90,
};

function horarioFixo(dados: Partial<HorarioNoCalendario> = {}): HorarioNoCalendario {
  return {
    horario: 'PRIMEIRO_HORARIO',
    inicio: '19:00',
    fim: '20:30',
    situacao: 'LIVRE',
    motivo: null,
    reserva: null,
    equipamentos: [
      { id: 1, nome: 'Bola de futsal', total: 4, disponivel: 2 },
      { id: 2, nome: 'Coletes', total: 2, disponivel: 0 },
    ],
    ...dados,
  };
}

function calendario(espaco: Espaco, horarios: HorarioNoCalendario[]): Calendario {
  const dia = (data: string): DiaNoCalendario => ({
    data,
    reservavel: true,
    motivo: null,
    horarios,
  });
  return {
    espaco: { ...espaco, bloco_minutos: espaco.modo_horario === 'LIVRE' ? 30 : null },
    de: '2026-11-09',
    ate: '2026-11-13',
    reservas_ate: '2026-11-24',
    curso: null,
    dias: ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'].map(dia),
  };
}

describe('Reservar', () => {
  const mudancas = signal(0);
  let controle: HttpTestingController;
  let fixture: ComponentFixture<Reservar>;
  let pagina: HTMLElement;

  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 10, 10, 10));
    TestBed.configureTestingModule({
      imports: [Reservar],
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
        {
          provide: AtualizacaoDisponibilidadeService,
          useValue: { observar: () => undefined, versao: mudancas },
        },
      ],
    });
    controle = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    controle.verify();
    vi.useRealTimers();
  });

  async function estabilizar() {
    fixture.detectChanges();
    await fixture.whenStable();
  }

  async function responder(endereco: string, corpo: object | null, status = 200) {
    let corpoEnviado: unknown;
    await vi.waitFor(() => {
      fixture.detectChanges();
      const requisicao = controle.expectOne(endereco);
      corpoEnviado = requisicao.request.body;
      requisicao.flush(corpo, { status, statusText: status < 300 ? 'OK' : 'erro' });
    });
    await estabilizar();
    return corpoEnviado;
  }

  async function abrir(
    espaco: Espaco,
    parametros: Record<string, string>,
    horarios: HorarioNoCalendario[],
  ) {
    fixture = TestBed.createComponent(Reservar);
    fixture.componentRef.setInput('id', String(espaco.id));
    for (const [nome, valor] of Object.entries(parametros)) {
      fixture.componentRef.setInput(nome, valor);
    }
    pagina = fixture.nativeElement;
    await responder(`${url}/espacos`, { data: [quadra, laboratorio] });
    await responder(semana.replace('ID', String(espaco.id)), {
      data: calendario(espaco, horarios),
    });
  }

  function digitarRa(ra: string) {
    const campo = pagina.querySelector<HTMLInputElement>('#ra')!;
    campo.value = ra;
    campo.dispatchEvent(new Event('input'));
  }

  function clicar(rotulo: string) {
    pagina.querySelector<HTMLButtonElement>(`[aria-label="${rotulo}"]`)!.click();
  }

  function enviar() {
    pagina.querySelector('form')!.dispatchEvent(new Event('submit'));
  }

  it('reserva um horário fixo com equipamentos e mostra o próximo passo', async () => {
    await abrir(quadra, { data: '2026-11-12', horario: 'PRIMEIRO_HORARIO' }, [horarioFixo()]);

    expect(pagina.querySelector('aside')?.textContent).toContain('Quinta-feira, 12/11');
    expect(pagina.querySelector('aside')?.textContent).toContain('19:00 às 20:30');
    expect(pagina.textContent).toContain('Indisponível neste horário');

    clicar('Mais Bola de futsal');
    clicar('Mais Bola de futsal');
    clicar('Mais Bola de futsal');
    await estabilizar();
    expect(pagina.querySelector('aside')?.textContent).toContain('2× Bola de futsal');
    expect(pagina.querySelector<HTMLButtonElement>('[aria-label="Mais Coletes"]')!.disabled).toBe(
      true,
    );

    digitarRa('2024000001');
    enviar();
    const corpo = await responder(
      `${url}/reservas`,
      {
        data: {
          id: 9,
          data_reserva: '2026-11-12',
          hora_inicio: '19:00',
          hora_fim: '20:30',
          confirmacao_enviada_em: '2026-11-10T13:00:00Z',
          confirmada_em: null,
        },
      },
      201,
    );

    expect(corpo).toEqual({
      registro_academico: '2024000001',
      espaco_id: 1,
      data_reserva: '2026-11-12',
      horario: 'PRIMEIRO_HORARIO',
      equipamentos: [{ id: 1, quantidade: 2 }],
    });
    expect(pagina.textContent).toContain('Reserva feita!');
    expect(pagina.textContent).toContain('confirmação');
    expect(pagina.querySelector('#ra')).toBeNull();
  });

  it('usa o RA já informado no calendário e o esquece depois de reservar', async () => {
    const memoria = TestBed.inject(RaEmMemoriaService);
    memoria.lembrar('2024000001');
    await abrir(quadra, { data: '2026-11-12', horario: 'PRIMEIRO_HORARIO' }, [horarioFixo()]);

    expect(pagina.querySelector<HTMLInputElement>('#ra')!.value).toBe('2024000001');
    expect(pagina.querySelector('#ra')!.getAttribute('autocomplete')).toBe('off');

    enviar();
    await responder(
      `${url}/reservas`,
      {
        data: {
          id: 9,
          data_reserva: '2026-11-10',
          hora_inicio: '19:00',
          hora_fim: '20:30',
          confirmacao_enviada_em: null,
          confirmada_em: '2026-11-10T13:00:00Z',
        },
      },
      201,
    );

    expect(pagina.textContent).toContain('já está confirmada');
    expect(memoria.ra()).toBeNull();
  });

  it('em horário livre, usa o intervalo escolhido e consulta os equipamentos dele', async () => {
    fixture = TestBed.createComponent(Reservar);
    fixture.componentRef.setInput('id', '4');
    fixture.componentRef.setInput('data', '2026-11-12');
    fixture.componentRef.setInput('inicio', '14:00');
    fixture.componentRef.setInput('fim', '15:30');
    pagina = fixture.nativeElement;
    await responder(`${url}/espacos`, { data: [quadra, laboratorio] });
    const dados = calendario(laboratorio, []);
    dados.espaco.bloco_minutos = 15;
    dados.dias = dados.dias.map((dia) => ({
      data: dia.data,
      reservavel: true,
      motivo: null,
      fim_minimo: null,
      ocupados: [
        { inicio: '16:00', fim: '16:30', situacao: 'RESERVADO', motivo: null, reserva: null },
      ],
    }));
    await responder(semana.replace('ID', '4'), { data: dados });
    await responder(
      `${url}/espacos/4/equipamentos/disponibilidade?data=2026-11-12&hora_inicio=14:00&hora_fim=15:30`,
      { data: [{ id: 5, nome: 'Microscópio', total: 2, disponivel: 1 }] },
    );

    expect(pagina.querySelector('aside')?.textContent).toContain('14:00 às 15:30');
    expect(pagina.querySelector('aside a')?.textContent).toContain('Alterar horário');
    expect(pagina.textContent).toContain('Microscópio');

    digitarRa('2024000001');
    enviar();
    const corpo = await responder(
      `${url}/reservas`,
      {
        data: {
          id: 3,
          data_reserva: '2026-11-12',
          hora_inicio: '14:00',
          hora_fim: '15:30',
          confirmacao_enviada_em: null,
          confirmada_em: null,
        },
      },
      201,
    );

    expect(corpo).toEqual({
      registro_academico: '2024000001',
      espaco_id: 4,
      data_reserva: '2026-11-12',
      hora_inicio: '14:00',
      hora_fim: '15:30',
    });
    expect(pagina.textContent).toContain('Dois dias úteis antes');
  });

  it('em horário livre, avisa quando o intervalo já foi ocupado', async () => {
    fixture = TestBed.createComponent(Reservar);
    fixture.componentRef.setInput('id', '4');
    fixture.componentRef.setInput('data', '2026-11-12');
    fixture.componentRef.setInput('inicio', '15:30');
    fixture.componentRef.setInput('fim', '16:45');
    pagina = fixture.nativeElement;
    await responder(`${url}/espacos`, { data: [quadra, laboratorio] });
    const dados = calendario(laboratorio, []);
    dados.dias = dados.dias.map((dia) => ({
      data: dia.data,
      reservavel: true,
      motivo: null,
      fim_minimo: null,
      ocupados: [
        { inicio: '16:00', fim: '16:30', situacao: 'EVENTO', motivo: null, reserva: null },
      ],
    }));
    await responder(semana.replace('ID', '4'), { data: dados });

    expect(pagina.textContent).toContain(
      'Esse horário bate com 16:00–16:30 (Evento da faculdade).',
    );
    expect(pagina.querySelector('form')).toBeNull();
  });

  it('mostra o erro do RA com o link de cadastro que volta para a reserva', async () => {
    await abrir(quadra, { data: '2026-11-12', horario: 'PRIMEIRO_HORARIO' }, [horarioFixo()]);

    digitarRa('2099999999');
    enviar();
    await responder(
      `${url}/reservas`,
      {
        message: 'O campo registro acadêmico selecionado é inválido.',
        errors: { registro_academico: ['O campo registro acadêmico selecionado é inválido.'] },
      },
      422,
    );

    expect(pagina.querySelector('#erro-ra')?.textContent).toContain('inválido');
    expect(pagina.querySelector('#ra')!.getAttribute('aria-invalid')).toBe('true');
    expect(pagina.querySelector('a[href^="/cadastro"]')).not.toBeNull();
  });

  it('recusa RA com formato errado sem chamar a API', async () => {
    await abrir(quadra, { data: '2026-11-12', horario: 'PRIMEIRO_HORARIO' }, [horarioFixo()]);

    digitarRa('123');
    enviar();
    await estabilizar();

    expect(pagina.querySelector('#erro-ra')?.textContent).toContain('O RA tem 10 números.');
  });

  it('quando outra pessoa reserva antes, avisa e recarrega o horário', async () => {
    await abrir(quadra, { data: '2026-11-12', horario: 'PRIMEIRO_HORARIO' }, [horarioFixo()]);

    digitarRa('2024000001');
    enviar();
    await responder(
      `${url}/reservas`,
      { message: 'Já existe uma reserva para esse espaço nesse horário.' },
      409,
    );
    await responder(semana.replace('ID', '1'), {
      data: calendario(quadra, [horarioFixo({ situacao: 'RESERVADO' })]),
    });

    expect(pagina.textContent).toContain('Já existe uma reserva para esse espaço nesse horário.');
    expect(pagina.querySelector('form')).toBeNull();
  });

  it('pede para escolher no calendário quando a URL não tem um horário válido', async () => {
    fixture = TestBed.createComponent(Reservar);
    fixture.componentRef.setInput('id', '1');
    fixture.componentRef.setInput('data', '2026-02-30');
    pagina = fixture.nativeElement;
    await responder(`${url}/espacos`, { data: [quadra] });

    expect(pagina.textContent).toContain('Escolha um horário livre no calendário');
  });
});
