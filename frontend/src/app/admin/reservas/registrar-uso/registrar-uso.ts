import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { ehDataIso, ehHora, paraIso, somarDias } from '../../../publico/datas';
import {
  CamposDoUso,
  equipamentosEscolhidos,
  horarioDaUrl,
  horarioDoUso,
} from '../../compartilhado/campos-do-uso';
import { NovoUsoPosterior } from '../modelos';
import { ReservasAdminService } from '../reservas-admin.service';

const DIAS_PARA_TRAS = 30;

@Component({
  selector: 'app-registrar-uso',
  imports: [FormsModule, RouterLink, AvisoErro, CamposDoUso],
  templateUrl: './registrar-uso.html',
})
export class RegistrarUso {
  private readonly api = inject(ReservasAdminService);
  private readonly publica = inject(ApiPublicaService);
  private readonly router = inject(Router);

  readonly espaco = input<string>();
  readonly data = input<string>();
  readonly horario = input<string>();
  readonly inicio = input<string>();
  readonly fim = input<string>();

  protected readonly hoje = paraIso(new Date());
  protected readonly desde = paraIso(somarDias(new Date(), -DIAS_PARA_TRAS));

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
  protected readonly quantidades = signal<Record<number, number>>({});
  protected readonly quem = signal<'ALUNO' | 'GRUPO'>('ALUNO');
  protected readonly registroAcademico = signal('');
  protected readonly descricao = signal('');
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

  protected readonly conflito = linkedSignal<number | null>(() => {
    this.espacoId();
    this.dataEscolhida();
    this.escolhido();
    return null;
  });

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos?.[campo]?.[0] ?? null;
  }

  protected registrar(): void {
    const espacoId = this.espacoId();
    const escolhido = this.escolhido();
    if (this.enviando()) {
      return;
    }
    if (!espacoId || !escolhido || !ehDataIso(this.dataEscolhida())) {
      this.mostrarErro('Escolha o espaço, a data e o horário do uso.');
      return;
    }
    const ra = this.registroAcademico().trim();
    const descricao = this.descricao().trim();
    if (this.quem() === 'ALUNO' ? !/^\d{10}$/.test(ra) : !descricao) {
      this.mostrarErro(
        this.quem() === 'ALUNO' ? 'O RA tem 10 números.' : 'Descreva o grupo que usou o espaço.',
      );
      return;
    }
    const { intervalo, ...horario } = escolhido;
    const equipamentos = equipamentosEscolhidos(this.quantidades());
    const uso: NovoUsoPosterior = {
      espaco_id: espacoId,
      data_reserva: this.dataEscolhida(),
      ...horario,
      ...(this.quem() === 'ALUNO' ? { registro_academico: ra } : { descricao }),
      ...(equipamentos.length ? { equipamentos } : {}),
    };

    this.enviando.set(true);
    this.erro.set(null);
    this.api.registrarUsoPosterior(uso).subscribe({
      next: ({ reserva, aviso }) => {
        this.registroAcademico.set('');
        this.router.navigate(['/admin/reservas', reserva.id], { state: { aviso } });
      },
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

  private mostrarErro(mensagem: string): void {
    this.erro.set({ status: 0, mensagem, campos: {}, dados: {} });
  }
}
