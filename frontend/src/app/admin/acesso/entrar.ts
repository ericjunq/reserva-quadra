import { Component, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { caminhoInterno } from '../../compartilhado/caminho-interno';
import { ErroApi } from '../../nucleo/erro-api';
import { AcessoService } from './acesso.service';

@Component({
  selector: 'app-entrar',
  imports: [FormsModule, RouterLink, AvisoErro],
  template: `
    <h1 class="text-2xl font-bold text-azul">Entrar</h1>
    <p class="mt-1 text-slate-600">Área da administração do sistema de reservas.</p>

    @if (motivo() === 'sessao') {
      <p class="mt-4 rounded-lg bg-amarelo/15 px-4 py-3 text-sm text-slate-800" role="status">
        Sua sessão terminou. Entre de novo para continuar.
      </p>
    }

    <form
      class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
      (ngSubmit)="entrar()"
      novalidate
    >
      @if (erro(); as erro) {
        <app-aviso-erro [mensagem]="erro.mensagem" />
      }
      <div>
        <label for="email" class="block font-semibold text-slate-900">Email</label>
        <input
          id="email"
          name="email"
          type="email"
          autocomplete="username"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="email()"
          (ngModelChange)="email.set($event)"
        />
      </div>
      <div>
        <label for="senha" class="block font-semibold text-slate-900">Senha</label>
        <input
          id="senha"
          name="senha"
          type="password"
          autocomplete="current-password"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="senha()"
          (ngModelChange)="senha.set($event)"
        />
      </div>
      <button
        type="submit"
        class="w-full rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
        [disabled]="!podeEnviar() || enviando()"
      >
        {{ enviando() ? 'Entrando…' : 'Entrar' }}
      </button>
      <p class="text-center text-sm">
        <a routerLink="/admin/esqueci-senha" class="font-semibold text-azul underline">
          Esqueci a senha
        </a>
      </p>
    </form>
  `,
})
export class Entrar {
  private readonly acesso = inject(AcessoService);
  private readonly router = inject(Router);

  readonly voltar = input<string>();
  readonly motivo = input<string>();

  protected readonly email = signal('');
  protected readonly senha = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);

  protected readonly podeEnviar = computed(() => !!this.email().trim() && !!this.senha());

  protected entrar(): void {
    if (!this.podeEnviar() || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.acesso.entrar(this.email().trim(), this.senha()).subscribe({
      next: () => {
        this.enviando.set(false);
        this.senha.set('');
        this.router.navigateByUrl(caminhoInterno(this.voltar()) ?? '/admin');
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.senha.set('');
        this.erro.set(erro);
      },
    });
  }
}
