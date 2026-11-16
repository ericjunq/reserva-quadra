import { Location } from '@angular/common';
import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { LucideCircleCheck } from '@lucide/angular';
import { Observable, catchError, map, of, startWith, switchMap, take } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { CartaoReserva } from '../cartao-reserva/cartao-reserva';
import { formatarDataHora } from '../datas';
import { ReservaDoAluno, ResumoParaManter } from '../modelos';

type Resumo =
  | { tipo: 'carregando' }
  | { tipo: 'sem-token' }
  | { tipo: 'pronto'; resumo: ResumoParaManter }
  | { tipo: 'erro'; mensagem: string };

@Component({
  selector: 'app-manter-reserva',
  imports: [RouterLink, AvisoErro, CartaoReserva, LucideCircleCheck],
  template: `
    <div class="mx-auto max-w-2xl px-4 py-8">
      <h1 class="text-3xl font-extrabold italic text-azul">Manter a reserva</h1>

      @if (mantida(); as reserva) {
        <section
          class="mt-6 rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-slate-200"
          role="status"
        >
          <svg
            lucideCircleCheck
            [size]="48"
            class="mx-auto text-emerald-600"
            aria-hidden="true"
          ></svg>
          <h2 class="mt-3 text-2xl font-extrabold italic text-azul">Reserva mantida</h2>
          <p class="mt-2 text-slate-700">
            {{ reserva.espaco?.nome }}: a reserva continua, sem o equipamento pedido.
          </p>
        </section>
      } @else {
        @switch (estado().tipo) {
          @case ('carregando') {
            <p class="mt-6 text-slate-600">Carregando a reserva…</p>
          }
          @case ('sem-token') {
            <p class="mt-6 text-slate-700">
              Abra esta página pelo botão "Manter a reserva sem o equipamento" do email.
            </p>
            <a
              routerLink="/minhas-reservas"
              class="mt-2 inline-block font-semibold text-azul underline"
              >Ver minhas reservas</a
            >
          }
          @case ('erro') {
            <div class="mt-6">
              <app-aviso-erro [mensagem]="$any(estado()).mensagem" />
            </div>
          }
          @case ('pronto') {
            @if (resumo(); as resumo) {
              <section class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <app-cartao-reserva [reserva]="resumo.data" />
                <app-aviso-erro class="mt-4 block" [mensagem]="erroDoEnvio()?.mensagem ?? null" />
                @if (resumo.pode_manter) {
                  <p class="mt-4 text-slate-700">
                    O equipamento pedido não foi liberado.
                    @if (prazo(); as prazo) {
                      <strong
                        >Se você não confirmar até {{ prazo }}, a reserva será cancelada.</strong
                      >
                    }
                  </p>
                  <button
                    type="button"
                    class="mt-4 rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
                    [disabled]="enviando()"
                    (click)="manter()"
                  >
                    {{ enviando() ? 'Mantendo…' : 'Manter a reserva sem o equipamento' }}
                  </button>
                } @else {
                  <p class="mt-4 rounded-lg bg-slate-100 px-4 py-3 text-slate-700">
                    Esta reserva não precisa mais ser mantida (já foi mantida, cancelada, revogada
                    ou o prazo terminou).
                  </p>
                }
              </section>
            }
          }
        }
      }
    </div>
  `,
})
export class ManterReserva {
  private readonly api = inject(ApiPublicaService);
  private readonly location = inject(Location);

  readonly token = input<string>();

  protected readonly enviando = signal(false);
  protected readonly erroDoEnvio = signal<ErroApi | null>(null);
  protected readonly mantida = signal<ReservaDoAluno | null>(null);
  private tokenLido: string | null = null;

  protected readonly estado = toSignal(
    toObservable(this.token).pipe(
      take(1),
      switchMap((token) => this.carregar(token)),
    ),
    { initialValue: { tipo: 'carregando' } as Resumo },
  );

  protected readonly resumo = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.resumo : null;
  });

  protected readonly prazo = computed(() => {
    const manterAte = this.resumo()?.data.manter_ate;
    return manterAte ? formatarDataHora(manterAte) : null;
  });

  protected manter(): void {
    if (!this.tokenLido || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erroDoEnvio.set(null);
    this.api.manter(this.tokenLido).subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.tokenLido = null;
        this.mantida.set(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erroDoEnvio.set(erro);
      },
    });
  }

  private carregar(token: string | undefined): Observable<Resumo> {
    if (!token) {
      return of<Resumo>({ tipo: 'sem-token' });
    }
    this.tokenLido = token;
    this.location.replaceState('/reservas/manter');
    return this.api.resumoParaManter(token).pipe(
      map((resumo): Resumo => ({ tipo: 'pronto', resumo })),
      catchError((erro: ErroApi) => of<Resumo>({ tipo: 'erro', mensagem: erro.mensagem })),
      startWith<Resumo>({ tipo: 'carregando' }),
    );
  }
}
