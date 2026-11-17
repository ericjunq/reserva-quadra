import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin cria um espaço e marca um equipamento para aceitar pedidos', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await page.getByRole('link', { name: 'Cadastros', exact: true }).first().click();
  await page.getByRole('link', { name: /^Espaços/ }).click();
  await expect(page.getByRole('heading', { name: 'Espaços' })).toBeVisible();
  await expect(page.getByText('Laboratório de Informática')).toBeVisible();

  await page.getByRole('link', { name: 'Novo espaço' }).click();
  await page.getByLabel('Nome').fill('Sala 201');
  await expect(page.getByLabel('Duração máxima (min)')).toHaveValue('180');
  await page.getByRole('button', { name: 'Criar espaço' }).click();
  await expect(page.getByRole('heading', { name: 'Sala 201' })).toBeVisible();
  expect(
    chamadas.find((chamada) => chamada.url === '/api/admin/espacos' && chamada.metodo === 'POST')
      ?.corpo,
  ).toMatchObject({
    nome: 'Sala 201',
    modo_horario: 'LIVRE',
    duracao_maxima_minutos: 180,
  });

  await page.goto('/admin/cadastros/equipamentos');
  await page.getByRole('link', { name: 'Microscópio' }).click();
  await page.getByLabel(/Aceita pedido de outros espaços/).check();
  await page.getByRole('button', { name: 'Salvar' }).click();
  await expect(page.getByText('Equipamento salvo.')).toBeVisible();
  expect(chamadas.find((chamada) => chamada.metodo === 'PATCH')?.corpo).toMatchObject({
    aceita_solicitacao: true,
  });
});

test('o admin cria um curso e bloqueia uma parte do dia num espaço', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto('/admin/cadastros/cursos');
  await page.getByLabel('Novo curso').fill('Enfermagem');
  await page.getByRole('button', { name: 'Criar curso' }).click();
  await expect(page.getByText('Curso criado.')).toBeVisible();
  await expect(page.getByText('Enfermagem')).toBeVisible();

  await page.goto('/admin/cadastros/datas-bloqueadas');
  await page.getByRole('link', { name: 'Novo bloqueio' }).click();
  await page.getByLabel('Só uma parte do dia').check();
  await page.getByLabel('Das').fill('14:00');
  await page.getByLabel('Às').fill('16:00');
  await page.getByLabel('Espaço').selectOption('4');
  await page.getByLabel('Motivo').fill('Manutenção elétrica');
  await page.getByRole('button', { name: 'Criar bloqueio' }).click();
  await expect(page.getByText('1 reserva(s) revogada(s), com email aos alunos.')).toBeVisible();
  expect(
    chamadas.find(
      (chamada) => chamada.url === '/api/admin/datas-bloqueadas' && chamada.metodo === 'POST',
    )?.corpo,
  ).toMatchObject({
    hora_inicio: '14:00',
    hora_fim: '16:00',
    espaco_id: 4,
    motivo: 'Manutenção elétrica',
  });
});
