import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { CalendarioAdminService } from '../../calendario/calendario-admin.service';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { DadosDoEquipamento, EquipamentoCadastro } from '../modelos';
import { HistoricoDoRegistro } from '../../historico/historico-do-registro';
import { EstoqueEquipamento } from '../estoque/estoque-equipamento';

type Carga =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; equipamento: EquipamentoCadastro | null }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-form-equipamento',
  imports: [
    FormsModule,
    RouterLink,
    AvisoErro,
    ConfirmarAcao,
    HistoricoDoRegistro,
    EstoqueEquipamento,
  ],
  templateUrl: './form-equipamento.html',
})
export class FormEquipamento {
  private readonly api = inject(CadastrosService);
  private readonly calendario = inject(CalendarioAdminService);
  private readonly router = inject(Router);

  readonly id = input<string>();
  readonly espaco = input<string>();

  protected readonly espacos = toSignal(
    this.calendario.listarEspacos().pipe(catchError(() => of([]))),
    { initialValue: [] },
  );

  private readonly carga = toSignal(
    toObservable(this.id).pipe(
      switchMap((id): Observable<Carga> =>
        id
          ? this.api.detalharEquipamento(Number(id)).pipe(
              map((equipamento): Carga => ({ tipo: 'pronto', equipamento })),
              catchError((erro: ErroApi) => of<Carga>({ tipo: 'erro', erro })),
              startWith<Carga>({ tipo: 'carregando' }),
            )
          : of<Carga>({ tipo: 'pronto', equipamento: null }),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Carga },
  );

  protected readonly estado = linkedSignal<Carga>(() => this.carga());
  protected readonly equipamento = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.equipamento : null;
  });

  protected readonly espacoId = linkedSignal<number | null>(
    () => this.equipamento()?.espaco_id ?? (Number(this.espaco()) || null),
  );
  protected readonly nome = linkedSignal(() => this.equipamento()?.nome ?? '');
  protected readonly quantidade = linkedSignal(() => this.equipamento()?.quantidade_total ?? 1);
  protected readonly aceitaSolicitacao = linkedSignal(
    () => this.equipamento()?.aceita_solicitacao ?? false,
  );
  protected readonly compartilhados = linkedSignal<number[]>(
    () => this.equipamento()?.espacos_compartilhados?.map((espaco) => espaco.id) ?? [],
  );
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);

  protected readonly outrosEspacos = computed(() =>
    this.espacos().filter((espaco) => espaco.id !== this.espacoId()),
  );

  protected erroDoCampo(...campos: string[]): string | null {
    const lista = this.erro()?.campos ?? {};
    const chaves = Object.keys(lista).filter((chave) =>
      campos.some((campo) => chave === campo || chave.startsWith(`${campo}.`)),
    );
    return chaves.length ? lista[chaves[0]][0] : null;
  }

  protected alternarCompartilhado(id: number, marcado: boolean): void {
    this.compartilhados.update((atuais) =>
      marcado ? [...new Set([...atuais, id])] : atuais.filter((atual) => atual !== id),
    );
  }

  protected salvar(): void {
    if (this.enviando()) {
      return;
    }
    const dados: DadosDoEquipamento = {
      nome: this.nome().trim(),
      aceita_solicitacao: this.aceitaSolicitacao(),
      espacos_compartilhados: this.compartilhados(),
    };
    const atual = this.equipamento();
    if (!atual) {
      dados.quantidade_total = Number(this.quantidade()) || 0;
    }
    if (atual) {
      this.executar(this.api.atualizarEquipamento(atual.id, dados), 'Equipamento salvo.');
      return;
    }
    const espacoId = this.espacoId();
    if (!espacoId) {
      this.erro.set({
        status: 0,
        mensagem: 'Escolha o espaço do equipamento.',
        campos: {},
        dados: {},
      });
      return;
    }
    this.executar(this.api.criarEquipamento({ ...dados, espaco_id: espacoId }), null);
  }

  protected desativar(): void {
    const atual = this.equipamento();
    if (atual) {
      this.executar(
        this.api.desativarEquipamento(atual.id).pipe(map(() => ({ ...atual, ativo: false }))),
        'Equipamento desativado.',
      );
    }
  }

  protected reativar(): void {
    const atual = this.equipamento();
    if (atual) {
      this.executar(
        this.api.atualizarEquipamento(atual.id, { ativo: true }),
        'Equipamento reativado.',
      );
    }
  }

  protected estoqueAtualizado(equipamento: EquipamentoCadastro): void {
    this.estado.set({ tipo: 'pronto', equipamento });
  }

  private executar(pedido: Observable<EquipamentoCadastro>, mensagem: string | null): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
    pedido.subscribe({
      next: (equipamento) => {
        this.enviando.set(false);
        if (!mensagem) {
          this.router.navigate(['/admin/cadastros/equipamentos', equipamento.id]);
          return;
        }
        this.estado.set({ tipo: 'pronto', equipamento });
        this.mensagem.set(mensagem);
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
