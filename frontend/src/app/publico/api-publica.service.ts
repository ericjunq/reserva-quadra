import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, shareReplay, tap } from 'rxjs';
import { ambiente } from '../../ambientes/ambiente';
import {
  Calendario,
  Curso,
  EquipamentoDisponivel,
  Espaco,
  NovaReserva,
  NovoAluno,
  ReservaCriada,
} from './modelos';

interface Resposta<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class ApiPublicaService {
  private readonly http = inject(HttpClient);
  private readonly url = ambiente.urlApi;
  private espacos$: Observable<Espaco[]> | null = null;
  private cursos$: Observable<Curso[]> | null = null;
  private readonly calendarios = new Map<string, Observable<Calendario>>();

  listarEspacos(): Observable<Espaco[]> {
    this.espacos$ ??= this.http.get<Resposta<Espaco[]>>(`${this.url}/espacos`).pipe(
      map((resposta) => resposta.data),
      shareReplay(1),
    );
    return this.espacos$;
  }

  listarCursos(): Observable<Curso[]> {
    this.cursos$ ??= this.http.get<Resposta<Curso[]>>(`${this.url}/cursos`).pipe(
      map((resposta) => resposta.data),
      shareReplay(1),
    );
    return this.cursos$;
  }

  consultarCalendario(espacoId: number, de: string, ate: string): Observable<Calendario> {
    const chave = `${espacoId}|${de}|${ate}`;
    let calendario$ = this.calendarios.get(chave);
    if (!calendario$) {
      calendario$ = this.http
        .get<Resposta<Calendario>>(`${this.url}/espacos/${espacoId}/disponibilidade`, {
          params: new HttpParams().set('de', de).set('ate', ate),
        })
        .pipe(
          map((resposta) => resposta.data),
          shareReplay(1),
        );
      this.calendarios.set(chave, calendario$);
    }
    return calendario$;
  }

  consultarCalendarioDoCurso(
    espacoId: number,
    de: string,
    ate: string,
    registroAcademico: string,
  ): Observable<Calendario> {
    return this.http
      .post<Resposta<Calendario>>(`${this.url}/espacos/${espacoId}/disponibilidade/curso`, {
        de,
        ate,
        registro_academico: registroAcademico,
      })
      .pipe(map((resposta) => resposta.data));
  }

  esquecerCalendarios(espacoId: number): void {
    for (const chave of [...this.calendarios.keys()]) {
      if (chave.startsWith(`${espacoId}|`)) {
        this.calendarios.delete(chave);
      }
    }
  }

  consultarEquipamentos(
    espacoId: number,
    data: string,
    inicio: string,
    fim: string,
  ): Observable<EquipamentoDisponivel[]> {
    return this.http
      .get<Resposta<EquipamentoDisponivel[]>>(
        `${this.url}/espacos/${espacoId}/equipamentos/disponibilidade`,
        {
          params: new HttpParams()
            .set('data', data)
            .set('hora_inicio', inicio)
            .set('hora_fim', fim),
        },
      )
      .pipe(map((resposta) => resposta.data));
  }

  reservar(reserva: NovaReserva): Observable<ReservaCriada> {
    return this.http.post<Resposta<ReservaCriada>>(`${this.url}/reservas`, reserva).pipe(
      map((resposta) => resposta.data),
      tap(() => this.esquecerCalendarios(reserva.espaco_id)),
    );
  }

  cadastrarAluno(aluno: NovoAluno): Observable<void> {
    return this.http.post<unknown>(`${this.url}/alunos`, aluno).pipe(map(() => undefined));
  }
}
