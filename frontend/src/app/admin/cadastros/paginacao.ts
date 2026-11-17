import { Component, input, output } from '@angular/core';

@Component({
  selector: 'app-paginacao',
  template: `
    @if (ultima() > 1) {
      <nav class="mt-4 flex items-center justify-between" aria-label="Páginas">
        <button
          type="button"
          class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40 disabled:opacity-40"
          [disabled]="atual() <= 1"
          (click)="ir.emit(atual() - 1)"
        >
          Anterior
        </button>
        <span class="text-sm text-slate-700">Página {{ atual() }} de {{ ultima() }}</span>
        <button
          type="button"
          class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40 disabled:opacity-40"
          [disabled]="atual() >= ultima()"
          (click)="ir.emit(atual() + 1)"
        >
          Próxima
        </button>
      </nav>
    }
  `,
})
export class Paginacao {
  readonly atual = input.required<number>();
  readonly ultima = input.required<number>();
  readonly ir = output<number>();
}
