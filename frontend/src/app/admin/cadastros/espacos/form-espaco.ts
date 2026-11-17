import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ModoHorario, TipoEspaco } from '../../../publico/modelos';
import { NOMES_DOS_TIPOS } from '../../../publico/textos';
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { CalendarioAdminService } from '../../calendario/calendario-admin.service';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { DadosDoEspaco, EspacoCadastro } from '../modelos';

type Carga =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; espaco: EspacoCadastro | null }
  | { tipo: 'erro'; erro: ErroApi };

const DURACAO_SUGERIDA = 180;

@Component({
  selector: 'app-form-espaco',
  imports: [FormsModule, RouterLink, AvisoErro, ConfirmarAcao],
  templateUrl: './form-espaco.html',
})
export class FormEspaco {
  private readonly api = inject(CadastrosService);
  private readonly router = inject(Router);
  private readonly calendario = inject(CalendarioAdminService);
  private readonly publica = inject(ApiPublicaService);

  readonly id = input<string>();

  protected readonly tipos: TipoEspaco[] = ['QUADRA', 'LABORATORIO', 'SALA'];
  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;

  private readonly carga = toSignal(
    toObservable(this.id).pipe(
      switchMap((id): Observable<Carga> =>
        id
          ? this.api.detalharEspaco(Number(id)).pipe(
              map((espaco): Carga => ({ tipo: 'pronto', espaco })),
              catchError((erro: ErroApi) => of<Carga>({ tipo: 'erro', erro })),
              startWith<Carga>({ tipo: 'carregando' }),
            )
          : of<Carga>({ tipo: 'pronto', espaco: null }),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Carga },
  );

  protected readonly estado = linkedSignal<Carga>(() => this.carga());
  protected readonly espaco = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.espaco : null;
  });

  protected readonly nome = linkedSignal(() => this.espaco()?.nome ?? '');
  protected readonly tipo = linkedSignal<TipoEspaco>(() => this.espaco()?.tipo ?? 'SALA');
  protected readonly modo = linkedSignal<ModoHorario>(() => this.espaco()?.modo_horario ?? 'LIVRE');
  protected readonly abreAs = linkedSignal(() => this.espaco()?.abre_as ?? '07:00');
  protected readonly fechaAs = linkedSignal(() => this.espaco()?.fecha_as ?? '22:00');
  protected readonly duracao = linkedSignal(
    () => this.espaco()?.duracao_maxima_minutos ?? DURACAO_SUGERIDA,
  );
  protected readonly limite = linkedSignal(() => this.espaco()?.limite_semanal_por_curso ?? null);
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  protected erroDoCampo(...campos: string[]): string | null {
    const lista = this.erro()?.campos ?? {};
    return campos.map((campo) => lista[campo]?.[0]).find(Boolean) ?? null;
  }

  protected salvar(): void {
    if (this.enviando()) {
      return;
    }
    const livre = this.modo() === 'LIVRE';
    const dados: DadosDoEspaco = {
      nome: this.nome().trim(),
      tipo: this.tipo(),
      modo_horario: this.modo(),
      abre_as: livre ? this.abreAs() : null,
      fecha_as: livre ? this.fechaAs() : null,
      duracao_maxima_minutos: livre ? Number(this.duracao()) || null : null,
      limite_semanal_por_curso: this.limite() ? Number(this.limite()) : null,
    };
    const atual = this.espaco();
    this.executar(
      atual ? this.api.atualizarEspaco(atual.id, dados) : this.api.criarEspaco(dados),
      atual ? 'Espaço salvo.' : null,
    );
  }

  protected desativar(): void {
    const atual = this.espaco();
    if (atual) {
      this.executar(
        this.api.desativarEspaco(atual.id).pipe(map(() => ({ ...atual, ativo: false }))),
        'Espaço desativado.',
      );
    }
  }

  protected reativar(): void {
    const atual = this.espaco();
    if (atual) {
      this.executar(this.api.atualizarEspaco(atual.id, { ativo: true }), 'Espaço reativado.');
    }
  }

  private executar(pedido: Observable<EspacoCadastro>, mensagem: string | null): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
    pedido.subscribe({
      next: (espaco) => {
        this.enviando.set(false);
        this.calendario.esquecerEspacos();
        this.publica.esquecerEspacos();
        if (!mensagem) {
          this.router.navigate(['/admin/cadastros/espacos', espaco.id]);
          return;
        }
        this.estado.set({ tipo: 'pronto', espaco });
        this.mensagem.set(mensagem);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
