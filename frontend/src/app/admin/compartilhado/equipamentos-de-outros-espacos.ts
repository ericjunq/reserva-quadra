import { Component, computed, inject, input, linkedSignal, model } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { LucideMinus, LucidePlus } from '@lucide/angular';
import { catchError, of, switchMap } from 'rxjs';
import { EquipamentoSolicitavel } from '../../publico/modelos';
import { CalendarioAdminService } from '../calendario/calendario-admin.service';

export interface IntervaloDoUso {
  espacoId: number;
  data: string;
  inicio: string;
  fim: string;
}

@Component({
  selector: 'app-equipamentos-de-outros-espacos',
  imports: [LucideMinus, LucidePlus],
  template: `
    <details
      class="rounded-xl border border-slate-200 p-4"
      [open]="aberto()"
      (toggle)="aberto.set($any($event.target).open)"
    >
      <summary class="cursor-pointer font-semibold text-slate-900">
        Equipamento de outro espaço (opcional)
      </summary>
      <p class="mt-2 text-sm text-slate-700">
        O pedido feito aqui, na coordenação, entra já aprovado.
      </p>
      @if (!intervalo()) {
        <p class="mt-2 text-sm text-slate-600">Escolha o espaço, a data e o horário antes.</p>
      } @else if (opcoes() === null) {
        <p class="mt-2 text-sm text-slate-600">Buscando os equipamentos…</p>
      } @else if (opcoes()!.length === 0) {
        <p class="mt-2 text-sm text-slate-600">Nenhum equipamento de outro espaço disponível.</p>
      } @else {
        <ul class="mt-2 divide-y divide-slate-100">
          @for (opcao of opcoes(); track opcao.id) {
            <li class="flex items-center justify-between gap-4 py-2">
              <div>
                <p class="font-medium text-slate-900">{{ opcao.nome }}</p>
                <p class="text-sm text-slate-600">
                  Do espaço {{ opcao.espaco }} · {{ opcao.disponivel }} disponível(is)
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="flex size-8 items-center justify-center rounded-full ring-1 ring-slate-300 enabled:hover:ring-azul disabled:opacity-40"
                  [disabled]="!(quantidades()[opcao.id] > 0)"
                  [attr.aria-label]="'Menos ' + opcao.nome + ' de outro espaço'"
                  (click)="alterar(opcao, -1)"
                >
                  <svg lucideMinus [size]="16" aria-hidden="true"></svg>
                </button>
                <output class="w-6 text-center font-semibold">{{
                  quantidades()[opcao.id] ?? 0
                }}</output>
                <button
                  type="button"
                  class="flex size-8 items-center justify-center rounded-full ring-1 ring-slate-300 enabled:hover:ring-azul disabled:opacity-40"
                  [disabled]="(quantidades()[opcao.id] ?? 0) >= opcao.disponivel"
                  [attr.aria-label]="'Mais ' + opcao.nome + ' de outro espaço'"
                  (click)="alterar(opcao, 1)"
                >
                  <svg lucidePlus [size]="16" aria-hidden="true"></svg>
                </button>
              </div>
            </li>
          }
        </ul>
      }
    </details>
  `,
})
export class EquipamentosDeOutrosEspacos {
  private readonly api = inject(CalendarioAdminService);

  readonly intervalo = input<IntervaloDoUso | null>(null);
  readonly quantidades = model<Record<number, number>>({});

  protected readonly aberto = linkedSignal<boolean>(() => {
    this.intervalo();
    return false;
  });

  private readonly consulta = computed(() => (this.aberto() ? this.intervalo() : null));

  protected readonly opcoes = toSignal(
    toObservable(this.consulta).pipe(
      switchMap((intervalo) =>
        intervalo
          ? this.api
              .solicitaveis(intervalo.espacoId, intervalo.data, intervalo.inicio, intervalo.fim)
              .pipe(catchError(() => of([] as EquipamentoSolicitavel[])))
          : of(null),
      ),
    ),
    { initialValue: null },
  );

  protected alterar(opcao: EquipamentoSolicitavel, variacao: number): void {
    this.quantidades.update((atuais) => ({
      ...atuais,
      [opcao.id]: Math.min(Math.max((atuais[opcao.id] ?? 0) + variacao, 0), opcao.disponivel),
    }));
  }
}
