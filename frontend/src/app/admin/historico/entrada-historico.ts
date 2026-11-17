import { Component, computed, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Auditoria } from './modelos';
import {
  NOMES_DAS_ACOES,
  NOMES_DOS_TIPOS,
  formatarQuando,
  linkDoRegistro,
  mudancasDe,
  quemFez,
} from './rotulos';

@Component({
  selector: 'app-entrada-historico',
  imports: [RouterLink],
  template: `
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <p class="font-semibold text-slate-900">
          {{ nomesDasAcoes[entrada().acao] }}
          @if (mostrarRegistro()) {
            @if (link(); as link) {
              <a [routerLink]="link" class="text-azul underline"
                >{{ nomesDosTipos[entrada().tipo] }} nº {{ entrada().registro_id }}</a
              >
            } @else {
              {{ nomesDosTipos[entrada().tipo] }} nº {{ entrada().registro_id }}
            }
          }
        </p>
        <p class="text-sm text-slate-700">
          {{ resumo() }}
        </p>
      </div>
      <button
        type="button"
        class="rounded-full px-3 py-1 text-sm font-semibold text-azul ring-1 ring-azul/40"
        [attr.aria-expanded]="aberta()"
        [attr.aria-controls]="'mudancas-' + entrada().id"
        (click)="aberta.set(!aberta())"
      >
        {{ aberta() ? 'Esconder mudanças' : 'Ver mudanças' }}
      </button>
    </div>
    @if (aberta()) {
      <div [id]="'mudancas-' + entrada().id" class="mt-3 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <caption class="sr-only">
            Mudanças campo a campo
          </caption>
          <thead class="text-slate-600">
            <tr>
              <th scope="col" class="py-1 pr-3 font-semibold">Campo</th>
              <th scope="col" class="py-1 pr-3 font-semibold">Antes</th>
              <th scope="col" class="py-1 font-semibold">Depois</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @for (mudanca of mudancas(); track mudanca.campo) {
              <tr>
                <th scope="row" class="py-1 pr-3 font-semibold text-slate-900">
                  {{ mudanca.rotulo }}
                </th>
                <td class="py-1 pr-3 break-all text-slate-700">{{ mudanca.antes }}</td>
                <td class="py-1 break-all text-slate-900">{{ mudanca.depois }}</td>
              </tr>
            } @empty {
              <tr>
                <td colspan="3" class="py-1 text-slate-700">Sem campos registrados.</td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
})
export class EntradaHistorico {
  readonly entrada = input.required<Auditoria>();
  readonly mostrarRegistro = input(true);

  protected readonly aberta = signal(false);
  protected readonly nomesDasAcoes = NOMES_DAS_ACOES;
  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;
  protected readonly mudancas = computed(() => mudancasDe(this.entrada()));
  protected readonly resumo = computed(() => {
    const entrada = this.entrada();
    const partes = [formatarQuando(entrada.em), quemFez(entrada)];
    if (entrada.acao === 'ATUALIZACAO') {
      const total = this.mudancas().length;
      partes.push(`${total} ${total === 1 ? 'campo' : 'campos'}`);
    }
    return partes.join(' · ');
  });
  protected readonly link = computed(() =>
    linkDoRegistro(this.entrada().tipo, this.entrada().registro_id),
  );
}
