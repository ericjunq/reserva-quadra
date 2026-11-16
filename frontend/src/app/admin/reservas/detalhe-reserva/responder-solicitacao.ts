import { Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ReservaAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';

@Component({
  selector: 'app-responder-solicitacao',
  imports: [FormsModule, AvisoErro],
  template: `
    <div class="mt-2 space-y-2">
      <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
      @if (!recusando()) {
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-full bg-emerald-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
            [disabled]="enviando()"
            (click)="aprovar()"
          >
            Aprovar pedido
          </button>
          <button
            type="button"
            class="rounded-full px-4 py-1.5 text-sm font-semibold text-red-700 ring-1 ring-red-300 hover:bg-red-50"
            (click)="recusando.set(true)"
          >
            Recusar pedido
          </button>
        </div>
      } @else {
        <form class="space-y-2" (ngSubmit)="recusar()" novalidate>
          <label [for]="'motivo-' + equipamentoId()" class="block text-sm font-semibold">
            Motivo da recusa (o aluno recebe por email)
          </label>
          <textarea
            [id]="'motivo-' + equipamentoId()"
            name="motivo"
            rows="2"
            maxlength="500"
            class="w-full rounded-lg border border-slate-300 px-3 py-2"
            [ngModel]="motivo()"
            (ngModelChange)="motivo.set($event)"
          ></textarea>
          <div class="flex flex-wrap gap-2">
            <button
              type="submit"
              class="rounded-full bg-red-700 px-4 py-1.5 text-sm font-semibold text-white hover:bg-red-800 disabled:opacity-50"
              [disabled]="enviando() || motivo().trim().length < 10"
            >
              Confirmar recusa
            </button>
            <button
              type="button"
              class="rounded-full px-4 py-1.5 text-sm font-semibold text-azul ring-1 ring-azul/40"
              (click)="recusando.set(false)"
            >
              Desistir
            </button>
          </div>
        </form>
      }
    </div>
  `,
})
export class ResponderSolicitacao {
  private readonly api = inject(ReservasAdminService);

  readonly reservaId = input.required<number>();
  readonly equipamentoId = input.required<number>();
  readonly respondida = output<ReservaAdmin>();

  protected readonly recusando = signal(false);
  protected readonly motivo = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);

  protected aprovar(): void {
    this.enviar(this.api.aprovarSolicitacao(this.reservaId(), this.equipamentoId()));
  }

  protected recusar(): void {
    const motivo = this.motivo().trim();
    if (motivo.length < 10) {
      return;
    }
    this.enviar(this.api.recusarSolicitacao(this.reservaId(), this.equipamentoId(), motivo));
  }

  private enviar(pedido: ReturnType<ReservasAdminService['aprovarSolicitacao']>): void {
    if (this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    pedido.subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.respondida.emit(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
