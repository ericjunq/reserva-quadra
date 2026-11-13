import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: 'admin',
    loadChildren: () => import('./admin/admin.routes').then((m) => m.rotasAdmin),
  },
  {
    path: 'redefinir-senha',
    loadChildren: () => import('./admin/acesso/acesso.routes').then((m) => m.rotasDeRedefinicao),
  },
  {
    path: 'aceitar-convite',
    loadChildren: () => import('./admin/acesso/acesso.routes').then((m) => m.rotasDeConvite),
  },
  {
    path: '',
    loadChildren: () => import('./publico/publico.routes').then((m) => m.rotasPublicas),
  },
  {
    path: '**',
    title: 'Página não encontrada',
    loadComponent: () =>
      import('./compartilhado/pagina-nao-encontrada/pagina-nao-encontrada').then(
        (m) => m.PaginaNaoEncontrada,
      ),
  },
];
