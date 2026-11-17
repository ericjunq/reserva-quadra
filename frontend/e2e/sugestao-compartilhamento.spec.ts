import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin aceita a sugestão de compartilhamento pelo painel', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  const sugestoes = page.getByRole('region', { name: 'Sugestões de compartilhamento' });
  await expect(sugestoes.getByText('pedido 3 vezes pelo')).toBeVisible();
  await sugestoes.getByRole('button', { name: 'Compartilhar com Quadra Poliesportiva' }).click();

  await expect(
    page.getByText('Microscópio agora é compartilhado com Quadra Poliesportiva.'),
  ).toBeVisible();
  await expect(sugestoes).toHaveCount(0);
  expect(
    chamadas.some(
      (chamada) =>
        chamada.url === '/api/admin/equipamentos/5/sugestoes-compartilhamento/1/aceitar' &&
        chamada.metodo === 'POST',
    ),
  ).toBe(true);
});

test('o admin dispensa a sugestão no detalhe do equipamento', async ({ page }) => {
  await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto('/admin/cadastros/equipamentos/5');
  await expect(page.getByRole('heading', { name: 'Microscópio' })).toBeVisible();
  await page.getByRole('button', { name: /^Agora não/ }).click();
  await expect(
    page.getByText('Sugestão dispensada. Ela volta se surgirem novos pedidos.'),
  ).toBeVisible();
});
