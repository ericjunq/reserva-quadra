import { Component, computed, inject, input, linkedSignal, model } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { LucideMinus, LucidePlus } from '@lucide/angular';
import { catchError, of, switchMap } from 'rxjs';
import { ApiPublicaService } from '../api-publica.service';
import { formatarDataHora } from '../datas';
import { EquipamentoSolicitavel, Solicitaveis } from '../modelos';

export interface Intervalo {
  espacoId: number;
  data: string;
  inicio: string;
  fim: string;
}

@Component({
  selector: 'app-pedidos-de-equipamento',
  imports: [LucideMinus, LucidePlus],
  template: `
    <details
      class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"
      [open]="aberto()"
      (toggle)="aberto.set($any($event.target).open)"
    >
      <summary class="cursor-pointer font-semibold text-slate-900">
        Pedir equipamento de outro espaço (opcional)
      </summary>
      <p class="mt-2 text-sm text-slate-700">
        O pedido depende da aprovação da administração e você recebe a resposta por email. Se ele
        for recusado ou ficar sem resposta, o email pede para você confirmar se mantém a reserva sem
        o equipamento; <strong>sem essa confirmação, a reserva é cancelada</strong>.
      </p>
      @if (solicitaveis() === null) {
        <p class="mt-3 text-sm text-slate-600">Buscando os equipamentos…</p>
      } @else if (!solicitaveis()!.responderAte) {
        <p class="mt-3 rounded-lg bg-amarelo/15 px-3 py-2 text-sm text-slate-800">
          Faltam poucas horas para a reserva: peça o equipamento diretamente na coordenação.
        </p>
      } @else if (opcoes().length === 0) {
        <p class="mt-3 text-sm text-slate-600">
          Nenhum equipamento de outro espaço pode ser pedido neste horário.
        </p>
      } @else {
        <p class="mt-3 text-sm font-semibold text-slate-800">A resposta chega até {{ prazo() }}.</p>
        <ul class="mt-3 divide-y divide-slate-100">
          @for (opcao of opcoes(); track opcao.id) {
            <li class="flex items-center justify-between gap-4 py-2">
              <div>
                <p class="font-medium text-slate-900">{{ opcao.nome }}</p>
                <p class="text-sm text-slate-600">
                  Do espaço {{ opcao.espaco }} ·
                  @if (opcao.disponivel > 0) {
                    {{ opcao.disponivel }} disponível(is) neste horário
                  } @else {
                    indisponível neste horário
                  }
                </p>
                @if (pedidos()[opcao.id] > 0) {
                  <label class="mt-1 flex items-center gap-2 text-sm text-slate-800">
                    <input
                      type="checkbox"
                      [checked]="!!obrigatorios()[opcao.id]"
                      (change)="marcarObrigatorio(opcao.id, $any($event.target).checked)"
                    />
                    A reserva só serve com este item
                  </label>
                }
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="flex size-8 items-center justify-center rounded-full ring-1 ring-slate-300 enabled:hover:ring-azul disabled:opacity-40"
                  [disabled]="!(pedidos()[opcao.id] > 0)"
                  [attr.aria-label]="'Pedir menos ' + opcao.nome"
                  (click)="alterar(opcao, -1)"
                >
                  <svg lucideMinus [size]="16" aria-hidden="true"></svg>
                </button>
                <output
                  class="w-6 text-center font-semibold"
                  [attr.aria-label]="'Quantidade pedida de ' + opcao.nome"
                >
                  {{ pedidos()[opcao.id] ?? 0 }}
                </output>
                <button
                  type="button"
                  class="flex size-8 items-center justify-center rounded-full ring-1 ring-slate-300 enabled:hover:ring-azul disabled:opacity-40"
                  [disabled]="(pedidos()[opcao.id] ?? 0) >= opcao.disponivel"
                  [attr.aria-label]="'Pedir mais ' + opcao.nome"
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
export class PedidosDeEquipamento {
  private readonly api = inject(ApiPublicaService);

  readonly intervalo = input<Intervalo | null>(null);
  readonly pedidos = model<Record<number, number>>({});
  readonly nomes = model<Record<number, string>>({});
  readonly obrigatorios = model<Record<number, boolean>>({});

  protected readonly aberto = linkedSignal<boolean>(() => {
    this.intervalo();
    return false;
  });

  private readonly consulta = computed(() => (this.aberto() ? this.intervalo() : null));

  protected readonly solicitaveis = toSignal(
    toObservable(this.consulta).pipe(
      switchMap((intervalo) =>
        intervalo
          ? this.api
              .consultarSolicitaveis(
                intervalo.espacoId,
                intervalo.data,
                intervalo.inicio,
                intervalo.fim,
              )
              .pipe(catchError(() => of<Solicitaveis>({ itens: [], responderAte: null })))
          : of(null),
      ),
    ),
    { initialValue: null },
  );

  protected readonly opcoes = computed(() => this.solicitaveis()?.itens ?? []);

  protected readonly prazo = computed(() => {
    const responderAte = this.solicitaveis()?.responderAte;
    return responderAte ? formatarDataHora(responderAte) : '';
  });

  protected marcarObrigatorio(id: number, marcado: boolean): void {
    this.obrigatorios.update((atuais) => ({ ...atuais, [id]: marcado }));
  }

  protected alterar(opcao: EquipamentoSolicitavel, variacao: number): void {
    this.nomes.update((atuais) => ({ ...atuais, [opcao.id]: opcao.nome }));
    this.pedidos.update((atuais) => ({
      ...atuais,
      [opcao.id]: Math.min(Math.max((atuais[opcao.id] ?? 0) + variacao, 0), opcao.disponivel),
    }));
  }
}

export function pedidosEscolhidos(
  pedidos: Record<number, number>,
  nomes: Record<number, string>,
  obrigatorios: Record<number, boolean> = {},
): { id: number; nome: string; quantidade: number; obrigatorio: boolean }[] {
  return Object.entries(pedidos)
    .filter(([, quantidade]) => quantidade > 0)
    .map(([id, quantidade]) => ({
      id: Number(id),
      nome: nomes[Number(id)] ?? '',
      quantidade,
      obrigatorio: !!obrigatorios[Number(id)],
    }));
}
