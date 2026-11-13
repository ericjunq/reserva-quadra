import { Location } from '@angular/common';
import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { map, take } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { AcessoService } from './acesso.service';
import { RegrasDaSenha } from './regras-da-senha';
import { senhaValida } from './regras-senha';

export type ModoDaSenha = 'redefinir' | 'convite';

const CAMINHOS: Record<ModoDaSenha, string> = {
  redefinir: '/redefinir-senha',
  convite: '/aceitar-convite',
};

@Component({
  selector: 'app-definir-senha',
  imports: [FormsModule, RouterLink, AvisoErro, RegrasDaSenha],
  template: `
    <h1 class="text-2xl font-bold text-azul">
      {{ modo() === 'convite' ? 'Aceitar o convite' : 'Criar uma senha nova' }}
    </h1>

    @if (concluido(); as mensagem) {
      <div class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200" role="status">
        <p class="text-slate-700">{{ mensagem }}</p>
        <a
          routerLink="/admin/entrar"
          class="mt-4 inline-block rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio"
        >
          Entrar
        </a>
      </div>
    } @else if (tokenLido() === null) {
      <div class="mt-6">
        <app-aviso-erro
          mensagem="Por segurança, o link some da barra de endereço depois de aberto. Abra de novo pelo botão do email."
        />
      </div>
    } @else {
      <p class="mt-1 text-slate-600">
        {{
          modo() === 'convite'
            ? 'Defina a sua senha para acessar a administração.'
            : 'Informe o email da sua conta e a senha nova.'
        }}
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
          @if (erroDoCampo('email'); as mensagem) {
            <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
          }
        </div>
        <div>
          <label for="senha" class="block font-semibold text-slate-900">Senha nova</label>
          <input
            id="senha"
            name="senha"
            type="password"
            autocomplete="new-password"
            aria-describedby="regras"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            [ngModel]="senha()"
            (ngModelChange)="senha.set($event)"
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
        <div id="regras">
          <app-regras-da-senha [senha]="senha()" [confirmacao]="confirmacao()" />
        </div>
        <button
          type="submit"
          class="w-full rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
          [disabled]="!podeEnviar() || enviando()"
        >
          Salvar a senha
        </button>
      </form>
    }
  `,
})
export class DefinirSenha {
  private readonly acesso = inject(AcessoService);
  private readonly location = inject(Location);

  readonly modo = input.required<ModoDaSenha>();
  readonly token = input<string>();

  protected readonly email = signal('');
  protected readonly senha = signal('');
  protected readonly confirmacao = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly concluido = signal<string | null>(null);

  protected readonly tokenLido = toSignal<string | null | undefined, undefined>(
    toObservable(this.modo).pipe(
      take(1),
      map(() => this.lerToken()),
    ),
    { initialValue: undefined },
  );

  protected readonly podeEnviar = computed(
    () => !!this.email().trim() && senhaValida(this.senha(), this.confirmacao()),
  );

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos[campo]?.[0] ?? null;
  }

  protected enviar(): void {
    const token = this.tokenLido();
    if (!token || !this.podeEnviar() || this.enviando()) {
      return;
    }
    const dados = {
      token,
      email: this.email().trim(),
      password: this.senha(),
      password_confirmation: this.confirmacao(),
    };
    const pedido =
      this.modo() === 'convite'
        ? this.acesso.aceitarConvite(dados)
        : this.acesso.redefinirSenha(dados);

    this.enviando.set(true);
    this.erro.set(null);
    pedido.subscribe({
      next: (mensagem) => {
        this.enviando.set(false);
        this.senha.set('');
        this.confirmacao.set('');
        this.concluido.set(mensagem);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }

  private lerToken(): string | null {
    const token = this.token();
    if (!token) {
      return null;
    }
    this.location.replaceState(CAMINHOS[this.modo()]);
    return token;
  }
}
