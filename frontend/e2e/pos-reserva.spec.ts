import { expect, test } from '@playwright/test';
import { prepararApi, tokenDoEmail } from './api-falsa';

test('o aluno consulta as próprias reservas com RA e email', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto('/');
  await page.getByRole('link', { name: 'Minhas reservas' }).click();

  await page.getByLabel('RA', { exact: true }).fill('2024000003');
  await page.getByLabel('Email', { exact: true }).fill('outra@email.com');
  await page.getByRole('button', { name: 'Consultar' }).click();
  await expect(page.getByText('Não encontramos reservas para esses dados.')).toBeVisible();

  await page.getByLabel('Email', { exact: true }).fill('carla@email.com');
  await page.getByRole('button', { name: 'Consultar' }).click();
  await expect(page.getByText('Laboratório de Informática')).toBeVisible();
  await expect(page.getByText(/Aguardando a sua confirmação pelo email até/)).toBeVisible();

  expect(page.url()).not.toContain('2024000003');
  expect(page.url()).not.toContain('carla');
  const consulta = chamadas
    .filter((chamada) => chamada.url.startsWith('/api/minhas-reservas'))
    .at(-1);
  expect(consulta?.corpo).toEqual({
    registro_academico: '2024000003',
    email: 'carla@email.com',
    periodo: 'FUTURAS',
  });
});

test('o aluno confirma pelo link do email aceitando os termos', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto(`/reservas/confirmar/${tokenDoEmail}`);

  await expect(page.getByText('Laboratório de Informática')).toBeVisible();
  await expect(page).toHaveURL(/\/reservas\/confirmar$/);
  await expect(page.getByText('Toda reserva precisa ser confirmada.')).toBeVisible();

  const confirmar = page.getByRole('button', { name: 'Confirmar reserva' });
  await expect(confirmar).toBeDisabled();
  await page.getByLabel('Li e aceito os termos de uso e o aviso de privacidade.').check();
  await confirmar.click();

  await expect(page.getByRole('heading', { name: 'Reserva confirmada!' })).toBeVisible();
  const envio = chamadas.find(
    (chamada) =>
      chamada.metodo === 'POST' && chamada.url === `/api/reservas/confirmacao/${tokenDoEmail}`,
  );
  expect(envio?.corpo).toEqual({ aceite_termos: true });
});

test('o aluno cancela pelo link do email', async ({ page }) => {
  await prepararApi(page);
  await page.goto(`/reservas/cancelar/${tokenDoEmail}`);

  await expect(page).toHaveURL(/\/reservas\/cancelar$/);
  await page.getByRole('button', { name: 'Cancelar reserva' }).click();
  await expect(page.getByRole('heading', { name: 'Reserva cancelada' })).toBeVisible();
});

test('a página de termos e privacidade abre pelo rodapé', async ({ page }) => {
  await prepararApi(page);
  await page.goto('/');
  await page.getByRole('link', { name: 'Termos e privacidade' }).click();

  await expect(page.getByRole('heading', { name: 'Termos e privacidade' })).toBeVisible();
  await expect(page.getByText('Nome, RA, email e curso.')).toBeVisible();
});
