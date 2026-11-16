import { Component, computed, input } from '@angular/core';
import { SituacaoDevolucao, SituacaoSolicitacao, StatusReserva } from '../../publico/modelos';
import { OrigemReserva, SituacaoConfirmacao } from './modelos';

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

export const SELO_DE_REGISTRO_POSTERIOR: Selo = {
  texto: 'Registro posterior',
  classes: 'border-2 border-dashed border-fuchsia-600 bg-fuchsia-50 text-fuchsia-900',
};

export const SELO_DE_PEDIDO: Selo = {
  texto: 'Pedido de equipamento',
  classes: 'bg-violet-100 text-violet-900 ring-1 ring-violet-300',
};

export const SELO_AGUARDANDO_MANTER: Selo = {
  texto: 'Aguardando o aluno manter',
  classes: 'bg-amber-100 text-amber-900 ring-1 ring-amber-300',
};

export const NOMES_DAS_SOLICITACOES: Record<SituacaoSolicitacao, string> = {
  AGUARDANDO: 'Aguardando resposta',
  APROVADA: 'Aprovado',
  RECUSADA: 'Recusado',
  VENCIDA: 'Sem resposta',
};

export const NOMES_DAS_ORIGENS: Record<OrigemReserva, string> = {
  SITE: 'Pelo site',
  ADMIN: 'Evento do admin',
  REGISTRO_POSTERIOR: 'Registro posterior',
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
      @if (origem() === 'REGISTRO_POSTERIOR') {
        <span
          class="rounded-full px-2 py-0.5 text-xs font-semibold"
          [class]="registroPosterior.classes"
          >{{ registroPosterior.texto }}</span
        >
      }
      @if (aguardandoManter()) {
        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" [class]="manter.classes">{{
          manter.texto
        }}</span>
      }
      @if (pedidoAguardando()) {
        <span class="rounded-full px-2 py-0.5 text-xs font-semibold" [class]="pedido.classes">{{
          pedido.texto
        }}</span>
      }
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
  readonly origem = input<OrigemReserva | null>(null);
  readonly pedidoAguardando = input(false);
  readonly aguardandoManter = input(false);

  protected readonly manter = SELO_AGUARDANDO_MANTER;

  protected readonly pedido = SELO_DE_PEDIDO;

  protected readonly registroPosterior = SELO_DE_REGISTRO_POSTERIOR;

  protected readonly seloDoStatus = computed(() => SELOS_DE_STATUS[this.status()]);
  protected readonly seloDaConfirmacao = computed(() => {
    const situacao = this.confirmacao();
    return this.status() === 'RESERVADO' && situacao ? SELOS_DE_CONFIRMACAO[situacao] : null;
  });
}
