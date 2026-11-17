import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ambiente } from '../../../../ambientes/ambiente';
import { interceptorApi } from '../../../nucleo/api.interceptor';
import { SessaoService } from '../../../nucleo/sessao.service';
import { ReservaAdmin } from '../../reservas/modelos';
import { ResponsavelDoEvento } from '../../reservas/detalhe-reserva/responsavel-do-evento';
import { EquipamentoCadastro } from '../modelos';
import { AjustarEstoque } from './ajustar-estoque';
import { EstoqueEquipamento } from './estoque-equipamento';
import { descreverQuantidade, utilizavel } from './tipos';

const url = ambiente.urlApi;

const projetor: EquipamentoCadastro = {
  id: 7,
  espaco_id: 4,
  nome: 'Projetor',
  quantidade_total: 5,
  quantidade_em_manutencao: 2,
  ativo: true,
  aceita_solicitacao: false,
};

describe('estoque e responsável', () => {
  let controle: HttpTestingController;

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

  function preencher(elemento: HTMLElement, seletor: string, valor: string, evento = 'input') {
    const campo = elemento.querySelector(seletor) as HTMLInputElement;
    campo.value = valor;
    campo.dispatchEvent(new Event(evento));
  }

  it('calcula o utilizável e descreve a quantidade de cada movimentação', () => {
    expect(utilizavel(5, 2)).toBe(3);
    expect(utilizavel(1, 3)).toBe(0);
    expect(descreverQuantidade('ENTRADA', 2)).toBe('+2');
    expect(descreverQuantidade('BAIXA', 1)).toBe('−1');
    expect(descreverQuantidade('MANUTENCAO', 1)).toBe('−1');
    expect(descreverQuantidade('AJUSTE', -3)).toBe('-3');
    expect(descreverQuantidade('AJUSTE', 3)).toBe('+3');
  });

  it('mostra o estoque, registra uma baixa que estava em manutenção e recarrega a lista', async () => {
    const fixture = TestBed.createComponent(EstoqueEquipamento);
    fixture.componentRef.setInput('equipamento', projetor);
    const atualizados: EquipamentoCadastro[] = [];
    fixture.componentInstance.atualizado.subscribe((item) => atualizados.push(item));
    await estabilizar(fixture);
    controle.expectOne(`${url}/admin/equipamentos/7/movimentacoes?page=1`).flush({
      data: [
        {
          id: 1,
          tipo: 'MANUTENCAO',
          quantidade: 2,
          estado: 'Lâmpadas queimadas.',
          reserva: null,
          feito_por: { id: 1, nome: 'Eric' },
          quantidade_total_depois: 5,
          em_manutencao_depois: 2,
          em: '2026-11-09T13:00:00Z',
        },
      ],
      meta: { current_page: 1, last_page: 1, total: 1, per_page: 15 },
    });
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    const numeros = [...elemento.querySelectorAll('dd')].map((dd) => dd.textContent?.trim());
    expect(numeros).toEqual(['5', '2', '3']);
    expect(elemento.textContent).toContain('Foi para manutenção · −2');
    expect(elemento.textContent).toContain('Lâmpadas queimadas.');

    preencher(elemento, '#estoque-7-cadastro-tipo', 'BAIXA', 'change');
    await estabilizar(fixture);
    (elemento.querySelector('input[name=da-manutencao]') as HTMLInputElement).click();
    preencher(elemento, '#estoque-7-cadastro-estado', 'Sem conserto.');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    await estabilizar(fixture);

    const envio = controle.expectOne(`${url}/admin/equipamentos/7/movimentacoes`);
    expect(envio.request.method).toBe('POST');
    expect(envio.request.body).toEqual({
      tipo: 'BAIXA',
      quantidade: 1,
      estado: 'Sem conserto.',
      da_manutencao: true,
    });
    envio.flush(
      {
        data: { id: 2 },
        equipamento: { ...projetor, quantidade_total: 4, quantidade_em_manutencao: 1 },
      },
      { status: 201, statusText: 'criado' },
    );
    await estabilizar(fixture);
    expect(atualizados[0].quantidade_total).toBe(4);
    expect(elemento.textContent).toContain('Movimentação registrada.');
    controle
      .expectOne(`${url}/admin/equipamentos/7/movimentacoes?page=1`)
      .flush({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 15 } });
  });

  it('vindo da devolução, já liga a baixa à reserva e mostra o erro da API', async () => {
    const fixture = TestBed.createComponent(AjustarEstoque);
    fixture.componentRef.setInput('equipamento', { id: 7 });
    fixture.componentRef.setInput('reservaId', 77);
    fixture.componentRef.setInput('tipoInicial', 'NAO_DEVOLVIDO');
    fixture.componentRef.setInput('quantidadeInicial', 2);
    fixture.componentRef.setInput('estadoInicial', 'Duas bolas sumiram.');
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.querySelector('#estoque-7-77-reserva')).toBeNull();
    expect(elemento.querySelector('input[name=da-manutencao]')).toBeNull();
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    await estabilizar(fixture);

    const envio = controle.expectOne(`${url}/admin/equipamentos/7/movimentacoes`);
    expect(envio.request.body).toEqual({
      tipo: 'NAO_DEVOLVIDO',
      quantidade: 2,
      estado: 'Duas bolas sumiram.',
      reserva_id: 77,
    });
    envio.flush(
      { message: 'Já existem 3 unidade(s) reservada(s) num mesmo horário a partir de agora.' },
      { status: 409, statusText: 'conflito' },
    );
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('Já existem 3 unidade(s) reservada(s)');
  });

  it('corrige e remove o responsável do evento a pedido', async () => {
    const evento = {
      id: 31,
      tipo: 'EVENTO',
      responsavel: { nome: 'Prof. Marcos Lima', contato: 'marcos@fac.edu' },
    } as ReservaAdmin;
    const fixture = TestBed.createComponent(ResponsavelDoEvento);
    fixture.componentRef.setInput('reserva', evento);
    const atualizadas: ReservaAdmin[] = [];
    fixture.componentInstance.atualizada.subscribe((reserva) => atualizadas.push(reserva));
    await estabilizar(fixture);
    const elemento = fixture.nativeElement as HTMLElement;
    expect(elemento.textContent).toContain('Prof. Marcos Lima');
    expect(elemento.textContent).toContain('marcos@fac.edu');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Corrigir')!
      .click();
    await estabilizar(fixture);
    preencher(elemento, '#detalhe-responsavel', 'Prof. Marcos Lima Souza');
    preencher(elemento, '#detalhe-contato', '');
    await estabilizar(fixture);
    elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
    const correcao = controle.expectOne(`${url}/admin/reservas/31/responsavel`);
    expect(correcao.request.method).toBe('PATCH');
    expect(correcao.request.body).toEqual({ responsavel_nome: 'Prof. Marcos Lima Souza' });
    correcao.flush({
      data: { ...evento, responsavel: { nome: 'Prof. Marcos Lima Souza', contato: null } },
    });
    await estabilizar(fixture);
    expect(atualizadas[0].responsavel?.nome).toBe('Prof. Marcos Lima Souza');

    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.trim() === 'Remover a pedido')!
      .click();
    await estabilizar(fixture);
    expect(elemento.textContent).toContain('somem do evento e do histórico');
    [...elemento.querySelectorAll('button')]
      .find((b) => b.textContent?.includes('Confirmar'))!
      .click();
    const remocao = controle.expectOne(`${url}/admin/reservas/31/responsavel`);
    expect(remocao.request.body).toEqual({ remover: true });
    remocao.flush({ data: { ...evento, responsavel: { nome: null, contato: null } } });
  });
});
