import { Routes } from '@angular/router';
import { LayoutAdmin } from './layout-admin/layout-admin';

export const rotasAdmin: Routes = [
  {
    path: '',
    component: LayoutAdmin,
    children: [
      {
        path: '',
        title: 'Painel · Administração',
        loadComponent: () => import('./painel/painel').then((m) => m.Painel),
      },
    ],
  },
];
