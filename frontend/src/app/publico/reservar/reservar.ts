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
  LucideCircleCheck,
  LucideMinus,
  LucidePackage,
  LucidePlus,
} from '@lucide/angular';
import {
  EMPTY,
  Observable,
  catchError,
  distinctUntilChanged,
  map,
  of,
  startWith,
  switchMap,
} from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { AtualizacaoDisponibilidadeService } from '../atualizacao-disponibilidade.service';
import {
  deIso,
  ehDataIso,
  ehHora,
  formatarDataHora,
  formatarDia,
  paraIso,
  segundaDaSemana,
  somarDias,
} from '../datas';
import { avaliarIntervalo } from '../intervalo';
import {
  DiaNoCalendario,
  EquipamentoDisponivel,
  Espaco,
  Horario,
  HorarioNoCalendario,
  NovaReserva,
  ReservaCriada,
} from '../modelos';
import { RaEmMemoriaService } from '../ra-em-memoria.service';

type Situacao<T> =
  { tipo: 'carregando' } | { tipo: 'pronto'; valor: T } | { tipo: 'erro'; mensagem: string };

interface Escolha {
  espaco: Espaco;
  data: string;
  horario: Horario | null;
  inicio: string;
  fim: string;
}

interface DiaCarregado {
  dia: DiaNoCalendario;
  bloco: number;
}

const HORARIOS: Horario[] = ['PRIMEIRO_HORARIO', 'SEGUNDO_HORARIO'];

@Component({
  selector: 'app-reservar',
  imports: [
    FormsModule,
    RouterLink,
    AvisoErro,
    LucideArrowLeft,
    LucideCircleCheck,
    LucideMinus,
    LucidePackage,
    LucidePlus,
  ],
  templateUrl: './reservar.html',
})
export class Reservar {
  private readonly api = inject(ApiPublicaService);
  private readonly router = inject(Router);
  private readonly raEmMemoria = inject(RaEmMemoriaService);
  private readonly atualizacao = inject(AtualizacaoDisponibilidadeService);

  readonly id = input.required<string>();
  readonly data = input<string>();
  readonly horario = input<string>();
  readonly inicio = input<string>();
  readonly fim = input<string>();

  protected readonly formatarDia = formatarDia;
  protected readonly formatarDataHora = formatarDataHora;

  protected readonly ra = signal(this.raEmMemoria.ra() ?? '');
  protected readonly quantidades = signal<Record<number, number>>({});
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly reservaCriada = signal<ReservaCriada | null>(null);
  private readonly recarregar = signal(0);

  private readonly espacos = toSignal(
    this.api.listarEspacos().pipe(
      map((espacos): Situacao<Espaco[]> => ({ tipo: 'pronto', valor: espacos })),
      catchError((erro: ErroApi) =>
        of<Situacao<Espaco[]>>({ tipo: 'erro', mensagem: erro.mensagem }),
      ),
    ),
  );

  protected readonly erroEspacos = computed(() => {
    const espacos = this.espacos();
    return espacos?.tipo === 'erro' ? espacos.mensagem : null;
  });

  protected readonly escolha = computed<Escolha | null | undefined>(() => {
    const espacos = this.espacos();
    if (espacos?.tipo !== 'pronto') {
      return undefined;
    }
    const espaco = espacos.valor.find((item) => item.id === Number(this.id()));
    const data = this.data();
    if (!espaco || !ehDataIso(data)) {
      return null;
    }
    if (espaco.modo_horario === 'FIXO') {
      const horario = this.horario() as Horario;
      return HORARIOS.includes(horario) ? { espaco, data, horario, inicio: '', fim: '' } : null;
    }
    const inicio = this.inicio();
    const fim = this.fim();
    return ehHora(inicio) && ehHora(fim) && inicio < fim
      ? { espaco, data, horario: null, inicio, fim }
      : null;
  });

  private readonly dia = toSignal(
    toObservable(computed(() => ({ escolha: this.escolha(), recarregar: this.recarregar() }))).pipe(
      switchMap(({ escolha }) => (escolha ? this.carregarDia(escolha) : of(null))),
    ),
    { initialValue: null },
  );

  protected readonly carregando = computed(
    () => this.escolha() === undefined || this.dia()?.tipo === 'carregando',
  );

  protected readonly erroDia = computed(() => {
    const dia = this.dia();
    return dia?.tipo === 'erro' ? dia.mensagem : null;
  });

  private readonly avaliacaoLivre = computed(() => {
    const escolha = this.escolha();
    const dia = this.dia();
    if (!escolha || escolha.horario || dia?.tipo !== 'pronto') {
      return null;
    }
    return avaliarIntervalo(
      dia.valor.dia,
      {
        abre: escolha.espaco.abre_as ?? '00:00',
        fecha: escolha.espaco.fecha_as ?? '23:59',
        duracaoMaxima: escolha.espaco.duracao_maxima_minutos ?? 0,
        bloco: dia.valor.bloco,
      },
      escolha.inicio,
      escolha.fim,
    );
  });

  protected readonly horarioEscolhido = computed<HorarioNoCalendario | null>(() => {
    const escolha = this.escolha();
    const dia = this.dia();
    if (!escolha || dia?.tipo !== 'pronto' || !dia.valor.dia.reservavel) {
      return null;
    }
    if (escolha.horario) {
      const horario = dia.valor.dia.horarios?.find((item) => item.horario === escolha.horario);
      return horario?.situacao === 'LIVRE' ? horario : null;
    }
    const avaliacao = this.avaliacaoLivre();
    return avaliacao && !avaliacao.erro
      ? {
          horario: null,
          inicio: avaliacao.inicio,
          fim: avaliacao.fim,
          situacao: 'LIVRE',
          motivo: null,
          reserva: null,
        }
      : null;
  });

  protected readonly horarioIndisponivel = computed(
    () => this.dia()?.tipo === 'pronto' && !this.horarioEscolhido(),
  );

  protected readonly motivoIndisponivel = computed(
    () =>
      this.erro()?.mensagem ??
      this.avaliacaoLivre()?.erro ??
      'Este horário não está mais livre. Escolha outro no calendário.',
  );

  protected readonly voltarAoCalendario = computed(() => {
    const data = this.data();
    if (!ehDataIso(data)) {
      return {};
    }
    return { semana: paraIso(segundaDaSemana(deIso(data))), dia: data };
  });

  private readonly equipamentosDoIntervalo = toSignal(
    toObservable(
      computed(() => {
        const escolha = this.escolha();
        const horario = this.horarioEscolhido();
        return escolha && !escolha.horario && horario
          ? `${escolha.espaco.id}|${escolha.data}|${horario.inicio}|${horario.fim}|${this.recarregar()}`
          : null;
      }),
    ).pipe(
      distinctUntilChanged(),
      switchMap((chave) => {
        if (!chave) {
          return of(null);
        }
        const [espacoId, data, inicio, fim] = chave.split('|');
        return this.api
          .consultarEquipamentos(Number(espacoId), data, inicio, fim)
          .pipe(catchError(() => of([] as EquipamentoDisponivel[])));
      }),
    ),
    { initialValue: null },
  );

  protected readonly equipamentos = computed<EquipamentoDisponivel[]>(() => {
    const horario = this.horarioEscolhido();
    if (!horario) {
      return [];
    }
    return horario.equipamentos ?? this.equipamentosDoIntervalo() ?? [];
  });

  protected readonly itensEscolhidos = computed(() =>
    this.equipamentos()
      .map((equipamento) => ({ equipamento, quantidade: this.quantidades()[equipamento.id] ?? 0 }))
      .filter((item) => item.quantidade > 0),
  );

  protected readonly faixaDeHorario = computed(() => {
    const horario = this.horarioEscolhido();
    return horario ? `${horario.inicio} às ${horario.fim}` : '';
  });

  protected readonly caminhoAtual = computed(() => this.router.url);

  constructor() {
    this.atualizacao.observar(inject(DestroyRef));
    let mudancasVistas = 0;
    effect(() => {
      const mudancas = this.atualizacao.versao();
      if (mudancas === mudancasVistas) {
        return;
      }
      mudancasVistas = mudancas;
      untracked(() => {
        const escolha = this.escolha();
        if (escolha && !this.reservaCriada()) {
          this.api.esquecerCalendarios(escolha.espaco.id);
          this.recarregar.update((valor) => valor + 1);
        }
      });
    });
  }

  protected alterarQuantidade(equipamento: EquipamentoDisponivel, variacao: number): void {
    this.quantidades.update((atuais) => {
      const nova = Math.min(
        Math.max((atuais[equipamento.id] ?? 0) + variacao, 0),
        equipamento.disponivel,
      );
      return { ...atuais, [equipamento.id]: nova };
    });
  }

  protected reservar(): void {
    const escolha = this.escolha();
    const horario = this.horarioEscolhido();
    const ra = this.ra().trim();
    if (!escolha || !horario || this.enviando()) {
      return;
    }
    if (!/^\d{10}$/.test(ra)) {
      this.erro.set({
        status: 422,
        mensagem: 'O RA tem 10 números.',
        campos: { registro_academico: ['O RA tem 10 números.'] },
        dados: {},
      });
      return;
    }

    const reserva: NovaReserva = {
      registro_academico: ra,
      espaco_id: escolha.espaco.id,
      data_reserva: escolha.data,
      ...(escolha.horario
        ? { horario: escolha.horario }
        : { hora_inicio: horario.inicio, hora_fim: horario.fim }),
    };
    const itens = this.itensEscolhidos().map((item) => ({
      id: item.equipamento.id,
      quantidade: item.quantidade,
    }));
    if (itens.length) {
      reserva.equipamentos = itens;
    }

    this.enviando.set(true);
    this.erro.set(null);
    this.api.reservar(reserva).subscribe({
      next: (criada) => {
        this.enviando.set(false);
        this.reservaCriada.set(criada);
        this.ra.set('');
        this.quantidades.set({});
        this.raEmMemoria.esquecer();
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
        if (erro.status === 409) {
          this.api.esquecerCalendarios(escolha.espaco.id);
          this.recarregar.update((valor) => valor + 1);
        }
      },
    });
  }

  protected faltaDeEquipamentos(): { nome: string; disponivel: number }[] {
    const lista = this.erro()?.dados['equipamentos_indisponiveis'];
    return Array.isArray(lista) ? lista : [];
  }

  private carregarDia(escolha: Escolha): Observable<Situacao<DiaCarregado>> {
    const segunda = segundaDaSemana(deIso(escolha.data));
    const atual = untracked(() => this.dia());
    const silencioso = atual?.tipo === 'pronto' && atual.valor.dia.data === escolha.data;
    const resultado = this.api
      .consultarCalendario(escolha.espaco.id, paraIso(segunda), paraIso(somarDias(segunda, 4)))
      .pipe(
        map((calendario): Situacao<DiaCarregado> => {
          const dia = calendario.dias.find((item) => item.data === escolha.data);
          return dia
            ? { tipo: 'pronto', valor: { dia, bloco: calendario.espaco.bloco_minutos ?? 15 } }
            : { tipo: 'erro', mensagem: 'Essa data não aceita reservas.' };
        }),
        catchError((erro: ErroApi) =>
          silencioso
            ? EMPTY
            : of<Situacao<DiaCarregado>>({ tipo: 'erro', mensagem: erro.mensagem }),
        ),
      );
    return silencioso
      ? resultado
      : resultado.pipe(startWith<Situacao<DiaCarregado>>({ tipo: 'carregando' }));
  }
}
