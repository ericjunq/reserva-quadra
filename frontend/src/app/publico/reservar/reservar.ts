import { Component, computed, inject, input, signal } from '@angular/core';
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
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import {
  deIso,
  ehDataIso,
  ehHora,
  formatarDia,
  paraIso,
  segundaDaSemana,
  somarDias,
} from '../datas';
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

const HORARIOS: Horario[] = ['PRIMEIRO_HORARIO', 'SEGUNDO_HORARIO'];

function minutos(hora: string): number {
  const [h, m] = hora.split(':').map(Number);
  return h * 60 + m;
}

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

  readonly id = input.required<string>();
  readonly data = input<string>();
  readonly horario = input<string>();
  readonly inicio = input<string>();
  readonly fim = input<string>();

  protected readonly formatarDia = formatarDia;

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

  protected readonly horarioEscolhido = computed<HorarioNoCalendario | null>(() => {
    const escolha = this.escolha();
    const dia = this.dia();
    if (!escolha || dia?.tipo !== 'pronto' || !dia.valor.reservavel) {
      return null;
    }
    if (escolha.horario) {
      const horario = dia.valor.horarios.find((item) => item.horario === escolha.horario);
      return horario?.situacao === 'LIVRE' ? horario : null;
    }
    const blocos = this.blocosLivresAPartirDoInicio(dia.valor, escolha);
    return blocos.length && blocos[blocos.length - 1].fim >= escolha.fim
      ? { ...blocos[0], fim: escolha.fim }
      : null;
  });

  protected readonly horarioIndisponivel = computed(
    () => this.dia()?.tipo === 'pronto' && !this.horarioEscolhido(),
  );

  protected readonly opcoesDeFim = computed(() => {
    const escolha = this.escolha();
    const dia = this.dia();
    if (!escolha || escolha.horario || dia?.tipo !== 'pronto') {
      return [];
    }
    const maximo = escolha.espaco.duracao_maxima_minutos ?? 0;
    return this.blocosLivresAPartirDoInicio(dia.valor, escolha)
      .map((bloco) => bloco.fim)
      .filter((fim) => minutos(fim) - minutos(escolha.inicio) <= maximo);
  });

  private readonly equipamentosDoIntervalo = toSignal(
    toObservable(
      computed(() => {
        const escolha = this.escolha();
        return escolha && !escolha.horario && this.horarioEscolhido() ? escolha : null;
      }),
    ).pipe(
      switchMap((escolha) =>
        escolha
          ? this.api
              .consultarEquipamentos(escolha.espaco.id, escolha.data, escolha.inicio, escolha.fim)
              .pipe(catchError(() => of([] as EquipamentoDisponivel[])))
          : of(null),
      ),
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

  protected mudarFim(fim: string): void {
    this.quantidades.set({});
    this.router.navigate([], {
      queryParams: { fim },
      queryParamsHandling: 'merge',
      replaceUrl: true,
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

  private blocosLivresAPartirDoInicio(
    dia: DiaNoCalendario,
    escolha: Escolha,
  ): HorarioNoCalendario[] {
    const blocos: HorarioNoCalendario[] = [];
    let esperado = escolha.inicio;
    for (const bloco of dia.horarios) {
      if (bloco.inicio < escolha.inicio) {
        continue;
      }
      if (bloco.inicio !== esperado || bloco.situacao !== 'LIVRE') {
        break;
      }
      blocos.push(bloco);
      esperado = bloco.fim;
    }
    return blocos;
  }

  private carregarDia(escolha: Escolha): Observable<Situacao<DiaNoCalendario>> {
    const segunda = segundaDaSemana(deIso(escolha.data));
    return this.api
      .consultarCalendario(escolha.espaco.id, paraIso(segunda), paraIso(somarDias(segunda, 4)))
      .pipe(
        map((calendario): Situacao<DiaNoCalendario> => {
          const dia = calendario.dias.find((item) => item.data === escolha.data);
          return dia
            ? { tipo: 'pronto', valor: dia }
            : { tipo: 'erro', mensagem: 'Essa data não aceita reservas.' };
        }),
        catchError((erro: ErroApi) =>
          of<Situacao<DiaNoCalendario>>({ tipo: 'erro', mensagem: erro.mensagem }),
        ),
        startWith<Situacao<DiaNoCalendario>>({ tipo: 'carregando' }),
      );
  }
}
