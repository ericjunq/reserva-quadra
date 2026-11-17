import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ambiente } from '../../../ambientes/ambiente';
import { interceptorApi } from '../../nucleo/api.interceptor';
import { SessaoService } from '../../nucleo/sessao.service';
import { paraIso, somarDias } from '../../publico/datas';
import { DetalheReserva } from './detalhe-reserva/detalhe-reserva';
import { ExportarReservas } from './exportar-reservas/exportar-reservas';
import { diasDoPeriodo, filtrosDaUrl } from './filtros';
import { jaComecou, linhaDoTempo } from './linha-do-tempo';
import { ListaReservas } from './lista-reservas/lista-reservas';
import { RegistrarUso } from './registrar-uso/registrar-uso';
import { Painel } from '../painel/painel';
import { PaginaReservasAdmin, ReservaAdmin } from './modelos';

const url = ambiente.urlApi;
const ontem = paraIso(somarDias(new Date(), -1));
const amanha = paraIso(somarDias(new Date(), 1));

function reserva(mudancas: Partial<ReservaAdmin> = {}): ReservaAdmin {
  return {
    id: 7,
    data_reserva: amanha,
    hora_inicio: '19:00',
    hora_fim: '20:30',
    status: 'RESERVADO',
    tipo: 'ALUNO',
    origem: 'SITE',
    espaco: { id: 1, nome: 'Quadra Poliesportiva', tipo: 'QUADRA' },
    aluno: {
      id: 3,
      nome_completo: 'Ana Souza',
      registro_academico: '2024000001',
      email: 'ana@email.com',
      curso: { id: 2, nome: 'Direito' },
    },
    descricao: null,
    equipamentos: [
      {
        id: 5,
        nome: 'Bola de futsal',
        quantidade: 2,
        devolucao: {
          situacao: 'PENDENTE',
          quantidade_devolvida: null,
          observacao: null,
          devolvido_em: null,
        },
      },
    ],
    situacao_confirmacao: 'CONFIRMADA',
    confirmar_ate: '2026-11-10T22:00:00.000000Z',
    confirmada_em: '2026-11-10T12:00:00.000000Z',
    termos_aceitos_em: '2026-11-10T12:00:00.000000Z',
    termos_versao: '2026-11',
    reconfirmacao_pedida_em: null,
    reconfirmar_ate: null,
    reconfirmada_em: null,
    criado_por: null,
    atualizado_por: null,
    criado_em: '2026-11-10T11:00:00.000000Z',
    atualizado_em: null,
    ...mudancas,
  };
}

function pagina(reservas: ReservaAdmin[]): PaginaReservasAdmin {
  return {
    data: reservas,
    meta: { current_page: 1, last_page: 1, total: reservas.length, per_page: 15 },
    totais_por_status: { RESERVADO: reservas.length, CANCELADO: 1, REVOGADO: 2 },
  };
}

describe('reservas do admin', () => {
  describe('filtros da URL', () => {
    it('aceita só valores conhecidos e o período tem prioridade sobre as datas', () => {
      expect(
        filtrosDaUrl({
          periodo: 'MES_ATUAL',
          de: '2026-11-01',
          espaco: '3',
          curso: 'abc',
          status: 'REVOGADO',
          tipo: 'X',
          confirmacao: 'CONFIRMADA',
          devolucao: 'PENDENTE',
        }),
      ).toEqual({
        periodo: 'MES_ATUAL',
        espaco_id: 3,
        status: 'REVOGADO',
        confirmacao: 'CONFIRMADA',
        devolucao: 'PENDENTE',
      });
      expect(filtrosDaUrl({ de: '2026-11-10', ate: '2026-11-01' })).toEqual({ de: '2026-11-10' });
      expect(filtrosDaUrl({ de: '10/11/2026', espaco: '-1' })).toEqual({});
    });

    it('conta os dias do período escolhido', () => {
      expect(diasDoPeriodo({ de: '2026-01-01', ate: '2026-12-31' })).toBe(365);
      expect(diasDoPeriodo({ de: '2026-01-01' })).toBeNull();
    });
  });

  describe('linha do tempo', () => {
    it('mostra o prazo enquanto o aluno não confirma', () => {
      const marcos = linhaDoTempo(
        reserva({ confirmada_em: null, situacao_confirmacao: 'AGUARDANDO_CONFIRMACAO' }),
      );
      expect(marcos.map((m) => m.rotulo)).toEqual(['Criada', 'Prazo para confirmar']);
    });

    it('mostra a versão dos termos, a reconfirmação com o prazo de 23:59 e a revogação', () => {
      const marcos = linhaDoTempo(
        reserva({
          reconfirmacao_pedida_em: '2026-11-11T11:00:00.000000Z',
          reconfirmar_ate: '2026-11-13T03:00:00.000000Z',
          status: 'REVOGADO',
          revogada_em: '2026-11-13T03:05:00.000000Z',
          revogada_por: { id: 1, nome: 'Eric' },
        }),
      );
      expect(marcos.map((m) => m.rotulo)).toEqual([
        'Criada',
        'Confirmada pelo aluno',
        'Reconfirmação pedida',
        'Prazo para reconfirmar',
        'Revogada',
      ]);
      expect(marcos[1].detalhe).toBe('termos versão 2026-11');
      expect(marcos[3].quando).toBe('12/11 às 23:59');
      expect(marcos[4].detalhe).toBe('Eric');
    });

    it('sabe se a reserva já começou', () => {
      expect(jaComecou({ data_reserva: ontem, hora_inicio: '19:00' })).toBe(true);
      expect(jaComecou({ data_reserva: amanha, hora_inicio: '07:00' })).toBe(false);
    });
  });

  describe('telas', () => {
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

    function responderListasPublicas() {
      controle.expectOne(`${url}/espacos`).flush({ data: [] });
      controle.expectOne(`${url}/cursos`).flush({ data: [{ id: 2, nome: 'Direito' }] });
    }

    it('lista por POST com os filtros da URL e mostra os selos e os totais', async () => {
      const fixture = TestBed.createComponent(ListaReservas);
      fixture.componentRef.setInput('status', 'RESERVADO');
      fixture.componentRef.setInput('confirmacao', 'AGUARDANDO_RECONFIRMACAO');
      fixture.componentRef.setInput('pagina', '2');
      await estabilizar(fixture);
      responderListasPublicas();

      const consulta = controle.expectOne(`${url}/admin/reservas/consulta`);
      expect(consulta.request.method).toBe('POST');
      expect(consulta.request.body).toEqual({
        status: 'RESERVADO',
        confirmacao: 'AGUARDANDO_RECONFIRMACAO',
        page: 2,
      });
      consulta.flush(
        pagina([
          reserva({
            situacao_confirmacao: 'AGUARDANDO_RECONFIRMACAO',
            reconfirmacao_pedida_em: '2026-11-11T11:00:00Z',
          }),
          reserva({ id: 8, tipo: 'EVENTO', aluno: null, descricao: 'Jogos internos' }),
        ]),
      );
      await estabilizar(fixture);

      const texto = fixture.nativeElement.textContent as string;
      expect(texto).toContain('Ana Souza');
      expect(texto).toContain('Direito');
      expect(texto).toContain('Aguardando reconfirmação');
      expect(texto).toContain('Evento: Jogos internos');
      expect(texto).toContain('Revogadas: 2');
      expect(texto).not.toContain('2024000001');
      expect(texto).not.toContain('ana@email.com');
    });

    it('busca por nome ou RA só no corpo, sem pôr na URL, e o status filtra pelos totais', async () => {
      const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
      const fixture = TestBed.createComponent(ListaReservas);
      await estabilizar(fixture);
      responderListasPublicas();
      controle.expectOne(`${url}/admin/reservas/consulta`).flush(pagina([reserva()]));
      await estabilizar(fixture);

      const busca = fixture.nativeElement.querySelector('#busca') as HTMLInputElement;
      busca.value = 'Souza';
      busca.dispatchEvent(new Event('input'));
      await new Promise((resolver) => setTimeout(resolver, 450));
      await estabilizar(fixture);

      const consulta = controle.expectOne(`${url}/admin/reservas/consulta`);
      expect(consulta.request.body).toEqual({ busca: 'Souza', page: 1 });
      consulta.flush(pagina([reserva()]));
      await estabilizar(fixture);
      expect(navegar).not.toHaveBeenCalled();

      const botao = [...fixture.nativeElement.querySelectorAll('button')].find((b: Element) =>
        b.textContent?.includes('Canceladas'),
      ) as HTMLButtonElement;
      botao.click();
      expect(navegar).toHaveBeenCalledWith([], {
        queryParams: { status: 'CANCELADO', pagina: null },
        queryParamsHandling: 'merge',
      });
    });

    it('detalhe mostra RA, email e linha do tempo, e revoga com justificativa', async () => {
      const fixture = TestBed.createComponent(DetalheReserva);
      fixture.componentRef.setInput('id', '7');
      await estabilizar(fixture);
      controle.expectOne(`${url}/admin/reservas/7`).flush({ data: reserva() });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).toContain('2024000001');
      expect(elemento.textContent).toContain('ana@email.com');
      expect(elemento.textContent).toContain('termos versão 2026-11');
      expect(elemento.textContent).toContain('a partir do início da reserva');

      [...elemento.querySelectorAll('button')]
        .find((b) => b.textContent?.includes('Revogar reserva'))!
        .click();
      await estabilizar(fixture);
      const campo = elemento.querySelector('#justificativa') as HTMLTextAreaElement;
      const confirmar = [...elemento.querySelectorAll('button')].find((b) =>
        b.textContent?.includes('Confirmar revogação'),
      ) as HTMLButtonElement;
      expect(confirmar.disabled).toBe(true);
      expect(elemento.textContent).toContain('O aluno recebe um email');

      campo.value = 'Quadra interditada para manutenção.';
      campo.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
      await estabilizar(fixture);

      const revogar = controle.expectOne(`${url}/admin/reservas/7/revogar`);
      expect(revogar.request.body).toEqual({
        justificativa: 'Quadra interditada para manutenção.',
      });
      revogar.flush({
        data: reserva({
          status: 'REVOGADO',
          justificativa_revogacao: 'Quadra interditada para manutenção.',
          revogada_em: '2026-11-12T10:00:00Z',
        }),
      });
      await estabilizar(fixture);
      expect(elemento.textContent).toContain('Revogada');
      expect(elemento.textContent).toContain('Motivo da revogação');
      expect(elemento.textContent).not.toContain('Revogar reserva');
    });

    it('registra a devolução de uma reserva que já começou, exigindo a observação da avaria', async () => {
      const fixture = TestBed.createComponent(DetalheReserva);
      fixture.componentRef.setInput('id', '7');
      await estabilizar(fixture);
      controle
        .expectOne(`${url}/admin/reservas/7`)
        .flush({ data: reserva({ data_reserva: ontem }) });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      const situacao = elemento.querySelector('#situacao-5') as HTMLSelectElement;
      situacao.value = 'DEVOLVIDO_COM_AVARIA';
      situacao.dispatchEvent(new Event('change'));
      const quantidade = elemento.querySelector('#quantidade-5') as HTMLInputElement;
      quantidade.value = '2';
      quantidade.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      [...elemento.querySelectorAll('form')]
        .find((f) => f.textContent?.includes('Salvar devolução'))!
        .dispatchEvent(new Event('submit'));
      await estabilizar(fixture);

      const envio = controle.expectOne(`${url}/admin/reservas/7/devolucao`);
      expect(envio.request.body).toEqual({
        itens: [
          {
            equipamento_id: 5,
            situacao: 'DEVOLVIDO_COM_AVARIA',
            quantidade_devolvida: 2,
            observacao: null,
          },
        ],
      });
      envio.flush(
        { message: 'Erro', errors: { 'itens.0.observacao': ['Descreva a avaria.'] } },
        { status: 422, statusText: 'erro' },
      );
      await estabilizar(fixture);
      expect(elemento.textContent).toContain('Descreva a avaria.');
    });

    it('exporta com os filtros e as colunas escolhidas e baixa o arquivo com o nome da API', async () => {
      const criar = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:teste');
      vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined);
      const clicar = vi
        .spyOn(HTMLAnchorElement.prototype, 'click')
        .mockImplementation(() => undefined);
      const fixture = TestBed.createComponent(ExportarReservas);
      fixture.componentRef.setInput('filtros', { status: 'RESERVADO', busca: 'Souza' });
      await estabilizar(fixture);

      const detalhes = fixture.nativeElement.querySelector('details') as HTMLDetailsElement;
      detalhes.open = true;
      detalhes.dispatchEvent(new Event('toggle'));
      controle.expectOne(`${url}/admin/reservas/exportar/colunas`).flush({
        data: {
          obrigatorias: [{ chave: 'id', titulo: 'ID' }],
          opcionais: [{ chave: 'curso', titulo: 'Curso' }],
        },
      });
      await estabilizar(fixture);
      (fixture.nativeElement.querySelector('input[type=checkbox]') as HTMLInputElement).click();
      await estabilizar(fixture);
      [...fixture.nativeElement.querySelectorAll('button')]
        .find((b: Element) => b.textContent?.includes('Baixar CSV'))
        .click();

      const exportar = controle.expectOne(`${url}/admin/reservas/exportar`);
      expect(exportar.request.method).toBe('POST');
      expect(exportar.request.body).toEqual({
        status: 'RESERVADO',
        busca: 'Souza',
        colunas: ['curso'],
      });
      exportar.flush(new Blob(['ID']), {
        headers: {
          'Content-Disposition': 'attachment; filename=reservas_a_partir_de_2026-11-16.csv',
        },
      });
      expect(criar).toHaveBeenCalled();
      expect(clicar).toHaveBeenCalled();
    });

    it('registra o uso de um aluno pelo RA no corpo e abre o detalhe com o aviso do limite', async () => {
      const navegar = vi.spyOn(router, 'navigate').mockResolvedValue(true);
      const fixture = TestBed.createComponent(RegistrarUso);
      fixture.componentRef.setInput('espaco', '1');
      fixture.componentRef.setInput('data', ontem);
      fixture.componentRef.setInput('horario', 'PRIMEIRO_HORARIO');
      await estabilizar(fixture);
      controle.expectOne(`${url}/espacos`).flush({
        data: [
          {
            id: 1,
            nome: 'Quadra Poliesportiva',
            tipo: 'QUADRA',
            limite_semanal_por_curso: 3,
            modo_horario: 'FIXO',
            abre_as: null,
            fecha_as: null,
            duracao_maxima_minutos: null,
          },
        ],
      });
      await estabilizar(fixture);
      controle
        .expectOne((r) => r.url === `${url}/espacos/1/equipamentos/disponibilidade`)
        .flush({ data: [] });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      const ra = elemento.querySelector('#uso-ra') as HTMLInputElement;
      ra.value = '2024000001';
      ra.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
      await estabilizar(fixture);

      const envio = controle.expectOne(`${url}/admin/reservas/registro-posterior`);
      expect(envio.request.body).toEqual({
        espaco_id: 1,
        data_reserva: ontem,
        horario: 'PRIMEIRO_HORARIO',
        registro_academico: '2024000001',
      });
      envio.flush(
        { data: reserva({ id: 40, origem: 'REGISTRO_POSTERIOR' }), aviso: 'Passou do limite.' },
        { status: 201, statusText: 'criado' },
      );
      expect(navegar).toHaveBeenCalledWith(['/admin/reservas', 40], {
        state: { aviso: 'Passou do limite.' },
      });
    });

    it('registro de grupo manda a descrição e mostra o conflito com link', async () => {
      const fixture = TestBed.createComponent(RegistrarUso);
      fixture.componentRef.setInput('espaco', '4');
      fixture.componentRef.setInput('data', ontem);
      fixture.componentRef.setInput('inicio', '14:00');
      fixture.componentRef.setInput('fim', '15:30');
      await estabilizar(fixture);
      controle.expectOne(`${url}/espacos`).flush({
        data: [
          {
            id: 4,
            nome: 'Laboratório de Informática',
            tipo: 'LABORATORIO',
            limite_semanal_por_curso: null,
            modo_horario: 'LIVRE',
            abre_as: '07:00',
            fecha_as: '22:00',
            duracao_maxima_minutos: 180,
          },
        ],
      });
      await estabilizar(fixture);
      controle
        .expectOne((r) => r.url === `${url}/espacos/4/equipamentos/disponibilidade`)
        .flush({ data: [] });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      const grupo = elemento.querySelector('input[value=GRUPO]') as HTMLInputElement;
      grupo.click();
      await estabilizar(fixture);
      const descricao = elemento.querySelector('#uso-descricao') as HTMLInputElement;
      descricao.value = 'Turma de Enfermagem';
      descricao.dispatchEvent(new Event('input'));
      const responsavel = elemento.querySelector('#uso-responsavel') as HTMLInputElement;
      responsavel.value = 'Prof. Ana Reis';
      responsavel.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      elemento.querySelector('form')!.dispatchEvent(new Event('submit'));
      await estabilizar(fixture);

      const envio = controle.expectOne(`${url}/admin/reservas/registro-posterior`);
      expect(envio.request.body).toEqual({
        espaco_id: 4,
        data_reserva: ontem,
        hora_inicio: '14:00',
        hora_fim: '15:30',
        descricao: 'Turma de Enfermagem',
        responsavel_nome: 'Prof. Ana Reis',
      });
      envio.flush(
        {
          message: 'Já existe uma reserva ou evento nesse horário. Confira o registro existente.',
          dados: { reserva_conflitante_id: 12 },
        },
        { status: 409, statusText: 'conflito' },
      );
      await estabilizar(fixture);
      expect(elemento.textContent).toContain('Ver o registro nº 12');
    });

    it('lista mostra o selo de registro posterior e o grupo', async () => {
      const fixture = TestBed.createComponent(ListaReservas);
      await estabilizar(fixture);
      controle.expectOne(`${url}/espacos`).flush({ data: [] });
      controle.expectOne(`${url}/cursos`).flush({ data: [] });
      controle.expectOne(`${url}/admin/reservas/consulta`).flush(
        pagina([
          reserva({
            tipo: 'EVENTO',
            aluno: null,
            origem: 'REGISTRO_POSTERIOR',
            descricao: 'Turma de Enfermagem',
            situacao_confirmacao: null,
          }),
        ]),
      );
      await estabilizar(fixture);
      const texto = fixture.nativeElement.textContent as string;
      expect(texto).toContain('Registro posterior');
      expect(texto).toContain('Grupo: Turma de Enfermagem');
    });

    it('admin recusa um pedido no detalhe e a devolução mostra só os itens que valem', async () => {
      const comPedidos = reserva({
        data_reserva: ontem,
        equipamentos: [
          {
            id: 5,
            nome: 'Bola de futsal',
            quantidade: 1,
            devolucao: {
              situacao: 'PENDENTE',
              quantidade_devolvida: null,
              observacao: null,
              devolvido_em: null,
            },
            solicitacao: null,
          },
          {
            id: 7,
            nome: 'Projetor',
            quantidade: 1,
            devolucao: {
              situacao: 'PENDENTE',
              quantidade_devolvida: null,
              observacao: null,
              devolvido_em: null,
            },
            solicitacao: { situacao: 'AGUARDANDO', justificativa: null, respondida_em: null },
          },
          {
            id: 8,
            nome: 'Caixa de som',
            quantidade: 1,
            devolucao: {
              situacao: 'PENDENTE',
              quantidade_devolvida: null,
              observacao: null,
              devolvido_em: null,
            },
            solicitacao: {
              situacao: 'RECUSADA',
              justificativa: 'Em manutenção.',
              respondida_em: null,
            },
          },
        ],
      });
      const fixture = TestBed.createComponent(DetalheReserva);
      fixture.componentRef.setInput('id', '7');
      await estabilizar(fixture);
      controle.expectOne(`${url}/admin/reservas/7`).flush({ data: comPedidos });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).toContain('De outro espaço: pedido aguardando aprovação');
      expect(elemento.textContent).toContain('De outro espaço: pedido recusado: Em manutenção.');
      expect(elemento.querySelector('#situacao-5')).not.toBeNull();
      expect(elemento.querySelector('#situacao-7')).toBeNull();
      expect(elemento.querySelector('#situacao-8')).toBeNull();

      [...elemento.querySelectorAll('button')]
        .find((b) => b.textContent?.includes('Recusar pedido'))!
        .click();
      await estabilizar(fixture);
      const motivo = elemento.querySelector('#motivo-7') as HTMLTextAreaElement;
      motivo.value = 'Projetor reservado para a coordenação.';
      motivo.dispatchEvent(new Event('input'));
      await estabilizar(fixture);
      [...elemento.querySelectorAll('form')]
        .find((f) => f.textContent?.includes('Confirmar recusa'))!
        .dispatchEvent(new Event('submit'));
      const recusa = controle.expectOne(`${url}/admin/reservas/7/solicitacoes/7/recusar`);
      expect(recusa.request.body).toEqual({
        justificativa: 'Projetor reservado para a coordenação.',
      });
      recusa.flush({
        data: {
          ...comPedidos,
          equipamentos: comPedidos.equipamentos!.map((e) =>
            e.id === 7
              ? {
                  ...e,
                  solicitacao: {
                    situacao: 'RECUSADA',
                    justificativa: 'Projetor reservado para a coordenação.',
                    respondida_em: null,
                  },
                }
              : e,
          ),
        },
      });
      await estabilizar(fixture);
      expect(elemento.textContent).toContain(
        'De outro espaço: pedido recusado: Projetor reservado para a coordenação.',
      );
      expect(elemento.textContent).not.toContain('Aprovar pedido');
    });

    it('com vários pedidos aguardando, aprova todos de uma vez e mostra o obrigatório', async () => {
      const pedido = (id: number, nome: string, obrigatorio: boolean) => ({
        id,
        nome,
        quantidade: 1,
        devolucao: {
          situacao: 'PENDENTE' as const,
          quantidade_devolvida: null,
          observacao: null,
          devolvido_em: null,
        },
        solicitacao: {
          situacao: 'AGUARDANDO' as const,
          justificativa: null,
          respondida_em: null,
          obrigatorio,
        },
      });
      const fixture = TestBed.createComponent(DetalheReserva);
      fixture.componentRef.setInput('id', '7');
      await estabilizar(fixture);
      controle.expectOne(`${url}/admin/reservas/7`).flush({
        data: reserva({
          equipamentos: [pedido(7, 'Projetor', true), pedido(9, 'Microscópio', false)],
        }),
      });
      await estabilizar(fixture);

      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).toContain('O aluno marcou como obrigatório para a reserva');
      expect(elemento.textContent).toContain('2 pedidos aguardando: responda todos de uma vez');
      [...elemento.querySelectorAll('button')]
        .find((b) => b.textContent?.includes('Aprovar todos os pedidos'))!
        .click();
      controle.expectOne(`${url}/admin/reservas/7/solicitacoes/aprovar`).flush({ data: reserva() });
      await estabilizar(fixture);
      expect(elemento.textContent).not.toContain('pedidos aguardando');
    });

    it('admin aprova um pedido e o item passa a valer na devolução', async () => {
      const comPedido = reserva({
        data_reserva: ontem,
        equipamentos: [
          {
            id: 7,
            nome: 'Projetor',
            quantidade: 1,
            devolucao: {
              situacao: 'PENDENTE',
              quantidade_devolvida: null,
              observacao: null,
              devolvido_em: null,
            },
            solicitacao: { situacao: 'AGUARDANDO', justificativa: null, respondida_em: null },
          },
        ],
      });
      const fixture = TestBed.createComponent(DetalheReserva);
      fixture.componentRef.setInput('id', '7');
      await estabilizar(fixture);
      controle.expectOne(`${url}/admin/reservas/7`).flush({ data: comPedido });
      await estabilizar(fixture);
      const elemento = fixture.nativeElement as HTMLElement;
      expect(elemento.textContent).not.toContain('Registrar a devolução');

      [...elemento.querySelectorAll('button')]
        .find((b) => b.textContent?.includes('Aprovar pedido'))!
        .click();
      controle.expectOne(`${url}/admin/reservas/7/solicitacoes/7/aprovar`).flush({
        data: {
          ...comPedido,
          equipamentos: comPedido.equipamentos!.map((e) => ({
            ...e,
            solicitacao: { situacao: 'APROVADA', justificativa: null, respondida_em: null },
          })),
        },
      });
      await estabilizar(fixture);
      expect(elemento.textContent).toContain('De outro espaço: pedido aprovado');
      expect(elemento.textContent).toContain('Registrar a devolução');
      expect(elemento.querySelector('#situacao-7')).not.toBeNull();
    });

    it('lista mostra o selo de pedido aguardando e o painel conta os pedidos', async () => {
      const fixture = TestBed.createComponent(ListaReservas);
      fixture.componentRef.setInput('solicitacao', 'AGUARDANDO');
      await estabilizar(fixture);
      controle.expectOne(`${url}/espacos`).flush({ data: [] });
      controle.expectOne(`${url}/cursos`).flush({ data: [] });
      const consulta = controle.expectOne(`${url}/admin/reservas/consulta`);
      expect(consulta.request.body).toEqual({ solicitacao: 'AGUARDANDO', page: 1 });
      consulta.flush(
        pagina([
          reserva({
            equipamentos: [
              {
                id: 7,
                nome: 'Projetor',
                quantidade: 1,
                devolucao: {
                  situacao: 'PENDENTE',
                  quantidade_devolvida: null,
                  observacao: null,
                  devolvido_em: null,
                },
                solicitacao: { situacao: 'AGUARDANDO', justificativa: null, respondida_em: null },
              },
            ],
          }),
        ]),
      );
      await estabilizar(fixture);
      expect(fixture.nativeElement.textContent).toContain('Pedido de equipamento');

      const painel = TestBed.createComponent(Painel);
      await estabilizar(painel);
      const contagem = controle.expectOne(`${url}/admin/reservas/consulta`);
      expect(contagem.request.body).toEqual({ solicitacao: 'AGUARDANDO', page: 1, per_page: 1 });
      contagem.flush({
        ...pagina([]),
        meta: { current_page: 1, last_page: 3, total: 3, per_page: 1 },
      });
      controle.expectOne(`${url}/admin/equipamentos/fora-agora`).flush({
        total: 1,
        data: [
          {
            equipamento: { id: 2, nome: 'Bola' },
            quantidade: 2,
            retirado_em: '2026-11-09T21:40:00Z',
            reserva: {
              id: 9,
              data_reserva: ontem,
              hora_inicio: '19:00',
              hora_fim: '20:30',
              espaco: 'Quadra',
              com_quem: 'Carla Mendes',
              terminou: true,
            },
          },
        ],
      });
      await estabilizar(painel);
      const texto = painel.nativeElement.textContent as string;
      expect(texto).toContain('3 pedidos de equipamento aguardando');
      expect(texto).toContain('Equipamentos fora agora (1)');
      expect(texto).toContain('Bola × 2');
      expect(texto).toContain('Carla Mendes');
      expect(texto).toContain('Passou do horário');
    });

    it('não exporta mais de 366 dias', async () => {
      const fixture = TestBed.createComponent(ExportarReservas);
      fixture.componentRef.setInput('filtros', { de: '2025-01-01', ate: '2026-12-31' });
      await estabilizar(fixture);
      expect(fixture.nativeElement.textContent).toContain('Escolha um período de até 366 dias');
    });
  });
});
