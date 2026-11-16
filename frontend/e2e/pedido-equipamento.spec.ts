import { expect, test } from '@playwright/test';
import { datas, prepararApi, rotuloDoHorario } from './api-falsa';

test('o aluno pede um equipamento de outro espaço junto da reserva', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto(`/espacos/1?semana=${datas.segundaSeguinte}`);
  await page
    .getByRole('link', { name: rotuloDoHorario(datas.tercaSeguinte, '20:30', '22:00') })
    .click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reservar Quadra Poliesportiva');

  await page.getByText('Pedir equipamento de outro espaço').click();
  await expect(page.getByText('Do espaço Laboratório de Informática')).toBeVisible();
  await expect(page.getByText('A reserva do espaço vale de qualquer jeito.')).toBeVisible();
  await page.getByRole('button', { name: 'Pedir mais Microscópio' }).click();
  await expect(page.locator('aside')).toContainText('Pedidos (dependem de aprovação)');
  await expect(page.locator('aside')).toContainText('1× Microscópio');

  await page.getByLabel('O registro acadêmico, com 10 números').fill('2024000001');
  await page.getByRole('button', { name: 'Reservar', exact: true }).click();
  await expect(page.getByText('Reserva feita!')).toBeVisible();
  await expect(
    page.getByText('O pedido de equipamento de outro espaço depende da aprovação'),
  ).toBeVisible();

  const envio = chamadas.find(
    (chamada) => chamada.url === '/api/reservas' && chamada.metodo === 'POST',
  );
  expect(envio?.corpo).toMatchObject({ solicitacoes: [{ id: 5, quantidade: 1 }] });
});
