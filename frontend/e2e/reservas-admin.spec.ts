import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin filtra as reservas, abre o detalhe e revoga com justificativa', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);

  await page.getByRole('link', { name: 'Reservas', exact: true }).first().click();
  await expect(page.getByRole('heading', { name: 'Reservas' })).toBeVisible();
  await expect(page.getByText('Carla Dias').filter({ visible: true }).first()).toBeVisible();
  await expect(
    page.getByText('Aguardando confirmação').filter({ visible: true }).first(),
  ).toBeVisible();
  await expect(page.getByText('2024000009')).toHaveCount(0);

  await page.getByLabel('Confirmação').selectOption('AGUARDANDO_CONFIRMACAO');
  await expect(page).toHaveURL(/confirmacao=AGUARDANDO_CONFIRMACAO/);

  await page.getByLabel('Nome ou RA').fill('Carla');
  await expect
    .poll(() =>
      chamadas.some((chamada) => (chamada.corpo as { busca?: string })?.busca === 'Carla'),
    )
    .toBe(true);
  expect(page.url()).not.toContain('Carla');

  await page
    .getByRole('link', { name: /\d{2}\/\d{2}/ })
    .first()
    .click();
  await expect(page.getByRole('heading', { name: 'Reserva nº 77' })).toBeVisible();
  await expect(page.getByText('2024000009')).toBeVisible();
  await expect(page.getByText('carla@email.com')).toBeVisible();

  await page.getByRole('button', { name: 'Revogar reserva' }).click();
  const confirmar = page.getByRole('button', { name: 'Confirmar revogação' });
  await expect(confirmar).toBeDisabled();
  await page.getByLabel('Por que esta reserva será revogada?').fill('Laboratório em manutenção.');
  await confirmar.click();
  await expect(page.getByText('Motivo da revogação')).toBeVisible();
  await expect(page.getByText('Revogada', { exact: true }).first()).toBeVisible();

  await page.getByRole('button', { name: 'Voltar à lista' }).click();
  await expect(page).toHaveURL(/\/admin\/reservas\?confirmacao=AGUARDANDO_CONFIRMACAO$/);
});
