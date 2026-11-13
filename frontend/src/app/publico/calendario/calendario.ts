import {
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  signal,
  untracked,
} from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  LucideArrowLeft,
  LucideChevronLeft,
  LucideChevronRight,
  LucideUsers,
} from '@lucide/angular';
import { EMPTY, Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { CampoHorario } from '../../compartilhado/campo-horario/campo-horario';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { AtualizacaoDisponibilidadeService } from '../atualizacao-disponibilidade.service';
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
  Avaliacao,
  Janela,
  arredondar,
  avaliarIntervalo,
  descreverOcupacao,
  duracaoLegivel,
  duracoesPossiveis,
  finsPossiveis,
  hora,
  horariosLivres,
  inicioNaPosicao,
  iniciosLivres,
  marcasDaBarra,
  minutos,
  normalizarHora,
  trechosDaBarra,
} from '../intervalo';
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
    CampoHorario,
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
  private readonly atualizacao = inject(AtualizacaoDisponibilidadeService);

  readonly id = input.required<string>();
  readonly semana = input<string>();
  readonly dia = input<string>();

  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;
  protected readonly descreverHorario = descreverHorario;
  protected readonly formatarDia = formatarDia;
  protected readonly formatarDiaCurto = formatarDiaCurto;
  protected readonly formatarDiaDaSemanaCurto = formatarDiaDaSemanaCurto;
  protected readonly descreverOcupacao = descreverOcupacao;
  protected readonly duracaoLegivel = duracaoLegivel;

  protected readonly raDigitado = signal('');
  protected readonly raConsultado = signal<string | null>(null);
  protected readonly erroRa = signal<string | null>(null);
  protected readonly recarregar = signal(0);
  protected readonly inicioDigitado = signal('');
  protected readonly fimDigitado = signal('');

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

  protected readonly janela = computed<Janela | null>(() => {
    const espaco = this.calendario()?.espaco;
    if (espaco?.modo_horario !== 'LIVRE' || !espaco.abre_as || !espaco.fecha_as) {
      return null;
    }
    return {
      abre: espaco.abre_as,
      fecha: espaco.fecha_as,
      duracaoMaxima: espaco.duracao_maxima_minutos ?? 0,
      bloco: espaco.bloco_minutos ?? 15,
    };
  });

  protected readonly diaEscolhido = computed<DiaNoCalendario | null>(() => {
    const dias = this.diasUteis();
    return (
      dias.find((dia) => dia.data === this.dia()) ??
      dias.find((dia) => this.minutosLivres(dia) > 0) ??
      dias.find((dia) => dia.reservavel) ??
      dias[0] ??
      null
    );
  });

  protected readonly livresDoDia = computed(() => {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    return dia?.reservavel && janela ? horariosLivres(dia, janela) : [];
  });

  protected readonly iniciosDoDia = computed(() => {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    return dia && janela ? iniciosLivres(dia, janela) : [];
  });

  private readonly inicioArredondado = computed(() => {
    const janela = this.janela();
    return janela ? arredondar(normalizarHora(this.inicioDigitado()), janela.bloco) : null;
  });

  protected readonly finsDoInicio = computed(() => {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    const inicio = this.inicioArredondado();
    return dia && janela && inicio ? finsPossiveis(dia, janela, inicio) : [];
  });

  protected readonly duracoes = computed(() => {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    const inicio = this.inicioArredondado();
    return dia && janela && inicio ? duracoesPossiveis(dia, janela, inicio) : [];
  });

  protected readonly duracaoEscolhida = computed(() => {
    const avaliacao = this.avaliacao();
    return avaliacao?.inicio && avaliacao.fim
      ? minutos(avaliacao.fim) - minutos(avaliacao.inicio)
      : null;
  });

  protected readonly trechosOcupados = computed(() => {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    if (!dia || !janela) {
      return [];
    }
    const encerrados = dia.fim_minimo
      ? [{ inicio: janela.abre, fim: hora(minutos(dia.fim_minimo) - janela.bloco) }]
      : [];
    return trechosDaBarra([...encerrados, ...(dia.ocupados ?? [])], janela);
  });

  protected readonly trechoEscolhido = computed(() => {
    const avaliacao = this.avaliacao();
    const janela = this.janela();
    if (!avaliacao || !janela || !avaliacao.inicio || !avaliacao.fim) {
      return null;
    }
    const [trecho] = trechosDaBarra([avaliacao], janela);
    return trecho ? { ...trecho, valido: !avaliacao.erro } : null;
  });

  protected readonly marcas = computed(() => {
    const janela = this.janela();
    return janela ? marcasDaBarra(janela) : [];
  });

  protected readonly avaliacao = computed<Avaliacao | null>(() => {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    if (!dia || !janela || (!this.inicioDigitado() && !this.fimDigitado())) {
      return null;
    }
    return avaliarIntervalo(dia, janela, this.inicioDigitado(), this.fimDigitado());
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

    this.atualizacao.observar(inject(DestroyRef));
    let mudancasVistas = 0;
    effect(() => {
      const mudancas = this.atualizacao.versao();
      if (mudancas === mudancasVistas) {
        return;
      }
      mudancasVistas = mudancas;
      untracked(() => {
        this.api.esquecerCalendarios(this.espacoId());
        this.recarregar.update((valor) => valor + 1);
      });
    });

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
    this.inicioDigitado.set('');
    this.fimDigitado.set('');
    this.router.navigate([], {
      queryParams: { dia: data },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }

  protected escolherInicio(inicio: string): void {
    const fimAtual = arredondar(normalizarHora(this.fimDigitado()), this.janela()?.bloco ?? 15);
    this.inicioDigitado.set(inicio);
    if (fimAtual && !this.finsDoInicio().includes(fimAtual)) {
      this.fimDigitado.set('');
    }
  }

  protected escolherDuracao(duracao: number): void {
    const inicio = this.inicioArredondado();
    if (inicio) {
      this.inicioDigitado.set(inicio);
      this.fimDigitado.set(hora(minutos(inicio) + duracao));
    }
  }

  protected tocarBarra(evento: MouseEvent): void {
    const dia = this.diaEscolhido();
    const janela = this.janela();
    const barra = evento.currentTarget as HTMLElement;
    const largura = barra.getBoundingClientRect().width;
    if (!dia || !janela || !largura) {
      return;
    }
    const inicio = inicioNaPosicao(
      dia,
      janela,
      (evento.clientX - barra.getBoundingClientRect().left) / largura,
    );
    if (inicio) {
      this.escolherInicio(inicio);
    }
  }

  protected minutosLivres(dia: DiaNoCalendario): number {
    const janela = this.janela();
    if (!dia.reservavel || !janela) {
      return 0;
    }
    return horariosLivres(dia, janela).reduce(
      (total, livre) => total + minutos(livre.fim) - minutos(livre.inicio),
      0,
    );
  }

  protected resumoDoDia(dia: DiaNoCalendario): string {
    if (!dia.reservavel) {
      return 'Fechado';
    }
    const livres = this.minutosLivres(dia);
    return livres ? `${duracaoLegivel(livres)} livres` : 'Lotado';
  }

  protected continuar(): void {
    const avaliacao = this.avaliacao();
    const dia = this.diaEscolhido();
    if (!avaliacao || avaliacao.erro || !dia) {
      return;
    }
    this.router.navigate(['/espacos', this.espacoId(), 'reservar'], {
      queryParams: { data: dia.data, inicio: avaliacao.inicio, fim: avaliacao.fim },
    });
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
    return { data: dia.data, horario: horario.horario };
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

    const atual = untracked(() => this.calendario());
    const silencioso = atual?.espaco.id === consulta.espacoId && atual.de === consulta.de;

    const resultado = pedido.pipe(
      map((calendario): Estado => ({ tipo: 'pronto', calendario })),
      catchError((erro: ErroApi) => {
        if (consulta.ra && erro.status === 422) {
          this.erroRa.set(erro.campos['registro_academico']?.[0] ?? erro.mensagem);
          this.raConsultado.set(null);
          this.raEmMemoria.esquecer();
          return of<Estado>({ tipo: 'carregando' });
        }
        if (silencioso) {
          return EMPTY;
        }
        return of<Estado>({ tipo: 'erro', mensagem: erro.mensagem });
      }),
    );
    return silencioso ? resultado : resultado.pipe(startWith<Estado>({ tipo: 'carregando' }));
  }
}
