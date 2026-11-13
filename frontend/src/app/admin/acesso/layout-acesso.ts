import { Component } from '@angular/core';
import { RouterLink, RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-layout-acesso',
  imports: [RouterLink, RouterOutlet],
  template: `
    <div class="flex min-h-dvh flex-col bg-slate-100">
      <header class="bg-azul text-white">
        <div class="mx-auto flex max-w-7xl items-center px-4 py-3">
          <a routerLink="/" class="font-semibold">Reservas · Administração</a>
        </div>
      </header>
      <main id="conteudo" class="mx-auto w-full max-w-md flex-1 px-4 py-10">
        <router-outlet />
      </main>
    </div>
  `,
})
export class LayoutAcesso {}
