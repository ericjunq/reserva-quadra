import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
  Observable,
  catchError,
  debounceTime,
  distinctUntilChanged,
  map,
  of,
  startWith,
  switchMap,
} from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { formatarDia } from '../../../publico/datas';
import { StatusReserva } from '../../../publico/modelos';
import { ExportarReservas } from '../exportar-reservas/exportar-reservas';
import {
  CONFIRMACOES,
  DEVOLUCOES,
  PERIODOS,
  ParametrosDaUrl,
  STATUS,
  filtrosDaUrl,
} from '../filtros';
import { FiltrosReservas, PaginaReservasAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';
import {
  NOMES_DAS_DEVOLUCOES,
  SELOS_DE_CONFIRMACAO,
  SELOS_DE_STATUS,
  SelosReserva,
} from '../selos';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: PaginaReservasAdmin }
  | { tipo: 'erro'; erro: ErroApi };

interface Consulta {
  filtros: FiltrosReservas;
  pagina: number;
  recarga: number;
}

@Component({
  selector: 'app-lista-reservas',
  imports: [FormsModule, RouterLink, AvisoErro, SelosReserva, ExportarReservas],
  templateUrl: './lista-reservas.html',
})
export class ListaReservas {
  private readonly api = inject(ReservasAdminService);
  private readonly publica = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly periodo = input<string>();
  readonly de = input<string>();
  readonly ate = input<string>();
  readonly espaco = input<string>();
  readonly curso = input<string>();
  readonly status = input<string>();
  readonly tipo = input<string>();
  readonly confirmacao = input<string>();
  readonly devolucao = input<string>();
  readonly pagina = input<string>();

  protected readonly busca = signal('');
  protected readonly escolhendoDatas = signal(false);
  private readonly recarga = signal(0);

  protected readonly periodos = PERIODOS;
  protected readonly todosOsStatus = STATUS;
  protected readonly confirmacoes = CONFIRMACOES;
  protected readonly devolucoes = DEVOLUCOES;
  protected readonly selosDeStatus = SELOS_DE_STATUS;
  protected readonly selosDeConfirmacao = SELOS_DE_CONFIRMACAO;
  protected readonly nomesDasDevolucoes = NOMES_DAS_DEVOLUCOES;
  protected readonly formatarDia = formatarDia;

  protected readonly espacos = toSignal(
    this.publica.listarEspacos().pipe(catchError(() => of([]))),
    {
      initialValue: [],
    },
  );
  protected readonly cursos = toSignal(this.publica.listarCursos().pipe(catchError(() => of([]))), {
    initialValue: [],
  });

  protected readonly filtrosDaUrl = computed(() =>
    filtrosDaUrl({
      periodo: this.periodo(),
      de: this.de(),
      ate: this.ate(),
      espaco: this.espaco(),
      curso: this.curso(),
      status: this.status(),
      tipo: this.tipo(),
      confirmacao: this.confirmacao(),
      devolucao: this.devolucao(),
    }),
  );

  protected readonly numeroDaPagina = computed(() => {
    const numero = Number(this.pagina());
    return Number.isInteger(numero) && numero > 0 ? numero : 1;
  });

  private readonly buscaComEspera = toSignal(
    toObservable(this.busca).pipe(
      debounceTime(400),
      map((texto) => texto.trim()),
      distinctUntilChanged(),
    ),
    { initialValue: '' },
  );

  protected readonly filtros = computed<FiltrosReservas>(() => {
    const busca = this.buscaComEspera();
    return busca ? { ...this.filtrosDaUrl(), busca } : this.filtrosDaUrl();
  });

  protected readonly valorDoPeriodo = computed(() => {
    const filtros = this.filtrosDaUrl();
    if (filtros.periodo) {
      return filtros.periodo;
    }
    return filtros.de || filtros.ate || this.escolhendoDatas() ? 'DATAS' : '';
  });

  private readonly consulta = computed<Consulta>(() => ({
    filtros: this.filtros(),
    pagina: this.numeroDaPagina(),
    recarga: this.recarga(),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(switchMap((consulta) => this.buscar(consulta))),
    { initialValue: { tipo: 'carregando' } as Resultado },
  );

  protected readonly paginaPronta = computed(() => {
    const resultado = this.resultado();
    return resultado.tipo === 'pronto' ? resultado.pagina : null;
  });

  protected escolherPeriodo(valor: string): void {
    if (valor === 'DATAS') {
      this.escolhendoDatas.set(true);
      this.filtrar({ periodo: null });
      return;
    }
    this.escolhendoDatas.set(false);
    this.filtrar({ periodo: valor || null, de: null, ate: null });
  }

  protected filtrar(
    mudancas: Partial<Record<keyof ParametrosDaUrl, string | number | null>>,
  ): void {
    const limpas = Object.fromEntries(
      Object.entries(mudancas).map(([chave, valor]) => [chave, valor === '' ? null : valor]),
    );
    this.router.navigate([], {
      queryParams: { ...limpas, pagina: null },
      queryParamsHandling: 'merge',
    });
  }

  protected filtrarStatus(status: StatusReserva | null): void {
    this.filtrar({ status: this.filtrosDaUrl().status === status ? null : status });
  }

  protected digitarBusca(texto: string): void {
    this.busca.set(texto);
    if (this.numeroDaPagina() > 1) {
      this.irParaPagina(1);
    }
  }

  protected limparFiltros(): void {
    this.busca.set('');
    this.escolhendoDatas.set(false);
    this.router.navigate([], { queryParams: {} });
  }

  protected irParaPagina(pagina: number): void {
    this.router.navigate([], {
      queryParams: { pagina: pagina > 1 ? pagina : null },
      queryParamsHandling: 'merge',
    });
  }

  protected atualizar(): void {
    this.recarga.update((valor) => valor + 1);
  }

  protected temFiltros(): boolean {
    return Object.keys(this.filtros()).length > 0 || this.escolhendoDatas();
  }

  private buscar(consulta: Consulta): Observable<Resultado> {
    return this.api.consultar(consulta.filtros, consulta.pagina).pipe(
      map((pagina): Resultado => ({ tipo: 'pronto', pagina })),
      catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
      startWith<Resultado>({ tipo: 'carregando' }),
    );
  }
}
