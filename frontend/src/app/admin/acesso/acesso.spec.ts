import { Location } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { SessaoService } from '../../nucleo/sessao.service';
import { AcessoService } from './acesso.service';
import { exigirLogin, somenteSemLogin } from './admin.guard';
import { DefinirSenha } from './definir-senha';
import { Entrar } from './entrar';
import { EsqueciSenha } from './esqueci-senha';
import { LayoutAdmin } from '../layout-admin/layout-admin';
import { MinhaConta } from './minha-conta';

const url = ambiente.urlApi;
const daquiAUmaHora = () => new Date(Date.now() + 3_600_000).toISOString();
const admin = { id: 1, nome: 'Eric', email: 'eric@fac.edu', principal: true };

@Component({ template: '' })
class Vazio {}

describe('acesso do admin', () => {
  let controle: HttpTestingController;
  let sessao: SessaoService;
  let router: Router;

  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: 'admin/entrar', component: Vazio },
          { path: 'admin', component: Vazio, canActivate: [exigirLogin] },
          { path: 'admin/reservas', component: Vazio, canActivate: [exigirLogin] },
          { path: 'so-sem-login', component: Vazio, canActivate: [somenteSemLogin] },
        ]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
    sessao = TestBed.inject(SessaoService);
    router = TestBed.inject(Router);
  });

  afterEach(() => {
    controle.verify();
    sessionStorage.clear();
  });

  function digitar(elemento: HTMLElement, id: string, valor: string) {
    const campo = elemento.querySelector<HTMLInputElement>(`#${id}`)!;
    campo.value = valor;
    campo.dispatchEvent(new Event('input'));
  }

  async function enviar(fixture: {
    nativeElement: HTMLElement;
    detectChanges(): void;
    whenStable(): Promise<unknown>;
  }) {
    fixture.nativeElement.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();
    await fixture.whenStable();
  }

  describe('guardas', () => {
    it('sem login, vai para entrar com o caminho de volta; com login, passa', async () => {
      await router.navigateByUrl('/admin/reservas?status=RESERVADO');
      expect(router.url).toBe('/admin/entrar?voltar=%2Fadmin%2Freservas%3Fstatus%3DRESERVADO');

      sessao.iniciar('abc', daquiAUmaHora());
      await router.navigateByUrl('/admin/reservas');
      expect(router.url).toBe('/admin/reservas');

      await router.navigateByUrl('/so-sem-login');
      expect(router.url).toBe('/admin');
    });
  });

  describe('service', () => {
    it('entrar guarda a sessão e sair a apaga mesmo se a API falhar', () => {
      const acesso = TestBed.inject(AcessoService);
      acesso.entrar('eric@fac.edu', 'Senha1234').subscribe();
      const login = controle.expectOne(`${url}/login`);
      expect(login.request.body).toEqual({ email: 'eric@fac.edu', password: 'Senha1234' });
      login.flush({ token: 'tok', expires_at: daquiAUmaHora(), user: admin });
      expect(sessao.tokenValido()).toBe('tok');

      acesso.eu().subscribe();
      acesso.eu().subscribe();
      const eu = controle.expectOne(`${url}/me`);
      expect(eu.request.headers.get('Authorization')).toBe('Bearer tok');
      eu.flush({ data: admin });

      acesso.sair().subscribe({ error: () => undefined });
      controle.expectOne(`${url}/logout`).flush({}, { status: 500, statusText: 'erro' });
      expect(sessao.tokenValido()).toBeNull();
    });
  });

  describe('Entrar', () => {
    it('entra e volta para o caminho interno pedido', async () => {
      const navegar = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
      const fixture = TestBed.createComponent(Entrar);
      fixture.componentRef.setInput('voltar', '/admin/reservas');
      fixture.detectChanges();
      await fixture.whenStable();
      digitar(fixture.nativeElement, 'email', ' eric@fac.edu ');
      digitar(fixture.nativeElement, 'senha', 'Senha1234');
      await enviar(fixture);

      const login = controle.expectOne(`${url}/login`);
      expect(login.request.body).toEqual({ email: 'eric@fac.edu', password: 'Senha1234' });
      login.flush({ token: 'tok', expires_at: daquiAUmaHora(), user: admin });
      expect(navegar).toHaveBeenCalledWith('/admin/reservas');
    });

    it('ignora caminho externo e mostra o erro genérico da API', async () => {
      const navegar = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
      const fixture = TestBed.createComponent(Entrar);
      fixture.componentRef.setInput('voltar', 'https://site-falso.com');
      fixture.componentRef.setInput('motivo', 'sessao');
      fixture.detectChanges();
      await fixture.whenStable();
      expect(fixture.nativeElement.textContent).toContain('Sua sessão terminou.');

      digitar(fixture.nativeElement, 'email', 'eric@fac.edu');
      digitar(fixture.nativeElement, 'senha', 'errada');
      await enviar(fixture);
      controle
        .expectOne(`${url}/login`)
        .flush({ message: 'Credenciais inválidas' }, { status: 401, statusText: 'erro' });
      fixture.detectChanges();
      await fixture.whenStable();
      expect(fixture.nativeElement.textContent).toContain('Credenciais inválidas');
      expect(
        (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>('#senha')!.value,
      ).toBe('');

      digitar(fixture.nativeElement, 'senha', 'Senha1234');
      await enviar(fixture);
      controle
        .expectOne(`${url}/login`)
        .flush({ token: 'tok', expires_at: daquiAUmaHora(), user: admin });
      expect(navegar).toHaveBeenCalledWith('/admin');
    });
  });

  describe('EsqueciSenha', () => {
    it('mostra a resposta genérica da API', async () => {
      const fixture = TestBed.createComponent(EsqueciSenha);
      fixture.detectChanges();
      await fixture.whenStable();
      digitar(fixture.nativeElement, 'email', 'alguem@fac.edu');
      await enviar(fixture);
      controle.expectOne(`${url}/esqueci-senha`).flush({
        message: 'Se o email estiver cadastrado, você receberá um link para redefinir a senha.',
      });
      fixture.detectChanges();
      expect(fixture.nativeElement.textContent).toContain('Se o email estiver cadastrado');
    });
  });

  describe('DefinirSenha', () => {
    it('aceita o convite com token, email e senha, tirando o token do endereço', async () => {
      const trocarEndereco = vi.spyOn(TestBed.inject(Location), 'replaceState');
      const fixture = TestBed.createComponent(DefinirSenha);
      fixture.componentRef.setInput('modo', 'convite');
      fixture.componentRef.setInput('token', 'abc123');
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();
      expect(trocarEndereco).toHaveBeenCalledWith('/aceitar-convite');

      const elemento = fixture.nativeElement as HTMLElement;
      const salvar = () =>
        [...elemento.querySelectorAll<HTMLButtonElement>('button')].find(
          (botao) => botao.textContent?.trim() === 'Salvar a senha',
        )!;
      digitar(elemento, 'email', 'ana@fac.edu');
      digitar(elemento, 'senha', 'curta');
      digitar(elemento, 'confirmacao', 'curta');
      fixture.detectChanges();
      expect(salvar().disabled).toBe(true);
      expect(elemento.textContent).toContain('Pelo menos 8 caracteres');

      digitar(elemento, 'senha', 'Senha1234');
      digitar(elemento, 'confirmacao', 'Senha1234');
      fixture.detectChanges();
      expect(salvar().disabled).toBe(false);
      await enviar(fixture);

      const pedido = controle.expectOne(`${url}/convites/aceitar`);
      expect(pedido.request.body).toEqual({
        token: 'abc123',
        email: 'ana@fac.edu',
        password: 'Senha1234',
        password_confirmation: 'Senha1234',
      });
      pedido.flush({ message: 'Senha definida. Seu acesso está liberado.' });
      fixture.detectChanges();
      expect(elemento.textContent).toContain('Senha definida. Seu acesso está liberado.');
    });

    it('sem token, pede para abrir pelo email', async () => {
      const fixture = TestBed.createComponent(DefinirSenha);
      fixture.componentRef.setInput('modo', 'redefinir');
      fixture.detectChanges();
      await fixture.whenStable();
      fixture.detectChanges();

      expect(fixture.nativeElement.textContent).toContain('Abra de novo pelo botão do email.');
    });
  });

  describe('MinhaConta', () => {
    it('mostra os dados, troca a senha e sai de todos os dispositivos', async () => {
      sessao.iniciar('tok', daquiAUmaHora());
      const navegar = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
      const fixture = TestBed.createComponent(MinhaConta);
      fixture.detectChanges();
      await fixture.whenStable();
      controle.expectOne(`${url}/me`).flush({ data: admin });
      fixture.detectChanges();
      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).toContain('eric@fac.edu');

      digitar(elemento, 'senha-atual', 'Antiga123');
      digitar(elemento, 'senha-nova', 'Nova12345');
      digitar(elemento, 'confirmacao', 'Nova12345');
      await enviar(fixture);
      const troca = controle.expectOne(`${url}/me/senha`);
      expect(troca.request.method).toBe('PATCH');
      expect(troca.request.body).toEqual({
        senha_atual: 'Antiga123',
        password: 'Nova12345',
        password_confirmation: 'Nova12345',
      });
      troca.flush({
        message: 'Senha alterada. As sessões em outros dispositivos foram encerradas.',
      });
      fixture.detectChanges();
      expect(elemento.textContent).toContain('Senha alterada.');

      [...elemento.querySelectorAll<HTMLButtonElement>('button')]
        .find((botao) => botao.textContent?.includes('Sair de todos'))!
        .click();
      controle.expectOne(`${url}/logout-geral`).flush({});
      expect(sessao.tokenValido()).toBeNull();
      expect(navegar).toHaveBeenCalledWith('/admin/entrar');
    });
  });

  describe('LayoutAdmin', () => {
    it('volta para entrar com o aviso quando a sessão termina, e sem aviso ao sair', async () => {
      sessao.iniciar('tok', daquiAUmaHora());
      const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
      const navegarPorUrl = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
      const fixture = TestBed.createComponent(LayoutAdmin);
      fixture.detectChanges();
      controle.expectOne(`${url}/me`).flush({ data: admin });
      fixture.detectChanges();
      expect(fixture.nativeElement.textContent).toContain('Eric');

      sessao.encerrar();
      fixture.detectChanges();
      await fixture.whenStable();
      expect(navegar).toHaveBeenCalledWith(['/admin/entrar'], {
        queryParams: { voltar: '/', motivo: 'sessao' },
      });

      fixture.destroy();
      navegar.mockClear();
      sessao.iniciar('tok', daquiAUmaHora());
      const outro = TestBed.createComponent(LayoutAdmin);
      outro.detectChanges();
      [...(outro.nativeElement as HTMLElement).querySelectorAll('button')]
        .find((botao) => botao.textContent?.trim() === 'Sair')!
        .click();
      controle.expectOne(`${url}/logout`).flush({});
      outro.detectChanges();
      await outro.whenStable();
      expect(navegarPorUrl).toHaveBeenCalledWith('/admin/entrar');
      expect(navegar).not.toHaveBeenCalled();
    });
  });
});
