import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { CursoCadastro, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<CursoCadastro> }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-cursos',
  imports: [FormsModule, RouterLink, AvisoErro, ConfirmarAcao, Paginacao],
  template: `
    <a routerLink="/admin/cadastros" class="text-sm font-semibold text-azul underline">Cadastros</a>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Cursos</h1>
    <p class="mt-1 text-slate-600">Curso desativado some da lista do cadastro do aluno.</p>

    <form
      class="mt-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"
      (ngSubmit)="criar()"
      novalidate
    >
      <div class="grow">
        <label for="curso-novo" class="block font-semibold text-slate-900">Novo curso</label>
        <input
          id="curso-novo"
          name="novo"
          maxlength="100"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="novo()"
          (ngModelChange)="novo.set($event)"
        />
      </div>
      <button
        type="submit"
        class="rounded-full bg-azul px-5 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
        [disabled]="enviando() || !novo().trim()"
      >
        Criar curso
      </button>
    </form>
    <div class="mt-3">
      <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
      @if (mensagem(); as mensagem) {
        <p class="rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">
          {{ mensagem }}
        </p>
      }
    </div>

    @switch (resultado().tipo) {
      @case ('carregando') {
        <p class="mt-4 text-slate-600">Carregando os cursos…</p>
      }
      @case ('erro') {
        <div class="mt-4"><app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" /></div>
      }
      @case ('pronto') {
        @if (paginaPronta(); as pagina) {
          <ul
            class="mt-4 divide-y divide-slate-200 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200"
          >
            @for (curso of cursos(); track curso.id) {
              <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                @if (editando() === curso.id) {
                  <form
                    class="flex grow flex-wrap items-center gap-2"
                    (ngSubmit)="renomear(curso)"
                    novalidate
                  >
                    <label class="sr-only" [for]="'curso-' + curso.id">Nome do curso</label>
                    <input
                      [id]="'curso-' + curso.id"
                      name="nome"
                      maxlength="100"
                      class="grow rounded-lg border border-slate-300 px-3 py-2"
                      [ngModel]="nomeEditado()"
                      (ngModelChange)="nomeEditado.set($event)"
                    />
                    <button
                      type="submit"
                      class="rounded-full bg-azul px-4 py-2 font-semibold text-white"
                    >
                      Salvar
                    </button>
                    <button
                      type="button"
                      class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40"
                      (click)="editando.set(null)"
                    >
                      Cancelar
                    </button>
                  </form>
                } @else {
                  <span class="font-semibold text-slate-900">
                    {{ curso.nome }}
                    @if (!curso.ativo) {
                      <span
                        class="ml-2 rounded-full bg-slate-200 px-2 py-0.5 text-xs text-slate-800"
                        >Desativado</span
                      >
                    }
                  </span>
                  <div class="flex flex-wrap gap-2">
                    <button
                      type="button"
                      class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40"
                      (click)="editar(curso)"
                    >
                      Renomear
                    </button>
                    @if (curso.ativo) {
                      <app-confirmar-acao
                        rotulo="Desativar"
                        aviso="O curso some do cadastro de alunos. Os alunos dele continuam cadastrados."
                        [perigo]="true"
                        [ocupado]="enviando()"
                        (confirmado)="mudarSituacao(curso, false)"
                      />
                    } @else {
                      <app-confirmar-acao
                        rotulo="Reativar"
                        aviso="O curso volta a aparecer no cadastro de alunos."
                        [ocupado]="enviando()"
                        (confirmado)="mudarSituacao(curso, true)"
                      />
                    }
                  </div>
                }
              </li>
            } @empty {
              <li class="p-4 text-slate-700">Nenhum curso.</li>
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
export class Cursos {
  private readonly api = inject(CadastrosService);
  private readonly publica = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly pagina = input<string>();

  private readonly recarga = signal(0);
  protected readonly novo = signal('');
  protected readonly editando = signal<number | null>(null);
  protected readonly nomeEditado = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  private readonly consulta = computed(() => ({
    pagina: Math.max(Number(this.pagina()) || 1, 1),
    recarga: this.recarga(),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ pagina }) =>
        this.api.listarCursos(pagina).pipe(
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

  protected readonly cursos = linkedSignal(() => this.paginaPronta()?.data ?? []);

  protected criar(): void {
    const nome = this.novo().trim();
    if (nome) {
      this.executar(this.api.criarCurso(nome), 'Curso criado.', () => {
        this.novo.set('');
        this.recarga.update((valor) => valor + 1);
      });
    }
  }

  protected editar(curso: CursoCadastro): void {
    this.editando.set(curso.id);
    this.nomeEditado.set(curso.nome);
  }

  protected renomear(curso: CursoCadastro): void {
    this.executar(
      this.api.atualizarCurso(curso.id, { nome: this.nomeEditado().trim() }),
      'Curso renomeado.',
      (atualizado) => {
        this.editando.set(null);
        this.trocar(atualizado);
      },
    );
  }

  protected mudarSituacao(curso: CursoCadastro, ativo: boolean): void {
    const pedido = ativo
      ? this.api.atualizarCurso(curso.id, { ativo: true })
      : this.api.desativarCurso(curso.id).pipe(map(() => ({ ...curso, ativo: false })));
    this.executar(pedido, ativo ? 'Curso reativado.' : 'Curso desativado.', (atualizado) =>
      this.trocar(atualizado),
    );
  }

  protected irParaPagina(pagina: number): void {
    this.router.navigate([], { queryParams: { pagina: pagina > 1 ? pagina : null } });
  }

  private trocar(atualizado: CursoCadastro): void {
    this.cursos.update((lista) =>
      lista.map((curso) => (curso.id === atualizado.id ? atualizado : curso)),
    );
  }

  private executar(
    pedido: Observable<CursoCadastro>,
    mensagem: string,
    depois: (curso: CursoCadastro) => void,
  ): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
    pedido.subscribe({
      next: (curso) => {
        this.enviando.set(false);
        this.publica.esquecerCursos();
        this.mensagem.set(mensagem);
        depois(curso);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
