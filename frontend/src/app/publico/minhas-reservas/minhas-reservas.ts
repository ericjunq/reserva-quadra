import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { LucideInfo } from '@lucide/angular';
import { Observable, catchError, combineLatest, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { CartaoReserva } from '../cartao-reserva/cartao-reserva';
import { paraIso } from '../datas';
import { PaginaDeReservas, PeriodoMinhasReservas, ReservaDoAluno } from '../modelos';
import { descreverConfirmacao, nomeDoStatus } from '../situacao-reserva';

interface Consulta {
  ra: string;
  email: string;
  periodo: PeriodoMinhasReservas;
}

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: PaginaDeReservas }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-minhas-reservas',
  imports: [FormsModule, RouterLink, AvisoErro, CartaoReserva, LucideInfo],
  templateUrl: './minhas-reservas.html',
})
export class MinhasReservas {
  private readonly api = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly pagina = input<string>();

  protected readonly ra = signal('');
  protected readonly email = signal('');
  protected readonly periodo = signal<PeriodoMinhasReservas>('FUTURAS');
  protected readonly erroDoFormulario = signal<string | null>(null);
  private readonly consulta = signal<Consulta | null>(null);

  protected readonly nomeDoStatus = nomeDoStatus;
  protected readonly descreverConfirmacao = descreverConfirmacao;

  protected readonly numeroDaPagina = computed(() => {
    const numero = Number(this.pagina());
    return Number.isInteger(numero) && numero > 0 ? numero : 1;
  });

  protected readonly resultado = toSignal(
    combineLatest([toObservable(this.consulta), toObservable(this.numeroDaPagina)]).pipe(
      switchMap(([consulta, pagina]) => (consulta ? this.buscar(consulta, pagina) : of(null))),
    ),
    { initialValue: null },
  );

  protected readonly paginaPronta = computed(() => {
    const resultado = this.resultado();
    return resultado?.tipo === 'pronto' ? resultado.pagina : null;
  });

  protected consultar(): void {
    const ra = this.ra().trim();
    const email = this.email().trim();
    if (!/^\d{10}$/.test(ra)) {
      this.erroDoFormulario.set('O RA tem 10 números.');
      return;
    }
    if (!email.includes('@')) {
      this.erroDoFormulario.set('Informe o email do seu cadastro.');
      return;
    }
    this.erroDoFormulario.set(null);
    this.consulta.set({ ra, email, periodo: this.periodo() });
    this.irParaPagina(1);
  }

  protected irParaPagina(pagina: number): void {
    this.router.navigate([], { queryParams: { pagina: pagina > 1 ? pagina : null } });
  }

  protected esquecer(): void {
    this.consulta.set(null);
    this.ra.set('');
    this.email.set('');
    this.irParaPagina(1);
  }

  protected podeCancelarPeloEmail(reserva: ReservaDoAluno): boolean {
    return reserva.status === 'RESERVADO' && reserva.data_reserva >= paraIso(new Date());
  }

  private buscar(consulta: Consulta, pagina: number): Observable<Resultado> {
    return this.api
      .consultarMinhasReservas(consulta.ra, consulta.email, consulta.periodo, pagina)
      .pipe(
        map((resposta): Resultado => ({ tipo: 'pronto', pagina: resposta })),
        catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
        startWith<Resultado>({ tipo: 'carregando' }),
      );
  }
}
