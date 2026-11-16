import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { SessaoService } from '../../nucleo/sessao.service';
import { NovoEvento } from '../eventos/novo-evento';
import { CalendarioAdmin } from './calendario-admin';
import { itensDoDia, linkDoEvento } from './itens-do-dia';
import { CalendarioAdmin as DadosDoCalendario, DiaAdmin } from './modelos';

const url = ambiente.urlApi;

const quadra: DadosDoCalendario['espaco'] = {
  id: 1,
  nome: 'Quadra Poliesportiva',
  tipo: 'QUADRA',
  modo_horario: 'FIXO',
  abre_as: null,
  fecha_as: null,
  duracao_maxima_minutos: null,
  bloco_minutos: null,
};

const laboratorio: DadosDoCalendario['espaco'] = {
  ...quadra,
  id: 4,
  nome: 'Laboratório de Informática',
  tipo: 'LABORATORIO',
  modo_horario: 'LIVRE',
  abre_as: '07:00',
  fecha_as: '22:00',
  duracao_maxima_minutos: 180,
  bloco_minutos: 15,
};

const reservaDaAna = {
  id: 9,
  tipo: 'ALUNO' as const,
  descricao: null,
  reservado_por: { tipo: 'ALUNO' as const, nome: 'Ana Souza', curso: 'Direito' },
  situacao_confirmacao: 'CONFIRMADA' as const,
};

function diaFixo(data: string): DiaAdmin {
  return {
    data,
    reservavel: false,
    motivo: 'Fim de semana.',
    horarios: [
      {
        horario: 'PRIMEIRO_HORARIO',
        inicio: '19:00',
        fim: '20:30',
        situacao: 'RESERVADO',
        motivo: null,
        reserva: reservaDaAna,
      },
      {
        horario: 'SEGUNDO_HORARIO',
        inicio: '20:30',
        fim: '22:00',
        situacao: 'INDISPONIVEL',
        motivo: 'Fim de semana.',
        reserva: null,
      },
    ],
  };
}

describe('calendário do admin', () => {
  describe('itens do dia', () => {
    it('no horário fixo, horário sem reserva nem bloqueio fica livre para evento, mesmo no fim de semana', () => {
      const itens = itensDoDia(diaFixo('2026-11-21'), quadra, '2026-11-16');
      expect(itens.map((item) => [item.tipo, item.podeCriarEvento])).toEqual([
        ['RESERVA', false],
        ['LIVRE', true],
      ]);
      expect(itensDoDia(diaFixo('2026-11-14'), quadra, '2026-11-16')[1].podeCriarEvento).toBe(
        false,
      );
    });

    it('no horário livre, junta ocupados e intervalos livres em ordem', () => {
      const dia: DiaAdmin = {
        data: '2026-11-18',
        reservavel: true,
        motivo: null,
        fim_minimo: null,
        ocupados: [
          {
            inicio: '14:00',
            fim: '15:30',
            situacao: 'EVENTO',
            motivo: null,
            reserva: { ...reservaDaAna, tipo: 'EVENTO', descricao: 'Palestra' },
          },
          {
            inicio: '09:00',
            fim: '10:00',
            situacao: 'BLOQUEADO',
            motivo: 'Manutenção',
            reserva: null,
          },
        ],
      };
      const itens = itensDoDia(dia, laboratorio, '2026-11-16');
      expect(itens.map((item) => `${item.tipo} ${item.inicio}-${item.fim}`)).toEqual([
        'LIVRE 07:00-09:00',
        'BLOQUEIO 09:00-10:00',
        'LIVRE 10:00-14:00',
        'EVENTO 14:00-15:30',
        'LIVRE 15:30-22:00',
      ]);
      expect(itens[1].motivo).toBe('Manutenção');
      expect(linkDoEvento(4, '2026-11-18', itens[2])).toEqual({
        espaco: 4,
        data: '2026-11-18',
        inicio: '10:00',
        fim: '14:00',
      });
      expect(
        linkDoEvento(1, '2026-11-21', itensDoDia(diaFixo('2026-11-21'), quadra, '2026-11-16')[1]),
      ).toEqual({
        espaco: 1,
        data: '2026-11-21',
        horario: 'SEGUNDO_HORARIO',
      });
    });
  });

  describe('telas', () => {
    let controle: HttpTestingController;
    let router: Router;

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
      router = TestBed.inject(Router);
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

    it('consulta a semana de segunda a domingo pela rota do admin e mostra quem reservou', async () => {
      const fixture = TestBed.createComponent(CalendarioAdmin);
      fixture.componentRef.setInput('semana', '2026-11-18');
      await estabilizar(fixture);
      controle.expectOne(`${url}/admin/espacos?per_page=100`).flush({
        data: [
          { id: 1, nome: 'Quadra Poliesportiva', ativo: true, tipo: 'QUADRA' },
          { id: 2, nome: 'Sala antiga', ativo: false, tipo: 'SALA' },
        ],
      });
      await estabilizar(fixture);

      const consulta = controle.expectOne(
        `${url}/admin/espacos/1/disponibilidade?de=2026-11-16&ate=2026-11-22`,
      );
      expect(consulta.request.headers.get('Authorization')).toBe('Bearer tok');
      consulta.flush({
        data: {
          espaco: quadra,
          de: '2026-11-16',
          ate: '2026-11-22',
          dias: [diaFixo('2099-11-21')],
        },
      });
      await estabilizar(fixture);

      const texto = fixture.nativeElement.textContent as string;
      expect(texto).toContain('Sala antiga (desativado)');
      expect(texto).toContain('Ana Souza · Direito');
      expect(texto).toContain('Confirmada');
      expect(texto).toContain('Fim de semana.');
      const criar = [...fixture.nativeElement.querySelectorAll('a')].find((a: HTMLAnchorElement) =>
        a.textContent?.includes('Criar evento aqui'),
      ) as HTMLAnchorElement;
      expect(criar.getAttribute('href')).toBe(
        '/admin/eventos/novo?espaco=1&data=2099-11-21&horario=SEGUNDO_HORARIO',
      );

      const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
      [...fixture.nativeElement.querySelectorAll('button')]
        .find((b: Element) => b.textContent?.includes('Próxima semana'))
        .click();
      expect(navegar).toHaveBeenCalledWith([], {
        queryParams: { semana: '2026-11-23' },
        queryParamsHandling: 'merge',
      });
    });

    it('novo evento vem preenchido, pede justificativa no conflito e reenvia com ela', async () => {
      const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
      const fixture = TestBed.createComponent(NovoEvento);
      fixture.componentRef.setInput('espaco', '4');
      fixture.componentRef.setInput('data', '2099-11-18');
      fixture.componentRef.setInput('inicio', '10:00');
      fixture.componentRef.setInput('fim', '14:00');
      await estabilizar(fixture);
      controle.expectOne(`${url}/espacos`).flush({
        data: [{ ...laboratorio, limite_semanal_por_curso: null }],
      });
      await estabilizar(fixture);
      controle
        .expectOne(
          `${url}/espacos/4/equipamentos/disponibilidade?data=2099-11-18&hora_inicio=10:00&hora_fim=14:00`,
        )
        .flush({ data: [{ id: 5, nome: 'Projetor', total: 2, disponivel: 1 }] });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      expect((elemento.querySelector('#evento-inicio') as HTMLInputElement).value).toBe('10:00');
      const descricao = elemento.querySelector('#evento-descricao') as HTMLInputElement;
      descricao.value = 'Maratona de programação';
      descricao.dispatchEvent(new Event('input'));
      const projetor = elemento.querySelector('#equipamento-5') as HTMLInputElement;
      projetor.value = '1';
      projetor.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
      await estabilizar(fixture);

      const primeira = controle.expectOne(`${url}/admin/reservas/eventos`);
      expect(primeira.request.body).toEqual({
        espaco_id: 4,
        data_reserva: '2099-11-18',
        hora_inicio: '10:00',
        hora_fim: '14:00',
        descricao: 'Maratona de programação',
        equipamentos: [{ id: 5, quantidade: 1 }],
      });
      primeira.flush(
        {
          message:
            'Já existe uma reserva nesse horário. Envie uma justificativa para revogá-la e criar o evento.',
          dados: { reserva_conflitante_id: 12 },
        },
        { status: 409, statusText: 'conflito' },
      );
      await estabilizar(fixture);
      expect(elemento.textContent).toContain('ver reserva nº 12');
      const botao = elemento.querySelector('button[type=submit]') as HTMLButtonElement;
      expect(botao.textContent).toContain('Revogar a reserva e criar o evento');
      expect(botao.disabled).toBe(true);

      const justificativa = elemento.querySelector('#evento-justificativa') as HTMLTextAreaElement;
      justificativa.value = 'Evento institucional marcado pela coordenação.';
      justificativa.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
      await estabilizar(fixture);

      const segunda = controle.expectOne(`${url}/admin/reservas/eventos`);
      expect(segunda.request.body.justificativa).toBe(
        'Evento institucional marcado pela coordenação.',
      );
      segunda.flush({ data: { id: 31 } }, { status: 201, statusText: 'criado' });
      expect(navegar).toHaveBeenCalledWith(['/admin/reservas', 31]);
    });
  });
});
