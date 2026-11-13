import { Component, computed, input } from '@angular/core';
import { avaliarSenha } from './regras-senha';

@Component({
  selector: 'app-regras-da-senha',
  template: `
    <ul class="space-y-1 text-sm" aria-label="Regras da senha">
      @for (regra of regras(); track regra.texto) {
        <li [class]="regra.cumprida ? 'text-emerald-800' : 'text-slate-600'">
          <span aria-hidden="true">{{ regra.cumprida ? '✓' : '○' }}</span>
          {{ regra.texto }}
          <span class="sr-only">{{ regra.cumprida ? '(cumprida)' : '(falta)' }}</span>
        </li>
      }
    </ul>
  `,
})
export class RegrasDaSenha {
  readonly senha = input('');
  readonly confirmacao = input('');

  protected readonly regras = computed(() => avaliarSenha(this.senha(), this.confirmacao()));
}
