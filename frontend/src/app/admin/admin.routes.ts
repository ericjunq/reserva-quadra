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
        path: 'reservas/nova',
        title: 'Nova reserva · Administração',
        loadComponent: () =>
          import('./reservas/nova-reserva/nova-reserva').then((m) => m.NovaReserva),
      },
      {
        path: 'reservas/registrar',
        title: 'Registrar uso · Administração',
        loadComponent: () =>
          import('./reservas/registrar-uso/registrar-uso').then((m) => m.RegistrarUso),
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
        redirectTo: 'reservas/nova',
      },
      {
        path: 'cadastros',
        title: 'Cadastros · Administração',
        loadComponent: () => import('./cadastros/cadastros').then((m) => m.Cadastros),
      },
      {
        path: 'cadastros/espacos',
        title: 'Espaços · Administração',
        loadComponent: () =>
          import('./cadastros/espacos/lista-espacos').then((m) => m.ListaEspacos),
      },
      ...['cadastros/espacos/novo', 'cadastros/espacos/:id'].map((path) => ({
        path,
        title: 'Espaço · Administração',
        loadComponent: () => import('./cadastros/espacos/form-espaco').then((m) => m.FormEspaco),
      })),
      {
        path: 'cadastros/equipamentos',
        title: 'Equipamentos · Administração',
        loadComponent: () =>
          import('./cadastros/equipamentos/lista-equipamentos').then((m) => m.ListaEquipamentos),
      },
      ...['cadastros/equipamentos/novo', 'cadastros/equipamentos/:id'].map((path) => ({
        path,
        title: 'Equipamento · Administração',
        loadComponent: () =>
          import('./cadastros/equipamentos/form-equipamento').then((m) => m.FormEquipamento),
      })),
      {
        path: 'cadastros/cursos',
        title: 'Cursos · Administração',
        loadComponent: () => import('./cadastros/cursos/cursos').then((m) => m.Cursos),
      },
      {
        path: 'cadastros/datas-bloqueadas',
        title: 'Datas bloqueadas · Administração',
        loadComponent: () =>
          import('./cadastros/datas-bloqueadas/datas-bloqueadas').then((m) => m.DatasBloqueadas),
      },
      {
        path: 'cadastros/datas-bloqueadas/novo',
        title: 'Novo bloqueio · Administração',
        loadComponent: () =>
          import('./cadastros/datas-bloqueadas/form-bloqueio').then((m) => m.FormBloqueio),
      },
      {
        path: 'cadastros/alunos',
        title: 'Alunos · Administração',
        loadComponent: () => import('./cadastros/alunos/lista-alunos').then((m) => m.ListaAlunos),
      },
      {
        path: 'cadastros/alunos/:id',
        title: 'Aluno · Administração',
        loadComponent: () => import('./cadastros/alunos/detalhe-aluno').then((m) => m.DetalheAluno),
      },
      {
        path: 'minha-conta',
        title: 'Minha conta · Administração',
        loadComponent: () => import('./acesso/minha-conta').then((m) => m.MinhaConta),
      },
    ],
  },
];
