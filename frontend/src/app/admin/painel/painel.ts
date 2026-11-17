import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';
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
}
