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
      {
        path: 'espacos/:id',
        title: 'Horários · Sistema de Reservas',
        loadComponent: () => import('./calendario/calendario').then((m) => m.Calendario),
      },
      {
        path: 'espacos/:id/reservar',
        title: 'Reservar · Sistema de Reservas',
        loadComponent: () => import('./reservar/reservar').then((m) => m.Reservar),
      },
      {
        path: 'cadastro',
        title: 'Cadastro · Sistema de Reservas',
        loadComponent: () => import('./cadastro/cadastro').then((m) => m.Cadastro),
      },
      {
        path: 'minhas-reservas',
        title: 'Minhas reservas · Sistema de Reservas',
        loadComponent: () =>
          import('./minhas-reservas/minhas-reservas').then((m) => m.MinhasReservas),
      },
      ...(['cancelar', 'confirmar'] as const).flatMap((modo) =>
        [`reservas/${modo}/:token`, `reservas/${modo}`].map((path) => ({
          path,
          title: `${modo === 'cancelar' ? 'Cancelar' : 'Confirmar'} reserva · Sistema de Reservas`,
          data: { modo },
          loadComponent: () => import('./link-reserva/link-reserva').then((m) => m.LinkReserva),
        })),
      ),
      {
        path: 'privacidade',
        title: 'Termos e privacidade · Sistema de Reservas',
        loadComponent: () => import('./privacidade/privacidade').then((m) => m.Privacidade),
      },
    ],
  },
];
