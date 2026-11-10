import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { ambiente } from '../../ambientes/ambiente';
import { converterErroApi } from './erro-api';
import { SessaoService } from './sessao.service';

export const interceptorApi: HttpInterceptorFn = (requisicao, proximo) => {
  if (!requisicao.url.startsWith(`${ambiente.urlApi}/`)) {
    return proximo(requisicao);
  }

  const sessao = inject(SessaoService);
  const token = sessao.tokenValido();

  const cabecalhos: Record<string, string> = { Accept: 'application/json' };
  if (token) {
    cabecalhos['Authorization'] = `Bearer ${token}`;
  }

  return proximo(requisicao.clone({ setHeaders: cabecalhos })).pipe(
    catchError((erro: unknown) => {
      if (!(erro instanceof HttpErrorResponse)) {
        return throwError(() => erro);
      }
      const sessaoEncerrada = erro.status === 401 && token !== null;
      if (sessaoEncerrada) {
        sessao.encerrar();
      }
      return throwError(() => converterErroApi(erro, sessaoEncerrada));
    }),
  );
};
