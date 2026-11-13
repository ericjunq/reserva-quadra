import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { AcessoService } from './acesso.service';

@Component({
  selector: 'app-esqueci-senha',
  imports: [FormsModule, RouterLink, AvisoErro],
  template: `
    <h1 class="text-2xl font-bold text-azul">Esqueci a senha</h1>
    @if (mensagem(); as mensagem) {
      <p
        class="mt-6 rounded-2xl bg-white p-6 text-slate-700 shadow-sm ring-1 ring-slate-200"
        role="status"
      >
        {{ mensagem }} O link vale por 60 minutos.
      </p>
    } @else {
      <p class="mt-1 text-slate-600">
        Informe o email da sua conta. Enviaremos um link para criar uma senha nova.
      </p>
      <form
        class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
        (ngSubmit)="enviar()"
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
        <button
          type="submit"
          class="w-full rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
          [disabled]="!email().trim() || enviando()"
        >
          Enviar o link
        </button>
      </form>
    }
    <p class="mt-4 text-center text-sm">
      <a routerLink="/admin/entrar" class="font-semibold text-azul underline">Voltar para entrar</a>
    </p>
  `,
})
export class EsqueciSenha {
  private readonly acesso = inject(AcessoService);

  protected readonly email = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  protected enviar(): void {
    if (!this.email().trim() || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.acesso.esqueciASenha(this.email().trim()).subscribe({
      next: (mensagem) => {
        this.enviando.set(false);
        this.email.set('');
        this.mensagem.set(mensagem);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
