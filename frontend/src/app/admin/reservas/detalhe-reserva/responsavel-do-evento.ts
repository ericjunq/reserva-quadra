import { Component, inject, input, linkedSignal, output, signal } from '@angular/core';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ConfirmarAcao } from '../../cadastros/confirmar-acao';
import { CamposDoResponsavel } from '../../compartilhado/campos-do-responsavel';
import { ReservaAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';

@Component({
  selector: 'app-responsavel-do-evento',
  imports: [AvisoErro, ConfirmarAcao, CamposDoResponsavel],
  template: `
    @if (editando()) {
      <form class="mt-2 space-y-3" (submit)="$event.preventDefault(); salvar()" novalidate>
        <app-campos-do-responsavel
          prefixo="detalhe"
          [(nome)]="nome"
          [(contato)]="contato"
          [erroNome]="erro()?.campos?.['responsavel_nome']?.[0] ?? null"
          [erroContato]="erro()?.campos?.['responsavel_contato']?.[0] ?? null"
        />
        <div class="flex flex-wrap gap-2">
          <button
            type="submit"
            class="rounded-full bg-azul px-4 py-2 font-semibold text-white disabled:opacity-50"
            [disabled]="enviando()"
          >
            Salvar o responsável
          </button>
          <button
            type="button"
            class="rounded-full px-4 py-2 font-semibold text-azul ring-1 ring-azul/40"
            (click)="editando.set(false)"
          >
            Cancelar
          </button>
        </div>
      </form>
    } @else {
      <p class="text-slate-900">
        @if (reserva().responsavel?.nome; as nome) {
          {{ nome }}
          @if (reserva().responsavel?.contato; as contato) {
            <span class="block text-sm break-all text-slate-700">{{ contato }}</span>
          }
        } @else {
          <span class="text-slate-600">Responsável removido</span>
        }
      </p>
      <div class="mt-2 flex flex-wrap gap-2">
        <button
          type="button"
          class="rounded-full px-3 py-1 text-sm font-semibold text-azul ring-1 ring-azul/40"
          (click)="editando.set(true)"
        >
          {{ reserva().responsavel?.nome ? 'Corrigir' : 'Informar o responsável' }}
        </button>
        @if (reserva().responsavel?.nome) {
          <app-confirmar-acao
            rotulo="Remover a pedido"
            aviso="Use quando o responsável pedir a exclusão dos dados. Nome e contato somem do evento e do histórico de alterações. Não dá para desfazer."
            [perigo]="true"
            [ocupado]="enviando()"
            (confirmado)="remover()"
          />
        }
      </div>
    }
    @if (erro(); as erro) {
      @if (!editando()) {
        <div class="mt-2"><app-aviso-erro [mensagem]="erro.mensagem" /></div>
      }
    }
  `,
})
export class ResponsavelDoEvento {
  private readonly api = inject(ReservasAdminService);

  readonly reserva = input.required<ReservaAdmin>();
  readonly atualizada = output<ReservaAdmin>();

  protected readonly editando = signal(false);
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly nome = linkedSignal(() => this.reserva().responsavel?.nome ?? '');
  protected readonly contato = linkedSignal(() => this.reserva().responsavel?.contato ?? '');

  protected salvar(): void {
    const contato = this.contato().trim();
    this.executar({
      responsavel_nome: this.nome().trim(),
      ...(contato ? { responsavel_contato: contato } : {}),
    });
  }

  protected remover(): void {
    this.executar({ remover: true });
  }

  private executar(
    dados: { responsavel_nome: string; responsavel_contato?: string } | { remover: true },
  ): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.api.atualizarResponsavel(this.reserva().id, dados).subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.editando.set(false);
        this.atualizada.emit(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
