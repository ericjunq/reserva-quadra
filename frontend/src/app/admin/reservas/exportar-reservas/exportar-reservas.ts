import { Component, computed, inject, input, signal } from '@angular/core';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { MAXIMO_DIAS_EXPORTACAO, diasDoPeriodo } from '../filtros';
import { ColunasExportacao, FiltrosReservas } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';

@Component({
  selector: 'app-exportar-reservas',
  imports: [AvisoErro],
  template: `
    <details
      class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200"
      (toggle)="abrir($any($event.target).open)"
    >
      <summary class="cursor-pointer font-semibold text-azul">Exportar em CSV</summary>
      <div class="mt-3 space-y-3">
        <p class="text-sm text-slate-700">
          O arquivo usa os filtros atuais e traz dados pessoais dos alunos. Guarde-o com cuidado e
          apague quando não precisar mais.
        </p>
        @if (colunas(); as colunas) {
          <fieldset>
            <legend class="text-sm font-semibold text-slate-900">Colunas a mais</legend>
            <p class="text-xs text-slate-600">Sempre vêm: {{ nomesObrigatorios(colunas) }}.</p>
            <div class="mt-2 grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
              @for (coluna of colunas.opcionais; track coluna.chave) {
                <label class="flex items-center gap-2 text-sm text-slate-800">
                  <input
                    type="checkbox"
                    [checked]="escolhidas().has(coluna.chave)"
                    (change)="alternar(coluna.chave)"
                  />
                  {{ coluna.titulo }}
                </label>
              }
            </div>
          </fieldset>
        }
        <app-aviso-erro [mensagem]="erro()" />
        <button
          type="button"
          class="rounded-full bg-azul px-5 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
          [disabled]="baixando()"
          (click)="baixar()"
        >
          {{ baixando() ? 'Gerando o arquivo…' : 'Baixar CSV' }}
        </button>
      </div>
    </details>
  `,
})
export class ExportarReservas {
  private readonly api = inject(ReservasAdminService);

  readonly filtros = input.required<FiltrosReservas>();

  protected readonly colunas = signal<ColunasExportacao | null>(null);
  protected readonly escolhidas = signal<Set<string>>(new Set());
  protected readonly baixando = signal(false);
  protected readonly erroDaApi = signal<string | null>(null);

  protected readonly erro = computed(() => {
    const dias = diasDoPeriodo(this.filtros());
    if (dias !== null && dias > MAXIMO_DIAS_EXPORTACAO) {
      return `Escolha um período de até ${MAXIMO_DIAS_EXPORTACAO} dias para exportar.`;
    }
    return this.erroDaApi();
  });

  protected abrir(aberto: boolean): void {
    if (!aberto || this.colunas()) {
      return;
    }
    this.api.colunasDaExportacao().subscribe({
      next: (colunas) => this.colunas.set(colunas),
      error: (erro: ErroApi) => this.erroDaApi.set(erro.mensagem),
    });
  }

  protected nomesObrigatorios(colunas: ColunasExportacao): string {
    return colunas.obrigatorias.map((coluna) => coluna.titulo).join(', ');
  }

  protected alternar(chave: string): void {
    this.escolhidas.update((atuais) => {
      const novas = new Set(atuais);
      if (!novas.delete(chave)) {
        novas.add(chave);
      }
      return novas;
    });
  }

  protected baixar(): void {
    const dias = diasDoPeriodo(this.filtros());
    if (dias !== null && dias > MAXIMO_DIAS_EXPORTACAO) {
      return;
    }
    this.baixando.set(true);
    this.erroDaApi.set(null);
    this.api.exportar(this.filtros(), [...this.escolhidas()]).subscribe({
      next: (arquivo) => {
        const endereco = URL.createObjectURL(arquivo.conteudo);
        const link = document.createElement('a');
        link.href = endereco;
        link.download = arquivo.nome;
        link.click();
        URL.revokeObjectURL(endereco);
        this.baixando.set(false);
      },
      error: (erro: ErroApi) => {
        this.erroDaApi.set(erro.mensagem);
        this.baixando.set(false);
      },
    });
  }
}
