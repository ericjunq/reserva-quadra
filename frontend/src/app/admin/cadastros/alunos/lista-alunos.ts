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
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { CadastrosService } from '../cadastros.service';
import { AlunoCadastro, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<AlunoCadastro> }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-lista-alunos',
  imports: [FormsModule, RouterLink, AvisoErro, Paginacao],
  template: `
    <a routerLink="/admin/cadastros" class="text-sm font-semibold text-azul underline">Cadastros</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Alunos</h1>
    <p class="mt-1 text-slate-600">RA e email aparecem só no detalhe de cada aluno.</p>

    <div class="mt-4 flex flex-wrap gap-4">
      <div>
        <label for="busca" class="block text-sm font-semibold text-slate-900">Nome ou RA</label>
        <input
          id="busca"
          type="search"
          autocomplete="off"
          maxlength="100"
          class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="busca()"
          (ngModelChange)="digitar($event)"
        />
      </div>
      <div>
        <label for="filtro-curso" class="block text-sm font-semibold text-slate-900">Curso</label>
        <select
          id="filtro-curso"
          class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
          [value]="cursoId() ?? ''"
          (change)="filtrar({ curso: $any($event.target).value || null })"
        >
          <option value="">Todos</option>
          @for (curso of cursos(); track curso.id) {
            <option [value]="curso.id" [selected]="curso.id === cursoId()">{{ curso.nome }}</option>
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
        <p class="mt-4 text-slate-600">Buscando os alunos…</p>
      }
      @case ('erro') {
        <div class="mt-4"><app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" /></div>
      }
      @case ('pronto') {
        @if (paginaPronta(); as pagina) {
          <p class="mt-4 text-sm text-slate-600">{{ pagina.meta.total }} aluno(s).</p>
          <ul
            class="mt-2 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
          >
            @for (aluno of pagina.data; track aluno.id) {
              <li class="flex flex-wrap items-center justify-between gap-2 p-4">
                <div>
                  <a [routerLink]="[aluno.id]" class="font-semibold text-azul underline">{{
                    aluno.nome_completo
                  }}</a>
                  <p class="text-sm text-slate-700">{{ aluno.curso?.nome ?? 'Sem curso' }}</p>
                </div>
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-semibold"
                  [class]="
                    aluno.anonimizado_em
                      ? 'bg-slate-700 text-white'
                      : aluno.ativo
                        ? 'bg-emerald-100 text-emerald-900'
                        : 'bg-slate-200 text-slate-800'
                  "
                  >{{
                    aluno.anonimizado_em ? 'Anonimizado' : aluno.ativo ? 'Ativo' : 'Desativado'
                  }}</span
                >
              </li>
            } @empty {
              <li class="p-4 text-slate-700">Nenhum aluno com esses filtros.</li>
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
export class ListaAlunos {
  private readonly api = inject(CadastrosService);
  private readonly publica = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly curso = input<string>();
  readonly situacao = input<string>();
  readonly pagina = input<string>();

  protected readonly busca = signal('');

  protected readonly cursos = toSignal(this.publica.listarCursos().pipe(catchError(() => of([]))), {
    initialValue: [],
  });

  protected readonly cursoId = computed(() => {
    const id = Number(this.curso());
    return Number.isInteger(id) && id > 0 ? id : null;
  });

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
      ...(this.cursoId() ? { curso_id: this.cursoId()! } : {}),
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
        this.api.consultarAlunos(filtros, pagina).pipe(
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

  protected digitar(texto: string): void {
    this.busca.set(texto);
    if (Number(this.pagina()) > 1) {
      this.filtrar({}, false);
    }
  }

  protected filtrar(mudancas: Record<string, string | number | null>, manterPagina = false): void {
    this.router.navigate([], {
      queryParams: manterPagina ? mudancas : { ...mudancas, pagina: null },
      queryParamsHandling: 'merge',
    });
  }
}
