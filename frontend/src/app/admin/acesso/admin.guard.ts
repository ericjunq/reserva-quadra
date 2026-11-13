import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { SessaoService } from '../../nucleo/sessao.service';

export const exigirLogin: CanActivateFn = (_rota, estado) =>
  inject(SessaoService).tokenValido()
    ? true
    : inject(Router).createUrlTree(['/admin/entrar'], { queryParams: { voltar: estado.url } });

export const somenteSemLogin: CanActivateFn = () =>
  inject(SessaoService).tokenValido() ? inject(Router).createUrlTree(['/admin']) : true;
