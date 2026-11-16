import { Component, computed, inject, input, model } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { catchError, of, switchMap } from 'rxjs';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../../publico/api-publica.service';
import { ehDataIso, ehHora } from '../../publico/datas';
import { Espaco, Horario } from '../../publico/modelos';

export const HORARIOS_FIXOS: Record<Horario, [string, string]> = {
  PRIMEIRO_HORARIO: ['19:00', '20:30'],
  SEGUNDO_HORARIO: ['20:30', '22:00'],
};

export interface HorarioDoUso {
  horario?: Horario;
  hora_inicio?: string;
  hora_fim?: string;
  intervalo: [string, string];
}

export function horarioDoUso(
  espaco: Espaco | null,
  horario: Horario | '',
  inicio: string,
  fim: string,
): HorarioDoUso | null {
  if (!espaco) {
    return null;
  }
  if (espaco.modo_horario !== 'LIVRE') {
    return horario ? { horario, intervalo: HORARIOS_FIXOS[horario] } : null;
  }
  return ehHora(inicio) && ehHora(fim) && inicio < fim
    ? { hora_inicio: inicio, hora_fim: fim, intervalo: [inicio, fim] }
    : null;
}

export function horarioDaUrl(valor: string | undefined): Horario | '' {
  return valor === 'PRIMEIRO_HORARIO' || valor === 'SEGUNDO_HORARIO' ? valor : '';
}

export function equipamentosEscolhidos(
  quantidades: Record<number, number>,
): { id: number; quantidade: number }[] {
  return Object.entries(quantidades)
    .filter(([, quantidade]) => quantidade > 0)
    .map(([id, quantidade]) => ({ id: Number(id), quantidade }));
}

@Component({
  selector: 'app-campos-do-uso',
  imports: [FormsModule],
  template: `
    <div>
      <label for="uso-espaco" class="block font-semibold text-slate-900">Espaço</label>
      <select
        id="uso-espaco"
        name="espaco"
        class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
        [value]="espacoId() ?? ''"
        (change)="trocarEspaco($any($event.target).value)"
      >
        <option value="">Escolha o espaço</option>
        @for (espaco of espacos(); track espaco.id) {
          <option [value]="espaco.id" [selected]="espaco.id === espacoId()">
            {{ espaco.nome }}
          </option>
        }
      </select>
      @if (erroDoCampo('espaco_id'); as mensagem) {
        <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
      }
    </div>

    <div>
      <label for="uso-data" class="block font-semibold text-slate-900">Data</label>
      <input
        id="uso-data"
        name="data"
        type="date"
        [min]="dataMinima()"
        [max]="dataMaxima()"
        class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
        [ngModel]="data()"
        (ngModelChange)="data.set($event)"
      />
      @if (erroDoCampo('data_reserva'); as mensagem) {
        <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
      }
    </div>

    @if (espacoEscolhido(); as espaco) {
      @if (espaco.modo_horario === 'LIVRE') {
        <div class="flex flex-wrap gap-4">
          <div>
            <label for="uso-inicio" class="block font-semibold text-slate-900">Início</label>
            <input
              id="uso-inicio"
              name="inicio"
              type="time"
              step="900"
              class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="inicio()"
              (ngModelChange)="inicio.set($event)"
            />
          </div>
          <div>
            <label for="uso-fim" class="block font-semibold text-slate-900">Fim</label>
            <input
              id="uso-fim"
              name="fim"
              type="time"
              step="900"
              class="mt-1 rounded-lg border border-slate-300 px-3 py-2"
              [ngModel]="fim()"
              (ngModelChange)="fim.set($event)"
            />
          </div>
        </div>
        <p class="text-sm text-slate-600">
          Janela do espaço: {{ espaco.abre_as }} às {{ espaco.fecha_as }}. Os horários são
          arredondados a cada 15 minutos.
        </p>
        @if (erroDoCampo('hora_inicio', 'hora_fim'); as mensagem) {
          <p class="text-sm text-red-700">{{ mensagem }}</p>
        }
      } @else {
        <fieldset>
          <legend class="font-semibold text-slate-900">Horário</legend>
          <div class="mt-2 flex flex-wrap gap-4">
            @for (opcao of opcoesFixas; track opcao) {
              <label class="flex items-center gap-2">
                <input
                  type="radio"
                  name="horario"
                  [value]="opcao"
                  [ngModel]="horario()"
                  (ngModelChange)="horario.set($event)"
                />
                {{ horariosFixos[opcao][0] }}–{{ horariosFixos[opcao][1] }}
              </label>
            }
          </div>
          @if (erroDoCampo('horario'); as mensagem) {
            <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
          }
        </fieldset>
      }
    }

    @if (equipamentos().length) {
      <fieldset>
        <legend class="font-semibold text-slate-900">Equipamentos (opcional)</legend>
        <ul class="mt-2 space-y-2">
          @for (equipamento of equipamentos(); track equipamento.id) {
            <li class="flex flex-wrap items-center gap-3">
              <label [for]="'equipamento-' + equipamento.id" class="min-w-40 text-slate-800">
                {{ equipamento.nome }}
                <span class="text-sm text-slate-600">({{ equipamento.disponivel }} livres)</span>
              </label>
              <input
                [id]="'equipamento-' + equipamento.id"
                [name]="'equipamento-' + equipamento.id"
                type="number"
                min="0"
                [max]="equipamento.disponivel"
                class="w-24 rounded-lg border border-slate-300 px-3 py-2"
                [ngModel]="quantidades()[equipamento.id] ?? 0"
                (ngModelChange)="mudarQuantidade(equipamento.id, +$event)"
              />
            </li>
          }
        </ul>
        @if (erroDoCampo('equipamentos'); as mensagem) {
          <p class="mt-1 text-sm text-red-700">{{ mensagem }}</p>
        }
      </fieldset>
    }
  `,
})
export class CamposDoUso {
  private readonly publica = inject(ApiPublicaService);

  readonly espacos = input<Espaco[]>([]);
  readonly dataMinima = input<string>('');
  readonly dataMaxima = input<string>('');
  readonly erro = input<ErroApi | null>(null);

  readonly espacoId = model<number | null>(null);
  readonly data = model('');
  readonly horario = model<Horario | ''>('');
  readonly inicio = model('');
  readonly fim = model('');
  readonly quantidades = model<Record<number, number>>({});

  protected readonly horariosFixos = HORARIOS_FIXOS;
  protected readonly opcoesFixas: Horario[] = ['PRIMEIRO_HORARIO', 'SEGUNDO_HORARIO'];

  protected readonly espacoEscolhido = computed(
    () => this.espacos().find((espaco) => espaco.id === this.espacoId()) ?? null,
  );

  private readonly consultaDeEquipamentos = computed(() => {
    const espacoId = this.espacoId();
    const data = this.data();
    const escolhido = horarioDoUso(
      this.espacoEscolhido(),
      this.horario(),
      this.inicio(),
      this.fim(),
    );
    return espacoId && ehDataIso(data) && escolhido
      ? { espacoId, data, intervalo: escolhido.intervalo }
      : null;
  });

  protected readonly equipamentos = toSignal(
    toObservable(this.consultaDeEquipamentos).pipe(
      switchMap((consulta) =>
        consulta
          ? this.publica
              .consultarEquipamentos(
                consulta.espacoId,
                consulta.data,
                consulta.intervalo[0],
                consulta.intervalo[1],
              )
              .pipe(catchError(() => of([])))
          : of([]),
      ),
    ),
    { initialValue: [] },
  );

  protected trocarEspaco(id: string): void {
    this.espacoId.set(Number(id) || null);
    this.horario.set('');
    this.inicio.set('');
    this.fim.set('');
    this.quantidades.set({});
  }

  protected mudarQuantidade(id: number, valor: number): void {
    this.quantidades.update((atuais) => ({ ...atuais, [id]: Math.max(0, Math.floor(valor) || 0) }));
  }

  protected erroDoCampo(...campos: string[]): string | null {
    const lista = this.erro()?.campos ?? {};
    return campos.map((campo) => lista[campo]?.[0]).find(Boolean) ?? null;
  }
}
