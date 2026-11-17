import { HttpClient, HttpResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ambiente } from '../../../ambientes/ambiente';
import {
  ArquivoExportado,
  ColunasExportacao,
  FiltrosReservas,
  ItemDevolucao,
  NovoUsoPosterior,
  PaginaReservasAdmin,
  ReservaAdmin,
  UsoRegistrado,
} from './modelos';

interface Resposta<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class ReservasAdminService {
  private readonly http = inject(HttpClient);
  private readonly url = `${ambiente.urlApi}/admin/reservas`;

  consultar(
    filtros: FiltrosReservas,
    pagina: number,
    porPagina?: number,
  ): Observable<PaginaReservasAdmin> {
    return this.http.post<PaginaReservasAdmin>(`${this.url}/consulta`, {
      ...filtros,
      page: pagina,
      ...(porPagina ? { per_page: porPagina } : {}),
    });
  }

  aprovarSolicitacao(id: number, equipamentoId: number | null): Observable<ReservaAdmin> {
    return this.http
      .post<Resposta<ReservaAdmin>>(
        `${this.url}/${id}/solicitacoes/${equipamentoId ? `${equipamentoId}/` : ''}aprovar`,
        {},
      )
      .pipe(map((resposta) => resposta.data));
  }

  recusarSolicitacao(
    id: number,
    equipamentoId: number | null,
    justificativa: string,
  ): Observable<ReservaAdmin> {
    return this.http
      .post<Resposta<ReservaAdmin>>(
        `${this.url}/${id}/solicitacoes/${equipamentoId ? `${equipamentoId}/` : ''}recusar`,
        {
          justificativa,
        },
      )
      .pipe(map((resposta) => resposta.data));
  }

  detalhar(id: number): Observable<ReservaAdmin> {
    return this.http
      .get<Resposta<ReservaAdmin>>(`${this.url}/${id}`)
      .pipe(map((resposta) => resposta.data));
  }

  revogar(id: number, justificativa: string): Observable<ReservaAdmin> {
    return this.http
      .post<Resposta<ReservaAdmin>>(`${this.url}/${id}/revogar`, { justificativa })
      .pipe(map((resposta) => resposta.data));
  }

  registrarDevolucao(id: number, itens: ItemDevolucao[]): Observable<ReservaAdmin> {
    return this.http
      .post<Resposta<ReservaAdmin>>(`${this.url}/${id}/devolucao`, { itens })
      .pipe(map((resposta) => resposta.data));
  }

  registrarUsoPosterior(uso: NovoUsoPosterior): Observable<UsoRegistrado> {
    return this.http
      .post<Resposta<ReservaAdmin> & { aviso: string | null }>(
        `${this.url}/registro-posterior`,
        uso,
      )
      .pipe(map((resposta) => ({ reserva: resposta.data, aviso: resposta.aviso })));
  }

  colunasDaExportacao(): Observable<ColunasExportacao> {
    return this.http
      .get<Resposta<ColunasExportacao>>(`${this.url}/exportar/colunas`)
      .pipe(map((resposta) => resposta.data));
  }

  exportar(filtros: FiltrosReservas, colunas: string[]): Observable<ArquivoExportado> {
    return this.http
      .post(
        `${this.url}/exportar`,
        { ...filtros, colunas },
        { observe: 'response', responseType: 'blob' },
      )
      .pipe(map((resposta) => ({ conteudo: resposta.body!, nome: nomeDoArquivo(resposta) })));
  }
}

function nomeDoArquivo(resposta: HttpResponse<Blob>): string {
  const cabecalho = resposta.headers.get('Content-Disposition') ?? '';
  const nome = /filename="?([^";]+)"?/.exec(cabecalho)?.[1];
  return nome && /^[\w.-]+\.csv$/.test(nome) ? nome : 'reservas.csv';
}
