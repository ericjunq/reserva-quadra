import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, finalize, map, shareReplay, tap } from 'rxjs';
import { ambiente } from '../../../ambientes/ambiente';
import { SessaoService } from '../../nucleo/sessao.service';

export interface Admin {
  id: number;
  nome: string;
  email: string;
  principal: boolean;
}

export interface NovaSenha {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

interface RespostaLogin {
  token: string;
  expires_at: string;
  user: Admin;
}

interface Mensagem {
  message: string;
}

@Injectable({ providedIn: 'root' })
export class AcessoService {
  private readonly http = inject(HttpClient);
  private readonly sessao = inject(SessaoService);
  private readonly url = ambiente.urlApi;
  private eu$: Observable<Admin> | null = null;

  entrar(email: string, senha: string): Observable<Admin> {
    return this.http.post<RespostaLogin>(`${this.url}/login`, { email, password: senha }).pipe(
      tap((resposta) => {
        this.eu$ = null;
        this.sessao.iniciar(resposta.token, resposta.expires_at);
      }),
      map((resposta) => resposta.user),
    );
  }

  eu(): Observable<Admin> {
    this.eu$ ??= this.http.get<{ data: Admin }>(`${this.url}/me`).pipe(
      map((resposta) => resposta.data),
      shareReplay(1),
    );
    return this.eu$;
  }

  sair(): Observable<void> {
    return this.http.post<unknown>(`${this.url}/logout`, {}).pipe(
      map(() => undefined),
      finalize(() => this.esquecerSessao()),
    );
  }

  sairDeTodos(): Observable<void> {
    return this.http.post<unknown>(`${this.url}/logout-geral`, {}).pipe(
      map(() => undefined),
      finalize(() => this.esquecerSessao()),
    );
  }

  esqueciASenha(email: string): Observable<string> {
    return this.http
      .post<Mensagem>(`${this.url}/esqueci-senha`, { email })
      .pipe(map((resposta) => resposta.message));
  }

  redefinirSenha(dados: NovaSenha): Observable<string> {
    return this.http
      .post<Mensagem>(`${this.url}/redefinir-senha`, dados)
      .pipe(map((resposta) => resposta.message));
  }

  aceitarConvite(dados: NovaSenha): Observable<string> {
    return this.http
      .post<Mensagem>(`${this.url}/convites/aceitar`, dados)
      .pipe(map((resposta) => resposta.message));
  }

  trocarSenha(senhaAtual: string, nova: string, confirmacao: string): Observable<string> {
    return this.http
      .patch<Mensagem>(`${this.url}/me/senha`, {
        senha_atual: senhaAtual,
        password: nova,
        password_confirmation: confirmacao,
      })
      .pipe(map((resposta) => resposta.message));
  }

  private esquecerSessao(): void {
    this.eu$ = null;
    this.sessao.encerrar();
  }
}
