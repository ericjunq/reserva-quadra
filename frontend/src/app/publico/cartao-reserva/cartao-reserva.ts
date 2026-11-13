import { Component, input } from '@angular/core';
import { formatarDia } from '../datas';
import { ReservaDoAluno } from '../modelos';
import { descreverDevolucao } from '../situacao-reserva';

@Component({
  selector: 'app-cartao-reserva',
  template: `
    @let item = reserva();
    <p class="font-semibold text-slate-900">{{ item.espaco?.nome ?? 'Espaço' }}</p>
    <p class="text-slate-700">
      {{ formatarDia(item.data_reserva) }}, das {{ item.hora_inicio }} às {{ item.hora_fim }}
    </p>
    @if (item.equipamentos?.length) {
      <ul class="mt-2 space-y-1 text-sm text-slate-700">
        @for (equipamento of item.equipamentos; track equipamento.id) {
          <li>
            {{ equipamento.nome }} × {{ equipamento.quantidade }}
            @if (descreverDevolucao(item, equipamento.devolucao.situacao); as devolucao) {
              <span class="text-slate-600">· {{ devolucao }}</span>
            }
          </li>
        }
      </ul>
    }
    <ng-content />
  `,
})
export class CartaoReserva {
  readonly reserva = input.required<ReservaDoAluno>();

  protected readonly formatarDia = formatarDia;
  protected readonly descreverDevolucao = descreverDevolucao;
}
