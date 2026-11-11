import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { DestroyRef } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ambiente } from '../../ambientes/ambiente';
import {
  AtualizacaoDisponibilidadeService,
  INTERVALO_DE_ATUALIZACAO_MS,
} from './atualizacao-disponibilidade.service';

const url = `${ambiente.urlApi}/disponibilidade/versao`;

describe('AtualizacaoDisponibilidadeService', () => {
  let controle: HttpTestingController;
  let servico: AtualizacaoDisponibilidadeService;
  let destruir: () => void;

  beforeEach(() => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    controle = TestBed.inject(HttpTestingController);
    servico = TestBed.inject(AtualizacaoDisponibilidadeService);
    const aoDestruir: (() => void)[] = [];
    destruir = () => aoDestruir.forEach((funcao) => funcao());
    servico.observar({
      onDestroy: (funcao: () => void) => aoDestruir.push(funcao),
    } as unknown as DestroyRef);
  });

  afterEach(() => {
    destruir();
    controle.verify();
    vi.useRealTimers();
  });

  it('consulta a versão ao abrir e a cada intervalo, avisando só quando ela muda', () => {
    controle.expectOne(url).flush({ data: { versao: 'a' } });
    expect(servico.versao()).toBe(0);

    vi.advanceTimersByTime(INTERVALO_DE_ATUALIZACAO_MS);
    controle.expectOne(url).flush({ data: { versao: 'a' } });
    expect(servico.versao()).toBe(0);

    vi.advanceTimersByTime(INTERVALO_DE_ATUALIZACAO_MS);
    controle.expectOne(url).flush({ data: { versao: 'b' } });
    expect(servico.versao()).toBe(1);
  });

  it('para de consultar quando a página sai da tela', () => {
    controle.expectOne(url).flush({ data: { versao: 'a' } });
    destruir();

    vi.advanceTimersByTime(INTERVALO_DE_ATUALIZACAO_MS * 3);
    controle.expectNone(url);
  });

  it('não acumula consultas se a anterior ainda não respondeu', () => {
    vi.advanceTimersByTime(INTERVALO_DE_ATUALIZACAO_MS * 3);
    expect(controle.match(url).length).toBe(1);
    controle.match(url).forEach((requisicao) => requisicao.flush({ data: { versao: 'a' } }));
  });
});
