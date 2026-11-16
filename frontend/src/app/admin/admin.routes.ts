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
        path: 'reservas',
        title: 'Reservas · Administração',
        loadComponent: () =>
          import('./reservas/lista-reservas/lista-reservas').then((m) => m.ListaReservas),
      },
      {
        path: 'reservas/:id',
        title: 'Reserva · Administração',
        loadComponent: () =>
          import('./reservas/detalhe-reserva/detalhe-reserva').then((m) => m.DetalheReserva),
      },
      {
        path: 'calendario',
        title: 'Calendário · Administração',
        loadComponent: () => import('./calendario/calendario-admin').then((m) => m.CalendarioAdmin),
      },
      {
        path: 'eventos/novo',
        title: 'Novo evento · Administração',
        loadComponent: () => import('./eventos/novo-evento').then((m) => m.NovoEvento),
      },
      {
        path: 'minha-conta',
        title: 'Minha conta · Administração',
        loadComponent: () => import('./acesso/minha-conta').then((m) => m.MinhaConta),
      },
    ],
  },
];
