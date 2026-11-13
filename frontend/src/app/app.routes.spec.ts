import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Title } from '@angular/platform-browser';
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { routes } from './app.routes';

const CARREGAMENTO_SOB_DEMANDA = 90_000;

describe('rotas', () => {
  let harness: RouterTestingHarness;

  beforeEach(async () => {
    TestBed.configureTestingModule({
      providers: [provideRouter(routes), provideHttpClient(), provideHttpClientTesting()],
    });
    harness = await RouterTestingHarness.create();
  });

  async function abrirInicio() {
    const navegacao = harness.navigateByUrl('/');
    const controle = TestBed.inject(HttpTestingController);
    await vi.waitFor(() => controle.expectOne(() => true).flush({ data: [] }), {
      timeout: CARREGAMENTO_SOB_DEMANDA,
    });
    await navegacao;
  }

  it(
    'abre o início dentro do layout público',
    async () => {
      await abrirInicio();

      expect(harness.fixture.nativeElement.querySelector('app-layout-publico')).not.toBeNull();
      expect(harness.fixture.nativeElement.querySelector('h1')?.textContent).toContain(
        'Reserve a quadra',
      );
      expect(TestBed.inject(Title).getTitle()).toBe('Sistema de Reservas');
    },
    CARREGAMENTO_SOB_DEMANDA,
  );

  it('abre o painel dentro do layout do admin, sem o layout público', async () => {
    await harness.navigateByUrl('/admin');
    const pagina = harness.fixture.nativeElement as HTMLElement;

    expect(pagina.querySelector('app-layout-admin')).not.toBeNull();
    expect(pagina.querySelector('app-layout-publico')).toBeNull();
    expect(pagina.querySelector('h1')?.textContent).toContain('Painel');
  });

  it('mostra a página não encontrada para endereços desconhecidos', async () => {
    await harness.navigateByUrl('/endereco-que-nao-existe');

    expect(harness.fixture.nativeElement.querySelector('h1')?.textContent).toContain(
      'Página não encontrada',
    );
  });

  it(
    'tem o link para pular direto ao conteúdo',
    async () => {
      await abrirInicio();

      expect(harness.fixture.nativeElement.querySelector('a[href="#conteudo"]')).not.toBeNull();
      expect(harness.fixture.nativeElement.querySelector('main#conteudo')).not.toBeNull();
    },
    CARREGAMENTO_SOB_DEMANDA,
  );
});
