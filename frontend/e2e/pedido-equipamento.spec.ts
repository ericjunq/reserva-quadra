import { expect, test } from '@playwright/test';
import { datas, prepararApi, rotuloDoHorario, tokenDoEmail } from './api-falsa';

test('o aluno pede um equipamento de outro espaço junto da reserva', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto(`/espacos/1?semana=${datas.segundaSeguinte}`);
  await page
    .getByRole('link', { name: rotuloDoHorario(datas.tercaSeguinte, '20:30', '22:00') })
    .click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reservar Quadra Poliesportiva');

  await page.getByText('Pedir equipamento de outro espaço').click();
  await expect(page.getByText('Do espaço Laboratório de Informática')).toBeVisible();
  await expect(page.getByText('sem essa confirmação, a reserva é cancelada')).toBeVisible();
  await expect(page.getByText(/A resposta chega até/)).toBeVisible();
  await page.getByRole('button', { name: 'Pedir mais Microscópio' }).click();
  await page.getByLabel('A reserva só serve com este item').check();
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
  expect(envio?.corpo).toMatchObject({
    solicitacoes: [{ id: 5, quantidade: 1, obrigatorio: true }],
  });
});

test('o aluno mantém a reserva pelo link do email depois de o equipamento não ser liberado', async ({
  page,
}) => {
  const chamadas = await prepararApi(page);
  await page.goto(`/reservas/manter/${tokenDoEmail}`);

  await expect(page).toHaveURL(/\/reservas\/manter$/);
  await expect(page.getByRole('heading', { name: 'Manter a reserva' })).toBeVisible();
  await expect(
    page.getByText(/Se você não confirmar até .*, a reserva será cancelada/),
  ).toBeVisible();
  await page.getByRole('button', { name: 'Manter a reserva sem o equipamento' }).click();

  await expect(page.getByRole('heading', { name: 'Reserva mantida' })).toBeVisible();
  expect(
    chamadas.some(
      (chamada) =>
        chamada.metodo === 'POST' && chamada.url === `/api/reservas/manter/${tokenDoEmail}`,
    ),
  ).toBe(true);
});
