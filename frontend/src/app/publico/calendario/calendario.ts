import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  LucideArrowLeft,
  LucideChevronLeft,
  LucideChevronRight,
  LucideUsers,
} from '@lucide/angular';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import {
  deIso,
  ehDataIso,
  ehDiaUtil,
  formatarDia,
  formatarDiaCurto,
  formatarDiaDaSemanaCurto,
  paraIso,
  segundaDaSemana,
  somarDias,
} from '../datas';
import {
  Calendario as DadosCalendario,
  DiaNoCalendario,
  Espaco,
  HorarioNoCalendario,
} from '../modelos';
import { RaEmMemoriaService } from '../ra-em-memoria.service';
import { NOMES_DOS_TIPOS, descreverHorario } from '../textos';

type Estado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; calendario: DadosCalendario }
  | { tipo: 'erro'; mensagem: string };

interface ReservaAgrupada {
  id: number;
  inicio: string;
  fim: string;
  texto: string;
  doCurso: boolean;
}

const NOMES_DAS_SITUACOES: Record<string, string> = {
  LIVRE: 'Livre',
  RESERVADO: 'Reservado',
  EVENTO: 'Evento',
  BLOQUEADO: 'Bloqueado',
  ENCERRADO: 'Encerrado',
};

@Component({
  selector: 'app-calendario',
  imports: [
    FormsModule,
    RouterLink,
    AvisoErro,
    LucideArrowLeft,
    LucideChevronLeft,
    LucideChevronRight,
    LucideUsers,
  ],
  templateUrl: './calendario.html',
})
export class Calendario {
  private readonly api = inject(ApiPublicaService);
  private readonly router = inject(Router);
  private readonly raEmMemoria = inject(RaEmMemoriaService);

  readonly id = input.required<string>();
  readonly semana = input<string>();
  readonly dia = input<string>();

  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;
  protected readonly descreverHorario = descreverHorario;
  protected readonly formatarDia = formatarDia;
  protected readonly formatarDiaCurto = formatarDiaCurto;
  protected readonly formatarDiaDaSemanaCurto = formatarDiaDaSemanaCurto;

  protected readonly raDigitado = signal('');
  protected readonly raConsultado = signal<string | null>(null);
  protected readonly erroRa = signal<string | null>(null);
  protected readonly recarregar = signal(0);

  private readonly espacoId = computed(() => Number(this.id()));

  private readonly espacos = toSignal(
    this.api.listarEspacos().pipe(
      map((espacos) => ({ espacos, erro: null as string | null })),
      catchError((erro: ErroApi) => of({ espacos: [] as Espaco[], erro: erro.mensagem })),
    ),
  );

  protected readonly espaco = computed(() =>
    this.espacos()?.espacos.find((espaco) => espaco.id === this.espacoId()),
  );

  protected readonly espacoNaoEncontrado = computed(() => {
    const espacos = this.espacos();
    return !!espacos && !espacos.erro && !this.espaco();
  });

  protected readonly erroEspacos = computed(() => this.espacos()?.erro ?? null);

  protected readonly temLimitePorCurso = computed(() => !!this.espaco()?.limite_semanal_por_curso);

  private readonly segundaAtual = paraIso(segundaDaSemana(new Date()));

  protected readonly segunda = computed(() => {
    const semana = this.semana();
    const segunda = ehDataIso(semana) ? paraIso(segundaDaSemana(deIso(semana))) : null;
    return segunda && segunda > this.segundaAtual ? segunda : this.segundaAtual;
  });

  protected readonly sexta = computed(() => paraIso(somarDias(deIso(this.segunda()), 4)));

  private readonly consulta = computed(() => {
    const espaco = this.espaco();
    if (!espaco) {
      return null;
    }
    return {
      espacoId: espaco.id,
      de: this.segunda(),
      ate: this.sexta(),
      ra: espaco.limite_semanal_por_curso ? this.raConsultado() : null,
      recarregar: this.recarregar(),
    };
  });

  protected readonly estado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap((consulta) => (consulta ? this.carregar(consulta) : of(null))),
    ),
    { initialValue: null },
  );

  protected readonly calendario = computed(() => {
    const estado = this.estado();
    return estado?.tipo === 'pronto' ? estado.calendario : null;
  });

  protected readonly diasUteis = computed(
    () => this.calendario()?.dias.filter((dia) => ehDiaUtil(dia.data)) ?? [],
  );

  protected readonly semanaDoCurso = computed(
    () =>
      this.calendario()?.curso?.semanas.find((semana) => semana.inicio === this.segunda()) ?? null,
  );

  protected readonly ehLivre = computed(() => this.calendario()?.espaco.modo_horario === 'LIVRE');

  protected readonly diaEscolhido = computed<DiaNoCalendario | null>(() => {
    const dias = this.diasUteis();
    return (
      dias.find((dia) => dia.data === this.dia()) ??
      dias.find((dia) => this.horariosLivres(dia) > 0) ??
      dias.find((dia) => dia.reservavel) ??
      dias[0] ??
      null
    );
  });

  protected readonly podeVoltar = computed(() => this.segunda() > this.segundaAtual);

  protected readonly podeAvancar = computed(() => {
    const calendario = this.calendario();
    return !!calendario && paraIso(somarDias(deIso(this.segunda()), 7)) <= calendario.reservas_ate;
  });

  constructor() {
    const raLembrado = this.raEmMemoria.ra();
    if (raLembrado) {
      this.raDigitado.set(raLembrado);
      this.raConsultado.set(raLembrado);
    }

    effect(() => {
      const ra = this.raConsultado();
      const calendario = this.calendario();
      if (ra && calendario?.curso) {
        this.raEmMemoria.lembrar(ra);
      }
    });
  }

  protected irParaSemana(deslocamento: number): void {
    const segunda = paraIso(somarDias(deIso(this.segunda()), deslocamento * 7));
    this.router.navigate([], {
      queryParams: { semana: segunda, dia: null },
      queryParamsHandling: 'merge',
    });
  }

  protected escolherDia(data: string): void {
    this.router.navigate([], {
      queryParams: { dia: data },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  protected horariosLivres(dia: DiaNoCalendario): number {
    return dia.horarios.filter((horario) => this.podeReservar(dia, horario)).length;
  }

  protected resumoDoDia(dia: DiaNoCalendario): string {
    if (!dia.reservavel) {
      return 'Fechado';
    }
    const livres = this.horariosLivres(dia);
    if (livres === 0) {
      return 'Lotado';
    }
    return livres === 1 ? '1 livre' : `${livres} livres`;
  }

  protected reservasDoDia(dia: DiaNoCalendario): ReservaAgrupada[] {
    const agrupadas: ReservaAgrupada[] = [];
    for (const horario of dia.horarios) {
      const reserva = horario.reserva;
      if (!reserva) {
        continue;
      }
      const anterior = agrupadas[agrupadas.length - 1];
      if (anterior?.id === reserva.id && anterior.fim === horario.inicio) {
        anterior.fim = horario.fim;
        continue;
      }
      const quem = reserva.reservado_por;
      agrupadas.push({
        id: reserva.id,
        inicio: horario.inicio,
        fim: horario.fim,
        texto:
          reserva.tipo === 'EVENTO'
            ? reserva.descricao || 'Evento da faculdade'
            : `${quem.nome ?? ''}${quem.curso ? ` (${quem.curso})` : ''}`,
        doCurso: !!reserva.do_curso,
      });
    }
    return agrupadas;
  }

  protected consultarRa(): void {
    const ra = this.raDigitado().trim();
    if (!/^\d{10}$/.test(ra)) {
      this.erroRa.set('O RA tem 10 números.');
      return;
    }
    this.erroRa.set(null);
    this.raConsultado.set(ra);
  }

  protected esquecerRa(): void {
    this.raConsultado.set(null);
    this.raDigitado.set('');
    this.erroRa.set(null);
    this.raEmMemoria.esquecer();
  }

  protected tentarDeNovo(): void {
    this.recarregar.update((valor) => valor + 1);
  }

  protected nomeDaSituacao(horario: HorarioNoCalendario): string {
    return NOMES_DAS_SITUACOES[horario.situacao] ?? 'Indisponível';
  }

  protected podeReservar(dia: DiaNoCalendario, horario: HorarioNoCalendario): boolean {
    return (
      dia.reservavel && horario.situacao === 'LIVRE' && horario.disponivel_para_o_curso !== false
    );
  }

  protected parametrosDaReserva(dia: DiaNoCalendario, horario: HorarioNoCalendario) {
    return horario.horario
      ? { data: dia.data, horario: horario.horario }
      : { data: dia.data, inicio: horario.inicio, fim: horario.fim };
  }

  private carregar(consulta: {
    espacoId: number;
    de: string;
    ate: string;
    ra: string | null;
  }): Observable<Estado> {
    const pedido = consulta.ra
      ? this.api.consultarCalendarioDoCurso(
          consulta.espacoId,
          consulta.de,
          consulta.ate,
          consulta.ra,
        )
      : this.api.consultarCalendario(consulta.espacoId, consulta.de, consulta.ate);

    return pedido.pipe(
      map((calendario): Estado => ({ tipo: 'pronto', calendario })),
      catchError((erro: ErroApi) => {
        if (consulta.ra && erro.status === 422) {
          this.erroRa.set(erro.campos['registro_academico']?.[0] ?? erro.mensagem);
          this.raConsultado.set(null);
          this.raEmMemoria.esquecer();
          return of<Estado>({ tipo: 'carregando' });
        }
        return of<Estado>({ tipo: 'erro', mensagem: erro.mensagem });
      }),
      startWith<Estado>({ tipo: 'carregando' }),
    );
  }
}
