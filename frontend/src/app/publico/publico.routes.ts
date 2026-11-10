import { Routes } from '@angular/router';
import { LayoutPublico } from './layout-publico/layout-publico';

export const rotasPublicas: Routes = [
  {
    path: '',
    component: LayoutPublico,
    children: [
      {
        path: '',
        title: 'Sistema de Reservas',
        loadComponent: () => import('./inicio/inicio').then((m) => m.Inicio),
      },
    ],
  },
];
