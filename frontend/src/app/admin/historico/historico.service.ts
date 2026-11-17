import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ambiente } from '../../../ambientes/ambiente';
import { AdminCadastro, Pagina } from '../cadastros/modelos';
import { Auditoria, FiltrosHistorico } from './modelos';

@Injectable({ providedIn: 'root' })
export class HistoricoService {
  private readonly http = inject(HttpClient);
  private readonly url = `${ambiente.urlApi}/admin`;

  listar(
    filtros: FiltrosHistorico,
    pagina: number,
    porPagina?: number,
  ): Observable<Pagina<Auditoria>> {
    let params = new HttpParams().set('page', pagina);
    if (porPagina) {
      params = params.set('per_page', porPagina);
    }
    for (const [chave, valor] of Object.entries(filtros)) {
      if (valor !== undefined && valor !== null && valor !== '') {
        params = params.set(chave, typeof valor === 'boolean' ? (valor ? 1 : 0) : valor);
      }
    }
    return this.http.get<Pagina<Auditoria>>(`${this.url}/auditorias`, { params });
  }

  listarAdmins(): Observable<{ id: number; nome: string }[]> {
    return this.http
      .post<Pagina<AdminCadastro>>(`${this.url}/admins/consulta`, { page: 1, per_page: 100 })
      .pipe(map((pagina) => pagina.data.map(({ id, nome }) => ({ id, nome }))));
  }
}
