import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { Privacidade } from './privacidade';

describe('Privacidade', () => {
  it('mostra os termos de uso e o aviso de privacidade vindos da API, com o aviso de rascunho', async () => {
    TestBed.configureTestingModule({
      imports: [Privacidade],
      providers: [
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    const controle = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(Privacidade);

    await vi.waitFor(() => {
      fixture.detectChanges();
      controle.expectOne(`${ambiente.urlApi}/termos`).flush({
        data: {
          versao: '2026-11',
          rascunho: true,
          termos_de_uso: [{ titulo: 'Regras de uso', paragrafos: ['Use no horário reservado.'] }],
          privacidade: [
            { titulo: 'Os seus direitos', paragrafos: ['Você pode pedir a anonimização.'] },
          ],
        },
      });
    });
    fixture.detectChanges();
    const pagina = fixture.nativeElement as HTMLElement;

    expect(pagina.querySelector('#termos')?.textContent).toContain('Use no horário reservado.');
    expect(pagina.querySelector('#privacidade')?.textContent).toContain(
      'Você pode pedir a anonimização.',
    );
    expect(pagina.textContent).toContain(
      'Texto provisório, em validação pela instituição. Versão 2026-11.',
    );
    controle.verify();
  });
});
