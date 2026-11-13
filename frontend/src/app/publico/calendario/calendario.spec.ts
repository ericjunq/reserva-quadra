import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import {
  Calendario as DadosCalendario,
  DiaNoCalendario,
  Espaco,
  HorarioNoCalendario,
} from '../modelos';
import { RaEmMemoriaService } from '../ra-em-memoria.service';
import { AtualizacaoDisponibilidadeService } from '../atualizacao-disponibilidade.service';
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

const laboratorio: Espaco = {
  ...quadra,
  id: 4,
  nome: 'Laboratório de Informática',
  tipo: 'LABORATORIO',
  limite_semanal_por_curso: null,
  modo_horario: 'LIVRE',
  abre_as: '14:00',
  fecha_as: '16:30',
  duracao_maxima_minutos: 180,
};

function bloco(
  inicio: string,
  fim: string,
  dados: Partial<HorarioNoCalendario> = {},
): HorarioNoCalendario {
  return { horario: null, inicio, fim, situacao: 'LIVRE', motivo: null, reserva: null, ...dados };
}

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
  const mudancas = signal(0);
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
    await responder(`${url}/espacos`, { data: [quadra, sala, laboratorio] });
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
    dados.dias[1].horarios![0] = {
      ...dados.dias[1].horarios![0],
      situacao: 'RESERVADO',
      reserva: {
        id: 7,
        tipo: 'ALUNO',
        descricao: null,
        reservado_por: { tipo: 'ALUNO', nome: 'Ana Souza', curso: 'Direito' },
      },
    };
    dados.dias[2].horarios![0] = {
      ...dados.dias[2].horarios![0],
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
    dados.dias[1].horarios![1] = { ...dados.dias[1].horarios![1], disponivel_para_o_curso: false };

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

  it('recarrega a semana sem piscar a tela quando a disponibilidade muda', async () => {
    const pagina = await abrir(quadra);
    expect(pagina.textContent).not.toContain('Ana Souza');

    const atualizado = calendario();
    atualizado.dias[2].horarios![0] = {
      ...atualizado.dias[2].horarios![0],
      situacao: 'RESERVADO',
      reserva: {
        id: 8,
        tipo: 'ALUNO',
        descricao: null,
        reservado_por: { tipo: 'ALUNO', nome: 'Ana Souza', curso: 'Direito' },
      },
    };
    mudancas.set(1);
    fixture.detectChanges();
    expect(pagina.querySelectorAll('article').length).toBe(5);

    await responder(`${url}/espacos/1/disponibilidade?de=2026-11-09&ate=2026-11-13`, {
      data: atualizado,
    });
    expect(pagina.textContent).toContain('Ana Souza');
  });

  describe('horário livre', () => {
    function calendarioLivre(): DadosCalendario {
      const ocupados = [
        {
          inicio: '14:00',
          fim: '15:30',
          situacao: 'RESERVADO',
          motivo: null,
          reserva: {
            id: 7,
            tipo: 'ALUNO' as const,
            descricao: null,
            reservado_por: { tipo: 'ALUNO' as const, nome: 'Ana Souza', curso: 'Direito' },
          },
        },
      ];
      const diaLivre = (data: string, dados: Partial<DiaNoCalendario> = {}): DiaNoCalendario => ({
        data,
        reservavel: true,
        motivo: null,
        fim_minimo: null,
        ocupados,
        ...dados,
      });
      return calendario({
        espaco: {
          ...laboratorio,
          abre_as: '07:00',
          fecha_as: '22:00',
          duracao_maxima_minutos: 180,
          bloco_minutos: 15,
        },
        dias: [
          diaLivre('2026-11-09', { reservavel: false, motivo: 'Data passada.', ocupados: [] }),
          diaLivre('2026-11-10'),
          diaLivre('2026-11-11', { ocupados: [] }),
          diaLivre('2026-11-12'),
          diaLivre('2026-11-13'),
        ],
      });
    }

    async function atualizar() {
      fixture.detectChanges();
      await fixture.whenStable();
    }

    function digitar(id: string, valor: string) {
      const campo = fixture.nativeElement.querySelector(`#${id}`) as HTMLInputElement;
      campo.value = valor;
      campo.dispatchEvent(new Event('input'));
      fixture.detectChanges();
    }

    it('mostra a janela, os ocupados e os horários livres do dia escolhido', async () => {
      const pagina = await abrir(laboratorio, calendarioLivre());

      const dias = pagina.querySelectorAll<HTMLButtonElement>(
        '[aria-label="Escolha o dia"] button',
      );
      expect(dias.length).toBe(5);
      expect(dias[0].textContent).toContain('Fechado');
      expect(dias[1].textContent).toContain('13h30 livres');
      expect(dias[2].textContent).toContain('15h livres');
      expect(dias[1].getAttribute('aria-pressed')).toBe('true');

      const painel = pagina.querySelector('article')!;
      expect(painel.textContent).toContain('Aberto das 07:00 às 22:00 · até 3h por reserva');
      expect(painel.textContent).toContain('14:00–15:30 · Ana Souza (Direito)');
      expect(painel.textContent).toContain('07:00–14:00');
      expect(painel.textContent).toContain('15:30–22:00');
      expect(pagina.querySelectorAll('article').length).toBe(1);
    });

    it('arredonda o horário digitado, explica os erros e leva para a reserva', async () => {
      const pagina = await abrir(laboratorio, calendarioLivre());
      const continuar = () =>
        [...pagina.querySelectorAll<HTMLButtonElement>('button')].find(
          (botao) => botao.textContent?.trim() === 'Continuar',
        )!;
      expect(continuar().disabled).toBe(true);

      digitar('inicio', '13:07');
      digitar('fim', '14:38');
      expect(pagina.textContent).toContain(
        'Esse horário bate com 14:00–15:30 (Ana Souza (Direito)).',
      );
      expect(continuar().disabled).toBe(true);

      digitar('inicio', '15:37');
      digitar('fim', '17:52');
      expect(pagina.textContent).toContain('Sua reserva ficará das 15:30 às 17:45.');
      expect(continuar().disabled).toBe(false);

      const navegar = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
      pagina.querySelector('article form')!.dispatchEvent(new Event('submit'));
      expect(navegar).toHaveBeenCalledWith(['/espacos', 4, 'reservar'], {
        queryParams: { data: '2026-11-10', inicio: '15:30', fim: '17:45' },
      });
    });

    it('escolhe o início pela lista de livres e a duração por botões', async () => {
      const pagina = await abrir(laboratorio, calendarioLivre());
      const botao = (texto: string) =>
        [...pagina.querySelectorAll<HTMLButtonElement>('button')].find(
          (item) => item.textContent?.trim() === texto,
        )!;

      pagina
        .querySelector<HTMLButtonElement>('[aria-label="Mostrar os horários de início"]')!
        .click();
      await atualizar();
      expect(pagina.querySelectorAll('#inicio-lista [role="option"]').length).toBe(54);
      pagina
        .querySelector<HTMLButtonElement>('[aria-label="Mostrar os horários de início"]')!
        .click();
      await atualizar();
      expect(pagina.querySelector('fieldset')).toBeNull();

      pagina
        .querySelector<HTMLButtonElement>('[aria-label="Começar às 15:30, livre até 22:00"]')!
        .click();
      await atualizar();
      expect(pagina.querySelector<HTMLInputElement>('#inicio')!.value).toBe('15:30');
      expect(pagina.textContent).toContain('Agora escolha a duração ou digite o fim.');
      const duracoes = [...pagina.querySelectorAll('fieldset button')].map((item) =>
        item.textContent?.trim(),
      );
      expect(duracoes).toEqual(['30 min', '1h', '1h30', '2h', '2h30', '3h']);

      botao('1h30').click();
      await atualizar();
      expect(pagina.querySelector<HTMLInputElement>('#fim')!.value).toBe('17:00');
      expect(botao('1h30').getAttribute('aria-pressed')).toBe('true');
      expect(pagina.textContent).toContain('Sua reserva ficará das 15:30 às 17:00.');
      pagina.querySelector<HTMLButtonElement>('[aria-label="Mostrar os horários de fim"]')!.click();
      await atualizar();
      expect(pagina.querySelectorAll('#fim-lista [role="option"]').length).toBe(12);
      pagina.querySelector<HTMLButtonElement>('[aria-label="Mostrar os horários de fim"]')!.click();
      await atualizar();

      pagina
        .querySelector<HTMLButtonElement>('[aria-label="Começar às 07:00, livre até 14:00"]')!
        .click();
      await atualizar();
      expect(pagina.querySelector<HTMLInputElement>('#fim')!.value).toBe('');
    });

    it('começa no trecho livre tocado na barra do dia e mostra a escolha nela', async () => {
      const pagina = await abrir(laboratorio, calendarioLivre());
      const barra = pagina.querySelector<HTMLElement>('[data-testid="barra-do-dia"]')!;
      vi.spyOn(barra, 'getBoundingClientRect').mockReturnValue(
        DOMRect.fromRect({ x: 0, y: 0, width: 900, height: 40 }),
      );
      expect(barra.querySelectorAll('div').length).toBe(1);

      barra.dispatchEvent(new MouseEvent('click', { clientX: 450 }));
      await atualizar();
      expect(pagina.querySelector<HTMLInputElement>('#inicio')!.value).toBe('');

      barra.dispatchEvent(new MouseEvent('click', { clientX: 607 }));
      await atualizar();
      expect(pagina.querySelector<HTMLInputElement>('#inicio')!.value).toBe('17:00');

      digitar('fim', '18h');
      const escolha = barra.querySelectorAll<HTMLElement>('div')[1];
      expect(escolha.className).toContain('bg-amarelo');
      expect(escolha.style.left).toBe('66.66666666666666%');
    });

    it('aceita a hora digitada de outros jeitos e avisa quando o início está ocupado', async () => {
      const pagina = await abrir(laboratorio, calendarioLivre());

      digitar('inicio', '14h30');
      expect(pagina.textContent).toContain('Esse início não está livre.');
      expect(pagina.querySelector('fieldset')).toBeNull();

      digitar('inicio', '1600');
      digitar('fim', '18');
      expect(pagina.textContent).toContain('Sua reserva ficará das 16:00 às 18:00.');
    });

    it('troca de dia pela URL e limpa o horário digitado', async () => {
      const pagina = await abrir(laboratorio, calendarioLivre());
      const navegar = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
      digitar('inicio', '08:00');

      pagina.querySelectorAll<HTMLButtonElement>('[aria-label="Escolha o dia"] button')[2].click();
      expect(navegar).toHaveBeenCalledWith(
        [],
        expect.objectContaining({ queryParams: { dia: '2026-11-11' }, replaceUrl: true }),
      );

      fixture.componentRef.setInput('dia', '2026-11-11');
      fixture.detectChanges();
      await fixture.whenStable();
      expect(pagina.querySelector('#dia-escolhido')?.textContent).toContain('Quarta-feira, 11/11');
      expect(pagina.querySelector<HTMLInputElement>('#inicio')!.value).toBe('');
      expect(pagina.textContent).toContain('Nenhum. O dia está todo livre.');
    });
  });
});
