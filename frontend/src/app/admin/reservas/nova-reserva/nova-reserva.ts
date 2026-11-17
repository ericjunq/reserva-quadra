import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, of } from 'rxjs';
import { ReservaAdmin } from '../modelos';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { ehDataIso, ehHora, paraIso } from '../../../publico/datas';
import { CalendarioAdminService } from '../../calendario/calendario-admin.service';
import { NovaReservaDeAluno, NovoEvento as DadosDoEvento } from '../../calendario/modelos';
import {
  CamposDoUso,
  equipamentosEscolhidos,
  horarioDaUrl,
  horarioDoUso,
} from '../../compartilhado/campos-do-uso';
import {
  EquipamentosDeOutrosEspacos,
  IntervaloDoUso,
} from '../../compartilhado/equipamentos-de-outros-espacos';

@Component({
  selector: 'app-nova-reserva',
  imports: [FormsModule, RouterLink, AvisoErro, CamposDoUso, EquipamentosDeOutrosEspacos],
  templateUrl: './nova-reserva.html',
})
export class NovaReserva {
  private readonly api = inject(CalendarioAdminService);
  private readonly publica = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly espaco = input<string>();
  readonly data = input<string>();
  readonly horario = input<string>();
  readonly inicio = input<string>();
  readonly fim = input<string>();
  readonly tipo = input<string>();

  protected readonly hoje = paraIso(new Date());

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
  protected readonly horarioEscolhido = linkedSignal(() => horarioDaUrl(this.horario()));
  protected readonly inicioEscolhido = linkedSignal(() =>
    ehHora(this.inicio()) ? this.inicio()! : '',
  );
  protected readonly fimEscolhido = linkedSignal(() => (ehHora(this.fim()) ? this.fim()! : ''));
  protected readonly paraQuem = linkedSignal<'ALUNO' | 'EVENTO'>(() =>
    this.tipo() === 'evento' ? 'EVENTO' : 'ALUNO',
  );
  protected readonly registroAcademico = signal('');
  protected readonly quantidades = signal<Record<number, number>>({});
  protected readonly deOutrosEspacos = signal<Record<number, number>>({});
  protected readonly descricao = signal('');
  protected readonly justificativa = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);

  private readonly escolhido = computed(() =>
    horarioDoUso(
      this.espacos().find((espaco) => espaco.id === this.espacoId()) ?? null,
      this.horarioEscolhido(),
      this.inicioEscolhido(),
      this.fimEscolhido(),
    ),
  );

  protected readonly intervalo = computed<IntervaloDoUso | null>(() => {
    const espacoId = this.espacoId();
    const escolhido = this.escolhido();
    return espacoId && escolhido && ehDataIso(this.dataEscolhida())
      ? {
          espacoId,
          data: this.dataEscolhida(),
          inicio: escolhido.intervalo[0],
          fim: escolhido.intervalo[1],
        }
      : null;
  });

  protected readonly conflito = linkedSignal<number | null>(() => {
    this.espacoId();
    this.dataEscolhida();
    this.escolhido();
    return null;
  });

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos?.[campo]?.[0] ?? null;
  }

  protected criar(): void {
    const espacoId = this.espacoId();
    const escolhido = this.escolhido();
    if (this.enviando()) {
      return;
    }
    if (!espacoId || !escolhido || !ehDataIso(this.dataEscolhida())) {
      this.mostrarErro('Escolha o espaço, a data e o horário do evento.');
      return;
    }
    const { intervalo, ...horario } = escolhido;
    const equipamentos = equipamentosEscolhidos(this.quantidades());
    const evento: DadosDoEvento = {
      espaco_id: espacoId,
      data_reserva: this.dataEscolhida(),
      descricao: this.descricao().trim(),
      ...horario,
      ...(equipamentos.length ? { equipamentos } : {}),
      ...(this.conflito() ? { justificativa: this.justificativa().trim() } : {}),
    };

    this.enviar(this.api.criarEvento(evento));
  }

  protected reservarParaAluno(): void {
    const espacoId = this.espacoId();
    const escolhido = this.escolhido();
    const ra = this.registroAcademico().trim();
    if (this.enviando()) {
      return;
    }
    if (!espacoId || !escolhido || !ehDataIso(this.dataEscolhida())) {
      this.mostrarErro('Escolha o espaço, a data e o horário da reserva.');
      return;
    }
    if (!/^\d{10}$/.test(ra)) {
      this.mostrarErro('O RA tem 10 números.');
      return;
    }
    const { intervalo, ...horario } = escolhido;
    const equipamentos = equipamentosEscolhidos(this.quantidades());
    const solicitacoes = equipamentosEscolhidos(this.deOutrosEspacos());
    const reserva: NovaReservaDeAluno = {
      espaco_id: espacoId,
      data_reserva: this.dataEscolhida(),
      registro_academico: ra,
      ...horario,
      ...(equipamentos.length ? { equipamentos } : {}),
      ...(solicitacoes.length ? { solicitacoes } : {}),
    };
    this.enviar(this.api.reservarParaAluno(reserva));
  }

  protected salvar(): void {
    if (this.paraQuem() === 'ALUNO') {
      this.reservarParaAluno();
    } else {
      this.criar();
    }
  }

  private mostrarErro(mensagem: string): void {
    this.erro.set({ status: 0, mensagem, campos: {}, dados: {} });
  }

  private enviar(pedido: Observable<ReservaAdmin>): void {
    this.enviando.set(true);
    this.erro.set(null);
    pedido.subscribe({
      next: (criada) => {
        this.registroAcademico.set('');
        this.router.navigate(['/admin/reservas', criada.id]);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        const conflitante = Number(erro.dados['reserva_conflitante_id']);
        if (erro.status === 409 && conflitante && this.paraQuem() === 'EVENTO') {
          this.conflito.set(conflitante);
        }
        this.erro.set(erro);
      },
    });
  }
}
