import { expect, test } from '@playwright/test';
import { datas, entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin registra o uso de um aluno que já aconteceu e vê a marcação', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.getByRole('link', { name: 'Reservas', exact: true }).first().click();
  await page.getByRole('link', { name: 'Registrar uso' }).click();
  await expect(page.getByRole('heading', { name: 'Registrar uso' })).toBeVisible();

  await page.getByLabel('Espaço').selectOption('4');
  await page.getByLabel('Data').fill(datas.ontem);
  await page.getByLabel('Início').fill('14:00');
  await page.getByLabel('Fim').fill('15:30');
  await page.getByLabel('RA do aluno').fill('2024000009');
  await page.getByRole('button', { name: 'Registrar uso' }).click();

  await expect(page.getByRole('heading', { name: 'Reserva nº 41' })).toBeVisible();
  await expect(page.getByText('Registro posterior').first()).toBeVisible();
  await expect(page.getByText('Registrado depois do uso por Eric')).toBeVisible();
  expect(page.url()).not.toContain('2024000009');
  const envio = chamadas.find(
    (chamada) => chamada.url === '/api/admin/reservas/registro-posterior',
  );
  expect(envio?.corpo).toEqual({
    espaco_id: 4,
    data_reserva: datas.ontem,
    hora_inicio: '14:00',
    hora_fim: '15:30',
    registro_academico: '2024000009',
  });
});
