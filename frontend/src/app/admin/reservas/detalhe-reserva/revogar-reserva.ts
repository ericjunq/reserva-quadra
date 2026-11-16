import { Component, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ReservaAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';

const MINIMO = 10;
const MAXIMO = 500;

@Component({
  selector: 'app-revogar-reserva',
  imports: [FormsModule, AvisoErro],
  template: `
    @if (!aberto()) {
      <button
        type="button"
        class="rounded-full px-4 py-2 font-semibold text-red-700 ring-1 ring-red-300 hover:bg-red-50"
        (click)="aberto.set(true)"
      >
        Revogar reserva
      </button>
    } @else {
      <form class="space-y-3" (ngSubmit)="revogar()" novalidate>
        <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
        <div>
          <label for="justificativa" class="block font-semibold text-slate-900">
            Por que esta reserva será revogada?
          </label>
          <textarea
            id="justificativa"
            name="justificativa"
            rows="3"
            [attr.maxlength]="maximo"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            aria-describedby="ajuda-justificativa"
            [ngModel]="justificativa()"
            (ngModelChange)="justificativa.set($event)"
          ></textarea>
          <p id="ajuda-justificativa" class="mt-1 text-sm text-slate-600">
            De {{ minimo }} a {{ maximo }} caracteres ({{ justificativa().trim().length }} até
            agora).
            @if (reserva().tipo === 'ALUNO') {
              O aluno recebe um email com essa justificativa.
            }
          </p>
          @if (erro()?.campos?.['justificativa']?.[0]; as mensagem) {
            <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
          }
        </div>
        <div class="flex flex-wrap gap-3">
          <button
            type="submit"
            class="rounded-full bg-red-700 px-5 py-2 font-semibold text-white hover:bg-red-800 disabled:opacity-50"
            [disabled]="enviando() || justificativa().trim().length < minimo"
          >
            {{ enviando() ? 'Revogando…' : 'Confirmar revogação' }}
          </button>
          <button
            type="button"
            class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40"
            (click)="desistir()"
          >
            Desistir
          </button>
        </div>
      </form>
    }
  `,
})
export class RevogarReserva {
  private readonly api = inject(ReservasAdminService);

  readonly reserva = input.required<ReservaAdmin>();
  readonly revogada = output<ReservaAdmin>();

  protected readonly minimo = MINIMO;
  protected readonly maximo = MAXIMO;
  protected readonly aberto = signal(false);
  protected readonly justificativa = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);

  protected desistir(): void {
    this.aberto.set(false);
    this.justificativa.set('');
    this.erro.set(null);
  }

  protected revogar(): void {
    const justificativa = this.justificativa().trim();
    if (justificativa.length < MINIMO || this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.api.revogar(this.reserva().id, justificativa).subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.desistir();
        this.revogada.emit(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
