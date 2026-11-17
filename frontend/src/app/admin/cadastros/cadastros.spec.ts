import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { SessaoService } from '../../nucleo/sessao.service';
import { FormAdmin } from './admins/form-admin';
import { ListaAdmins } from './admins/lista-admins';
import { DetalheAluno } from './alunos/detalhe-aluno';
import { ListaAlunos } from './alunos/lista-alunos';
import { Cursos } from './cursos/cursos';
import { DatasBloqueadas } from './datas-bloqueadas/datas-bloqueadas';
import { FormBloqueio } from './datas-bloqueadas/form-bloqueio';
import { FormEquipamento } from './equipamentos/form-equipamento';
import { paraIso } from '../../publico/datas';
import { FormEspaco } from './espacos/form-espaco';
import { ListaEspacos } from './espacos/lista-espacos';
import { EspacoCadastro } from './modelos';

const url = ambiente.urlApi;

const laboratorio: EspacoCadastro = {
  id: 4,
  nome: 'Laboratório de Informática',
  tipo: 'LABORATORIO',
  ativo: true,
  limite_semanal_por_curso: null,
  modo_horario: 'LIVRE',
  abre_as: '07:00',
  fecha_as: '22:00',
  duracao_maxima_minutos: 180,
};

describe('cadastros do admin', () => {
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
    const campo = elemento.querySelector(seletor) as HTMLInputElement;
    campo.value = valor;
    campo.dispatchEvent(new Event('input'));
  }

  it('lista os espaços filtrando pelo tipo e descreve o horário', async () => {
    const fixture = TestBed.createComponent(ListaEspacos);
    fixture.componentRef.setInput('tipo', 'LABORATORIO');
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/espacos?page=1&tipo=LABORATORIO`).flush({
      data: [laboratorio, { ...laboratorio, id: 5, nome: 'Sala antiga', ativo: false }],
      meta: { current_page: 1, last_page: 1, total: 2, per_page: 15 },
    });
    await estabilizar(fixture);
    const texto = fixture.nativeElement.textContent as string;
    expect(texto).toContain('horário livre, 07:00–22:00, até 3h');
    expect(texto).toContain('Desativado');
  });

  it('cria um espaço com horário livre e a duração sugerida de 180 min', async () => {
    const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(FormEspaco);
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect((elemento.querySelector('#espaco-duracao') as HTMLInputElement).value).toBe('180');
    digitar(elemento, '#espaco-nome', 'Sala 201');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    await estabilizar(fixture);

    const envio = controle.expectOne(`${url}/admin/espacos`);
    expect(envio.request.method).toBe('POST');
    expect(envio.request.body).toEqual({
      nome: 'Sala 201',
      tipo: 'SALA',
      modo_horario: 'LIVRE',
      abre_as: '07:00',
      fecha_as: '22:00',
      duracao_maxima_minutos: 180,
      limite_semanal_por_curso: null,
    });
    envio.flush(
      { data: { ...laboratorio, id: 9, nome: 'Sala 201', tipo: 'SALA' } },
      { status: 201, statusText: 'criado' },
    );
    expect(navegar).toHaveBeenCalledWith(['/admin/cadastros/espacos', 9]);
  });

  it('desativa com confirmação e mostra a recusa da API', async () => {
    const fixture = TestBed.createComponent(FormEspaco);
    fixture.componentRef.setInput('id', '4');
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/espacos/4`).flush({ data: laboratorio });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect((elemento.querySelector('#espaco-nome') as HTMLInputElement).value).toBe(
      'Laboratório de Informática',
    );

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Desativar')!
      .click();
    await estabilizar(fixture);
    expect(elemento.textContent).toContain(
      'O sistema não deixa desativar se houver reservas futuras.',
    );
    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.includes('Confirmar: desativar'))!
      .click();
    const pedido = controle.expectOne(`${url}/admin/espacos/4`);
    expect(pedido.request.method).toBe('DELETE');
    pedido.flush(
      { message: 'Há reservas futuras neste espaço. Revogue-as antes de desativá-lo.' },
      { status: 409, statusText: 'conflito' },
    );
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Há reservas futuras neste espaço.');
  });

  it('edita um equipamento com aceita pedido e espaços compartilhados', async () => {
    const fixture = TestBed.createComponent(FormEquipamento);
    fixture.componentRef.setInput('id', '7');
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/espacos?per_page=100`).flush({
      data: [
        { id: 4, nome: 'Laboratório de Informática', ativo: true },
        { id: 5, nome: 'Sala 101', ativo: true },
        { id: 6, nome: 'Sala 102', ativo: true },
      ],
    });
    controle.expectOne(`${url}/admin/equipamentos/7`).flush({
      data: {
        id: 7,
        espaco_id: 4,
        nome: 'Projetor',
        quantidade_total: 2,
        ativo: true,
        aceita_solicitacao: false,
        espacos_compartilhados: [{ id: 5, nome: 'Sala 101' }],
      },
    });
    await estabilizar(fixture);
    controle
      .expectOne(`${url}/admin/equipamentos/7/movimentacoes?page=1`)
      .flush({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 } });
    controle
      .expectOne(`${url}/admin/equipamentos/sugestoes-compartilhamento?equipamento_id=7`)
      .flush({ data: [] });
    await estabilizar(fixture);

    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.querySelector('#equipamento-quantidade')).toBeNull();
    expect((elemento.querySelector('#equipamento-espaco') as HTMLSelectElement).disabled).toBe(
      true,
    );
    const caixas = [
      ...elemento.querySelectorAll('fieldset input[type=checkbox]'),
    ] as HTMLInputElement[];
    expect(caixas.map((caixa) => caixa.checked)).toEqual([true, false]);
    caixas[1].click();
    (elemento.querySelector('input[name=aceita]') as HTMLInputElement).click();
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    await estabilizar(fixture);

    const envio = controle.expectOne(`${url}/admin/equipamentos/7`);
    expect(envio.request.method).toBe('PATCH');
    expect(envio.request.body).toEqual({
      nome: 'Projetor',
      aceita_solicitacao: true,
      espacos_compartilhados: [5, 6],
    });
    envio.flush({
      data: {
        id: 7,
        espaco_id: 4,
        nome: 'Projetor',
        quantidade_total: 2,
        ativo: true,
        aceita_solicitacao: true,
        espacos_compartilhados: [
          { id: 5, nome: 'Sala 101' },
          { id: 6, nome: 'Sala 102' },
        ],
      },
    });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Equipamento salvo.');
  });

  it('cria, renomeia e desativa cursos na mesma página', async () => {
    const fixture = TestBed.createComponent(Cursos);
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/cursos?page=1`).flush({
      data: [{ id: 2, nome: 'Direito', ativo: true }],
      meta: { current_page: 1, last_page: 1, total: 1, per_page: 15 },
    });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;

    digitar(elemento, '#curso-novo', 'Enfermagem');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    const criar = controle.expectOne(`${url}/admin/cursos`);
    expect(criar.request.body).toEqual({ nome: 'Enfermagem' });
    criar.flush(
      { data: { id: 3, nome: 'Enfermagem', ativo: true } },
      { status: 201, statusText: 'criado' },
    );
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/cursos?page=1`).flush({
      data: [
        { id: 2, nome: 'Direito', ativo: true },
        { id: 3, nome: 'Enfermagem', ativo: true },
      ],
      meta: { current_page: 1, last_page: 1, total: 2, per_page: 15 },
    });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Curso criado.');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Renomear')!
      .click();
    await estabilizar(fixture);
    digitar(elemento, '#curso-2', 'Direito noturno');
    await estabilizar(fixture);
    (elemento.querySelector('#curso-2') as HTMLInputElement).form!.dispatchEvent(
      new Event('submit'),
    );
    const renomear = controle.expectOne(`${url}/admin/cursos/2`);
    expect(renomear.request.body).toEqual({ nome: 'Direito noturno' });
    renomear.flush({ data: { id: 2, nome: 'Direito noturno', ativo: true } });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Direito noturno');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Desativar')!
      .click();
    await estabilizar(fixture);
    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.includes('Confirmar: desativar'))!
      .click();
    const desativar = controle.expectOne(`${url}/admin/cursos/2`);
    expect(desativar.request.method).toBe('DELETE');
    desativar.flush(null, { status: 204, statusText: 'ok' });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Curso desativado.');
  });

  it('lista os bloqueios de hoje em diante, marca feriado e exclui com confirmação', async () => {
    const fixture = TestBed.createComponent(DatasBloqueadas);
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/espacos?per_page=100`).flush({ data: [] });
    controle.expectOne(`${url}/admin/datas-bloqueadas?page=1&de=${paraIso(new Date())}`).flush({
      data: [
        {
          id: 1,
          data_inicio: '2099-12-25',
          data_fim: '2099-12-25',
          dia_inteiro: true,
          hora_inicio: null,
          hora_fim: null,
          espaco: null,
          motivo: 'Natal',
          origem: 'FERIADO',
        },
      ],
      meta: { current_page: 1, last_page: 1, total: 1, per_page: 15 },
    });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('25/12, dia inteiro');
    expect(elemento.textContent).toContain('Todos os espaços');
    expect(elemento.textContent).toContain('Feriado');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Excluir')!
      .click();
    await estabilizar(fixture);
    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.includes('Confirmar: excluir'))!
      .click();
    const excluir = controle.expectOne(`${url}/admin/datas-bloqueadas/1`);
    expect(excluir.request.method).toBe('DELETE');
    excluir.flush(null, { status: 204, statusText: 'ok' });
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/datas-bloqueadas?page=1&de=${paraIso(new Date())}`).flush({
      data: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
    });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Bloqueio excluído.');
  });

  it('cria um bloqueio de parte do dia num espaço e mostra quantas reservas foram revogadas', async () => {
    const fixture = TestBed.createComponent(FormBloqueio);
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/espacos?per_page=100`).flush({
      data: [{ id: 4, nome: 'Laboratório de Informática', ativo: true }],
    });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    (elemento.querySelector('input[name=parte]') as HTMLInputElement).click();
    await estabilizar(fixture);
    digitar(elemento, '#bloqueio-inicio', '14:00');
    digitar(elemento, '#bloqueio-fim', '16:00');
    const espaco = elemento.querySelector('#bloqueio-espaco') as HTMLSelectElement;
    espaco.value = '4';
    espaco.dispatchEvent(new Event('change'));
    digitar(elemento, '#bloqueio-motivo', 'Manutenção elétrica');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    await estabilizar(fixture);

    const envio = controle.expectOne(`${url}/admin/datas-bloqueadas`);
    const hoje = paraIso(new Date());
    expect(envio.request.body).toEqual({
      data_inicio: hoje,
      data_fim: hoje,
      motivo: 'Manutenção elétrica',
      hora_inicio: '14:00',
      hora_fim: '16:00',
      espaco_id: 4,
    });
    envio.flush({ data: { id: 3 }, reservas_revogadas: 2 }, { status: 201, statusText: 'criado' });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('2 reserva(s) revogada(s), com email aos alunos.');
  });

  it('busca alunos por POST, sem RA nem email na lista', async () => {
    const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(ListaAlunos);
    await estabilizar(fixture);
    controle.expectOne(`${url}/cursos`).flush({ data: [{ id: 2, nome: 'Direito' }] });
    controle.expectOne(`${url}/admin/alunos/consulta`).flush({
      data: [
        {
          id: 3,
          nome_completo: 'Maria Souza',
          registro_academico: '2024000001',
          email: 'maria@email.com',
          curso: { id: 2, nome: 'Direito' },
          ativo: true,
          anonimizado_em: null,
        },
      ],
      meta: { current_page: 1, last_page: 1, total: 1, per_page: 15 },
    });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('Maria Souza');
    expect(elemento.textContent).not.toContain('2024000001');
    expect(elemento.textContent).not.toContain('maria@email.com');

    digitar(elemento, '#busca', 'Souza');
    await new Promise((resolver) => setTimeout(resolver, 450));
    await estabilizar(fixture);
    const busca = controle.expectOne(`${url}/admin/alunos/consulta`);
    expect(busca.request.method).toBe('POST');
    expect(busca.request.body).toEqual({ busca: 'Souza', page: 1 });
    busca.flush({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 } });
    expect(navegar).not.toHaveBeenCalled();
  });

  it('detalhe mostra RA e email, edita e só anonimiza com a confirmação forte', async () => {
    const aluno = {
      id: 3,
      nome_completo: 'Maria Souza',
      registro_academico: '2024000001',
      email: 'maria@email.com',
      curso: { id: 2, nome: 'Direito' },
      ativo: true,
      anonimizado_em: null,
    };
    const fixture = TestBed.createComponent(DetalheAluno);
    fixture.componentRef.setInput('id', '3');
    await estabilizar(fixture);
    controle.expectOne(`${url}/cursos`).flush({ data: [{ id: 2, nome: 'Direito' }] });
    controle.expectOne(`${url}/admin/alunos/3`).flush({ data: aluno });
    const reservas = controle.expectOne(`${url}/admin/reservas/consulta`);
    expect(reservas.request.body).toEqual({
      aluno_id: 3,
      de: '2000-01-01',
      ordem: 'desc',
      page: 1,
      per_page: 10,
    });
    reservas.flush({
      data: [],
      meta: { current_page: 1, last_page: 1, total: 0, per_page: 10 },
      totais_por_status: {},
    });
    await estabilizar(fixture);

    const elemento = fixture.nativeElement as HTMLElement;
    expect((elemento.querySelector('#aluno-ra') as HTMLInputElement).value).toBe('2024000001');
    expect((elemento.querySelector('#aluno-email') as HTMLInputElement).value).toBe(
      'maria@email.com',
    );

    digitar(elemento, '#aluno-email', 'maria.souza@email.com');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    const salvar = controle.expectOne(`${url}/admin/alunos/3`);
    expect(salvar.request.method).toBe('PATCH');
    expect(salvar.request.body).toEqual({
      nome_completo: 'Maria Souza',
      registro_academico: '2024000001',
      email: 'maria.souza@email.com',
      curso_id: 2,
    });
    salvar.flush({ data: { ...aluno, email: 'maria.souza@email.com' }, reservas_revogadas: 0 });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Dados salvos.');

    const botao = () =>
      [...elemento.querySelectorAll('button')].find((b) =>
        b.textContent?.includes('Anonimizar os dados'),
      ) as HTMLButtonElement;
    expect(botao().disabled).toBe(true);
    (
      elemento
        .querySelector('#titulo-anonimizar')!
        .parentElement!.querySelector('input[type=checkbox]') as HTMLInputElement
    ).click();
    digitar(elemento, '#aluno-palavra', 'anonimizar');
    await estabilizar(fixture);
    expect(botao().disabled).toBe(false);
    botao().click();
    const anonimizar = controle.expectOne(`${url}/admin/alunos/3/anonimizar`);
    expect(anonimizar.request.body).toEqual({ confirmar: true });
    anonimizar.flush({
      data: {
        ...aluno,
        nome_completo: 'Aluno anonimizado',
        ativo: false,
        anonimizado_em: '2026-11-17T12:00:00Z',
      },
    });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Dados pessoais anonimizados em');
    expect(elemento.querySelector('#aluno-ra')).toBeNull();
  });

  it('lista admins por POST com principal e convite pendente marcados', async () => {
    const fixture = TestBed.createComponent(ListaAdmins);
    await estabilizar(fixture);
    const consulta = controle.expectOne(`${url}/admin/admins/consulta`);
    expect(consulta.request.body).toEqual({ page: 1 });
    consulta.flush({
      data: [
        {
          id: 1,
          nome: 'Eric',
          email: 'eric@fac.edu',
          ativo: true,
          principal: true,
          convite_aceito_em: '2026-11-01T10:00:00Z',
        },
        {
          id: 2,
          nome: 'Ana',
          email: 'ana@fac.edu',
          ativo: true,
          principal: false,
          convite_aceito_em: null,
        },
      ],
      meta: { current_page: 1, last_page: 1, total: 2, per_page: 15 },
    });
    await estabilizar(fixture);
    const texto = fixture.nativeElement.textContent as string;
    expect(texto).toContain('Principal');
    expect(texto).toContain('Convite pendente');
    expect(texto).not.toContain('ana@fac.edu');
  });

  it('convida um admin e, no detalhe, reenvia o convite e desativa', async () => {
    const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    const novo = TestBed.createComponent(FormAdmin);
    await estabilizar(novo);
    digitar(novo.nativeElement, '#admin-nome', 'Ana Lima');
    digitar(novo.nativeElement, '#admin-email', 'ana@fac.edu');
    await estabilizar(novo);
    (novo.nativeElement as HTMLElement).querySelector('form')!.dispatchEvent(new Event('submit'));
    const convite = controle.expectOne(`${url}/admin/admins`);
    expect(convite.request.body).toEqual({ nome: 'Ana Lima', email: 'ana@fac.edu' });
    convite.flush({ data: { id: 5 } }, { status: 201, statusText: 'criado' });
    expect(navegar).toHaveBeenCalledWith(['/admin/cadastros/admins', 5], {
      state: { mensagem: 'Convite enviado por email.' },
    });

    const fixture = TestBed.createComponent(FormAdmin);
    fixture.componentRef.setInput('id', '5');
    await estabilizar(fixture);
    const ana = {
      id: 5,
      nome: 'Ana Lima',
      email: 'ana@fac.edu',
      ativo: true,
      principal: false,
      convite_aceito_em: null,
    };
    controle.expectOne(`${url}/admin/admins/5`).flush({ data: ana });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.includes('Reenviar o convite'))!
      .click();
    controle
      .expectOne(`${url}/admin/admins/5/reenviar-convite`)
      .flush({ message: 'Convite reenviado.' });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Convite reenviado.');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Desativar')!
      .click();
    await estabilizar(fixture);
    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.includes('Confirmar: desativar'))!
      .click();
    const desativar = controle.expectOne(`${url}/admin/admins/5`);
    expect(desativar.request.method).toBe('DELETE');
    desativar.flush({ data: { ...ana, ativo: false } });
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Admin desativado.');
    expect(elemento.textContent).toContain('Reativar');
  });
});
