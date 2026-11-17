import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../../ambientes/ambiente';
import { interceptorApi } from '../../../nucleo/api.interceptor';
import { SessaoService } from '../../../nucleo/sessao.service';
import { ClienteApiCadastro } from '../modelos';
import { FormSistemaParceiro } from './form-sistema-parceiro';
import { ListaSistemasParceiros } from './lista-sistemas-parceiros';

const url = ambiente.urlApi;

const permissoes = [
  { valor: 'espacos:ler', nome: 'Ver espaços, calendário e equipamentos' },
  { valor: 'reservas:criar', nome: 'Reservar para um aluno pelo RA' },
  { valor: 'reservas:ler', nome: 'Ver as reservas criadas pelo sistema' },
  { valor: 'reservas:cancelar', nome: 'Cancelar as reservas criadas pelo sistema' },
];

const atletica: ClienteApiCadastro = {
  id: 3,
  nome: 'App da Atlética',
  organizacao: 'Atlética de Computação',
  responsavel: { nome: 'Bruno Lima', email: 'bruno@atletica.org' },
  finalidade: 'Reservar a quadra para os treinos.',
  permissoes: ['espacos:ler', 'reservas:criar'],
  limite_por_minuto: 60,
  ativo: true,
  situacao: 'ATIVO',
  termos_versao: '2026-11',
  termos_aceitos_em: '2026-11-17T13:00:00Z',
  acesso_expira_em: '2027-11-17T13:00:00Z',
  link_aceite: null,
};

describe('sistemas parceiros no admin', () => {
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

  function digitar(elemento: HTMLElement, seletor: string, valor: string) {
    const campo = elemento.querySelector(seletor) as HTMLInputElement | HTMLTextAreaElement;
    campo.value = valor;
    campo.dispatchEvent(new Event('input'));
  }

  function clicar(elemento: HTMLElement, texto: string) {
    [...elemento.querySelectorAll('button')]
      .find((botao) => botao.textContent?.trim().startsWith(texto))!
      .click();
  }

  it('lista os sistemas com a situação e filtra os ativos', async () => {
    const fixture = TestBed.createComponent(ListaSistemasParceiros);
    fixture.componentRef.setInput('situacao', 'ativos');
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/clientes-api?page=1&ativo=1`).flush({
      data: [atletica, { ...atletica, id: 4, nome: 'Portal', situacao: 'AGUARDANDO_ACEITE' }],
      meta: { current_page: 1, last_page: 1, total: 2, per_page: 15 },
    });
    await estabilizar(fixture);
    const texto = fixture.nativeElement.textContent as string;
    expect(texto).toContain('App da Atlética');
    expect(texto).toContain('Atlética de Computação');
    expect(texto).toContain('Ativo');
    expect(texto).toContain('Aguardando aceite');
  });

  it('cadastra com as permissões marcadas e o limite, e vai para o detalhe', async () => {
    const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(FormSistemaParceiro);
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/clientes-api/permissoes`).flush({ data: permissoes });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;

    digitar(elemento, '#sistema-nome', 'App da Atlética');
    digitar(elemento, '#sistema-organizacao', 'Atlética de Computação');
    digitar(elemento, '#responsavel-nome', 'Bruno Lima');
    digitar(elemento, '#responsavel-email', 'bruno@atletica.org');
    digitar(elemento, '#sistema-finalidade', 'Reservar a quadra para os treinos.');
    const caixas = elemento.querySelectorAll<HTMLInputElement>('input[type=checkbox]');
    caixas[1].click();
    caixas[0].click();
    digitar(elemento, '#sistema-limite', '30');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));

    const pedido = controle.expectOne(`${url}/admin/clientes-api`);
    expect(pedido.request.method).toBe('POST');
    expect(pedido.request.body).toEqual({
      nome: 'App da Atlética',
      organizacao: 'Atlética de Computação',
      responsavel_nome: 'Bruno Lima',
      responsavel_email: 'bruno@atletica.org',
      finalidade: 'Reservar a quadra para os treinos.',
      permissoes: ['reservas:criar', 'espacos:ler'],
      limite_por_minuto: 30,
    });
    pedido.flush(
      { data: { ...atletica, situacao: 'AGUARDANDO_ACEITE' } },
      { status: 201, statusText: 'criado' },
    );
    expect(navegar).toHaveBeenCalledWith(['/admin/cadastros/sistemas-parceiros', 3], {
      state: {
        mensagem: 'Sistema cadastrado. O responsável recebeu o link para aceitar os termos.',
      },
    });
  });

  it('mostra o erro de cada campo devolvido pela API', async () => {
    const fixture = TestBed.createComponent(FormSistemaParceiro);
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/clientes-api/permissoes`).flush({ data: permissoes });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    controle.expectOne(`${url}/admin/clientes-api`).flush(
      {
        message: 'Dados inválidos.',
        errors: { nome: ['Informe o nome.'], permissoes: ['Escolha ao menos uma permissão.'] },
      },
      { status: 422, statusText: 'inválido' },
    );
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Informe o nome.');
    expect(elemento.textContent).toContain('Escolha ao menos uma permissão.');
  });

  it('no detalhe, envia link de renovação, revoga o token e desativa', async () => {
    const fixture = TestBed.createComponent(FormSistemaParceiro);
    fixture.componentRef.setInput('id', '3');
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/clientes-api/permissoes`).flush({ data: permissoes });
    controle.expectOne(`${url}/admin/clientes-api/3`).flush({ data: atletica });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('versão 2026-11');
    expect(elemento.querySelectorAll<HTMLInputElement>('input[type=checkbox]')[0].checked).toBe(
      true,
    );

    clicar(elemento, 'Enviar link de renovação');
    await estabilizar(fixture);
    clicar(elemento, 'Confirmar');
    const renovar = controle.expectOne(`${url}/admin/clientes-api/3/enviar-aceite`);
    expect(renovar.request.body).toEqual({ revogar_atual: false });
    renovar.flush({ data: { ...atletica, link_aceite: { expira_em: '2026-11-24T13:00:00Z' } } });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Link enviado ao responsável.');

    clicar(elemento, 'Revogar o token');
    await estabilizar(fixture);
    clicar(elemento, 'Confirmar');
    const revogar = controle.expectOne(`${url}/admin/clientes-api/3/enviar-aceite`);
    expect(revogar.request.body).toEqual({ revogar_atual: true });
    revogar.flush({ data: { ...atletica, situacao: 'AGUARDANDO_ACEITE', acesso_expira_em: null } });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Token revogado.');
    expect(elemento.textContent).toContain('Aguardando aceite');

    clicar(elemento, 'Desativar');
    await estabilizar(fixture);
    clicar(elemento, 'Confirmar');
    const desativar = controle.expectOne(`${url}/admin/clientes-api/3`);
    expect(desativar.request.method).toBe('DELETE');
    desativar.flush({ data: { ...atletica, ativo: false, situacao: 'DESATIVADO' } });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Sistema desativado.');
    expect(elemento.textContent).toContain('Reativar');
  });
});
