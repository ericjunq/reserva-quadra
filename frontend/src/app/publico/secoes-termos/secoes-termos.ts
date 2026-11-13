import { Component, input } from '@angular/core';
import { SecaoDosTermos } from '../modelos';

@Component({
  selector: 'app-secoes-termos',
  template: `
    @for (secao of secoes(); track secao.titulo) {
      <section class="mt-4">
        <h3 class="font-semibold text-slate-900">{{ secao.titulo }}</h3>
        @for (paragrafo of secao.paragrafos; track $index) {
          <p class="mt-1 text-sm text-slate-700">{{ paragrafo }}</p>
        }
      </section>
    }
  `,
})
export class SecoesTermos {
  readonly secoes = input.required<SecaoDosTermos[]>();
}
