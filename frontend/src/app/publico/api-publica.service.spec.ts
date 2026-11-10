import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ambiente } from '../../ambientes/ambiente';
import { ApiPublicaService } from './api-publica.service';

const url = ambiente.urlApi;

describe('ApiPublicaService', () => {
  let api: ApiPublicaService;
  let controle: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    api = TestBed.inject(ApiPublicaService);
    controle = TestBed.inject(HttpTestingController);
  });

  afterEach(() => controle.verify());

  it('busca os espaços uma vez só por visita', () => {
    api.listarEspacos().subscribe();
    api.listarEspacos().subscribe();

    controle.expectOne(`${url}/espacos`).flush({ data: [] });
  });

  it('guarda cada semana do calendário já carregada', () => {
    api.consultarCalendario(1, '2026-11-09', '2026-11-13').subscribe();
    api.consultarCalendario(1, '2026-11-09', '2026-11-13').subscribe();

    const requisicao = controle.expectOne(
      `${url}/espacos/1/disponibilidade?de=2026-11-09&ate=2026-11-13`,
    );
    expect(requisicao.request.method).toBe('GET');
    requisicao.flush({ data: {} });
  });

  it('manda o RA só no corpo de um POST, nunca na URL', () => {
    api.consultarCalendarioDoCurso(1, '2026-11-09', '2026-11-13', '2024000001').subscribe();

    const requisicao = controle.expectOne(`${url}/espacos/1/disponibilidade/curso`);
    expect(requisicao.request.method).toBe('POST');
    expect(requisicao.request.urlWithParams).not.toContain('2024000001');
    expect(requisicao.request.body).toEqual({
      de: '2026-11-09',
      ate: '2026-11-13',
      registro_academico: '2024000001',
    });
    requisicao.flush({ data: {} });
  });

  it('recarrega o calendário do espaço depois de uma reserva', () => {
    api.consultarCalendario(1, '2026-11-09', '2026-11-13').subscribe();
    controle.expectOne(() => true).flush({ data: {} });

    api
      .reservar({
        registro_academico: '2024000001',
        espaco_id: 1,
        data_reserva: '2026-11-10',
        horario: 'PRIMEIRO_HORARIO',
      })
      .subscribe();
    const reserva = controle.expectOne(`${url}/reservas`);
    expect(reserva.request.method).toBe('POST');
    reserva.flush({ data: { id: 1 } });

    api.consultarCalendario(1, '2026-11-09', '2026-11-13').subscribe();
    controle.expectOne(`${url}/espacos/1/disponibilidade?de=2026-11-09&ate=2026-11-13`).flush({
      data: {},
    });
  });

  it('consulta os equipamentos de um intervalo', () => {
    api
      .consultarEquipamentos(4, '2026-11-11', '14:00', '15:30')
      .subscribe((equipamentos) => expect(equipamentos).toEqual([]));

    controle
      .expectOne(
        `${url}/espacos/4/equipamentos/disponibilidade?data=2026-11-11&hora_inicio=14:00&hora_fim=15:30`,
      )
      .flush({ data: [] });
  });

  it('cadastra o aluno pelo corpo da requisição', () => {
    const aluno = {
      nome_completo: 'Ana Souza',
      registro_academico: '2024000001',
      email: 'ana@email.com',
      curso_id: 2,
    };
    api.cadastrarAluno(aluno).subscribe();

    const requisicao = controle.expectOne(`${url}/alunos`);
    expect(requisicao.request.method).toBe('POST');
    expect(requisicao.request.body).toEqual(aluno);
    requisicao.flush({ data: {} });
  });
});
