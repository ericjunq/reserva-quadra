import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { HistoricoDoRegistro } from '../../historico/historico-do-registro';
import { formatarQuando } from '../../historico/rotulos';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { ClienteApiCadastro, DadosDoClienteApi, OpcaoDePermissao, PermissaoApi } from '../modelos';
import { CORES_DAS_SITUACOES, NOMES_DAS_SITUACOES } from './situacao';

type Carga =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; sistema: ClienteApiCadastro | null }
  | { tipo: 'erro'; erro: ErroApi };

export const LIMITE_PADRAO = 60;

@Component({
  selector: 'app-form-sistema-parceiro',
  imports: [FormsModule, RouterLink, AvisoErro, ConfirmarAcao, HistoricoDoRegistro],
  templateUrl: './form-sistema-parceiro.html',
})
export class FormSistemaParceiro {
  private readonly api = inject(CadastrosService);
  private readonly router = inject(Router);

  readonly id = input<string>();

  protected readonly situacoes = NOMES_DAS_SITUACOES;
  protected readonly cores = CORES_DAS_SITUACOES;
  protected readonly formatarQuando = formatarQuando;

  protected readonly opcoes = toSignal(
    this.api.listarPermissoesApi().pipe(catchError(() => of<OpcaoDePermissao[]>([]))),
    { initialValue: [] as OpcaoDePermissao[] },
  );

  private readonly carga = toSignal(
    toObservable(this.id).pipe(
      switchMap((id): Observable<Carga> =>
        id
          ? this.api.detalharClienteApi(Number(id)).pipe(
              map((sistema): Carga => ({ tipo: 'pronto', sistema })),
              catchError((erro: ErroApi) => of<Carga>({ tipo: 'erro', erro })),
              startWith<Carga>({ tipo: 'carregando' }),
            )
          : of<Carga>({ tipo: 'pronto', sistema: null }),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Carga },
  );

  protected readonly estado = linkedSignal<Carga>(() => this.carga());
  protected readonly sistema = computed(() => {
    const estado = this.estado();
    return estado.tipo === 'pronto' ? estado.sistema : null;
  });

  protected readonly nome = linkedSignal(() => this.sistema()?.nome ?? '');
  protected readonly organizacao = linkedSignal(() => this.sistema()?.organizacao ?? '');
  protected readonly responsavelNome = linkedSignal(() => this.sistema()?.responsavel.nome ?? '');
  protected readonly responsavelEmail = linkedSignal(() => this.sistema()?.responsavel.email ?? '');
  protected readonly finalidade = linkedSignal(() => this.sistema()?.finalidade ?? '');
  protected readonly permissoes = linkedSignal<PermissaoApi[]>(
    () => this.sistema()?.permissoes ?? [],
  );
  protected readonly limite = linkedSignal(
    () => this.sistema()?.limite_por_minuto ?? LIMITE_PADRAO,
  );

  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(
    typeof history.state?.mensagem === 'string' ? history.state.mensagem : null,
  );

  protected erroDoCampo(...campos: string[]): string | null {
    for (const campo of campos) {
      const texto = this.erro()?.campos?.[campo]?.[0];
      if (texto) {
        return texto;
      }
    }
    return null;
  }

  protected marcar(permissao: PermissaoApi, marcada: boolean): void {
    const atuais = this.permissoes().filter((valor) => valor !== permissao);
    this.permissoes.set(marcada ? [...atuais, permissao] : atuais);
  }

  protected salvar(): void {
    if (this.enviando()) {
      return;
    }
    const dados: DadosDoClienteApi = {
      nome: this.nome().trim(),
      organizacao: this.organizacao().trim(),
      responsavel_nome: this.responsavelNome().trim(),
      responsavel_email: this.responsavelEmail().trim(),
      finalidade: this.finalidade().trim(),
      permissoes: this.permissoes(),
      limite_por_minuto: Number(this.limite()),
    };
    const atual = this.sistema();
    if (atual) {
      this.executar(this.api.atualizarClienteApi(atual.id, dados), 'Dados salvos.');
      return;
    }
    this.comecar();
    this.api.criarClienteApi(dados).subscribe({
      next: (criado) => {
        this.enviando.set(false);
        this.router.navigate(['/admin/cadastros/sistemas-parceiros', criado.id], {
          state: {
            mensagem: 'Sistema cadastrado. O responsável recebeu o link para aceitar os termos.',
          },
        });
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  protected enviarLink(revogarAtual: boolean): void {
    const atual = this.sistema();
    if (atual) {
      this.executar(
        this.api.enviarAceiteClienteApi(atual.id, revogarAtual),
        revogarAtual
          ? 'Token revogado. O responsável recebeu um link para gerar outro.'
          : 'Link enviado ao responsável.',
      );
    }
  }

  protected mudarSituacao(ativo: boolean): void {
    const atual = this.sistema();
    if (atual) {
      this.executar(
        ativo
          ? this.api.atualizarClienteApi(atual.id, { ativo: true })
          : this.api.desativarClienteApi(atual.id),
        ativo
          ? 'Sistema reativado. O responsável recebeu um link para aceitar os termos de novo.'
          : 'Sistema desativado. O acesso foi cortado.',
      );
    }
  }

  private executar(pedido: Observable<ClienteApiCadastro>, mensagem: string): void {
    this.comecar();
    pedido.subscribe({
      next: (sistema) => {
        this.enviando.set(false);
        this.estado.set({ tipo: 'pronto', sistema });
        this.mensagem.set(mensagem);
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  private comecar(): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
  }

  private falhar(erro: ErroApi): void {
    this.enviando.set(false);
    this.erro.set(erro);
  }
}
