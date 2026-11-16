import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-painel',
  imports: [RouterLink],
  template: `
    <h1 class="text-2xl font-semibold text-slate-900">Painel</h1>
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
    </ul>
    <p class="mt-6 text-slate-600">Calendário, cadastros e histórico chegam nos próximos passos.</p>
  `,
})
export class Painel {}
