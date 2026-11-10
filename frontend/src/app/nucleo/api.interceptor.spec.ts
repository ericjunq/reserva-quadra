import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ambiente } from '../../ambientes/ambiente';
import { interceptorApi } from './api.interceptor';
import { ErroApi } from './erro-api';
import { SessaoService } from './sessao.service';

const url = `${ambiente.urlApi}/espacos`;

describe('interceptorApi', () => {
  let http: HttpClient;
  let controle: HttpTestingController;
  let sessao: SessaoService;

  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    http = TestBed.inject(HttpClient);
    controle = TestBed.inject(HttpTestingController);
    sessao = TestBed.inject(SessaoService);
  });

  afterEach(() => controle.verify());

  function falhar(status: number, corpo: object): ErroApi {
    let recebido: ErroApi | undefined;
    http.get(url).subscribe({ error: (erro: ErroApi) => (recebido = erro) });
    controle.expectOne(url).flush(corpo, { status, statusText: 'erro' });
    return recebido!;
  }

  it('pede JSON e não manda token sem sessão', () => {
    http.get(url).subscribe();
    const requisicao = controle.expectOne(url);

    expect(requisicao.request.headers.get('Accept')).toBe('application/json');
    expect(requisicao.request.headers.has('Authorization')).toBe(false);
    requisicao.flush({});
  });

  it('manda o token do admin para a API', () => {
    sessao.iniciar('abc', new Date(Date.now() + 3_600_000).toISOString());
    http.get(url).subscribe();
    const requisicao = controle.expectOne(url);

    expect(requisicao.request.headers.get('Authorization')).toBe('Bearer abc');
    requisicao.flush({});
  });

  it('nunca manda o token para outros endereços', () => {
    sessao.iniciar('abc', new Date(Date.now() + 3_600_000).toISOString());
    http.get('https://outro-site.com/api/x').subscribe();
    const requisicao = controle.expectOne('https://outro-site.com/api/x');

    expect(requisicao.request.headers.has('Authorization')).toBe(false);
    expect(requisicao.request.headers.has('Accept')).toBe(false);
    requisicao.flush({});
  });

  it('encerra a sessão quando a API responde 401', () => {
    sessao.iniciar('abc', new Date(Date.now() + 3_600_000).toISOString());
    const erro = falhar(401, { message: 'Unauthenticated.' });

    expect(erro.status).toBe(401);
    expect(erro.mensagem).toBe('Sua sessão terminou. Entre de novo para continuar.');
    expect(sessao.tokenValido()).toBeNull();
  });

  it('mostra a mensagem da API no 401 sem sessão (login recusado)', () => {
    const erro = falhar(401, { message: 'Credenciais inválidas' });

    expect(erro.mensagem).toBe('Credenciais inválidas');
  });

  it('entrega os erros por campo do 422', () => {
    const erro = falhar(422, {
      message: 'O campo email é obrigatório.',
      errors: { email: ['O campo email é obrigatório.'] },
    });

    expect(erro.mensagem).toBe('O campo email é obrigatório.');
    expect(erro.campos).toEqual({ email: ['O campo email é obrigatório.'] });
  });

  it('entrega a mensagem e os dados da regra de negócio (409)', () => {
    const erro = falhar(409, { message: 'Horário ocupado.', dados: { reserva_conflitante_id: 3 } });

    expect(erro.mensagem).toBe('Horário ocupado.');
    expect(erro.dados).toEqual({ reserva_conflitante_id: 3 });
    expect(erro.campos).toEqual({});
  });

  it('usa mensagem própria para o limite de requisições (429)', () => {
    expect(falhar(429, { message: 'Too Many Attempts.' }).mensagem).toBe(
      'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.',
    );
  });

  it('não mostra detalhes de erro do servidor (500)', () => {
    expect(falhar(500, { message: 'SQLSTATE[HY000] ...' }).mensagem).toBe(
      'Ocorreu um erro no servidor. Tente de novo em instantes.',
    );
  });

  it('avisa quando não consegue conectar', () => {
    let recebido: ErroApi | undefined;
    http.get(url).subscribe({ error: (erro: ErroApi) => (recebido = erro) });
    controle.expectOne(url).error(new ProgressEvent('error'));

    expect(recebido!.status).toBe(0);
    expect(recebido!.mensagem).toContain('Não foi possível conectar');
  });
});
