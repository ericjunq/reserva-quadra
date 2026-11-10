import { TestBed } from '@angular/core/testing';
import { SessaoService } from './sessao.service';

const CHAVE = 'reservas.sessao';
const daquiAUmaHora = () => new Date(Date.now() + 3_600_000).toISOString();

describe('SessaoService', () => {
  beforeEach(() => sessionStorage.clear());

  it('começa sem token', () => {
    expect(TestBed.inject(SessaoService).tokenValido()).toBeNull();
  });

  it('guarda o token no sessionStorage e nunca no localStorage', () => {
    const sessao = TestBed.inject(SessaoService);
    sessao.iniciar('abc', daquiAUmaHora());

    expect(sessao.tokenValido()).toBe('abc');
    expect(sessionStorage.getItem(CHAVE)).toContain('abc');
    expect(localStorage.length).toBe(0);
  });

  it('recupera a sessão guardada ao recarregar a página', () => {
    sessionStorage.setItem(CHAVE, JSON.stringify({ token: 'abc', expiraEm: daquiAUmaHora() }));

    expect(TestBed.inject(SessaoService).tokenValido()).toBe('abc');
  });

  it('descarta a sessão guardada que já venceu', () => {
    sessionStorage.setItem(
      CHAVE,
      JSON.stringify({ token: 'abc', expiraEm: '2000-01-01T00:00:00Z' }),
    );

    expect(TestBed.inject(SessaoService).tokenValido()).toBeNull();
    expect(sessionStorage.getItem(CHAVE)).toBeNull();
  });

  it('descarta conteúdo inválido guardado', () => {
    sessionStorage.setItem(CHAVE, '{quebrado');

    expect(TestBed.inject(SessaoService).tokenValido()).toBeNull();
  });

  it('encerra a sessão quando o token vence durante o uso', () => {
    const sessao = TestBed.inject(SessaoService);
    sessao.iniciar('abc', new Date(Date.now() + 50).toISOString());
    vi.useFakeTimers();
    vi.setSystemTime(Date.now() + 1000);

    expect(sessao.tokenValido()).toBeNull();
    expect(sessionStorage.getItem(CHAVE)).toBeNull();
    vi.useRealTimers();
  });

  it('encerrar apaga o token', () => {
    const sessao = TestBed.inject(SessaoService);
    sessao.iniciar('abc', daquiAUmaHora());
    sessao.encerrar();

    expect(sessao.tokenValido()).toBeNull();
    expect(sessionStorage.getItem(CHAVE)).toBeNull();
  });
});
