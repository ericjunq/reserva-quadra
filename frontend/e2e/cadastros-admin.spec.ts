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
