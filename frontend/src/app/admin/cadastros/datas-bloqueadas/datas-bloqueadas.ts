import { Component, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../../nucleo/erro-api';
import { ehDataIso, formatarDiaCurto, paraIso } from '../../../publico/datas';
import { CalendarioAdminService } from '../../calendario/calendario-admin.service';
import { CadastrosService } from '../cadastros.service';
import { ConfirmarAcao } from '../confirmar-acao';
import { DataBloqueadaCadastro, Pagina } from '../modelos';
import { Paginacao } from '../paginacao';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; pagina: Pagina<DataBloqueadaCadastro> }
  | { tipo: 'erro'; erro: ErroApi };

@Component({
  selector: 'app-datas-bloqueadas',
  imports: [FormsModule, RouterLink, AvisoErro, ConfirmarAcao, Paginacao],
  templateUrl: './datas-bloqueadas.html',
})
export class DatasBloqueadas {
  private readonly api = inject(CadastrosService);
  private readonly calendario = inject(CalendarioAdminService);
  private readonly router = inject(Router);

  readonly de = input<string>();
  readonly ate = input<string>();
  readonly espaco = input<string>();
  readonly pagina = input<string>();

  private readonly recarga = signal(0);
  protected readonly editando = signal<number | null>(null);
  protected readonly motivoEditado = signal('');
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly mensagem = signal<string | null>(null);
  protected readonly formatarDiaCurto = formatarDiaCurto;

  protected readonly espacos = toSignal(
    this.calendario.listarEspacos().pipe(catchError(() => of([]))),
    { initialValue: [] },
  );

  protected readonly filtros = computed(() => {
    const espacoId = Number(this.espaco());
    return {
      de: ehDataIso(this.de()) ? this.de()! : paraIso(new Date()),
      ate: ehDataIso(this.ate()) ? this.ate()! : undefined,
      espaco_id: Number.isInteger(espacoId) && espacoId > 0 ? espacoId : undefined,
    };
  });

  private readonly consulta = computed(() => ({
    filtros: this.filtros(),
    pagina: Math.max(Number(this.pagina()) || 1, 1),
    recarga: this.recarga(),
  }));

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap(({ filtros, pagina }) =>
        this.api.listarBloqueios(pagina, filtros).pipe(
          map((dados): Resultado => ({ tipo: 'pronto', pagina: dados })),
          catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
          startWith<Resultado>({ tipo: 'carregando' }),
        ),
      ),
    ),
    { initialValue: { tipo: 'carregando' } as Resultado },
  );

  protected readonly paginaPronta = computed(() => {
    const resultado = this.resultado();
    return resultado.tipo === 'pronto' ? resultado.pagina : null;
  });

  protected readonly bloqueios = linkedSignal(() => this.paginaPronta()?.data ?? []);

  protected descreverPeriodo(bloqueio: DataBloqueadaCadastro): string {
    const dias =
      bloqueio.data_inicio === bloqueio.data_fim
        ? formatarDiaCurto(bloqueio.data_inicio)
        : `${formatarDiaCurto(bloqueio.data_inicio)} a ${formatarDiaCurto(bloqueio.data_fim)}`;
    return bloqueio.dia_inteiro
      ? `${dias}, dia inteiro`
      : `${dias}, ${bloqueio.hora_inicio}–${bloqueio.hora_fim}`;
  }

  protected filtrar(mudancas: Record<string, string | null>): void {
    const limpas = Object.fromEntries(
      Object.entries(mudancas).map(([chave, valor]) => [chave, valor || null]),
    );
    this.router.navigate([], {
      queryParams: { ...limpas, pagina: null },
      queryParamsHandling: 'merge',
    });
  }

  protected irParaPagina(pagina: number): void {
    this.router.navigate([], {
      queryParams: { pagina: pagina > 1 ? pagina : null },
      queryParamsHandling: 'merge',
    });
  }

  protected editar(bloqueio: DataBloqueadaCadastro): void {
    this.editando.set(bloqueio.id);
    this.motivoEditado.set(bloqueio.motivo);
  }

  protected salvarMotivo(bloqueio: DataBloqueadaCadastro): void {
    this.comecar();
    this.api.atualizarMotivo(bloqueio.id, this.motivoEditado().trim()).subscribe({
      next: (atualizado) => {
        this.terminar('Motivo salvo.');
        this.editando.set(null);
        this.bloqueios.update((lista) =>
          lista.map((item) => (item.id === atualizado.id ? atualizado : item)),
        );
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  protected excluir(bloqueio: DataBloqueadaCadastro): void {
    this.comecar();
    this.api.excluirBloqueio(bloqueio.id).subscribe({
      next: () => {
        this.terminar('Bloqueio excluído. As reservas revogadas por ele não voltam sozinhas.');
        this.recarga.update((valor) => valor + 1);
      },
      error: (erro: ErroApi) => this.falhar(erro),
    });
  }

  private comecar(): void {
    this.enviando.set(true);
    this.erro.set(null);
    this.mensagem.set(null);
  }

  private terminar(mensagem: string): void {
    this.enviando.set(false);
    this.mensagem.set(mensagem);
  }

  private falhar(erro: ErroApi): void {
    this.enviando.set(false);
    this.erro.set(erro);
  }
}
