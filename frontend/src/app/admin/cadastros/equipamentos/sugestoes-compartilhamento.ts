import { Component, inject, input, linkedSignal, output, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { CadastrosService } from '../cadastros.service';
import { EquipamentoCadastro, SugestaoCompartilhamento } from '../modelos';

@Component({
  selector: 'app-sugestoes-compartilhamento',
  imports: [RouterLink, AvisoErro],
  template: `
    @if (sugestoes().length) {
      <section
        class="rounded-2xl bg-sky-50 p-4 ring-1 ring-sky-200"
        aria-labelledby="titulo-sugestoes"
      >
        <h2 id="titulo-sugestoes" class="font-semibold text-sky-950">
          Sugestões de compartilhamento
        </h2>
        <p class="text-sm text-sky-900">
          Equipamentos pedidos várias vezes pelo mesmo espaço nos últimos dias. Compartilhar deixa o
          aluno reservar direto, sem pedido.
        </p>
        <app-aviso-erro class="mt-2 block" [mensagem]="erro()?.mensagem ?? null" />
        <ul class="mt-2 divide-y divide-sky-100">
          @for (sugestao of sugestoes(); track chave(sugestao)) {
            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
              <span class="text-slate-800">
                @if (mostrarEquipamento()) {
                  <a
                    [routerLink]="['/admin/cadastros/equipamentos', sugestao.equipamento.id]"
                    class="font-semibold text-azul underline"
                    >{{ sugestao.equipamento.nome }}</a
                  >
                  ({{ sugestao.espaco_de_origem.nome }})
                }
                pedido {{ sugestao.pedidos }} vezes pelo
                <strong>{{ sugestao.espaco_que_pede.nome }}</strong>
              </span>
              <span class="flex flex-wrap gap-2">
                <button
                  type="button"
                  class="rounded-full bg-azul px-3 py-1 text-sm font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
                  [disabled]="ocupado()"
                  (click)="aceitar(sugestao)"
                >
                  Compartilhar com {{ sugestao.espaco_que_pede.nome }}
                </button>
                <button
                  type="button"
                  class="rounded-full px-3 py-1 text-sm font-semibold text-azul ring-1 ring-azul/40 disabled:opacity-50"
                  [disabled]="ocupado()"
                  (click)="dispensar(sugestao)"
                >
                  Agora não<span class="sr-only">
                    para {{ sugestao.equipamento.nome }} em
                    {{ sugestao.espaco_que_pede.nome }}</span
                  >
                </button>
              </span>
            </li>
          }
        </ul>
      </section>
    }
    @if (mensagem(); as mensagem) {
      <p class="mt-2 rounded-lg bg-emerald-50 px-4 py-3 text-emerald-900" role="status">
        {{ mensagem }}
      </p>
    }
  `,
})
export class SugestoesCompartilhamento {
  private readonly api = inject(CadastrosService);

  readonly equipamentoId = input<number | null>(null);
  readonly mostrarEquipamento = input(true);
  readonly compartilhado = output<EquipamentoCadastro>();

  protected readonly ocupado = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  private readonly carregadas = toSignal(
    toObservable(this.equipamentoId).pipe(
      switchMap((id) =>
        this.api
          .listarSugestoesCompartilhamento(id ?? undefined)
          .pipe(catchError(() => of<SugestaoCompartilhamento[]>([]))),
      ),
    ),
    { initialValue: [] as SugestaoCompartilhamento[] },
  );

  protected readonly sugestoes = linkedSignal(() => this.carregadas());

  protected chave(sugestao: SugestaoCompartilhamento): string {
    return `${sugestao.equipamento.id}-${sugestao.espaco_que_pede.id}`;
  }

  protected aceitar(sugestao: SugestaoCompartilhamento): void {
    this.comecar();
    this.api
      .aceitarSugestaoCompartilhamento(sugestao.equipamento.id, sugestao.espaco_que_pede.id)
      .subscribe({
        next: (equipamento) => {
          this.remover(sugestao);
          this.mensagem.set(
            `${sugestao.equipamento.nome} agora é compartilhado com ${sugestao.espaco_que_pede.nome}.`,
          );
          this.compartilhado.emit(equipamento);
        },
        error: (erro: ErroApi) => this.falhar(erro),
      });
  }

  protected dispensar(sugestao: SugestaoCompartilhamento): void {
    this.comecar();
    this.api
      .dispensarSugestaoCompartilhamento(sugestao.equipamento.id, sugestao.espaco_que_pede.id)
      .subscribe({
        next: () => {
          this.remover(sugestao);
          this.mensagem.set('Sugestão dispensada. Ela volta se surgirem novos pedidos.');
        },
        error: (erro: ErroApi) => this.falhar(erro),
      });
  }

  private remover(sugestao: SugestaoCompartilhamento): void {
    this.ocupado.set(false);
    this.sugestoes.update((lista) => lista.filter((item) => item !== sugestao));
  }

  private comecar(): void {
    this.ocupado.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
  }

  private falhar(erro: ErroApi): void {
    this.ocupado.set(false);
    this.erro.set(erro);
  }
}
