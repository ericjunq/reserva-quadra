import { Component, DestroyRef, computed, inject, input, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Subscription, interval, take } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { caminhoInterno } from '../../compartilhado/caminho-interno';
import { ErroApi } from '../../nucleo/erro-api';
import { AcessoService, ResultadoDoLogin } from './acesso.service';

const SEGUNDOS_ENTRE_REENVIOS = 60;

@Component({
  selector: 'app-entrar',
  imports: [FormsModule, RouterLink, AvisoErro],
  template: `
    <h1 class="text-2xl font-bold text-azul">Entrar</h1>
    <p class="mt-1 text-slate-600">Área da administração do sistema de reservas.</p>

    @if (motivo() === 'sessao' && etapa() === 'SENHA') {
      <p class="mt-4 rounded-lg bg-amarelo/15 px-4 py-3 text-sm text-slate-800" role="status">
        Sua sessão terminou. Entre de novo para continuar.
      </p>
    }

    @if (etapa() === 'SENHA') {
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
    } @else {
      <form
        class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
        (ngSubmit)="confirmar()"
        novalidate
      >
        <p class="text-slate-800" role="status">
          Enviamos um código de 6 números para o seu email. Ele vale por 10 minutos.
        </p>
        @if (erro(); as erro) {
          <app-aviso-erro [mensagem]="erro.mensagem" />
        }
        @if (aviso(); as aviso) {
          <p class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
            {{ aviso }}
          </p>
        }
        <div>
          <label for="codigo" class="block font-semibold text-slate-900">Código</label>
          <input
            id="codigo"
            name="codigo"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            class="mt-1 w-40 rounded-lg border border-slate-300 px-3 py-2 text-lg tracking-widest"
            [ngModel]="codigo()"
            (ngModelChange)="codigo.set($event)"
          />
        </div>
        <button
          type="submit"
          class="w-full rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
          [disabled]="!codigoCompleto() || enviando()"
        >
          {{ enviando() ? 'Entrando…' : 'Entrar' }}
        </button>
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
          <button
            type="button"
            class="font-semibold text-azul underline disabled:text-slate-500 disabled:no-underline"
            [disabled]="espera() > 0 || enviando()"
            (click)="reenviar()"
          >
            {{ espera() > 0 ? 'Reenviar código em ' + espera() + ' s' : 'Reenviar código' }}
          </button>
          <button
            type="button"
            class="font-semibold text-azul underline"
            (click)="voltarParaSenha()"
          >
            Voltar
          </button>
        </div>
      </form>
    }
  `,
})
export class Entrar {
  private readonly acesso = inject(AcessoService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private desafio: string | null = null;
  private contagem: Subscription | null = null;

  readonly voltar = input<string>();
  readonly motivo = input<string>();

  protected readonly etapa = signal<'SENHA' | 'CODIGO'>('SENHA');
  protected readonly email = signal('');
  protected readonly senha = signal('');
  protected readonly codigo = signal('');
  protected readonly espera = signal(0);
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly aviso = signal<string | null>(null);

  protected readonly podeEnviar = computed(() => !!this.email().trim() && !!this.senha());
  protected readonly codigoCompleto = computed(() => /^\d{6}$/.test(this.codigo().trim()));

  protected entrar(): void {
    if (!this.podeEnviar() || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.acesso.entrar(this.email().trim(), this.senha()).subscribe({
      next: (resultado) => {
        this.enviando.set(false);
        this.senha.set('');
        this.seguir(resultado);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.senha.set('');
        this.erro.set(erro);
      },
    });
  }

  protected confirmar(): void {
    if (!this.desafio || !this.codigoCompleto() || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.aviso.set(null);
    this.acesso.confirmarCodigo(this.desafio, this.codigo().trim()).subscribe({
      next: () => {
        this.enviando.set(false);
        this.desafio = null;
        this.irParaOPainel();
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.codigo.set('');
        this.erro.set(erro);
      },
    });
  }

  protected reenviar(): void {
    if (!this.desafio || this.espera() > 0) {
      return;
    }
    this.erro.set(null);
    this.aviso.set(null);
    this.acesso.reenviarCodigo(this.desafio).subscribe({
      next: () => {
        this.codigo.set('');
        this.aviso.set('Enviamos um código novo. O anterior não vale mais.');
        this.iniciarContagem(SEGUNDOS_ENTRE_REENVIOS);
      },
      error: (erro: ErroApi) => {
        this.erro.set(erro);
        const segundos = Number(erro.dados['segundos']);
        if (segundos > 0) {
          this.iniciarContagem(segundos);
        }
      },
    });
  }

  protected voltarParaSenha(): void {
    this.desafio = null;
    this.contagem?.unsubscribe();
    this.espera.set(0);
    this.codigo.set('');
    this.erro.set(null);
    this.aviso.set(null);
    this.etapa.set('SENHA');
  }

  private seguir(resultado: ResultadoDoLogin): void {
    if (resultado.tipo === 'ENTROU') {
      this.irParaOPainel();
      return;
    }
    this.desafio = resultado.desafio;
    this.codigo.set('');
    this.etapa.set('CODIGO');
    this.iniciarContagem(SEGUNDOS_ENTRE_REENVIOS);
  }

  private irParaOPainel(): void {
    this.router.navigateByUrl(caminhoInterno(this.voltar()) ?? '/admin');
  }

  private iniciarContagem(segundos: number): void {
    this.contagem?.unsubscribe();
    this.espera.set(segundos);
    this.contagem = interval(1000)
      .pipe(take(segundos), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.espera.update((valor) => valor - 1));
  }
}
