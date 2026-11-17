import { Component, computed, inject, input, linkedSignal, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { CadastrosService } from '../cadastros.service';
import {
  DadosDaMovimentacao,
  EquipamentoCadastro,
  MovimentacaoEstoque,
  TipoMovimentacao,
} from '../modelos';
import { TIPOS_DE_MOVIMENTACAO } from './tipos';

@Component({
  selector: 'app-ajustar-estoque',
  imports: [FormsModule, AvisoErro],
  template: `
    <form class="space-y-4" (ngSubmit)="enviar()" novalidate>
      @if (erro(); as erro) {
        <app-aviso-erro [mensagem]="erro.mensagem" />
      }
      <div class="flex flex-wrap gap-4">
        <div>
          <label [for]="prefixo() + '-tipo'" class="block font-semibold text-slate-900"
            >O que aconteceu</label
          >
          <select
            [id]="prefixo() + '-tipo'"
            name="tipo"
            class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
            [value]="tipo()"
            (change)="tipo.set($any($event.target).value)"
          >
            @for (opcao of tipos; track opcao.valor) {
              <option [value]="opcao.valor" [selected]="opcao.valor === tipo()">
                {{ opcao.nome }}
              </option>
            }
          </select>
          <p class="mt-1 max-w-xs text-sm text-slate-600">{{ ajuda() }}</p>
        </div>
        <div>
          <label [for]="prefixo() + '-quantidade'" class="block font-semibold text-slate-900"
            >Quantidade</label
          >
          <input
            [id]="prefixo() + '-quantidade'"
            name="quantidade"
            type="number"
            [min]="tipo() === 'AJUSTE' ? null : 1"
            class="mt-1 w-28 rounded-lg border border-slate-300 px-3 py-2"
            [ngModel]="quantidade()"
            (ngModelChange)="quantidade.set($event)"
          />
          @if (erroDoCampo('quantidade'); as texto) {
            <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
          }
        </div>
        @if (tipo() === 'NAO_DEVOLVIDO' && !reservaId()) {
          <div>
            <label [for]="prefixo() + '-reserva'" class="block font-semibold text-slate-900"
              >Nº da reserva ou evento</label
            >
            <input
              [id]="prefixo() + '-reserva'"
              name="reserva"
              type="number"
              min="1"
              class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="reservaDigitada()"
              (ngModelChange)="reservaDigitada.set($event)"
            />
            @if (erroDoCampo('reserva_id'); as texto) {
              <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
            }
          </div>
        }
      </div>
      @if (podeSairDaManutencao()) {
        <label class="flex items-center gap-2 text-slate-800">
          <input
            type="checkbox"
            name="da-manutencao"
            [ngModel]="daManutencao()"
            (ngModelChange)="daManutencao.set($event)"
          />
          Estas unidades estavam em manutenção
        </label>
      }
      <div>
        <label [for]="prefixo() + '-estado'" class="block font-semibold text-slate-900"
          >Estado do equipamento</label
        >
        <textarea
          [id]="prefixo() + '-estado'"
          name="estado"
          rows="2"
          maxlength="500"
          class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
          [ngModel]="estado()"
          (ngModelChange)="estado.set($event)"
        ></textarea>
        <p class="text-sm text-slate-600">
          Descreva como o equipamento está. Não escreva nomes de pessoas: a reserva já fica ligada.
        </p>
        @if (erroDoCampo('estado'); as texto) {
          <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
        }
      </div>
      <button
        type="submit"
        class="rounded-full bg-azul px-5 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
        [disabled]="enviando()"
      >
        {{ enviando() ? 'Registrando…' : 'Registrar no estoque' }}
      </button>
    </form>
  `,
})
export class AjustarEstoque {
  private readonly api = inject(CadastrosService);

  readonly equipamento =
    input.required<Pick<EquipamentoCadastro, 'id' | 'quantidade_em_manutencao'>>();
  readonly reservaId = input<number | null>(null);
  readonly tipoInicial = input<TipoMovimentacao>('ENTRADA');
  readonly quantidadeInicial = input(1);
  readonly estadoInicial = input('');
  readonly ajustado = output<{
    movimentacao: MovimentacaoEstoque;
    equipamento: EquipamentoCadastro;
  }>();

  protected readonly tipos = TIPOS_DE_MOVIMENTACAO;
  protected readonly tipo = linkedSignal<TipoMovimentacao>(() => this.tipoInicial());
  protected readonly quantidade = linkedSignal<number | string>(() => this.quantidadeInicial());
  protected readonly estado = linkedSignal(() => this.estadoInicial());
  protected readonly reservaDigitada = signal<number | string>('');
  protected readonly daManutencao = signal(false);
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);

  protected readonly prefixo = computed(
    () => `estoque-${this.equipamento().id}-${this.reservaId() ?? 'cadastro'}`,
  );
  protected readonly ajuda = computed(
    () => this.tipos.find((opcao) => opcao.valor === this.tipo())?.ajuda ?? '',
  );
  protected readonly podeSairDaManutencao = computed(
    () =>
      ['BAIXA', 'NAO_DEVOLVIDO'].includes(this.tipo()) &&
      (this.equipamento().quantidade_em_manutencao ?? 0) > 0,
  );

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos[campo]?.[0] ?? null;
  }

  protected enviar(): void {
    if (this.enviando()) {
      return;
    }
    const dados: DadosDaMovimentacao = {
      tipo: this.tipo(),
      quantidade: Number(this.quantidade()) || 0,
      estado: this.estado().trim(),
    };
    const reserva = this.reservaId() ?? (Number(this.reservaDigitada()) || null);
    if (reserva && this.tipo() === 'NAO_DEVOLVIDO') {
      dados.reserva_id = reserva;
    } else if (this.reservaId()) {
      dados.reserva_id = this.reservaId()!;
    }
    if (this.podeSairDaManutencao() && this.daManutencao()) {
      dados.da_manutencao = true;
    }
    this.enviando.set(true);
    this.erro.set(null);
    this.api.movimentarEstoque(this.equipamento().id, dados).subscribe({
      next: (resultado) => {
        this.enviando.set(false);
        this.estado.set('');
        this.ajustado.emit(resultado);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
