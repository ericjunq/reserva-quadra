import { Component, input, model } from '@angular/core';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-campos-do-responsavel',
  imports: [FormsModule],
  template: `
    <div class="grid gap-4 sm:grid-cols-2">
      <div>
        <label [for]="prefixo() + '-responsavel'" class="block font-semibold text-slate-900"
          >Responsável</label
        >
        <input
          [id]="prefixo() + '-responsavel'"
          name="responsavel_nome"
          maxlength="100"
          autocomplete="off"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
          [attr.aria-describedby]="prefixo() + '-ajuda-responsavel'"
          [ngModel]="nome()"
          (ngModelChange)="nome.set($event)"
        />
        @if (erroNome(); as texto) {
          <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
        }
      </div>
      <div>
        <label [for]="prefixo() + '-contato'" class="block font-semibold text-slate-900"
          >Contato do responsável (opcional)</label
        >
        <input
          [id]="prefixo() + '-contato'"
          name="responsavel_contato"
          maxlength="120"
          autocomplete="off"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="contato()"
          (ngModelChange)="contato.set($event)"
        />
        @if (erroContato(); as texto) {
          <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
        }
      </div>
      <p [id]="prefixo() + '-ajuda-responsavel'" class="text-sm text-slate-600 sm:col-span-2">
        Quem responde pelo espaço e pelos equipamentos. Só a administração vê. Se o contato for um
        email, ele recebe o aviso de pendência na devolução.
      </p>
    </div>
  `,
})
export class CamposDoResponsavel {
  readonly prefixo = input.required<string>();
  readonly nome = model('');
  readonly contato = model('');
  readonly erroNome = input<string | null>(null);
  readonly erroContato = input<string | null>(null);
}
