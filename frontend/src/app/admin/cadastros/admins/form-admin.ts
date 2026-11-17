import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { AdminCadastro } from '../modelos';
import { situacaoDoAdmin } from './lista-admins';
import { HistoricoDoRegistro } from '../../historico/historico-do-registro';

type Carga =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; admin: AdminCadastro | null }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-form-admin',
  imports: [FormsModule, RouterLink, AvisoErro, ConfirmarAcao, HistoricoDoRegistro],
  template: `
    <a routerLink="/admin/cadastros/admins" class="text-sm font-semibold text-azul underline"
      >Admins</a
    >
    <h1 class="mt-2 flex flex-wrap items-center gap-2 text-2xl font-semibold text-slate-900">
      {{ admin() ? admin()!.nome : 'Convidar admin' }}
      @if (admin()?.principal) {
        <span class="rounded-full bg-azul px-2 py-0.5 text-xs font-semibold text-white"
          >Principal</span
        >
      }
    </h1>
    @if (admin(); as atual) {
      <p class="mt-1 text-slate-600">{{ situacaoDoAdmin(atual) }}</p>
    } @else {
      <p class="mt-1 text-slate-600">
        O admin recebe um email com o link para criar a senha (vale 2 dias).
      </p>
    }

    @switch (estado().tipo) {
      @case ('carregando') {
        <p class="mt-4 text-slate-600">Carregando…</p>
      }
      @case ('erro') {
        <div class="mt-4"><app-aviso-erro [mensagem]="$any(estado()).erro.mensagem" /></div>
      }
      @case ('pronto') {
        <form
          class="mt-6 max-w-2xl space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
          (ngSubmit)="salvar()"
          novalidate
        >
          <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
          @if (mensagem(); as mensagem) {
            <p class="rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">
              {{ mensagem }}
            </p>
          }
          <div>
            <label for="admin-nome" class="block font-semibold text-slate-900">Nome</label>
            <input
              id="admin-nome"
              name="nome"
              maxlength="255"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="nome()"
              (ngModelChange)="nome.set($event)"
            />
            @if (erroDoCampo('nome'); as texto) {
              <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
            }
          </div>
          <div>
            <label for="admin-email" class="block font-semibold text-slate-900">Email</label>
            <input
              id="admin-email"
              name="email"
              type="email"
              autocomplete="off"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="email()"
              (ngModelChange)="email.set($event)"
            />
            @if (erroDoCampo('email'); as texto) {
              <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
            }
          </div>
          <button
            type="submit"
            class="rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
            [disabled]="enviando()"
          >
            {{ enviando() ? 'Salvando…' : admin() ? 'Salvar' : 'Enviar convite' }}
          </button>
        </form>

        @if (admin(); as atual) {
          <section class="mt-6 flex max-w-2xl flex-wrap gap-3" aria-label="Ações">
            @if (atual.ativo && !atual.convite_aceito_em) {
              <button
                type="button"
                class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40 hover:bg-azul/5 disabled:opacity-50"
                [disabled]="enviando()"
                (click)="reenviarConvite()"
              >
                Reenviar o convite
              </button>
            }
            @if (!atual.principal) {
              @if (atual.ativo) {
                <app-confirmar-acao
                  rotulo="Desativar"
                  aviso="O admin não consegue mais entrar e todas as sessões dele são encerradas na hora."
                  [perigo]="true"
                  [ocupado]="enviando()"
                  (confirmado)="mudarSituacao(false)"
                />
              } @else {
                <app-confirmar-acao
                  rotulo="Reativar"
                  aviso="O admin volta a poder entrar com a senha dele."
                  [ocupado]="enviando()"
                  (confirmado)="mudarSituacao(true)"
                />
              }
            }
          </section>
          <div class="mt-6 max-w-2xl">
            <app-historico-do-registro tipo="ADMIN" [registroId]="atual.id" />
          </div>
        }
      }
    }
  `,
})
export class FormAdmin {
  private readonly api = inject(CadastrosService);
  private readonly router = inject(Router);

  readonly id = input<string>();

  protected readonly situacaoDoAdmin = situacaoDoAdmin;

  private readonly carga = toSignal(
    toObservable(this.id).pipe(
      switchMap((id): Observable<Carga> =>
        id
          ? this.api.detalharAdmin(Number(id)).pipe(
              map((admin): Carga => ({ tipo: 'pronto', admin })),
              catchError((erro: ErroApi) => of<Carga>({ tipo: 'erro', erro })),
              startWith<Carga>({ tipo: 'carregando' }),
            )
          : of<Carga>({ tipo: 'pronto', admin: null }),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Carga },
  );

  protected readonly estado = linkedSignal<Carga>(() => this.carga());
  protected readonly admin = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.admin : null;
  });

  protected readonly nome = linkedSignal(() => this.admin()?.nome ?? '');
  protected readonly email = linkedSignal(() => this.admin()?.email ?? '');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(
    typeof history.state?.mensagem === 'string' ? history.state.mensagem : null,
  );

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos?.[campo]?.[0] ?? null;
  }

  protected salvar(): void {
    if (this.enviando()) {
      return;
    }
    const atual = this.admin();
    const nome = this.nome().trim();
    const email = this.email().trim();
    if (atual) {
      this.executar(this.api.atualizarAdmin(atual.id, { nome, email }), 'Dados salvos.');
      return;
    }
    this.comecar();
    this.api.convidarAdmin(nome, email).subscribe({
      next: (criado) => {
        this.enviando.set(false);
        this.router.navigate(['/admin/cadastros/admins', criado.id], {
          state: { mensagem: 'Convite enviado por email.' },
        });
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  protected reenviarConvite(): void {
    const atual = this.admin();
    if (!atual) {
      return;
    }
    this.comecar();
    this.api.reenviarConvite(atual.id).subscribe({
      next: (texto) => {
        this.enviando.set(false);
        this.mensagem.set(texto);
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  protected mudarSituacao(ativo: boolean): void {
    const atual = this.admin();
    if (atual) {
      this.executar(
        ativo
          ? this.api.atualizarAdmin(atual.id, { ativo: true })
          : this.api.desativarAdmin(atual.id),
        ativo ? 'Admin reativado.' : 'Admin desativado.',
      );
    }
  }

  private executar(pedido: Observable<AdminCadastro>, mensagem: string): void {
    this.comecar();
    pedido.subscribe({
      next: (admin) => {
        this.enviando.set(false);
        this.estado.set({ tipo: 'pronto', admin });
        this.mensagem.set(mensagem);
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  private comecar(): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
  }

  private falhar(erro: ErroApi): void {
    this.enviando.set(false);
    this.erro.set(erro);
  }
}
