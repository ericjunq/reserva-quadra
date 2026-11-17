import { Component, computed, inject, input, linkedSignal, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { itemValeNaReserva } from '../../../publico/situacao-reserva';
import { ItemRetirada, ReservaAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';

export const MINUTOS_ANTES_DA_RETIRADA = 30;

export function janelaDaRetirada(
  reserva: Pick<ReservaAdmin, 'data_reserva' | 'hora_inicio' | 'hora_fim' | 'status'>,
  agora = Date.now(),
): 'antes' | 'aberta' | 'encerrada' | 'fora' {
  if (reserva.status !== 'RESERVADO') {
    return 'fora';
  }
  const inicio = new Date(`${reserva.data_reserva}T${reserva.hora_inicio}:00`).getTime();
  const fim = new Date(`${reserva.data_reserva}T${reserva.hora_fim}:00`).getTime();
  if (agora < inicio - MINUTOS_ANTES_DA_RETIRADA * 60_000) {
    return 'antes';
  }
  return agora > fim ? 'encerrada' : 'aberta';
}

@Component({
  selector: 'app-registrar-retirada',
  imports: [FormsModule, AvisoErro],
  template: `
    <form class="space-y-3" (ngSubmit)="registrar()" novalidate>
      <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
      <div class="grid gap-3 sm:grid-cols-2">
        @for (item of itens(); track item.equipamento_id; let i = $index) {
          <div>
            <label [for]="'retirada-' + item.equipamento_id" class="block text-sm font-semibold">
              {{ nomes()[item.equipamento_id] }}
              <span class="font-normal text-slate-600"
                >(de {{ quantidades()[item.equipamento_id] }} reservado(s))</span
              >
            </label>
            <input
              [id]="'retirada-' + item.equipamento_id"
              [name]="'retirada-' + item.equipamento_id"
              type="number"
              min="0"
              [max]="quantidades()[item.equipamento_id]"
              class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="item.quantidade_retirada"
              (ngModelChange)="mudar(i, +$event)"
            />
            @if (erroDoItem(item.equipamento_id); as mensagem) {
              <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
            }
          </div>
        }
      </div>
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
        {{ enviando() ? 'Salvando…' : 'Salvar retirada' }}
      </button>
    </form>
  `,
})
export class RegistrarRetirada {
  private readonly api = inject(ReservasAdminService);

  readonly reserva = input.required<ReservaAdmin>();
  readonly registrada = output<ReservaAdmin>();

  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  private readonly entregues = computed(() =>
    (this.reserva().equipamentos ?? []).filter((e) => itemValeNaReserva(e.solicitacao)),
  );

  protected readonly itens = linkedSignal<ItemRetirada[]>(() =>
    this.entregues().map((equipamento) => ({
      equipamento_id: equipamento.id,
      quantidade_retirada: equipamento.retirada?.quantidade ?? equipamento.quantidade,
    })),
  );

  protected readonly nomes = computed(() =>
    Object.fromEntries(this.entregues().map((e) => [e.id, e.nome])),
  );

  protected readonly quantidades = computed(() =>
    Object.fromEntries(this.entregues().map((e) => [e.id, e.quantidade])),
  );

  protected mudar(indice: number, quantidade: number): void {
    this.mensagem.set(null);
    this.itens.update((itens) =>
      itens.map((item, i) =>
        i === indice ? { ...item, quantidade_retirada: Math.max(quantidade || 0, 0) } : item,
      ),
    );
  }

  protected erroDoItem(equipamentoId: number): string | null {
    const invalidos = this.erro()?.dados?.['itens_invalidos'];
    if (!Array.isArray(invalidos)) {
      return null;
    }
    const encontrado = invalidos.find(
      (item: { equipamento_id: number; motivo: string }) => item.equipamento_id === equipamentoId,
    );
    return encontrado?.motivo ?? null;
  }

  protected registrar(): void {
    if (this.enviando()) {
      return;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.api.registrarRetirada(this.reserva().id, this.itens()).subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.mensagem.set('Retirada registrada.');
        this.registrada.emit(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
