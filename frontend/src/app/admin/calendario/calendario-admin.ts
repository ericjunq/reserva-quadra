import { Component, computed, inject, input, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { Observable, catchError, map, of, startWith, switchMap } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import {
  deIso,
  ehDataIso,
  formatarDiaCurto,
  formatarDiaDaSemanaCurto,
  paraIso,
  segundaDaSemana,
  somarDias,
} from '../../publico/datas';
import { SELOS_DE_CONFIRMACAO, SELO_DE_REGISTRO_POSTERIOR } from '../reservas/selos';
import { CalendarioAdminService } from './calendario-admin.service';
import { itensDoDia, linkDoHorario } from './itens-do-dia';
import { CalendarioAdmin as DadosDoCalendario, ItemDoDia } from './modelos';

type Resultado =
  | { tipo: 'carregando' }
  | { tipo: 'pronto'; calendario: DadosDoCalendario }
  | { tipo: 'erro'; erro: ErroApi };

interface Consulta {
  espacoId: number;
  de: string;
  ate: string;
  recarga: number;
}

@Component({
  selector: 'app-calendario-admin',
  imports: [RouterLink, AvisoErro],
  templateUrl: './calendario-admin.html',
})
export class CalendarioAdmin {
  private readonly api = inject(CalendarioAdminService);
  private readonly router = inject(Router);

  readonly espaco = input<string>();
  readonly semana = input<string>();

  private readonly recarga = signal(0);
  protected readonly hoje = paraIso(new Date());
  protected readonly selos = SELOS_DE_CONFIRMACAO;
  protected readonly formatarDiaCurto = formatarDiaCurto;
  protected readonly formatarDiaDaSemanaCurto = formatarDiaDaSemanaCurto;
  protected readonly linkDoHorario = linkDoHorario;
  protected readonly registroPosterior = SELO_DE_REGISTRO_POSTERIOR;

  protected readonly espacos = toSignal(this.api.listarEspacos().pipe(catchError(() => of([]))), {
    initialValue: null,
  });

  protected readonly espacoId = computed(() => {
    const lista = this.espacos();
    const pedido = Number(this.espaco());
    if (Number.isInteger(pedido) && pedido > 0) {
      return pedido;
    }
    return lista?.[0]?.id ?? null;
  });

  protected readonly segunda = computed(() =>
    paraIso(segundaDaSemana(ehDataIso(this.semana()) ? deIso(this.semana()!) : new Date())),
  );

  protected readonly domingo = computed(() => paraIso(somarDias(deIso(this.segunda()), 6)));

  private readonly consulta = computed<Consulta | null>(() => {
    const espacoId = this.espacoId();
    return espacoId
      ? { espacoId, de: this.segunda(), ate: this.domingo(), recarga: this.recarga() }
      : null;
  });

  protected readonly resultado = toSignal(
    toObservable(this.consulta).pipe(
      switchMap((consulta) => (consulta ? this.buscar(consulta) : of(null))),
    ),
    { initialValue: null },
  );

  protected readonly dias = computed(() => {
    const resultado = this.resultado();
    if (resultado?.tipo !== 'pronto') {
      return [];
    }
    const { calendario } = resultado;
    return calendario.dias.map((dia) => ({
      data: dia.data,
      aviso: dia.motivo && dia.motivo !== 'Data passada.' ? dia.motivo : null,
      itens: itensDoDia(dia, calendario.espaco, {
        hoje: this.hoje,
        agora: horaAgora(),
        registroDesde: paraIso(somarDias(new Date(), -30)),
      }),
    }));
  });

  protected readonly nomeDoEspaco = computed(() => {
    const resultado = this.resultado();
    return resultado?.tipo === 'pronto' ? resultado.calendario.espaco.nome : null;
  });

  protected trocarEspaco(id: string): void {
    this.navegar({ espaco: id || null });
  }

  protected mudarSemana(deslocamento: number): void {
    this.navegar({ semana: paraIso(somarDias(deIso(this.segunda()), deslocamento * 7)) });
  }

  protected irParaDia(data: string): void {
    if (ehDataIso(data)) {
      this.navegar({ semana: paraIso(segundaDaSemana(deIso(data))) });
    }
  }

  protected atualizar(): void {
    this.recarga.update((valor) => valor + 1);
  }

  protected descreverQuem(item: ItemDoDia): string {
    const quem = item.reserva?.reservado_por;
    if (!quem?.nome) {
      return item.tipo === 'EVENTO' ? 'Evento' : 'Reservado';
    }
    return quem.curso ? `${quem.nome} · ${quem.curso}` : quem.nome;
  }

  private navegar(queryParams: Record<string, string | null>): void {
    this.router.navigate([], { queryParams, queryParamsHandling: 'merge' });
  }

  private buscar(consulta: Consulta): Observable<Resultado> {
    return this.api.consultar(consulta.espacoId, consulta.de, consulta.ate).pipe(
      map((calendario): Resultado => ({ tipo: 'pronto', calendario })),
      catchError((erro: ErroApi) => of<Resultado>({ tipo: 'erro', erro })),
      startWith<Resultado>({ tipo: 'carregando' }),
    );
  }
}

function horaAgora(): string {
  const agora = new Date();
  return `${String(agora.getHours()).padStart(2, '0')}:${String(agora.getMinutes()).padStart(2, '0')}`;
}
