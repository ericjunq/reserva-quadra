import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { paraIso } from '../../../publico/datas';
import { CalendarioAdminService } from '../../calendario/calendario-admin.service';
import { CadastrosService } from '../cadastros.service';
import { DadosDoBloqueio } from '../modelos';

@Component({
  selector: 'app-form-bloqueio',
  imports: [FormsModule, RouterLink, AvisoErro],
  template: `
    <a
      routerLink="/admin/cadastros/datas-bloqueadas"
      class="text-sm font-semibold text-azul underline"
      >Datas bloqueadas</a
    >
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">Novo bloqueio</h1>
    <p class="mt-1 text-slate-600">
      Reservas de alunos que caírem no período são revogadas e os alunos recebem email com o motivo.
    </p>

    @if (resultado(); as resultado) {
      <section class="mt-6 max-w-2xl rounded-2xl bg-emerald-50 p-6 text-emerald-900" role="status">
        <p class="font-semibold">Bloqueio criado.</p>
        <p>
          {{
            resultado.revogadas === 0
              ? 'Nenhuma reserva precisou ser revogada.'
              : resultado.revogadas + ' reserva(s) revogada(s), com email aos alunos.'
          }}
        </p>
        <a
          routerLink="/admin/cadastros/datas-bloqueadas"
          class="mt-2 inline-block font-semibold underline"
          >Voltar às datas bloqueadas</a
        >
      </section>
    } @else {
      <form
        class="mt-6 max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
        (ngSubmit)="criar()"
        novalidate
      >
        <app-aviso-erro [mensagem]="erro()?.mensagem ?? null" />
        <div class="flex flex-wrap gap-4">
          <div>
            <label for="bloqueio-de" class="block font-semibold text-slate-900">De</label>
            <input
              id="bloqueio-de"
              name="de"
              type="date"
              [min]="hoje"
              class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="de()"
              (ngModelChange)="de.set($event)"
            />
          </div>
          <div>
            <label for="bloqueio-ate" class="block font-semibold text-slate-900">Até</label>
            <input
              id="bloqueio-ate"
              name="ate"
              type="date"
              [min]="de() || hoje"
              class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="ate()"
              (ngModelChange)="ate.set($event)"
            />
          </div>
        </div>
        @if (erroDoCampo('data_inicio', 'data_fim'); as texto) {
          <p class="text-sm text-red-700">{{ texto }}</p>
        }
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            name="parte"
            [ngModel]="parteDoDia()"
            (ngModelChange)="parteDoDia.set($event)"
          />
          Só uma parte do dia
        </label>
        @if (parteDoDia()) {
          <div class="flex flex-wrap gap-4">
            <div>
              <label for="bloqueio-inicio" class="block font-semibold text-slate-900">Das</label>
              <input
                id="bloqueio-inicio"
                name="inicio"
                type="time"
                class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
                [ngModel]="inicio()"
                (ngModelChange)="inicio.set($event)"
              />
            </div>
            <div>
              <label for="bloqueio-fim" class="block font-semibold text-slate-900">Às</label>
              <input
                id="bloqueio-fim"
                name="fim"
                type="time"
                class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
                [ngModel]="fim()"
                (ngModelChange)="fim.set($event)"
              />
            </div>
          </div>
          @if (erroDoCampo('hora_inicio', 'hora_fim'); as texto) {
            <p class="text-sm text-red-700">{{ texto }}</p>
          }
        }
        <div>
          <label for="bloqueio-espaco" class="block font-semibold text-slate-900">Espaço</label>
          <select
            id="bloqueio-espaco"
            name="espaco"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            [value]="espacoId() ?? ''"
            (change)="espacoId.set(+$any($event.target).value || null)"
          >
            <option value="">Todos os espaços</option>
            @for (espaco of espacos(); track espaco.id) {
              <option [value]="espaco.id">{{ espaco.nome }}</option>
            }
          </select>
        </div>
        <div>
          <label for="bloqueio-motivo" class="block font-semibold text-slate-900">Motivo</label>
          <input
            id="bloqueio-motivo"
            name="motivo"
            maxlength="255"
            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
            aria-describedby="ajuda-motivo"
            [ngModel]="motivo()"
            (ngModelChange)="motivo.set($event)"
          />
          <p id="ajuda-motivo" class="mt-1 text-sm text-slate-600">
            Aparece no calendário e no email aos alunos (ex.: "Recesso acadêmico").
          </p>
          @if (erroDoCampo('motivo'); as texto) {
            <p class="mt-1 text-sm text-red-700">{{ texto }}</p>
          }
        </div>
        <button
          type="submit"
          class="rounded-full bg-azul px-6 py-2 font-semibold text-white hover:bg-azul-medio disabled:opacity-50"
          [disabled]="enviando()"
        >
          {{ enviando() ? 'Criando…' : 'Criar bloqueio' }}
        </button>
      </form>
    }
  `,
})
export class FormBloqueio {
  private readonly api = inject(CadastrosService);
  private readonly calendario = inject(CalendarioAdminService);

  protected readonly hoje = paraIso(new Date());
  protected readonly espacos = toSignal(
    this.calendario.listarEspacos().pipe(catchError(() => of([]))),
    { initialValue: [] },
  );

  protected readonly de = signal(this.hoje);
  protected readonly ate = signal(this.hoje);
  protected readonly parteDoDia = signal(false);
  protected readonly inicio = signal('');
  protected readonly fim = signal('');
  protected readonly espacoId = signal<number | null>(null);
  protected readonly motivo = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly resultado = signal<{ revogadas: number } | null>(null);

  protected erroDoCampo(...campos: string[]): string | null {
    const lista = this.erro()?.campos ?? {};
    return campos.map((campo) => lista[campo]?.[0]).find(Boolean) ?? null;
  }

  protected criar(): void {
    if (this.enviando()) {
      return;
    }
    const espacoId = this.espacoId();
    const dados: DadosDoBloqueio = {
      data_inicio: this.de(),
      data_fim: this.ate(),
      motivo: this.motivo().trim(),
      ...(this.parteDoDia() ? { hora_inicio: this.inicio(), hora_fim: this.fim() } : {}),
      ...(espacoId ? { espaco_id: espacoId } : {}),
    };
    this.enviando.set(true);
    this.erro.set(null);
    this.api.criarBloqueio(dados).subscribe({
      next: ({ revogadas }) => {
        this.enviando.set(false);
        this.resultado.set({ revogadas });
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
