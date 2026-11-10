import { Component, input } from '@angular/core';
import { LucideCircleExclamationPoint } from '@lucide/angular';

@Component({
  selector: 'app-aviso-erro',
  imports: [LucideCircleExclamationPoint],
  template: `
    @if (mensagem()) {
      <div
        role="alert"
        class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"
      >
        <svg
          lucideCircleExclamationPoint
          [size]="20"
          class="mt-0.5 shrink-0"
          aria-hidden="true"
        ></svg>
        <div class="space-y-2">
          <p>{{ mensagem() }}</p>
          <ng-content />
        </div>
      </div>
    }
  `,
})
export class AvisoErro {
  readonly mensagem = input<string | null>(null);
}
