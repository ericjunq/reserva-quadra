import { Location } from '@angular/common';
import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Observable, catchError, map, of, startWith, switchMap, take } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { formatarDataHora } from '../datas';
import { AcessoDoParceiro, ResumoDoAceiteApi } from '../modelos';
import { SecoesTermos } from '../secoes-termos/secoes-termos';

type Estado =
  | { tipo: 'carregando' }
  | { tipo: 'sem-token' }
  | { tipo: 'pronto'; resumo: ResumoDoAceiteApi }
  | { tipo: 'erro'; mensagem: string };

const FORMATO_DATA = new Intl.DateTimeFormat('pt-BR', {
  timeZone: 'America/Sao_Paulo',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
});

@Component({
  selector: 'app-aceite-parceiro',
  imports: [FormsModule, AvisoErro, SecoesTermos],
  templateUrl: './aceite-parceiro.html',
})
export class AceiteParceiro {
  private readonly api = inject(ApiPublicaService);
  private readonly location = inject(Location);

  readonly token = input<string>();

  protected readonly aceito = signal(false);
  protected readonly enviando = signal(false);
  protected readonly erroDoEnvio = signal<ErroApi | null>(null);
  protected readonly acesso = signal<AcessoDoParceiro | null>(null);
  protected readonly copiado = signal(false);
  private tokenLido: string | null = null;

  protected readonly estado = toSignal(
    toObservable(this.token).pipe(
      take(1),
      switchMap((token) => this.carregar(token)),
    ),
    { initialValue: { tipo: 'carregando' } as Estado },
  );

  protected readonly resumo = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.resumo : null;
  });

  protected readonly linkValeAte = computed(() => {
    const resumo = this.resumo();
    return resumo ? formatarDataHora(resumo.link_expira_em) : null;
  });

  protected readonly acessoValeAte = computed(() => {
    const acesso = this.acesso();
    return acesso ? FORMATO_DATA.format(new Date(acesso.expira_em)) : null;
  });

  protected aceitar(): void {
    if (!this.tokenLido || this.enviando() || !this.aceito()) {
      return;
    }
    this.enviando.set(true);
    this.erroDoEnvio.set(null);
    this.api.aceitarTermosApi(this.tokenLido).subscribe({
      next: (acesso) => {
        this.enviando.set(false);
        this.tokenLido = null;
        this.acesso.set(acesso);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erroDoEnvio.set(erro);
      },
    });
  }

  protected copiar(): void {
    const acesso = this.acesso();
    if (!acesso || !navigator.clipboard) {
      return;
    }
    navigator.clipboard.writeText(acesso.token).then(
      () => this.copiado.set(true),
      () => this.copiado.set(false),
    );
  }

  private carregar(token: string | undefined): Observable<Estado> {
    if (!token) {
      return of<Estado>({ tipo: 'sem-token' });
    }
    this.tokenLido = token;
    this.location.replaceState('/parceiros/aceite');
    return this.api.resumoDoAceiteApi(token).pipe(
      map((resumo): Estado => ({ tipo: 'pronto', resumo })),
      catchError((erro: ErroApi) => of<Estado>({ tipo: 'erro', mensagem: erro.mensagem })),
      startWith<Estado>({ tipo: 'carregando' }),
    );
  }
}
