import { Component, input, output, signal } from '@angular/core';

@Component({
  selector: 'app-confirmar-acao',
  template: `
    @if (!aberto()) {
      <button
        type="button"
        class="rounded-full px-4 py-2 font-semibold ring-1"
        [class]="
          perigo()
            ? 'text-red-700 ring-red-300 hover:bg-red-50'
            : 'text-azul ring-azul/40 hover:bg-azul/5'
        "
        [disabled]="ocupado()"
        (click)="aberto.set(true)"
      >
        {{ rotulo() }}
      </button>
    } @else {
      <div
        class="rounded-xl border p-4"
        [class]="perigo() ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-slate-50'"
      >
        <p class="text-slate-800">{{ aviso() }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-full px-4 py-2 font-semibold text-white disabled:opacity-50"
            [class]="perigo() ? 'bg-red-700 hover:bg-red-800' : 'bg-azul hover:bg-azul-medio'"
            [disabled]="ocupado()"
            (click)="confirmar()"
          >
            Confirmar: {{ rotulo().toLowerCase() }}
          </button>
          <button
            type="button"
            class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40"
            (click)="aberto.set(false)"
          >
            Desistir
          </button>
        </div>
      </div>
    }
  `,
})
export class ConfirmarAcao {
  readonly rotulo = input.required<string>();
  readonly aviso = input.required<string>();
  readonly perigo = input(false);
  readonly ocupado = input(false);
  readonly confirmado = output<void>();

  protected readonly aberto = signal(false);

  protected confirmar(): void {
    this.aberto.set(false);
    this.confirmado.emit();
  }
}
