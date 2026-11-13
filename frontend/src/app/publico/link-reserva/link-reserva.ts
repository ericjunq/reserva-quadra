import { Location } from '@angular/common';
import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { LucideCircleCheck } from '@lucide/angular';
import { Observable, catchError, filter, map, of, startWith, switchMap, take } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { CartaoReserva } from '../cartao-reserva/cartao-reserva';
import { formatarDataHora } from '../datas';
import { ReservaDoAluno, ResumoDaConfirmacao, ResumoDoCancelamento, Termos } from '../modelos';
import { SecoesTermos } from '../secoes-termos/secoes-termos';

export type ModoDoLink = 'cancelar' | 'confirmar';

type Resumo =
  | { tipo: 'carregando' }
  | { tipo: 'sem-token' }
  | { tipo: 'pronto'; cancelamento?: ResumoDoCancelamento; confirmacao?: ResumoDaConfirmacao }
  | { tipo: 'erro'; mensagem: string };

const FIM_DO_PRAZO_DA_RECONFIRMACAO = 60_000;

@Component({
  selector: 'app-link-reserva',
  imports: [FormsModule, RouterLink, AvisoErro, CartaoReserva, SecoesTermos, LucideCircleCheck],
  templateUrl: './link-reserva.html',
})
export class LinkReserva {
  private readonly api = inject(ApiPublicaService);
  private readonly location = inject(Location);

  readonly modo = input.required<ModoDoLink>();
  readonly token = input<string>();

  protected readonly aceite = signal(false);
  protected readonly enviando = signal(false);
  protected readonly erroDoEnvio = signal<ErroApi | null>(null);
  protected readonly concluida = signal<ReservaDoAluno | null>(null);

  private tokenLido: string | null = null;

  protected readonly resumo = toSignal(
    toObservable(this.modo).pipe(
      take(1),
      switchMap(() => this.carregar()),
    ),
    { initialValue: { tipo: 'carregando' } as Resumo },
  );

  protected readonly reserva = computed(() => {
    const resumo = this.resumo();
    if (resumo.tipo !== 'pronto') {
      return null;
    }
    return resumo.cancelamento?.data ?? resumo.confirmacao?.data ?? null;
  });

  protected readonly confirmacao = computed(() => {
    const resumo = this.resumo();
    return resumo.tipo === 'pronto' ? (resumo.confirmacao ?? null) : null;
  });

  protected readonly cancelamento = computed(() => {
    const resumo = this.resumo();
    return resumo.tipo === 'pronto' ? (resumo.cancelamento ?? null) : null;
  });

  protected readonly termos = toSignal(
    toObservable(this.modo).pipe(
      filter((modo) => modo === 'confirmar'),
      take(1),
      switchMap(() => this.api.consultarTermos()),
      map((termos) => ({ termos, erro: null as string | null })),
      catchError((erro: ErroApi) => of({ termos: null as Termos | null, erro: erro.mensagem })),
    ),
  );

  protected readonly prazo = computed(() => {
    const confirmacao = this.confirmacao();
    if (!confirmacao?.prazo_confirmacao) {
      return null;
    }
    const prazo = new Date(confirmacao.prazo_confirmacao).getTime();
    const exibido =
      confirmacao.etapa === 'RECONFIRMACAO' ? prazo - FIM_DO_PRAZO_DA_RECONFIRMACAO : prazo;
    return formatarDataHora(new Date(exibido).toISOString());
  });

  protected readonly podeEnviar = computed(() => {
    const confirmacao = this.confirmacao();
    if (confirmacao) {
      return confirmacao.pode_confirmar && (!confirmacao.exige_aceite_termos || this.aceite());
    }
    return !!this.cancelamento()?.pode_cancelar;
  });

  protected enviar(): void {
    if (!this.tokenLido || !this.podeEnviar() || this.enviando()) {
      return;
    }
    const pedido =
      this.modo() === 'confirmar'
        ? this.api.confirmar(this.tokenLido, this.aceite())
        : this.api.cancelar(this.tokenLido);

    this.enviando.set(true);
    this.erroDoEnvio.set(null);
    pedido.subscribe({
      next: (reserva) => {
        this.enviando.set(false);
        this.tokenLido = null;
        this.concluida.set(reserva);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erroDoEnvio.set(erro);
      },
    });
  }

  private carregar(): Observable<Resumo> {
    const token = this.lerToken();
    if (!token) {
      return of<Resumo>({ tipo: 'sem-token' });
    }
    const pedido: Observable<Resumo> =
      this.modo() === 'confirmar'
        ? this.api
            .resumoDaConfirmacao(token)
            .pipe(map((confirmacao): Resumo => ({ tipo: 'pronto', confirmacao })))
        : this.api
            .resumoDoCancelamento(token)
            .pipe(map((cancelamento): Resumo => ({ tipo: 'pronto', cancelamento })));

    return pedido.pipe(
      catchError((erro: ErroApi) => of<Resumo>({ tipo: 'erro', mensagem: erro.mensagem })),
      startWith<Resumo>({ tipo: 'carregando' }),
    );
  }

  private lerToken(): string | null {
    const token = this.token();
    if (!token) {
      return null;
    }
    this.tokenLido = token;
    this.location.replaceState(`/reservas/${this.modo()}`);
    return token;
  }
}
