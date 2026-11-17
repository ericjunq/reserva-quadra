import { Component, computed, inject, input } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { CalendarioAdminService } from '../../calendario/calendario-admin.service';
import { CadastrosService } from '../cadastros.service';
import { EquipamentoCadastro, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<EquipamentoCadastro> }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-lista-equipamentos',
  imports: [RouterLink, AvisoErro, Paginacao],
  template: `
    <a routerLink="/admin/cadastros" class="text-sm font-semibold text-azul underline">Cadastros</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">Equipamentos</h1>
      <a
        routerLink="novo"
        [queryParams]="{ espaco: espacoId() ?? null }"
        class="rounded-full bg-azul px-4 py-2 font-semibold text-white hover:bg-azul-medio"
        >Novo equipamento</a
      >
    </div>

    <div class="mt-4 flex flex-wrap gap-4">
      <div>
        <label for="filtro-espaco" class="block text-sm font-semibold text-slate-900">Espaço</label>
        <select
          id="filtro-espaco"
          class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
          [value]="espacoId() ?? ''"
          (change)="filtrar({ espaco: $any($event.target).value || null })"
        >
          <option value="">Todos</option>
          @for (espaco of espacos(); track espaco.id) {
            <option [value]="espaco.id" [selected]="espaco.id === espacoId()">
              {{ espaco.nome }}
            </option>
          }
        </select>
      </div>
      <div>
        <label for="filtro-situacao" class="block text-sm font-semibold text-slate-900"
          >Situação</label
        >
        <select
          id="filtro-situacao"
          class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
          [value]="situacao() ?? ''"
          (change)="filtrar({ situacao: $any($event.target).value || null })"
        >
          <option value="">Todos</option>
          <option value="ativos">Ativos</option>
          <option value="desativados">Desativados</option>
        </select>
      </div>
    </div>

    @switch (resultado().tipo) {
      @case ('carregando') {
        <p class="mt-4 text-slate-600">Carregando os equipamentos…</p>
      }
      @case ('erro') {
        <div class="mt-4"><app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" /></div>
      }
      @case ('pronto') {
        @if (paginaPronta(); as pagina) {
          <ul
            class="mt-4 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
          >
            @for (equipamento of pagina.data; track equipamento.id) {
              <li class="flex flex-wrap items-center justify-between gap-2 p-4">
                <div>
                  <a [routerLink]="[equipamento.id]" class="font-semibold text-azul underline">{{
                    equipamento.nome
                  }}</a>
                  <p class="text-sm text-slate-700">
                    {{ nomeDoEspaco(equipamento.espaco_id) }} ·
                    {{ equipamento.quantidade_total }} no total
                    @if (equipamento.espacos_compartilhados?.length) {
                      · compartilhado com {{ equipamento.espacos_compartilhados!.length }} espaço(s)
                    }
                    @if (equipamento.aceita_solicitacao) {
                      · aceita pedido de outros espaços
                    }
                  </p>
                </div>
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  [class]="
                    equipamento.ativo
                      ? 'bg-emerald-100 text-emerald-900'
                      : 'bg-slate-200 text-slate-800'
                  "
                  >{{ equipamento.ativo ? 'Ativo' : 'Desativado' }}</span
                >
              </li>
            } @empty {
              <li class="p-4 text-slate-700">Nenhum equipamento.</li>
            }
          </ul>
          <app-paginacao
            [atual]="pagina.meta.current_page"
            [ultima]="pagina.meta.last_page"
            (ir)="filtrar({ pagina: $event > 1 ? $event : null }, true)"
          />
        }
      }
    }
  `,
})
export class ListaEquipamentos {
  private readonly api = inject(CadastrosService);
  private readonly calendario = inject(CalendarioAdminService);
  private readonly router = inject(Router);

  readonly espaco = input<string>();
  readonly situacao = input<string>();
  readonly pagina = input<string>();

  protected readonly espacos = toSignal(
    this.calendario.listarEspacos().pipe(catchError(() => of([]))),
    { initialValue: [] },
  );

  protected readonly espacoId = computed(() => {
    const id = Number(this.espaco());
    return Number.isInteger(id) && id > 0 ? id : null;
  });

  private readonly consulta = computed(() => ({
    espaco_id: this.espacoId() ?? undefined,
    ativo:
      this.situacao() === 'ativos' ? true : this.situacao() === 'desativados' ? false : undefined,
    pagina: Math.max(Number(this.pagina()) || 1, 1),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ pagina, ...filtros }) =>
        this.api.listarEquipamentos(pagina, filtros).pipe(
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

  protected nomeDoEspaco(id: number): string {
    return this.espacos().find((espaco) => espaco.id === id)?.nome ?? 'Espaço';
  }

  protected filtrar(mudancas: Record<string, string | number | null>, manterPagina = false): void {
    this.router.navigate([], {
      queryParams: manterPagina ? mudancas : { ...mudancas, pagina: null },
      queryParamsHandling: 'merge',
    });
  }
}
