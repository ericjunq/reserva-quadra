import { Routes } from '@angular/router';
import { exigirLogin, somenteSemLogin } from './acesso/admin.guard';
import { LayoutAcesso } from './acesso/layout-acesso';
import { LayoutAdmin } from './layout-admin/layout-admin';

export const rotasAdmin: Routes = [
  {
    path: 'entrar',
    component: LayoutAcesso,
    canActivate: [somenteSemLogin],
    children: [
      {
        path: '',
        title: 'Entrar · Administração',
        loadComponent: () => import('./acesso/entrar').then((m) => m.Entrar),
      },
    ],
  },
  {
    path: 'esqueci-senha',
    component: LayoutAcesso,
    children: [
      {
        path: '',
        title: 'Esqueci a senha · Administração',
        loadComponent: () => import('./acesso/esqueci-senha').then((m) => m.EsqueciSenha),
      },
    ],
  },
  {
    path: '',
    component: LayoutAdmin,
    canActivate: [exigirLogin],
    children: [
      {
        path: '',
        title: 'Painel · Administração',
        loadComponent: () => import('./painel/painel').then((m) => m.Painel),
      },
      {
        path: 'minha-conta',
        title: 'Minha conta · Administração',
        loadComponent: () => import('./acesso/minha-conta').then((m) => m.MinhaConta),
      },
    ],
  },
];
