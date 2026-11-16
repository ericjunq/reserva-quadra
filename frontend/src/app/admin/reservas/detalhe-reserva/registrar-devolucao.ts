import { Component, computed, inject, input, linkedSignal, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ItemDevolucao, ReservaAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';
import { NOMES_DAS_DEVOLUCOES } from '../selos';

type Situacao = ItemDevolucao['situacao'];

const SITUACOES: Situacao[] = ['DEVOLVIDO', 'DEVOLVIDO_COM_AVARIA', 'NAO_DEVOLVIDO'];

@Component({
  selector: 'app-registrar-devolucao',
  imports: [FormsModule, AvisoErro],
  template: `
    <form class="space-y-4" (ngSubmit)="registrar()" novalidate>
      <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
      @for (item of itens(); track item.equipamento_id; let i = $index) {
        <fieldset class="rounded-xl border border-slate-200 p-3">
          <legend class="px-1 font-semibold text-slate-900">
            {{ nomes()[item.equipamento_id] }} ({{ quantidades()[item.equipamento_id] }}
            reservado(s))
          </legend>
          <div class="grid gap-3 sm:grid-cols-3">
            <div>
              <label [for]="'situacao-' + item.equipamento_id" class="block text-sm font-semibold"
                >Situação</label
              >
              <select
                [id]="'situacao-' + item.equipamento_id"
                [name]="'situacao-' + item.equipamento_id"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                [ngModel]="item.situacao"
                (ngModelChange)="mudar(i, { situacao: $event })"
              >
                @for (situacao of situacoes; track situacao) {
                  <option [value]="situacao">{{ nomesDasSituacoes[situacao] }}</option>
                }
              </select>
            </div>
            <div>
              <label [for]="'quantidade-' + item.equipamento_id" class="block text-sm font-semibold"
                >Quantidade devolvida</label
              >
              <input
                [id]="'quantidade-' + item.equipamento_id"
                [name]="'quantidade-' + item.equipamento_id"
                type="number"
                min="0"
                [max]="quantidades()[item.equipamento_id]"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                [ngModel]="item.quantidade_devolvida"
                (ngModelChange)="mudar(i, { quantidade_devolvida: +$event })"
              />
            </div>
            <div>
              <label
                [for]="'observacao-' + item.equipamento_id"
                class="block text-sm font-semibold"
              >
                Observação
                @if (item.situacao !== 'DEVOLVIDO') {
                  <span class="font-normal text-slate-600">(obrigatória)</span>
                }
              </label>
              <input
                [id]="'observacao-' + item.equipamento_id"
                [name]="'observacao-' + item.equipamento_id"
                maxlength="500"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                [ngModel]="item.observacao ?? ''"
                (ngModelChange)="mudar(i, { observacao: $event })"
              />
            </div>
          </div>
          @if (erroDoItem(i); as mensagem) {
            <p class="mt-2 text-sm text-red-700">{{ mensagem }}</p>
          }
        </fieldset>
      }
      @if (mensagem(); as mensagem) {
        <p class="rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">
          {{ mensagem }}
        </p>
      }
      <button
        type="submit"
        class="rounded-full bg-azul px-5 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
        [disabled]="enviando()"
      >
        {{ enviando() ? 'Salvando…' : 'Salvar devolução' }}
      </button>
    </form>
  `,
})
export class RegistrarDevolucao {
  private readonly api = inject(ReservasAdminService);

  readonly reserva = input.required<ReservaAdmin>();
  readonly registrada = output<ReservaAdmin>();

  protected readonly situacoes = SITUACOES;
  protected readonly nomesDasSituacoes = NOMES_DAS_DEVOLUCOES;
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  protected readonly itens = linkedSignal<ItemDevolucao[]>(() =>
    (this.reserva().equipamentos ?? []).map((equipamento) => ({
      equipamento_id: equipamento.id,
      situacao:
        equipamento.devolucao.situacao && equipamento.devolucao.situacao !== 'PENDENTE'
          ? equipamento.devolucao.situacao
          : 'DEVOLVIDO',
      quantidade_devolvida: equipamento.devolucao.quantidade_devolvida ?? equipamento.quantidade,
      observacao: equipamento.devolucao.observacao,
    })),
  );

  protected readonly nomes = computed(() =>
    Object.fromEntries((this.reserva().equipamentos ?? []).map((e) => [e.id, e.nome])),
  );

  protected readonly quantidades = computed(() =>
    Object.fromEntries((this.reserva().equipamentos ?? []).map((e) => [e.id, e.quantidade])),
  );

  protected mudar(indice: number, mudanca: Partial<ItemDevolucao>): void {
    this.mensagem.set(null);
    this.itens.update((itens) =>
      itens.map((item, i) => (i === indice ? { ...item, ...mudanca } : item)),
    );
  }

  protected erroDoItem(indice: number): string | null {
    const campos = this.erro()?.campos ?? {};
    const mensagens = Object.entries(campos)
      .filter(([campo]) => campo.startsWith(`itens.${indice}.`))
      .map(([, lista]) => lista[0]);
    return mensagens[0] ?? null;
  }

  protected registrar(): void {
    if (this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    const itens = this.itens().map((item) => ({
      ...item,
      observacao: item.observacao?.trim() || null,
    }));
    this.api.registrarDevolucao(this.reserva().id, itens).subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.mensagem.set('Devolução registrada.');
        this.registrada.emit(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
