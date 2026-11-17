import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { SessaoService } from '../../nucleo/sessao.service';
import { Historico, filtrosDaUrl } from './historico';
import { HistoricoDoRegistro } from './historico-do-registro';
import { Auditoria } from './modelos';
import { formatarQuando, formatarValor, linkDoRegistro, mudancasDe } from './rotulos';

const url = ambiente.urlApi;

const revogacao: Auditoria = {
  id: 31,
  tipo: 'RESERVA',
  registro_id: 7,
  acao: 'ATUALIZACAO',
  campos_alterados: ['status', 'revogada_em', 'justificativa_revogacao'],
  antes: { status: 'RESERVADO', revogada_em: null, justificativa_revogacao: null },
  depois: {
    status: 'REVOGADO',
    revogada_em: '2026-11-09 10:00:00',
    justificativa_revogacao: 'Manutenção da quadra',
  },
  feito_por: { id: 1, nome: 'Eric' },
  em: '2026-11-09T13:00:00.000000Z',
};

const anonimizacao: Auditoria = {
  id: 30,
  tipo: 'ALUNO',
  registro_id: 3,
  acao: 'ATUALIZACAO',
  campos_alterados: ['nome_completo', 'ativo'],
  antes: { nome_completo: '[anonimizado]', ativo: true },
  depois: { nome_completo: '[anonimizado]', ativo: false },
  feito_por: null,
  em: '2026-11-08T12:30:00.000000Z',
};

function pagina(data: Auditoria[], total = data.length, ultima = 1) {
  return { data, meta: { current_page: 1, last_page: ultima, total, per_page: 15 } };
}

describe('rótulos do histórico', () => {
  it('formata valores, datas e códigos de forma legível', () => {
    expect(formatarValor('ativo', true)).toBe('Sim');
    expect(formatarValor('ativo', false)).toBe('Não');
    expect(formatarValor('motivo', null)).toBe('—');
    expect(formatarValor('revogada_em', '2026-11-09 10:00:00')).toBe('09/11/2026 10:00');
    expect(formatarValor('data_reserva', '2026-11-20')).toBe('20/11/2026');
    expect(formatarValor('status', 'REVOGADO')).toBe('Revogada');
    expect(formatarValor('descricao', 'REVOGADO')).toBe('REVOGADO');
    expect(formatarValor('espacos_compartilhados', [2, 5])).toBe('2, 5');
    expect(formatarValor('espacos_compartilhados', [])).toBe('Nenhum');
    expect(formatarValor('password', '[oculto]')).toBe('[oculto]');
    expect(formatarQuando('2026-11-09T13:00:00.000000Z')).toBe('09/11/2026 10:00');
  });

  it('monta as mudanças campo a campo, inclusive na criação e na exclusão', () => {
    expect(mudancasDe(revogacao)[0]).toEqual({
      campo: 'status',
      rotulo: 'Situação',
      antes: 'Reservada',
      depois: 'Revogada',
    });
    const criacao: Auditoria = {
      ...revogacao,
      acao: 'CRIACAO',
      campos_alterados: ['campo_novo'],
      antes: null,
      depois: { campo_novo: 'x' },
    };
    expect(mudancasDe(criacao)).toEqual([
      { campo: 'campo_novo', rotulo: 'campo_novo', antes: '—', depois: 'x' },
    ]);
  });

  it('liga só os registros que têm página', () => {
    expect(linkDoRegistro('RESERVA', 7)).toBe('/admin/reservas/7');
    expect(linkDoRegistro('ALUNO', 3)).toBe('/admin/cadastros/alunos/3');
    expect(linkDoRegistro('ADMIN', 2)).toBe('/admin/cadastros/admins/2');
    expect(linkDoRegistro('CURSO', 1)).toBeNull();
    expect(linkDoRegistro('ITEM_RESERVA', 1)).toBeNull();
  });

  it('lê os filtros da URL e ignora valores inválidos', () => {
    expect(
      filtrosDaUrl({ tipo: 'RESERVA', registro: '7', acao: 'EXCLUSAO', quem: 'sistema' }),
    ).toEqual({ tipo: 'RESERVA', registro_id: 7, acao: 'EXCLUSAO', sem_admin: true });
    expect(filtrosDaUrl({ registro: '7', tipo: 'X', acao: 'Y', quem: 'abc' })).toEqual({});
    expect(filtrosDaUrl({ periodo: 'HOJE', de: '2026-11-01' })).toEqual({ periodo: 'HOJE' });
    expect(filtrosDaUrl({ de: '2026-11-01', ate: 'ontem', quem: '4' })).toEqual({
      de: '2026-11-01',
      admin_id: 4,
    });
  });
});

describe('histórico do admin', () => {
  let controle: HttpTestingController;
  let router: Router;

  beforeEach(() => {
    sessionStorage.clear();
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([interceptorApi])),
        provideHttpClientTesting(),
      ],
    });
    controle = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
    TestBed.inject(SessaoService).iniciar('tok', new Date(Date.now() + 3_600_000).toISOString());
  });

  afterEach(() => {
    controle.verify();
    sessionStorage.clear();
  });

  async function estabilizar(fixture: { detectChanges(): void; whenStable(): Promise<unknown> }) {
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();
  }

  function responderAdmins() {
    const pedido = controle.expectOne(`${url}/admin/admins/consulta`);
    expect(pedido.request.method).toBe('POST');
    expect(pedido.request.body).toEqual({ page: 1, per_page: 100 });
    pedido.flush(
      pagina([
        {
          id: 1,
          nome: 'Eric',
          email: 'a@b.c',
          ativo: true,
          principal: true,
          convite_aceito_em: null,
        },
      ] as never[]),
    );
  }

  it('lista com os filtros da URL e mostra as mudanças ao abrir', async () => {
    const fixture = TestBed.createComponent(Historico);
    fixture.componentRef.setInput('tipo', 'RESERVA');
    fixture.componentRef.setInput('registro', '7');
    fixture.componentRef.setInput('quem', '1');
    fixture.componentRef.setInput('periodo', 'MES_ATUAL');
    await estabilizar(fixture);
    responderAdmins();
    const pedido = controle.expectOne(
      (r) => r.url === `${url}/admin/auditorias` && r.method === 'GET',
    );
    expect(pedido.request.params.toString()).toBe(
      'page=1&tipo=RESERVA&registro_id=7&admin_id=1&periodo=MES_ATUAL',
    );
    pedido.flush(pagina([revogacao]));
    await estabilizar(fixture);

    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('Só o registro Reserva nº 7');
    expect(elemento.textContent).toContain('Alterou Reserva nº 7');
    expect(elemento.textContent).toContain('09/11/2026 10:00 · Eric · 3 campos');
    expect((elemento.querySelector('#filtro-quem') as HTMLSelectElement).value).toBe('1');
    expect(elemento.querySelector('[aria-pressed="true"]')?.textContent?.trim()).toBe('Este mês');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Ver mudanças')!
      .click();
    await estabilizar(fixture);
    const linhas = [...elemento.querySelectorAll('tbody tr')].map((tr) =>
      [...tr.children].map((celula) => celula.textContent!.trim()),
    );
    expect(linhas).toEqual([
      ['Situação', 'Reservada', 'Revogada'],
      ['Revogada em', '—', '09/11/2026 10:00'],
      ['Motivo da revogação', '—', 'Manutenção da quadra'],
    ]);
  });

  it('mostra quem não é admin como sistema ou aluno e filtra pela URL', async () => {
    const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(Historico);
    await estabilizar(fixture);
    responderAdmins();
    controle.expectOne(`${url}/admin/auditorias?page=1`).flush(pagina([anonimizacao], 40, 3));
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('Sistema ou aluno');
    expect(elemento.textContent).toContain('40 mudanças');
    expect(elemento.textContent).toContain('Página 1 de 3');

    const quem = elemento.querySelector('#filtro-quem') as HTMLSelectElement;
    quem.value = 'sistema';
    quem.dispatchEvent(new Event('change'));
    expect(navegar).toHaveBeenCalledWith([], {
      queryParams: { quem: 'sistema', pagina: null },
      queryParamsHandling: 'merge',
    });

    const tipo = elemento.querySelector('#filtro-tipo') as HTMLSelectElement;
    tipo.value = 'ALUNO';
    tipo.dispatchEvent(new Event('change'));
    expect(navegar).toHaveBeenCalledWith([], {
      queryParams: { tipo: 'ALUNO', registro: null, pagina: null },
      queryParamsHandling: 'merge',
    });

    [...elemento.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Hoje')!.click();
    expect(navegar).toHaveBeenCalledWith([], {
      queryParams: { periodo: 'HOJE', de: null, ate: null, pagina: null },
      queryParamsHandling: 'merge',
    });
  });

  it('o histórico do registro só consulta ao abrir e busca as 10 mais recentes', async () => {
    const fixture = TestBed.createComponent(HistoricoDoRegistro);
    fixture.componentRef.setInput('tipo', 'RESERVA');
    fixture.componentRef.setInput('registroId', 7);
    await estabilizar(fixture);
    controle.expectNone(() => true);

    const elemento = fixture.nativeElement as HTMLElement;
    const botao = elemento.querySelector('button')!;
    expect(botao.getAttribute('aria-expanded')).toBe('false');
    botao.click();
    await estabilizar(fixture);
    controle
      .expectOne(`${url}/admin/auditorias?page=1&per_page=10&tipo=RESERVA&registro_id=7`)
      .flush(pagina([revogacao], 12));
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Mostrando as 10 mais recentes de 12.');
    expect(elemento.textContent).not.toContain('Reserva nº 7');
    const link = elemento.querySelector('a[href*="historico"]') as HTMLAnchorElement;
    expect(link.getAttribute('href')).toBe('/admin/historico?tipo=RESERVA&registro=7');

    botao.click();
    await estabilizar(fixture);
    expect(elemento.textContent).not.toContain('Ver tudo no histórico');
  });

  it('mostra o erro da API no histórico do registro', async () => {
    const fixture = TestBed.createComponent(HistoricoDoRegistro);
    fixture.componentRef.setInput('tipo', 'ALUNO');
    fixture.componentRef.setInput('registroId', 3);
    await estabilizar(fixture);
    (fixture.nativeElement as HTMLElement).querySelector('button')!.click();
    await estabilizar(fixture);
    controle
      .expectOne(`${url}/admin/auditorias?page=1&per_page=10&tipo=ALUNO&registro_id=3`)
      .flush({ message: 'x' }, { status: 500, statusText: 'erro' });
    await estabilizar(fixture);
    expect((fixture.nativeElement as HTMLElement).textContent).toContain(
      'Ocorreu um erro no servidor',
    );
  });
});
