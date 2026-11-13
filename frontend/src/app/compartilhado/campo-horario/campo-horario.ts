import {
  Component,
  ElementRef,
  afterNextRender,
  inject,
  input,
  model,
  output,
  signal,
  Injector,
} from '@angular/core';
import { LucideChevronDown } from '@lucide/angular';

@Component({
  selector: 'app-campo-horario',
  imports: [LucideChevronDown],
  template: `
    <div class="relative mt-1 inline-flex" (focusout)="aoSair($event)">
      <input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="none"
        class="w-28 rounded-l-lg border border-slate-300 bg-white px-3 py-2"
        [id]="idCampo()"
        [placeholder]="exemplo()"
        [value]="valor()"
        [attr.aria-describedby]="descricao()"
        [attr.aria-expanded]="aberto()"
        [attr.aria-controls]="idCampo() + '-lista'"
        [attr.aria-activedescendant]="aberto() && ativo() >= 0 ? idOpcao(ativo()) : null"
        (input)="digitar($event)"
        (keydown)="teclar($event)"
      />
      <button
        type="button"
        tabindex="-1"
        class="rounded-r-lg border border-l-0 border-slate-300 bg-white px-2 text-azul hover:bg-slate-50"
        [attr.aria-label]="'Mostrar os horários de ' + rotulo()"
        (mousedown)="$event.preventDefault()"
        (click)="alternar()"
      >
        <svg lucideChevronDown [size]="18" aria-hidden="true"></svg>
      </button>
      @if (aberto()) {
        <ul
          role="listbox"
          class="absolute top-full left-0 z-20 mt-1 max-h-60 w-full min-w-32 overflow-y-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-slate-200"
          [id]="idCampo() + '-lista'"
          [attr.aria-label]="rotulo()"
        >
          @for (opcao of opcoes(); track opcao; let indice = $index) {
            <li
              role="option"
              class="cursor-pointer px-3 py-2"
              [id]="idOpcao(indice)"
              [class]="
                indice === ativo()
                  ? 'bg-amarelo/30 font-semibold text-azul'
                  : 'text-slate-800 hover:bg-slate-100'
              "
              [attr.aria-selected]="opcao === valor()"
              (mousedown)="$event.preventDefault()"
              (click)="escolher(opcao)"
            >
              {{ opcao }}
            </li>
          } @empty {
            <li class="px-3 py-2 text-sm text-slate-600">{{ semOpcoes() }}</li>
          }
        </ul>
      }
    </div>
  `,
})
export class CampoHorario {
  private readonly elemento: ElementRef<HTMLElement> = inject(ElementRef);
  private readonly injector = inject(Injector);

  readonly idCampo = input.required<string>();
  readonly rotulo = input.required<string>();
  readonly opcoes = input<string[]>([]);
  readonly exemplo = input('');
  readonly descricao = input<string | null>(null);
  readonly semOpcoes = input('Nenhum horário disponível.');
  readonly valor = model('');
  readonly escolhido = output<string>();

  protected readonly aberto = signal(false);
  protected readonly ativo = signal(-1);

  protected idOpcao(indice: number): string {
    return `${this.idCampo()}-opcao-${indice}`;
  }

  protected digitar(evento: Event): void {
    this.valor.set((evento.target as HTMLInputElement).value);
    if (this.aberto()) {
      this.ativo.set(this.maisProxima());
    }
  }

  protected alternar(): void {
    if (this.aberto()) {
      this.fechar();
    } else {
      this.abrir();
    }
    this.elemento.nativeElement.querySelector('input')?.focus();
  }

  protected teclar(evento: KeyboardEvent): void {
    const total = this.opcoes().length;
    if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
      evento.preventDefault();
      if (!this.aberto()) {
        this.abrir();
        return;
      }
      const passo = evento.key === 'ArrowDown' ? 1 : -1;
      this.ativo.set(Math.min(Math.max(this.ativo() + passo, 0), total - 1));
      this.rolarAteAtivo();
    } else if (evento.key === 'Enter' && this.aberto()) {
      evento.preventDefault();
      const opcao = this.opcoes()[this.ativo()];
      if (opcao) {
        this.escolher(opcao);
      } else {
        this.fechar();
      }
    } else if (evento.key === 'Escape' && this.aberto()) {
      evento.preventDefault();
      this.fechar();
    }
  }

  protected escolher(opcao: string): void {
    this.valor.set(opcao);
    this.escolhido.emit(opcao);
    this.fechar();
  }

  protected aoSair(evento: FocusEvent): void {
    const destino = evento.relatedTarget as Node | null;
    if (!destino || !this.elemento.nativeElement.contains(destino)) {
      this.fechar();
    }
  }

  private abrir(): void {
    this.aberto.set(true);
    this.ativo.set(this.maisProxima());
    this.rolarAteAtivo();
  }

  private fechar(): void {
    this.aberto.set(false);
    this.ativo.set(-1);
  }

  private maisProxima(): number {
    const opcoes = this.opcoes();
    const atual = /^\d{2}:\d{2}$/.test(this.valor()) ? this.valor() : '';
    if (!opcoes.length) {
      return -1;
    }
    const indice = atual ? opcoes.findIndex((opcao) => opcao >= atual) : 0;
    return indice === -1 ? opcoes.length - 1 : indice;
  }

  private rolarAteAtivo(): void {
    afterNextRender(
      () => {
        const ativo = this.elemento.nativeElement.querySelector<HTMLElement>(
          `#${this.idOpcao(this.ativo())}`,
        );
        ativo?.scrollIntoView?.({ block: 'nearest' });
      },
      { injector: this.injector },
    );
  }
}
