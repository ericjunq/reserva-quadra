import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, of, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../../publico/api-publica.service';
import { ehDataIso, ehHora, paraIso } from '../../publico/datas';
import { Horario } from '../../publico/modelos';
import { CalendarioAdminService } from '../calendario/calendario-admin.service';
import { NovoEvento as DadosDoEvento } from '../calendario/modelos';

const HORARIOS_FIXOS: Record<Horario, [string, string]> = {
  PRIMEIRO_HORARIO: ['19:00', '20:30'],
  SEGUNDO_HORARIO: ['20:30', '22:00'],
};

@Component({
  selector: 'app-novo-evento',
  imports: [FormsModule, RouterLink, AvisoErro],
  templateUrl: './novo-evento.html',
})
export class NovoEvento {
  private readonly api = inject(CalendarioAdminService);
  private readonly publica = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly espaco = input<string>();
  readonly data = input<string>();
  readonly horario = input<string>();
  readonly inicio = input<string>();
  readonly fim = input<string>();

  protected readonly hoje = paraIso(new Date());
  protected readonly horariosFixos = HORARIOS_FIXOS;

  protected readonly espacos = toSignal(
    this.publica.listarEspacos().pipe(catchError(() => of([]))),
    { initialValue: [] },
  );

  protected readonly espacoId = linkedSignal(() => {
    const id = Number(this.espaco());
    return Number.isInteger(id) && id > 0 ? id : null;
  });
  protected readonly dataEscolhida = linkedSignal(() =>
    ehDataIso(this.data()) ? this.data()! : '',
  );
  protected readonly horarioEscolhido = linkedSignal<Horario | ''>(() => {
    const horario = this.horario();
    return horario === 'PRIMEIRO_HORARIO' || horario === 'SEGUNDO_HORARIO' ? horario : '';
  });
  protected readonly inicioEscolhido = linkedSignal(() =>
    ehHora(this.inicio()) ? this.inicio()! : '',
  );
  protected readonly fimEscolhido = linkedSignal(() => (ehHora(this.fim()) ? this.fim()! : ''));
  protected readonly descricao = signal('');
  protected readonly quantidades = signal<Record<number, number>>({});
  protected readonly justificativa = signal('');
  protected readonly conflito = signal<number | null>(null);
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);

  protected readonly espacoEscolhido = computed(
    () => this.espacos().find((espaco) => espaco.id === this.espacoId()) ?? null,
  );

  protected readonly livre = computed(() => this.espacoEscolhido()?.modo_horario === 'LIVRE');

  protected readonly intervalo = computed<[string, string] | null>(() => {
    if (!this.espacoEscolhido()) {
      return null;
    }
    if (!this.livre()) {
      const horario = this.horarioEscolhido();
      return horario ? HORARIOS_FIXOS[horario] : null;
    }
    const inicio = this.inicioEscolhido();
    const fim = this.fimEscolhido();
    return ehHora(inicio) && ehHora(fim) && inicio < fim ? [inicio, fim] : null;
  });

  private readonly consultaDeEquipamentos = computed(() => {
    const espacoId = this.espacoId();
    const data = this.dataEscolhida();
    const intervalo = this.intervalo();
    return espacoId && ehDataIso(data) && intervalo ? { espacoId, data, intervalo } : null;
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
    this.horarioEscolhido.set('');
    this.inicioEscolhido.set('');
    this.fimEscolhido.set('');
    this.quantidades.set({});
    this.conflito.set(null);
  }

  protected mudarQuantidade(id: number, valor: number): void {
    this.quantidades.update((atuais) => ({ ...atuais, [id]: Math.max(0, Math.floor(valor) || 0) }));
  }

  protected erroDoCampo(...campos: string[]): string | null {
    const lista = this.erro()?.campos ?? {};
    for (const campo of campos) {
      if (lista[campo]?.[0]) {
        return lista[campo][0];
      }
    }
    return null;
  }

  protected criar(): void {
    const espacoId = this.espacoId();
    const intervalo = this.intervalo();
    if (this.enviando() || !espacoId || !intervalo) {
      this.erro.set({
        status: 0,
        mensagem: 'Escolha o espaço, a data e o horário do evento.',
        campos: {},
        dados: {},
      });
      return;
    }
    const evento: DadosDoEvento = {
      espaco_id: espacoId,
      data_reserva: this.dataEscolhida(),
      descricao: this.descricao().trim(),
      ...(this.livre()
        ? { hora_inicio: intervalo[0], hora_fim: intervalo[1] }
        : { horario: this.horarioEscolhido() as Horario }),
    };
    const equipamentos = Object.entries(this.quantidades())
      .filter(([, quantidade]) => quantidade > 0)
      .map(([id, quantidade]) => ({ id: Number(id), quantidade }));
    if (equipamentos.length) {
      evento.equipamentos = equipamentos;
    }
    if (this.conflito()) {
      evento.justificativa = this.justificativa().trim();
    }

    this.enviando.set(true);
    this.erro.set(null);
    this.api.criarEvento(evento).subscribe({
      next: (criado) => this.router.navigate(['/admin/reservas', criado.id]),
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        const conflitante = Number(erro.dados['reserva_conflitante_id']);
        if (erro.status === 409 && conflitante) {
          this.conflito.set(conflitante);
        }
        this.erro.set(erro);
      },
    });
  }
}
