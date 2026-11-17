import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay } from 'rxjs';
import { ambiente } from '../../../ambientes/ambiente';
import { ReservaAdmin } from '../reservas/modelos';
import { EquipamentoSolicitavel } from '../../publico/modelos';
import { CalendarioAdmin, EspacoAdmin, NovaReservaDeAluno, NovoEvento } from './modelos';

interface Resposta<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class CalendarioAdminService {
  private readonly http = inject(HttpClient);
  private readonly url = `${ambiente.urlApi}/admin`;
  private espacos$: Observable<EspacoAdmin[]> | null = null;

  listarEspacos(): Observable<EspacoAdmin[]> {
    this.espacos$ ??= this.http
      .get<Resposta<EspacoAdmin[]>>(`${this.url}/espacos`, {
        params: new HttpParams().set('per_page', 100),
      })
      .pipe(
        map((resposta) => resposta.data.map(({ id, nome, ativo }) => ({ id, nome, ativo }))),
        shareReplay(1),
      );
    return this.espacos$;
  }

  esquecerEspacos(): void {
    this.espacos$ = null;
  }

  consultar(espacoId: number, de: string, ate: string): Observable<CalendarioAdmin> {
    return this.http
      .get<Resposta<CalendarioAdmin>>(`${this.url}/espacos/${espacoId}/disponibilidade`, {
        params: new HttpParams().set('de', de).set('ate', ate),
      })
      .pipe(map((resposta) => resposta.data));
  }

  solicitaveis(
    espacoId: number,
    data: string,
    inicio: string,
    fim: string,
  ): Observable<EquipamentoSolicitavel[]> {
    return this.http
      .get<Resposta<EquipamentoSolicitavel[]>>(
        `${this.url}/espacos/${espacoId}/equipamentos/solicitaveis`,
        {
          params: new HttpParams()
            .set('data', data)
            .set('hora_inicio', inicio)
            .set('hora_fim', fim),
        },
      )
      .pipe(map((resposta) => resposta.data));
  }

  reservarParaAluno(reserva: NovaReservaDeAluno): Observable<ReservaAdmin> {
    return this.http
      .post<Resposta<ReservaAdmin>>(`${this.url}/reservas/aluno`, reserva)
      .pipe(map((resposta) => resposta.data));
  }

  criarEvento(evento: NovoEvento): Observable<ReservaAdmin> {
    return this.http
      .post<Resposta<ReservaAdmin>>(`${this.url}/reservas/eventos`, evento)
      .pipe(map((resposta) => resposta.data));
  }
}
