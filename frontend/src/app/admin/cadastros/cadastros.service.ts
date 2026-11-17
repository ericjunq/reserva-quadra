import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ambiente } from '../../../ambientes/ambiente';
import {
  DadosDoEquipamento,
  DadosDoEspaco,
  EquipamentoCadastro,
  EspacoCadastro,
  Pagina,
} from './modelos';

interface Resposta<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class CadastrosService {
  private readonly http = inject(HttpClient);
  private readonly url = `${ambiente.urlApi}/admin`;

  listarEspacos(pagina: number, tipo?: string): Observable<Pagina<EspacoCadastro>> {
    let params = new HttpParams().set('page', pagina);
    if (tipo) {
      params = params.set('tipo', tipo);
    }
    return this.http.get<Pagina<EspacoCadastro>>(`${this.url}/espacos`, { params });
  }

  detalharEspaco(id: number): Observable<EspacoCadastro> {
    return this.dados(this.http.get<Resposta<EspacoCadastro>>(`${this.url}/espacos/${id}`));
  }

  criarEspaco(espaco: DadosDoEspaco): Observable<EspacoCadastro> {
    return this.dados(this.http.post<Resposta<EspacoCadastro>>(`${this.url}/espacos`, espaco));
  }

  atualizarEspaco(id: number, espaco: Partial<EspacoCadastro>): Observable<EspacoCadastro> {
    return this.dados(
      this.http.patch<Resposta<EspacoCadastro>>(`${this.url}/espacos/${id}`, espaco),
    );
  }

  desativarEspaco(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/espacos/${id}`);
  }

  listarEquipamentos(
    pagina: number,
    filtros: { espaco_id?: number; ativo?: boolean },
  ): Observable<Pagina<EquipamentoCadastro>> {
    let params = new HttpParams().set('page', pagina);
    if (filtros.espaco_id) {
      params = params.set('espaco_id', filtros.espaco_id);
    }
    if (filtros.ativo !== undefined) {
      params = params.set('ativo', filtros.ativo ? 1 : 0);
    }
    return this.http.get<Pagina<EquipamentoCadastro>>(`${this.url}/equipamentos`, { params });
  }

  detalharEquipamento(id: number): Observable<EquipamentoCadastro> {
    return this.dados(
      this.http.get<Resposta<EquipamentoCadastro>>(`${this.url}/equipamentos/${id}`),
    );
  }

  criarEquipamento(equipamento: DadosDoEquipamento): Observable<EquipamentoCadastro> {
    return this.dados(
      this.http.post<Resposta<EquipamentoCadastro>>(`${this.url}/equipamentos`, equipamento),
    );
  }

  atualizarEquipamento(
    id: number,
    equipamento: Partial<DadosDoEquipamento> & { ativo?: boolean },
  ): Observable<EquipamentoCadastro> {
    return this.dados(
      this.http.patch<Resposta<EquipamentoCadastro>>(`${this.url}/equipamentos/${id}`, equipamento),
    );
  }

  desativarEquipamento(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/equipamentos/${id}`);
  }

  private dados<T>(pedido: Observable<Resposta<T>>): Observable<T> {
    return pedido.pipe(map((resposta) => resposta.data));
  }
}
