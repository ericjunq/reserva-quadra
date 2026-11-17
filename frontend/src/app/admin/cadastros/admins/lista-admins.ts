import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import {
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
import { CadastrosService } from '../cadastros.service';
import { AdminCadastro, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<AdminCadastro> }
  | { tipo: 'erro'; erro: ErroApi };

export function situacaoDoAdmin(admin: AdminCadastro): string {
  if (!admin.ativo) {
    return 'Desativado';
  }
  return admin.convite_aceito_em ? 'Ativo' : 'Convite pendente';
}

@Component({
  selector: 'app-lista-admins',
  imports: [FormsModule, RouterLink, AvisoErro, Paginacao],
  template: `
    <a routerLink="/admin/cadastros" class="text-sm font-semibold text-azul underline">Cadastros</a>
    <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold text-slate-900">Admins</h1>
      <a
        routerLink="novo"
        class="rounded-full bg-azul px-4 py-2 font-semibold text-white hover:bg-azul-medio"
        >Convidar admin</a
      >
    </div>

    <div class="mt-4 flex flex-wrap gap-4">
      <div>
        <label for="busca" class="block text-sm font-semibold text-slate-900">Nome ou email</label>
        <input
          id="busca"
          type="search"
          autocomplete="off"
          maxlength="100"
          class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="busca()"
          (ngModelChange)="busca.set($event)"
        />
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
        <p class="mt-4 text-slate-600">Buscando os admins…</p>
      }
      @case ('erro') {
        <div class="mt-4"><app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" /></div>
      }
      @case ('pronto') {
        @if (paginaPronta(); as pagina) {
          <ul
            class="mt-4 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
          >
            @for (admin of pagina.data; track admin.id) {
              <li class="flex flex-wrap items-center justify-between gap-2 p-4">
                <a [routerLink]="[admin.id]" class="font-semibold text-azul underline">{{
                  admin.nome
                }}</a>
                <span class="flex flex-wrap gap-1.5">
                  @if (admin.principal) {
                    <span class="rounded-full bg-azul px-2 py-0.5 text-xs font-semibold text-white"
                      >Principal</span
                    >
                  }
                  <span
                    class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-800"
                    >{{ situacaoDoAdmin(admin) }}</span
                  >
                </span>
              </li>
            } @empty {
              <li class="p-4 text-slate-700">Nenhum admin com esses filtros.</li>
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
export class ListaAdmins {
  private readonly api = inject(CadastrosService);
  private readonly router = inject(Router);

  readonly situacao = input<string>();
  readonly pagina = input<string>();

  protected readonly busca = signal('');
  protected readonly situacaoDoAdmin = situacaoDoAdmin;

  private readonly buscaComEspera = toSignal(
    toObservable(this.busca).pipe(
      debounceTime(400),
      map((texto) => texto.trim()),
      distinctUntilChanged(),
    ),
    { initialValue: '' },
  );

  private readonly consulta = computed(() => ({
    filtros: {
      ...(this.buscaComEspera() ? { busca: this.buscaComEspera() } : {}),
      ...(this.situacao() === 'ativos'
        ? { ativo: true }
        : this.situacao() === 'desativados'
          ? { ativo: false }
          : {}),
    },
    pagina: Math.max(Number(this.pagina()) || 1, 1),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ filtros, pagina }) =>
        this.api.consultarAdmins(filtros, pagina).pipe(
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

  protected filtrar(mudancas: Record<string, string | number | null>, manterPagina = false): void {
    this.router.navigate([], {
      queryParams: manterPagina ? mudancas : { ...mudancas, pagina: null },
      queryParamsHandling: 'merge',
    });
  }
}
