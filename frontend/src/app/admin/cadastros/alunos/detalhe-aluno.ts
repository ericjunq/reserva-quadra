import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ApiPublicaService } from '../../../publico/api-publica.service';
import { formatarDataHora, formatarDia } from '../../../publico/datas';
import { ReservasAdminService } from '../../reservas/reservas-admin.service';
import { SelosReserva } from '../../reservas/selos';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { AlunoCadastro } from '../modelos';
import { HistoricoDoRegistro } from '../../historico/historico-do-registro';

type Carga =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; aluno: AlunoCadastro }
  | { tipo: 'erro'; erro: ErroApi };

const PALAVRA_DE_CONFIRMACAO = 'ANONIMIZAR';

@Component({
  selector: 'app-detalhe-aluno',
  imports: [FormsModule, RouterLink, AvisoErro, ConfirmarAcao, SelosReserva, HistoricoDoRegistro],
  templateUrl: './detalhe-aluno.html',
})
export class DetalheAluno {
  private readonly api = inject(CadastrosService);
  private readonly reservasApi = inject(ReservasAdminService);
  private readonly publica = inject(ApiPublicaService);

  readonly id = input.required<string>();

  protected readonly palavra = PALAVRA_DE_CONFIRMACAO;
  protected readonly formatarDia = formatarDia;
  protected readonly formatarDataHora = formatarDataHora;

  protected readonly cursos = toSignal(this.publica.listarCursos().pipe(catchError(() => of([]))), {
    initialValue: [],
  });

  private readonly carga = toSignal(
    toObservable(this.id).pipe(
      switchMap((id) =>
        this.api.detalharAluno(Number(id)).pipe(
          map((aluno): Carga => ({ tipo: 'pronto', aluno })),
          catchError((erro: ErroApi) => of<Carga>({ tipo: 'erro', erro })),
          startWith<Carga>({ tipo: 'carregando' }),
        ),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Carga },
  );

  protected readonly estado = linkedSignal<Carga>(() => this.carga());
  protected readonly aluno = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.aluno : null;
  });

  protected readonly reservas = toSignal(
    toObservable(this.id).pipe(
      switchMap((id) =>
        this.reservasApi
          .consultar({ aluno_id: Number(id), de: '2000-01-01', ordem: 'desc' }, 1, 10)
          .pipe(
            map((pagina) => pagina.data),
            catchError(() => of([])),
          ),
      ),
    ),
    { initialValue: null },
  );

  protected readonly nome = linkedSignal(() => this.aluno()?.nome_completo ?? '');
  protected readonly ra = linkedSignal(() => this.aluno()?.registro_academico ?? '');
  protected readonly email = linkedSignal(() => this.aluno()?.email ?? '');
  protected readonly cursoId = linkedSignal<number | null>(() => this.aluno()?.curso?.id ?? null);
  protected readonly entendi = signal(false);
  protected readonly palavraDigitada = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  protected readonly podeAnonimizar = computed(
    () => this.entendi() && this.palavraDigitada().trim().toUpperCase() === PALAVRA_DE_CONFIRMACAO,
  );

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos?.[campo]?.[0] ?? null;
  }

  protected salvar(): void {
    const aluno = this.aluno();
    if (!aluno || this.enviando()) {
      return;
    }
    this.executar(
      this.api
        .atualizarAluno(aluno.id, {
          nome_completo: this.nome().trim(),
          registro_academico: this.ra().trim(),
          email: this.email().trim(),
          ...(this.cursoId() ? { curso_id: this.cursoId()! } : {}),
        })
        .pipe(map(({ aluno }) => ({ aluno, texto: 'Dados salvos.' }))),
    );
  }

  protected desativar(): void {
    const aluno = this.aluno();
    if (aluno) {
      this.executar(
        this.api.desativarAluno(aluno.id).pipe(
          map(({ aluno, revogadas }) => ({
            aluno,
            texto:
              revogadas > 0
                ? `Aluno desativado. ${revogadas} reserva(s) futura(s) revogada(s), com email.`
                : 'Aluno desativado.',
          })),
        ),
      );
    }
  }

  protected reativar(): void {
    const aluno = this.aluno();
    if (aluno) {
      this.executar(
        this.api
          .atualizarAluno(aluno.id, { ativo: true })
          .pipe(map(({ aluno }) => ({ aluno, texto: 'Aluno reativado.' }))),
      );
    }
  }

  protected anonimizar(): void {
    const aluno = this.aluno();
    if (aluno && this.podeAnonimizar()) {
      this.executar(
        this.api
          .anonimizarAluno(aluno.id)
          .pipe(map((anonimo) => ({ aluno: anonimo, texto: 'Dados pessoais anonimizados.' }))),
      );
    }
  }

  private executar(pedido: Observable<{ aluno: AlunoCadastro; texto: string }>): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
    pedido.subscribe({
      next: ({ aluno, texto }) => {
        this.enviando.set(false);
        this.estado.set({ tipo: 'pronto', aluno });
        this.mensagem.set(texto);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
