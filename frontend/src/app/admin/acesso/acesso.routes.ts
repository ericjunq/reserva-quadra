import { Routes } from '@angular/router';
import { LayoutAcesso } from './layout-acesso';
import { ModoDaSenha } from './definir-senha';

function rotasDoLink(modo: ModoDaSenha, titulo: string): Routes {
  const pagina = {
    title: `${titulo} · Administração`,
    data: { modo },
    loadComponent: () => import('./definir-senha').then((m) => m.DefinirSenha),
  };
  return [
    {
      path: '',
      component: LayoutAcesso,
      children: [
        { path: ':token', ...pagina },
        { path: '', ...pagina },
      ],
    },
  ];
}

export const rotasDeRedefinicao = rotasDoLink('redefinir', 'Criar senha nova');
export const rotasDeConvite = rotasDoLink('convite', 'Aceitar convite');
