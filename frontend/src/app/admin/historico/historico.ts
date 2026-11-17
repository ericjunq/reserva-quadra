import { Component, computed, inject, input } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ehDataIso } from '../../publico/datas';
import { Pagina } from '../cadastros/modelos';
import { Paginacao } from '../cadastros/paginacao';
import { PeriodoAdmin } from '../reservas/modelos';
import { EntradaHistorico } from './entrada-historico';
import { HistoricoService } from './historico.service';
import { AcaoAuditoria, Auditoria, FiltrosHistorico, TipoAuditavel } from './modelos';
import { NOMES_DAS_ACOES, NOMES_DOS_TIPOS } from './rotulos';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<Auditoria> }
  | { tipo: 'erro'; erro: ErroApi };

export const PERIODOS_DO_HISTORICO: { valor: PeriodoAdmin; nome: string }[] = [
  { valor: 'HOJE', nome: 'Hoje' },
  { valor: 'SEMANA_ATUAL', nome: 'Esta semana' },
  { valor: 'MES_ATUAL', nome: 'Este mês' },
  { valor: 'MES_ANTERIOR', nome: 'Mês anterior' },
];

export const SEM_ADMIN = 'sistema';

export function filtrosDaUrl(url: {
  periodo?: string;
  de?: string;
  ate?: string;
  tipo?: string;
  registro?: string;
  acao?: string;
  quem?: string;
}): FiltrosHistorico {
  const filtros: FiltrosHistorico = {};
  const tipo = url.tipo as TipoAuditavel;
  if (tipo && tipo in NOMES_DOS_TIPOS) {
    filtros.tipo = tipo;
    const registro = Number(url.registro);
    if (Number.isInteger(registro) && registro > 0) {
      filtros.registro_id = registro;
    }
  }
  const acao = url.acao as AcaoAuditoria;
  if (acao && acao in NOMES_DAS_ACOES) {
    filtros.acao = acao;
  }
  if (url.quem === SEM_ADMIN) {
    filtros.sem_admin = true;
  } else {
    const adminId = Number(url.quem);
    if (Number.isInteger(adminId) && adminId > 0) {
      filtros.admin_id = adminId;
    }
  }
  const periodo = PERIODOS_DO_HISTORICO.find((item) => item.valor === url.periodo);
  if (periodo) {
    filtros.periodo = periodo.valor;
  } else {
    if (ehDataIso(url.de)) {
      filtros.de = url.de;
    }
    if (ehDataIso(url.ate)) {
      filtros.ate = url.ate;
    }
  }
  return filtros;
}

@Component({
  selector: 'app-historico',
  imports: [AvisoErro, EntradaHistorico, Paginacao],
  templateUrl: './historico.html',
})
export class Historico {
  private readonly api = inject(HistoricoService);
  private readonly router = inject(Router);

  readonly periodo = input<string>();
  readonly de = input<string>();
  readonly ate = input<string>();
  readonly tipo = input<string>();
  readonly registro = input<string>();
  readonly acao = input<string>();
  readonly quem = input<string>();
  readonly pagina = input<string>();

  protected readonly periodos = PERIODOS_DO_HISTORICO;
  protected readonly semAdmin = SEM_ADMIN;
  protected readonly tipos = Object.entries(NOMES_DOS_TIPOS) as [TipoAuditavel, string][];
  protected readonly acoes = Object.entries(NOMES_DAS_ACOES) as [AcaoAuditoria, string][];
  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;

  protected readonly admins = toSignal(this.api.listarAdmins().pipe(catchError(() => of([]))), {
    initialValue: [],
  });

  protected readonly filtros = computed(() =>
    filtrosDaUrl({
      periodo: this.periodo(),
      de: this.de(),
      ate: this.ate(),
      tipo: this.tipo(),
      registro: this.registro(),
      acao: this.acao(),
      quem: this.quem(),
    }),
  );

  protected readonly quemEscolhido = computed(() => {
    const filtros = this.filtros();
    return filtros.sem_admin ? SEM_ADMIN : filtros.admin_id ? String(filtros.admin_id) : '';
  });

  private readonly consulta = computed(() => ({
    filtros: this.filtros(),
    pagina: Math.max(Number(this.pagina()) || 1, 1),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ filtros, pagina }) =>
        this.api.listar(filtros, pagina).pipe(
          map((dados): Resultado => ({ tipo: 'pronto', pagina: dados })),
          catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
          startWith<Resultado>({ tipo: 'carregando' }),
        ),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Resultado },
  );

  protected readonly paginaPronta = computed(() => {
    const resultado = this.resultado();
    return resultado.tipo === 'pronto' ? resultado.pagina : null;
  });

  protected escolherPeriodo(periodo: PeriodoAdmin | null): void {
    this.filtrar({
      periodo: periodo === this.filtros().periodo ? null : periodo,
      de: null,
      ate: null,
    });
  }

  protected escolherData(campo: 'de' | 'ate', valor: string): void {
    this.filtrar({ [campo]: valor, periodo: null });
  }

  protected escolherTipo(tipo: string): void {
    this.filtrar({ tipo, registro: null });
  }

  protected filtrar(mudancas: Record<string, string | null>): void {
    const limpas = Object.fromEntries(
      Object.entries(mudancas).map(([chave, valor]) => [chave, valor || null]),
    );
    this.router.navigate([], {
      queryParams: { ...limpas, pagina: null },
      queryParamsHandling: 'merge',
    });
  }

  protected limpar(): void {
    this.router.navigate([]);
  }

  protected irParaPagina(pagina: number): void {
    this.router.navigate([], {
      queryParams: { pagina: pagina > 1 ? pagina : null },
      queryParamsHandling: 'merge',
    });
  }
}
