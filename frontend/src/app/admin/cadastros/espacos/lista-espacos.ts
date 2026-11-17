import { Component, computed, inject, input } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { TipoEspaco } from '../../../publico/modelos';
import { NOMES_DOS_TIPOS } from '../../../publico/textos';
import { CadastrosService } from '../cadastros.service';
import { EspacoCadastro, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<EspacoCadastro> }
  | { tipo: 'erro'; erro: ErroApi };

const TIPOS: TipoEspaco[] = ['QUADRA', 'LABORATORIO', 'SALA'];

@Component({
  selector: 'app-lista-espacos',
  imports: [RouterLink, AvisoErro, Paginacao],
  template: `
    <a routerLink="/admin/cadastros" class="text-sm font-semibold text-azul underline">Cadastros</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">Espaços</h1>
      <a
        routerLink="novo"
        class="rounded-full bg-azul px-4 py-2 font-semibold text-white hover:bg-azul-medio"
        >Novo espaço</a
      >
    </div>

    <div class="mt-4">
      <label for="filtro-tipo" class="block text-sm font-semibold text-slate-900">Tipo</label>
      <select
        id="filtro-tipo"
        class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
        [value]="tipoValido() ?? ''"
        (change)="filtrar($any($event.target).value)"
      >
        <option value="">Todos</option>
        @for (opcao of tipos; track opcao) {
          <option [value]="opcao">{{ nomesDosTipos[opcao] }}</option>
        }
      </select>
    </div>

    @switch (resultado().tipo) {
      @case ('carregando') {
        <p class="mt-4 text-slate-600">Carregando os espaços…</p>
      }
      @case ('erro') {
        <div class="mt-4"><app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" /></div>
      }
      @case ('pronto') {
        @if (paginaPronta(); as pagina) {
          <ul
            class="mt-4 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
          >
            @for (espaco of pagina.data; track espaco.id) {
              <li class="flex flex-wrap items-center justify-between gap-2 p-4">
                <div>
                  <a [routerLink]="[espaco.id]" class="font-semibold text-azul underline">{{
                    espaco.nome
                  }}</a>
                  <p class="text-sm text-slate-700">
                    {{ nomesDosTipos[espaco.tipo] }} · {{ descreverHorario(espaco) }}
                    @if (espaco.limite_semanal_por_curso) {
                      · até {{ espaco.limite_semanal_por_curso }} por curso na semana
                    }
                  </p>
                </div>
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  [class]="
                    espaco.ativo ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-800'
                  "
                  >{{ espaco.ativo ? 'Ativo' : 'Desativado' }}</span
                >
              </li>
            } @empty {
              <li class="p-4 text-slate-700">Nenhum espaço.</li>
            }
          </ul>
          <app-paginacao
            [atual]="pagina.meta.current_page"
            [ultima]="pagina.meta.last_page"
            (ir)="irParaPagina($event)"
          />
        }
      }
    }
  `,
})
export class ListaEspacos {
  private readonly api = inject(CadastrosService);
  private readonly router = inject(Router);

  readonly tipo = input<string>();
  readonly pagina = input<string>();

  protected readonly tipos = TIPOS;
  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;

  protected readonly tipoValido = computed(() => TIPOS.find((opcao) => opcao === this.tipo()));

  private readonly consulta = computed(() => ({
    tipo: this.tipoValido(),
    pagina: Math.max(Number(this.pagina()) || 1, 1),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ tipo, pagina }) =>
        this.api.listarEspacos(pagina, tipo).pipe(
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

  protected descreverHorario(espaco: EspacoCadastro): string {
    if (espaco.modo_horario === 'LIVRE') {
      const horas = (espaco.duracao_maxima_minutos ?? 0) / 60;
      return `horário livre, ${espaco.abre_as}–${espaco.fecha_as}, até ${horas.toLocaleString('pt-BR')}h`;
    }
    return 'horários fixos';
  }

  protected filtrar(tipo: string): void {
    this.router.navigate([], { queryParams: { tipo: tipo || null, pagina: null } });
  }

  protected irParaPagina(pagina: number): void {
    this.router.navigate([], {
      queryParams: { pagina: pagina > 1 ? pagina : null },
      queryParamsHandling: 'merge',
    });
  }
}
