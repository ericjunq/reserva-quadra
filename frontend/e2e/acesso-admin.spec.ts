import { expect, test } from '@playwright/test';
import { prepararApi, tokenDoEmail } from './api-falsa';

test('o admin entra, vê o painel e sai', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto('/admin/minha-conta');

  await expect(page).toHaveURL(/\/admin\/entrar\?voltar=%2Fadmin%2Fminha-conta$/);
  await page.getByLabel('Email').fill('eric@fac.edu');
  await page.getByLabel('Senha').fill('errada');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page.getByText('Credenciais inválidas')).toBeVisible();

  await page.getByLabel('Senha').fill('Senha1234');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page.getByRole('heading', { name: 'Minha conta' })).toBeVisible();
  await expect(page.getByText('eric@fac.edu')).toBeVisible();
  expect(page.url()).not.toContain('eric');

  await page.getByRole('link', { name: 'Painel' }).click();
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.getByRole('button', { name: 'Sair' }).click();
  await expect(page).toHaveURL(/\/admin\/entrar$/);
  await expect(page.getByText('Sua sessão terminou')).toHaveCount(0);
  expect(chamadas.some((chamada) => chamada.url === '/api/logout')).toBe(true);

  await page.goto('/admin');
  await expect(page).toHaveURL(/\/admin\/entrar/);
});

test('o admin cria uma senha nova pelo link do email', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await page.goto(`/redefinir-senha/${tokenDoEmail}`);

  await expect(page).toHaveURL(/\/redefinir-senha$/);
  await page.getByLabel('Email').fill('eric@fac.edu');
  await page.getByLabel('Senha nova', { exact: true }).fill('Nova12345');
  await page.getByLabel('Repita a senha nova').fill('Nova12345');
  await page.getByRole('button', { name: 'Salvar a senha' }).click();

  await expect(page.getByText('Senha redefinida. Faça login com a nova senha.')).toBeVisible();
  const envio = chamadas.find((chamada) => chamada.url === '/api/redefinir-senha');
  expect(envio?.corpo).toEqual({
    token: tokenDoEmail,
    email: 'eric@fac.edu',
    password: 'Nova12345',
    password_confirmation: 'Nova12345',
  });
});
