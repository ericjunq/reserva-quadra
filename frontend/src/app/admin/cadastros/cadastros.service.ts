import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { ambiente } from '../../../ambientes/ambiente';
import {
  AdminCadastro,
  AlunoCadastro,
  ClienteApiCadastro,
  CursoCadastro,
  DadosDoAluno,
  DadosDoClienteApi,
  DadosDoBloqueio,
  DataBloqueadaCadastro,
  DadosDoEquipamento,
  DadosDoEspaco,
  EquipamentoCadastro,
  EspacoCadastro,
  DadosDaMovimentacao,
  MovimentacaoEstoque,
  OpcaoDePermissao,
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

  listarMovimentacoes(
    equipamentoId: number,
    pagina: number,
  ): Observable<Pagina<MovimentacaoEstoque>> {
    return this.http.get<Pagina<MovimentacaoEstoque>>(
      `${this.url}/equipamentos/${equipamentoId}/movimentacoes`,
      { params: new HttpParams().set('page', pagina) },
    );
  }

  movimentarEstoque(
    equipamentoId: number,
    dados: DadosDaMovimentacao,
  ): Observable<{ movimentacao: MovimentacaoEstoque; equipamento: EquipamentoCadastro }> {
    return this.http
      .post<{ data: MovimentacaoEstoque; equipamento: EquipamentoCadastro }>(
        `${this.url}/equipamentos/${equipamentoId}/movimentacoes`,
        dados,
      )
      .pipe(
        map((resposta) => ({ movimentacao: resposta.data, equipamento: resposta.equipamento })),
      );
  }

  listarCursos(pagina: number): Observable<Pagina<CursoCadastro>> {
    return this.http.get<Pagina<CursoCadastro>>(`${this.url}/cursos`, {
      params: new HttpParams().set('page', pagina),
    });
  }

  criarCurso(nome: string): Observable<CursoCadastro> {
    return this.dados(this.http.post<Resposta<CursoCadastro>>(`${this.url}/cursos`, { nome }));
  }

  atualizarCurso(id: number, curso: { nome?: string; ativo?: boolean }): Observable<CursoCadastro> {
    return this.dados(this.http.patch<Resposta<CursoCadastro>>(`${this.url}/cursos/${id}`, curso));
  }

  desativarCurso(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/cursos/${id}`);
  }

  listarBloqueios(
    pagina: number,
    filtros: { de?: string; ate?: string; espaco_id?: number },
  ): Observable<Pagina<DataBloqueadaCadastro>> {
    let params = new HttpParams().set('page', pagina);
    for (const [chave, valor] of Object.entries(filtros)) {
      if (valor) {
        params = params.set(chave, valor);
      }
    }
    return this.http.get<Pagina<DataBloqueadaCadastro>>(`${this.url}/datas-bloqueadas`, { params });
  }

  criarBloqueio(
    bloqueio: DadosDoBloqueio,
  ): Observable<{ bloqueio: DataBloqueadaCadastro; revogadas: number }> {
    return this.http
      .post<Resposta<DataBloqueadaCadastro> & { reservas_revogadas: number }>(
        `${this.url}/datas-bloqueadas`,
        bloqueio,
      )
      .pipe(
        map((resposta) => ({ bloqueio: resposta.data, revogadas: resposta.reservas_revogadas })),
      );
  }

  atualizarMotivo(id: number, motivo: string): Observable<DataBloqueadaCadastro> {
    return this.dados(
      this.http.patch<Resposta<DataBloqueadaCadastro>>(`${this.url}/datas-bloqueadas/${id}`, {
        motivo,
      }),
    );
  }

  excluirBloqueio(id: number): Observable<void> {
    return this.http.delete<void>(`${this.url}/datas-bloqueadas/${id}`);
  }

  consultarAlunos(
    filtros: { busca?: string; curso_id?: number; ativo?: boolean },
    pagina: number,
  ): Observable<Pagina<AlunoCadastro>> {
    return this.http.post<Pagina<AlunoCadastro>>(`${this.url}/alunos/consulta`, {
      ...filtros,
      page: pagina,
    });
  }

  detalharAluno(id: number): Observable<AlunoCadastro> {
    return this.dados(this.http.get<Resposta<AlunoCadastro>>(`${this.url}/alunos/${id}`));
  }

  atualizarAluno(
    id: number,
    aluno: Partial<DadosDoAluno> & { ativo?: boolean },
  ): Observable<{ aluno: AlunoCadastro; revogadas: number }> {
    return this.http
      .patch<Resposta<AlunoCadastro> & { reservas_revogadas?: number }>(
        `${this.url}/alunos/${id}`,
        aluno,
      )
      .pipe(
        map((resposta) => ({ aluno: resposta.data, revogadas: resposta.reservas_revogadas ?? 0 })),
      );
  }

  desativarAluno(id: number): Observable<{ aluno: AlunoCadastro; revogadas: number }> {
    return this.http
      .delete<Resposta<AlunoCadastro> & { reservas_revogadas: number }>(`${this.url}/alunos/${id}`)
      .pipe(map((resposta) => ({ aluno: resposta.data, revogadas: resposta.reservas_revogadas })));
  }

  anonimizarAluno(id: number): Observable<AlunoCadastro> {
    return this.dados(
      this.http.post<Resposta<AlunoCadastro>>(`${this.url}/alunos/${id}/anonimizar`, {
        confirmar: true,
      }),
    );
  }

  consultarAdmins(
    filtros: { busca?: string; ativo?: boolean },
    pagina: number,
  ): Observable<Pagina<AdminCadastro>> {
    return this.http.post<Pagina<AdminCadastro>>(`${this.url}/admins/consulta`, {
      ...filtros,
      page: pagina,
    });
  }

  detalharAdmin(id: number): Observable<AdminCadastro> {
    return this.dados(this.http.get<Resposta<AdminCadastro>>(`${this.url}/admins/${id}`));
  }

  convidarAdmin(nome: string, email: string): Observable<AdminCadastro> {
    return this.dados(
      this.http.post<Resposta<AdminCadastro>>(`${this.url}/admins`, { nome, email }),
    );
  }

  atualizarAdmin(
    id: number,
    admin: { nome?: string; email?: string; ativo?: boolean },
  ): Observable<AdminCadastro> {
    return this.dados(this.http.patch<Resposta<AdminCadastro>>(`${this.url}/admins/${id}`, admin));
  }

  desativarAdmin(id: number): Observable<AdminCadastro> {
    return this.dados(this.http.delete<Resposta<AdminCadastro>>(`${this.url}/admins/${id}`));
  }

  reenviarConvite(id: number): Observable<string> {
    return this.http
      .post<{ message: string }>(`${this.url}/admins/${id}/reenviar-convite`, {})
      .pipe(map((resposta) => resposta.message));
  }

  listarClientesApi(
    filtros: { busca?: string; ativo?: boolean },
    pagina: number,
  ): Observable<Pagina<ClienteApiCadastro>> {
    let params = new HttpParams().set('page', pagina);
    if (filtros.busca) {
      params = params.set('busca', filtros.busca);
    }
    if (filtros.ativo !== undefined) {
      params = params.set('ativo', filtros.ativo ? 1 : 0);
    }
    return this.http.get<Pagina<ClienteApiCadastro>>(`${this.url}/clientes-api`, { params });
  }

  listarPermissoesApi(): Observable<OpcaoDePermissao[]> {
    return this.dados(
      this.http.get<Resposta<OpcaoDePermissao[]>>(`${this.url}/clientes-api/permissoes`),
    );
  }

  detalharClienteApi(id: number): Observable<ClienteApiCadastro> {
    return this.dados(
      this.http.get<Resposta<ClienteApiCadastro>>(`${this.url}/clientes-api/${id}`),
    );
  }

  criarClienteApi(cliente: DadosDoClienteApi): Observable<ClienteApiCadastro> {
    return this.dados(
      this.http.post<Resposta<ClienteApiCadastro>>(`${this.url}/clientes-api`, cliente),
    );
  }

  atualizarClienteApi(id: number, cliente: DadosDoClienteApi): Observable<ClienteApiCadastro> {
    return this.dados(
      this.http.patch<Resposta<ClienteApiCadastro>>(`${this.url}/clientes-api/${id}`, cliente),
    );
  }

  desativarClienteApi(id: number): Observable<ClienteApiCadastro> {
    return this.dados(
      this.http.delete<Resposta<ClienteApiCadastro>>(`${this.url}/clientes-api/${id}`),
    );
  }

  enviarAceiteClienteApi(id: number, revogarAtual: boolean): Observable<ClienteApiCadastro> {
    return this.dados(
      this.http.post<Resposta<ClienteApiCadastro>>(`${this.url}/clientes-api/${id}/enviar-aceite`, {
        revogar_atual: revogarAtual,
      }),
    );
  }

  private dados<T>(pedido: Observable<Resposta<T>>): Observable<T> {
    return pedido.pipe(map((resposta) => resposta.data));
  }
}
