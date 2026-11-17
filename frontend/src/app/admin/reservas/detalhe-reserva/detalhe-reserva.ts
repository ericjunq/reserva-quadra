import { Location } from '@angular/common';
import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { formatarDia } from '../../../publico/datas';
import { descreverSolicitacao, itemValeNaReserva } from '../../../publico/situacao-reserva';
import { jaComecou, linhaDoTempo } from '../linha-do-tempo';
import { EquipamentoDaReserva, ReservaAdmin } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';
import { NOMES_DAS_DEVOLUCOES, SelosReserva } from '../selos';
import { RegistrarDevolucao } from './registrar-devolucao';
import { ResponderSolicitacao } from './responder-solicitacao';
import { RevogarReserva } from './revogar-reserva';
import { HistoricoDoRegistro } from '../../historico/historico-do-registro';
import { AjustarEstoque } from '../../cadastros/estoque/ajustar-estoque';
import { ResponsavelDoEvento } from './responsavel-do-evento';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; reserva: ReservaAdmin }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-detalhe-reserva',
  imports: [
    AvisoErro,
    SelosReserva,
    RevogarReserva,
    RegistrarDevolucao,
    ResponderSolicitacao,
    HistoricoDoRegistro,
    ResponsavelDoEvento,
    AjustarEstoque,
  ],
  templateUrl: './detalhe-reserva.html',
})
export class DetalheReserva {
  private readonly api = inject(ReservasAdminService);
  private readonly location = inject(Location);
  private readonly router = inject(Router);
  private readonly veioDaLista = history.state?.daLista === true;
  protected readonly aviso: string | null =
    typeof history.state?.aviso === 'string' ? history.state.aviso : null;

  readonly id = input.required<string>();

  protected readonly formatarDia = formatarDia;
  protected readonly nomesDasDevolucoes = NOMES_DAS_DEVOLUCOES;
  protected readonly descreverSolicitacao = descreverSolicitacao;
  protected readonly itemValeNaReserva = itemValeNaReserva;
  protected readonly ajustandoEstoque = signal<number | null>(null);
  protected readonly mensagemDoEstoque = signal<string | null>(null);

  protected temPendencia(equipamento: EquipamentoDaReserva): boolean {
    return (
      equipamento.devolucao.situacao === 'NAO_DEVOLVIDO' ||
      equipamento.devolucao.situacao === 'DEVOLVIDO_COM_AVARIA'
    );
  }

  protected quantidadeSugerida(equipamento: EquipamentoDaReserva): number {
    return equipamento.devolucao.situacao === 'NAO_DEVOLVIDO'
      ? Math.max(equipamento.quantidade - (equipamento.devolucao.quantidade_devolvida ?? 0), 1)
      : 1;
  }

  protected estoqueAjustado(resultado: {
    equipamento: { nome: string; quantidade_total: number; quantidade_em_manutencao?: number };
  }): void {
    const { nome, quantidade_total, quantidade_em_manutencao } = resultado.equipamento;
    this.ajustandoEstoque.set(null);
    this.mensagemDoEstoque.set(
      `Estoque de ${nome} atualizado: ${quantidade_total} no total, ${quantidade_em_manutencao ?? 0} em manutenção.`,
    );
  }

  private readonly carregado = toSignal(
    toObservable(this.id).pipe(switchMap((id) => this.buscar(id))),
    { initialValue: { tipo: 'carregando' } as Resultado },
  );

  protected readonly resultado = linkedSignal<Resultado>(() => this.carregado());

  protected readonly reserva = computed(() => {
    const resultado = this.resultado();
    return resultado.tipo === 'pronto' ? resultado.reserva : null;
  });

  protected readonly marcos = computed(() => {
    const reserva = this.reserva();
    return reserva ? linhaDoTempo(reserva) : [];
  });

  protected readonly pedidosAguardando = computed(
    () =>
      this.reserva()?.equipamentos?.filter((e) => e.solicitacao?.situacao === 'AGUARDANDO')
        .length ?? 0,
  );

  protected readonly podeRevogar = computed(() => this.reserva()?.status === 'RESERVADO');

  protected readonly podeRegistrarDevolucao = computed(() => {
    const reserva = this.reserva();
    return (
      !!reserva &&
      reserva.status === 'RESERVADO' &&
      !!reserva.equipamentos?.some((e) => itemValeNaReserva(e.solicitacao)) &&
      jaComecou(reserva)
    );
  });

  protected atualizar(reserva: ReservaAdmin): void {
    this.resultado.set({ tipo: 'pronto', reserva });
  }

  protected voltar(): void {
    if (this.veioDaLista) {
      this.location.back();
      return;
    }
    this.router.navigateByUrl('/admin/reservas');
  }

  private buscar(id: string): Observable<Resultado> {
    const numero = Number(id);
    if (!Number.isInteger(numero) || numero < 1) {
      return of<Resultado>({
        tipo: 'erro',
        erro: { status: 404, mensagem: 'Reserva não encontrada.', campos: {}, dados: {} },
      });
    }
    return this.api.detalhar(numero).pipe(
      map((reserva): Resultado => ({ tipo: 'pronto', reserva })),
      catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
      startWith<Resultado>({ tipo: 'carregando' }),
    );
  }
}
