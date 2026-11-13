import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { catchError, of } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { AcessoService } from './acesso.service';
import { RegrasDaSenha } from './regras-da-senha';
import { senhaValida } from './regras-senha';

@Component({
  selector: 'app-minha-conta',
  imports: [FormsModule, AvisoErro, RegrasDaSenha],
  template: `
    <h1 class="text-2xl font-semibold text-slate-900">Minha conta</h1>

    @if (admin(); as admin) {
      <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <p class="font-semibold text-slate-900">{{ admin.nome }}</p>
        <p class="text-slate-700">{{ admin.email }}</p>
        @if (admin.principal) {
          <p class="mt-1 text-sm text-slate-600">Admin principal</p>
        }
      </section>
    }

    <section
      class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
      aria-labelledby="titulo-senha"
    >
      <h2 id="titulo-senha" class="text-lg font-semibold text-slate-900">Trocar a senha</h2>
      @if (mensagem(); as mensagem) {
        <p class="mt-3 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">
          {{ mensagem }}
        </p>
      }
      <form class="mt-4 max-w-md space-y-4" (ngSubmit)="trocar()" novalidate>
        @if (erro(); as erro) {
          <app-aviso-erro [mensagem]="erro.mensagem" />
        }
        <div>
          <label for="senha-atual" class="block font-semibold text-slate-900">Senha atual</label>
          <input
            id="senha-atual"
            name="senha-atual"
            type="password"
            autocomplete="current-password"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            [ngModel]="atual()"
            (ngModelChange)="atual.set($event)"
          />
          @if (erroDoCampo('senha_atual'); as mensagem) {
            <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
          }
        </div>
        <div>
          <label for="senha-nova" class="block font-semibold text-slate-900">Senha nova</label>
          <input
            id="senha-nova"
            name="senha-nova"
            type="password"
            autocomplete="new-password"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            [ngModel]="nova()"
            (ngModelChange)="nova.set($event)"
          />
          @if (erroDoCampo('password'); as mensagem) {
            <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
          }
        </div>
        <div>
          <label for="confirmacao" class="block font-semibold text-slate-900">
            Repita a senha nova
          </label>
          <input
            id="confirmacao"
            name="confirmacao"
            type="password"
            autocomplete="new-password"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            [ngModel]="confirmacao()"
            (ngModelChange)="confirmacao.set($event)"
          />
        </div>
        <app-regras-da-senha [senha]="nova()" [confirmacao]="confirmacao()" />
        <button
          type="submit"
          class="rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
          [disabled]="!podeTrocar() || enviando()"
        >
          Trocar a senha
        </button>
      </form>
    </section>

    <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
      <h2 class="text-lg font-semibold text-slate-900">Sessões</h2>
      <p class="mt-1 text-sm text-slate-600">
        Encerra o acesso em todos os computadores e celulares, inclusive neste.
      </p>
      <button
        type="button"
        class="mt-3 rounded-full px-5 py-2 font-semibold text-red-800 ring-1 ring-red-300 hover:bg-red-50"
        (click)="sairDeTodos()"
      >
        Sair de todos os dispositivos
      </button>
    </section>
  `,
})
export class MinhaConta {
  private readonly acesso = inject(AcessoService);
  private readonly router = inject(Router);

  protected readonly admin = toSignal(this.acesso.eu().pipe(catchError(() => of(null))));

  protected readonly atual = signal('');
  protected readonly nova = signal('');
  protected readonly confirmacao = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  protected readonly podeTrocar = computed(
    () => !!this.atual() && senhaValida(this.nova(), this.confirmacao()),
  );

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos[campo]?.[0] ?? null;
  }

  protected trocar(): void {
    if (!this.podeTrocar() || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
    this.acesso.trocarSenha(this.atual(), this.nova(), this.confirmacao()).subscribe({
      next: (mensagem) => {
        this.enviando.set(false);
        this.atual.set('');
        this.nova.set('');
        this.confirmacao.set('');
        this.mensagem.set(mensagem);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }

  protected sairDeTodos(): void {
    this.acesso.sairDeTodos().subscribe({
      complete: () => this.router.navigateByUrl('/admin/entrar'),
      error: () => this.router.navigateByUrl('/admin/entrar'),
    });
  }
}
