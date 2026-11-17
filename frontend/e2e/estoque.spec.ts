import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin manda um equipamento para manutenção com o estado e vê a movimentação', async ({
  page,
}) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto('/admin/cadastros/equipamentos/5');
  await expect(page.getByRole('heading', { name: 'Microscópio' })).toBeVisible();
  await expect(page.getByLabel('Quantidade total')).toHaveCount(0);
  await expect(page.getByText('Cadastro do equipamento.')).toBeVisible();

  await page.getByLabel('O que aconteceu').selectOption({ label: 'Foi para manutenção' });
  await page.getByLabel('Estado do equipamento').fill('Lente riscada, enviada para conserto.');
  await page.getByRole('button', { name: 'Registrar no estoque' }).click();

  await expect(page.getByText('Movimentação registrada.')).toBeVisible();
  await expect(page.getByText('Lente riscada, enviada para conserto.')).toBeVisible();
  await expect(page.getByText('Foi para manutenção · −1')).toBeVisible();
  const estoque = page.getByRole('region', { name: 'Estoque' });
  await expect(estoque.locator('dd').nth(1)).toHaveText('1');
  await expect(estoque.locator('dd').nth(2)).toHaveText('1');

  const envio = chamadas.find(
    (chamada) =>
      chamada.url === '/api/admin/equipamentos/5/movimentacoes' && chamada.metodo === 'POST',
  );
  expect(envio?.corpo).toEqual({
    tipo: 'MANUTENCAO',
    quantidade: 1,
    estado: 'Lente riscada, enviada para conserto.',
  });
});
