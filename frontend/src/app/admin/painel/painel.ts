import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { formatarDiaCurto } from '../../publico/datas';
import { EquipamentoFora } from '../reservas/modelos';
import { ReservasAdminService } from '../reservas/reservas-admin.service';

@Component({
  selector: 'app-painel',
  imports: [RouterLink],
  template: `
    <h1 class="text-2xl font-semibold text-slate-900">Painel</h1>
    @if (pedidosAguardando(); as total) {
      <a
        routerLink="/admin/reservas"
        [queryParams]="{ solicitacao: 'AGUARDANDO' }"
        class="mt-6 flex items-center justify-between rounded-2xl bg-violet-50 p-4 font-semibold text-violet-900 ring-1 ring-violet-200 hover:ring-violet-400"
      >
        <span
          >{{ total }}
          {{
            total === 1 ? 'pedido de equipamento aguardando' : 'pedidos de equipamento aguardando'
          }}
          resposta</span
        >
        <span class="underline">Ver</span>
      </a>
    }
    @if (fora(); as fora) {
      @if (fora.total > 0) {
        <section
          class="mt-6 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"
          aria-labelledby="titulo-fora"
        >
          <h2 id="titulo-fora" class="font-semibold text-slate-900">
            Equipamentos fora agora ({{ fora.total }})
          </h2>
          <ul class="mt-2 divide-y divide-slate-100">
            @for (item of fora.itens.slice(0, mostrar); track $index) {
              <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                <a [routerLink]="['/admin/reservas', item.reserva.id]" class="text-azul underline"
                  >{{ item.equipamento.nome }} × {{ item.quantidade }}</a
                >
                <span class="text-sm text-slate-700">
                  {{ item.reserva.espaco }} · {{ item.reserva.com_quem ?? 'Sem nome' }} · até
                  {{ formatarDiaCurto(item.reserva.data_reserva) }} {{ item.reserva.hora_fim }}
                  @if (item.reserva.terminou) {
                    <span
                      class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-900"
                      >Passou do horário</span
                    >
                  }
                </span>
              </li>
            }
          </ul>
          @if (fora.total > mostrar) {
            <p class="mt-2 text-sm text-slate-600">
              E mais {{ fora.total - mostrar }}. Veja na lista de reservas com devolução pendente.
            </p>
          }
        </section>
      }
    }
    <ul class="mt-6 grid gap-4 sm:grid-cols-2">
      <li>
        <a
          routerLink="/admin/reservas"
          class="block rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 hover:ring-azul"
        >
          <span class="block text-lg font-semibold text-azul">Reservas</span>
          <span class="text-slate-700"
            >Ver, filtrar, revogar, registrar devoluções e exportar.</span
          >
        </a>
      </li>
      <li>
        <a
          routerLink="/admin/calendario"
          class="block rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 hover:ring-azul"
        >
          <span class="block text-lg font-semibold text-azul">Calendário</span>
          <span class="text-slate-700"
            >Ver a semana de cada espaço com quem reservou e criar eventos.</span
          >
        </a>
      </li>
      <li>
        <a
          routerLink="/admin/cadastros"
          class="block rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 hover:ring-azul"
        >
          <span class="block text-lg font-semibold text-azul">Cadastros</span>
          <span class="text-slate-700">Espaços, equipamentos e os demais cadastros.</span>
        </a>
      </li>
      <li>
        <a
          routerLink="/admin/historico"
          class="block rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 hover:ring-azul"
        >
          <span class="block text-lg font-semibold text-azul">Histórico</span>
          <span class="text-slate-700"
            >Quem criou, alterou ou excluiu cada registro, e quando.</span
          >
        </a>
      </li>
    </ul>
  `,
})
export class Painel {
  private readonly reservas = inject(ReservasAdminService);

  protected readonly pedidosAguardando = toSignal(
    this.reservas.consultar({ solicitacao: 'AGUARDANDO' }, 1, 1).pipe(
      map((pagina) => pagina.meta.total),
      catchError(() => of(0)),
    ),
    { initialValue: 0 },
  );

  protected readonly mostrar = 8;
  protected readonly formatarDiaCurto = formatarDiaCurto;

  protected readonly fora = toSignal(
    this.reservas
      .equipamentosForaAgora()
      .pipe(catchError(() => of<{ total: number; itens: EquipamentoFora[] } | null>(null))),
    { initialValue: null },
  );
}
