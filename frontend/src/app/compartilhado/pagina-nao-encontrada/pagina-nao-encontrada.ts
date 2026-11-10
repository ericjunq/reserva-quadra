import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-pagina-nao-encontrada',
  imports: [RouterLink],
  template: `
    <main class="flex min-h-dvh flex-col items-center justify-center gap-4 px-4 text-center">
      <p class="text-6xl font-extrabold italic text-azul">404</p>
      <h1 class="text-xl font-semibold text-slate-900">Página não encontrada</h1>
      <p class="text-slate-600">O endereço pode estar errado ou a página mudou de lugar.</p>
      <a
        routerLink="/"
        class="rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio"
      >
        Voltar ao início
      </a>
    </main>
  `,
})
export class PaginaNaoEncontrada {}
