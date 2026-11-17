import { Component, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { EntradaHistorico } from './entrada-historico';
import { HistoricoService } from './historico.service';
import { Auditoria, TipoAuditavel } from './modelos';

const QUANTAS_MOSTRAR = 10;

type Resultado =
  | { tipo: 'fechado' }
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; entradas: Auditoria[]; total: number }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-historico-do-registro',
  imports: [RouterLink, AvisoErro, EntradaHistorico],
  template: `
    <section
      class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
      aria-labelledby="titulo-historico-registro"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id="titulo-historico-registro" class="text-lg font-semibold text-slate-900">
          Histórico deste registro
        </h2>
        <button
          type="button"
          class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40"
          [attr.aria-expanded]="resultado().tipo !== 'fechado'"
          (click)="alternar()"
        >
          {{ resultado().tipo === 'fechado' ? 'Mostrar o histórico' : 'Esconder o histórico' }}
        </button>
      </div>
      <div aria-live="polite">
        @switch (resultado().tipo) {
          @case ('carregando') {
            <p class="mt-3 text-slate-600">Carregando o histórico…</p>
          }
          @case ('erro') {
            <div class="mt-3"><app-aviso-erro [mensagem]="$any(resultado()).erro.mensagem" /></div>
          }
          @case ('pronto') {
            <ul class="mt-3 divide-y divide-slate-200">
              @for (entrada of $any(resultado()).entradas; track entrada.id) {
                <li class="py-3">
                  <app-entrada-historico [entrada]="entrada" [mostrarRegistro]="false" />
                </li>
              } @empty {
                <li class="py-3 text-slate-700">Nenhuma mudança registrada.</li>
              }
            </ul>
            @if ($any(resultado()).total > quantasMostrar) {
              <p class="mt-2 text-sm text-slate-600">
                Mostrando as {{ quantasMostrar }} mais recentes de {{ $any(resultado()).total }}.
              </p>
            }
            <a
              routerLink="/admin/historico"
              [queryParams]="{ tipo: tipo(), registro: registroId() }"
              class="mt-3 inline-block text-sm font-semibold text-azul underline"
              >Ver tudo no histórico</a
            >
          }
        }
      </div>
    </section>
  `,
})
export class HistoricoDoRegistro {
  private readonly api = inject(HistoricoService);

  readonly tipo = input.required<TipoAuditavel>();
  readonly registroId = input.required<number>();

  protected readonly quantasMostrar = QUANTAS_MOSTRAR;
  protected readonly resultado = signal<Resultado>({ tipo: 'fechado' });
  private pedido?: Subscription;

  protected alternar(): void {
    if (this.resultado().tipo !== 'fechado') {
      this.pedido?.unsubscribe();
      this.resultado.set({ tipo: 'fechado' });
      return;
    }
    this.resultado.set({ tipo: 'carregando' });
    this.pedido = this.api
      .listar({ tipo: this.tipo(), registro_id: this.registroId() }, 1, QUANTAS_MOSTRAR)
      .subscribe({
        next: (pagina) =>
          this.resultado.set({ tipo: 'pronto', entradas: pagina.data, total: pagina.meta.total }),
        error: (erro: ErroApi) => this.resultado.set({ tipo: 'erro', erro }),
      });
  }
}
