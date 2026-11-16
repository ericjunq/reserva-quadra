import { Component, computed, input } from '@angular/core';
import { SituacaoDevolucao, StatusReserva } from '../../publico/modelos';
import { SituacaoConfirmacao } from './modelos';

interface Selo {
  texto: string;
  classes: string;
}

export const SELOS_DE_STATUS: Record<StatusReserva, Selo> = {
  RESERVADO: { texto: 'Reservada', classes: 'bg-sky-100 text-sky-900 ring-1 ring-sky-300' },
  CANCELADO: { texto: 'Cancelada', classes: 'bg-slate-200 text-slate-800 ring-1 ring-slate-300' },
  REVOGADO: { texto: 'Revogada', classes: 'bg-red-100 text-red-900 ring-1 ring-red-300' },
};

export const SELOS_DE_CONFIRMACAO: Record<SituacaoConfirmacao, Selo> = {
  AGUARDANDO_CONFIRMACAO: {
    texto: 'Aguardando confirmação',
    classes: 'bg-amber-100 text-amber-900 ring-1 ring-amber-300',
  },
  CONFIRMADA: {
    texto: 'Confirmada',
    classes: 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-300',
  },
  AGUARDANDO_RECONFIRMACAO: {
    texto: 'Aguardando reconfirmação',
    classes: 'border-2 border-dashed border-amber-500 bg-white text-amber-900',
  },
  RECONFIRMADA: {
    texto: 'Reconfirmada',
    classes: 'bg-emerald-700 text-white ring-1 ring-emerald-800',
  },
};

export const NOMES_DAS_DEVOLUCOES: Record<SituacaoDevolucao, string> = {
  PENDENTE: 'Pendente',
  DEVOLVIDO: 'Devolvido',
  DEVOLVIDO_COM_AVARIA: 'Devolvido com avaria',
  NAO_DEVOLVIDO: 'Não devolvido',
};

@Component({
  selector: 'app-selos-reserva',
  template: `
    <span class="flex flex-wrap gap-1.5">
      <span
        class="rounded-full px-2 py-0.5 text-xs font-semibold"
        [class]="seloDoStatus().classes"
        >{{ seloDoStatus().texto }}</span
      >
      @if (seloDaConfirmacao(); as selo) {
        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" [class]="selo.classes">{{
          selo.texto
        }}</span>
      }
    </span>
  `,
})
export class SelosReserva {
  readonly status = input.required<StatusReserva>();
  readonly confirmacao = input<SituacaoConfirmacao | null>(null);

  protected readonly seloDoStatus = computed(() => SELOS_DE_STATUS[this.status()]);
  protected readonly seloDaConfirmacao = computed(() => {
    const situacao = this.confirmacao();
    return this.status() === 'RESERVADO' && situacao ? SELOS_DE_CONFIRMACAO[situacao] : null;
  });
}
