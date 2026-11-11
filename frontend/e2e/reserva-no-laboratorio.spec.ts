import { expect, test } from '@playwright/test';
import { formatarDia } from '../src/app/publico/datas';
import { datas, prepararApi } from './api-falsa';

test('o aluno escolhe o dia, monta o horário no laboratório e reserva', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto(`/espacos/4?semana=${datas.segundaSeguinte}`);

  await page.getByRole('button', { name: new RegExp(formatarDia(datas.tercaSeguinte)) }).click();
  await expect(page.locator('#dia-escolhido')).toHaveText(formatarDia(datas.tercaSeguinte));
  await expect(page.getByText('14:00–15:30 · Carla Dias (Enfermagem)')).toBeVisible();
  await expect(page.getByText('Aberto das 07:00 às 22:00 · até 3h por reserva')).toBeVisible();

  await page.getByLabel('Início').fill('14:37');
  await page.getByLabel('Fim').fill('16:00');
  await expect(page.getByText(/Esse horário bate com 14:00–15:30/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Continuar' })).toBeDisabled();

  await page.getByLabel('Início').fill('15:37');
  await page.getByLabel('Fim').fill('17:52');
  await expect(page.getByText('Sua reserva ficará das 15:30 às 17:45.')).toBeVisible();
  await page.getByRole('button', { name: 'Continuar' }).click();

  await expect(page.getByRole('heading', { level: 1 })).toHaveText(
    'Reservar Laboratório de Informática',
  );
  await expect(page.locator('aside')).toContainText('15:30 às 17:45');
  await expect(page.getByText('Microscópio')).toBeVisible();

  await page.getByLabel('O registro acadêmico, com 10 números').fill('2024000003');
  await page.getByRole('button', { name: 'Reservar', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Reserva feita!' })).toBeVisible();

  const reserva = chamadas.find((chamada) => chamada.url === '/api/reservas');
  expect(reserva?.corpo).toEqual({
    registro_academico: '2024000003',
    espaco_id: 4,
    data_reserva: datas.tercaSeguinte,
    hora_inicio: '15:30',
    hora_fim: '17:45',
  });
});
