import { Component, computed, inject, input, output, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { formatarQuando } from '../../historico/rotulos';
import { CadastrosService } from '../cadastros.service';
import { EquipamentoCadastro, MovimentacaoEstoque, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';
import { AjustarEstoque } from './ajustar-estoque';
import { NOMES_DAS_MOVIMENTACOES, descreverQuantidade, utilizavel } from './tipos';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<MovimentacaoEstoque> }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-estoque-equipamento',
  imports: [RouterLink, AvisoErro, AjustarEstoque, Paginacao],
  template: `
    <section
      class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
      aria-labelledby="titulo-estoque"
    >
      <h2 id="titulo-estoque" class="text-lg font-semibold text-slate-900">Estoque</h2>
      <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-xl bg-slate-50 p-3">
          <dt class="text-sm text-slate-600">Total</dt>
          <dd class="text-2xl font-semibold text-slate-900">
            {{ equipamento().quantidade_total }}
          </dd>
        </div>
        <div class="rounded-xl bg-amber-50 p-3">
          <dt class="text-sm text-amber-900">Em manutenção</dt>
          <dd class="text-2xl font-semibold text-amber-900">
            {{ equipamento().quantidade_em_manutencao ?? 0 }}
          </dd>
        </div>
        <div class="rounded-xl bg-emerald-50 p-3">
          <dt class="text-sm text-emerald-900">Para reservar</dt>
          <dd class="text-2xl font-semibold text-emerald-900">{{ disponivel() }}</dd>
        </div>
      </dl>
      @if (mensagem(); as mensagem) {
        <p class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">
          {{ mensagem }}
        </p>
      }
      <h3 class="mt-6 font-semibold text-slate-900">Ajustar estoque</h3>
      <div class="mt-3">
        <app-ajustar-estoque [equipamento]="equipamento()" (ajustado)="registrado($event)" />
      </div>

      <h3 class="mt-8 font-semibold text-slate-900">Movimentações</h3>
      <div aria-live="polite">
        @switch (resultado().tipo) {
          @case ('carregando') {
            <p class="mt-2 text-slate-600">Carregando as movimentações…</p>
          }
          @case ('erro') {
            <div class="mt-2">
              <app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" />
            </div>
          }
          @case ('pronto') {
            @if (paginaPronta(); as pagina) {
              <ul class="mt-2 divide-y divide-slate-200">
                @for (item of pagina.data; track item.id) {
                  <li class="py-3">
                    <p class="font-semibold text-slate-900">
                      {{ nomes[item.tipo] }} · {{ descrever(item.tipo, item.quantidade) }}
                    </p>
                    <p class="text-slate-800">{{ item.estado }}</p>
                    <p class="text-sm text-slate-600">
                      {{ quando(item.em) }} · {{ item.feito_por?.nome ?? 'Sistema' }} · ficou com
                      {{ item.quantidade_total_depois }} no total e
                      {{ item.em_manutencao_depois }} em manutenção
                      @if (item.reserva; as reserva) {
                        ·
                        <a
                          [routerLink]="['/admin/reservas', reserva.id]"
                          class="text-azul underline"
                          >{{ reserva.tipo === 'EVENTO' ? 'evento' : 'reserva' }} nº
                          {{ reserva.id }}</a
                        >
                      }
                    </p>
                  </li>
                } @empty {
                  <li class="py-3 text-slate-700">Nenhuma movimentação.</li>
                }
              </ul>
              <app-paginacao
                [atual]="pagina.meta.current_page"
                [ultima]="pagina.meta.last_page"
                (ir)="pagina$.set($event)"
              />
            }
          }
        }
      </div>
    </section>
  `,
})
export class EstoqueEquipamento {
  private readonly api = inject(CadastrosService);

  readonly equipamento = input.required<EquipamentoCadastro>();
  readonly atualizado = output<EquipamentoCadastro>();

  protected readonly nomes = NOMES_DAS_MOVIMENTACOES;
  protected readonly descrever = descreverQuantidade;
  protected readonly quando = formatarQuando;
  protected readonly mensagem = signal<string | null>(null);
  protected readonly pagina$ = signal(1);
  private readonly recarga = signal(0);

  protected readonly disponivel = computed(() =>
    utilizavel(this.equipamento().quantidade_total, this.equipamento().quantidade_em_manutencao),
  );

  private readonly idDoEquipamento = computed(() => this.equipamento().id);

  private readonly consulta = computed(() => ({
    id: this.idDoEquipamento(),
    pagina: this.pagina$(),
    recarga: this.recarga(),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ id, pagina }) =>
        this.api.listarMovimentacoes(id, pagina).pipe(
          map((dados): Resultado => ({ tipo: 'pronto', pagina: dados })),
          catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
          startWith<Resultado>({ tipo: 'carregando' }),
        ),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Resultado },
  );

  protected readonly paginaPronta = computed(() => {
    const resultado = this.resultado();
    return resultado.tipo === 'pronto' ? resultado.pagina : null;
  });

  protected registrado(resultado: { equipamento: EquipamentoCadastro }): void {
    this.mensagem.set('Movimentação registrada.');
    this.pagina$.set(1);
    this.recarga.update((valor) => valor + 1);
    this.atualizado.emit(resultado.equipamento);
  }
}
