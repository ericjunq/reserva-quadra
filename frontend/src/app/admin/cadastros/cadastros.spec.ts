import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { SessaoService } from '../../nucleo/sessao.service';
import { FormEquipamento } from './equipamentos/form-equipamento';
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
    expect(elemento.textContent).toContain('A API não deixa desativar se houver reservas futuras.');
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

    const elemento = fixture.nativeElement as HTMLElement;
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
      quantidade_total: 2,
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
});
