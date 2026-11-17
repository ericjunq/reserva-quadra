import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin registra a retirada da bola e vê no painel o que está fora', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();
  await expect(page.getByText(/Equipamentos fora agora/)).toHaveCount(0);

  await page.goto('/admin/reservas/78');
  await expect(page.getByRole('heading', { name: 'Registrar a retirada' })).toBeVisible();
  await page
    .getByLabel(/Bola de vôlei/)
    .first()
    .fill('2');
  await page.getByRole('button', { name: 'Salvar retirada' }).click();

  await expect(page.getByText('Retirada registrada.')).toBeVisible();
  await expect(page.getByText(/Retirado: 2 em/)).toBeVisible();
  const envio = chamadas.find((chamada) => chamada.url === '/api/admin/reservas/78/retirada');
  expect(envio?.corpo).toEqual({ itens: [{ equipamento_id: 2, quantidade_retirada: 2 }] });

  await page.goto('/admin');
  await expect(page.getByRole('heading', { name: 'Equipamentos fora agora (1)' })).toBeVisible();
  await page.getByRole('link', { name: 'Bola de vôlei × 2' }).click();
  await expect(page).toHaveURL(/\/admin\/reservas\/78$/);
});
