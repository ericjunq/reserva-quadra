import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

const CADASTROS = [
  {
    caminho: 'espacos',
    nome: 'Espaços',
    texto: 'Quadra, laboratórios e salas: horário, janela e limite por curso.',
  },
  {
    caminho: 'equipamentos',
    nome: 'Equipamentos',
    texto: 'Itens de cada espaço, compartilhamento e pedidos de outros espaços.',
  },
  { caminho: 'cursos', nome: 'Cursos', texto: 'Cursos que aparecem no cadastro dos alunos.' },
  {
    caminho: 'datas-bloqueadas',
    nome: 'Datas bloqueadas',
    texto: 'Feriados, recessos e manutenções: dias ou horários sem reservas.',
  },
  {
    caminho: 'alunos',
    nome: 'Alunos',
    texto: 'Busca por nome ou RA, edição, desativação e anonimização (LGPD).',
  },
];

@Component({
  selector: 'app-cadastros',
  imports: [RouterLink],
  template: `
    <h1 class="text-2xl font-semibold text-slate-900">Cadastros</h1>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @for (cadastro of cadastros; track cadastro.caminho) {
        <li>
          <a
            [routerLink]="cadastro.caminho"
            class="block h-full rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 hover:ring-azul"
          >
            <span class="block text-lg font-semibold text-azul">{{ cadastro.nome }}</span>
            <span class="text-slate-700">{{ cadastro.texto }}</span>
          </a>
        </li>
      }
    </ul>
    <p class="mt-6 text-slate-600">O cadastro de admins chega no próximo passo.</p>
  `,
})
export class Cadastros {
  protected readonly cadastros = CADASTROS;
}
