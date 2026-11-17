import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin vê o histórico de uma reserva e filtra o histórico geral', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto('/admin/reservas/77');
  await expect(page.getByRole('heading', { name: 'Reserva nº 77' })).toBeVisible();
  expect(chamadas.some((chamada) => chamada.url.startsWith('/api/admin/auditorias'))).toBe(false);
  await page.getByRole('button', { name: 'Mostrar o histórico' }).click();
  await expect(page.getByText('Alterou', { exact: true })).toBeVisible();
  await expect(page.getByText('Criou', { exact: true })).toBeVisible();

  await page.getByRole('link', { name: 'Ver tudo no histórico' }).click();
  await expect(page.getByRole('heading', { name: 'Histórico', exact: true })).toBeVisible();
  await expect(page.getByText('Só o registro Reserva nº 77')).toBeVisible();
  await expect(page).toHaveURL(/tipo=RESERVA&registro=77/);
  await expect(page.getByText('2 mudanças')).toBeVisible();

  await page.getByRole('button', { name: 'Ver mudanças' }).first().click();
  const linha = page.getByRole('row', { name: /Situação/ });
  await expect(linha).toContainText('Reservada');
  await expect(linha).toContainText('Revogada');

  await page.getByLabel('Quem fez').selectOption({ label: 'Sistema, aluno ou parceiro' });
  await expect(page).toHaveURL(/quem=sistema/);
  await expect(page.getByText('1 mudança', { exact: true })).toBeVisible();
  await expect(page.getByText('Sistema ou aluno', { exact: false }).last()).toBeVisible();

  await page.getByRole('button', { name: 'Mostrar todos' }).click();
  await expect(page.getByText('Só o registro')).toHaveCount(0);
  await page.getByRole('button', { name: 'Limpar filtros' }).click();
  await expect(page.getByText('3 mudanças')).toBeVisible();
  await page.getByRole('link', { name: 'Reserva nº 77' }).first().click();
  await expect(page.getByRole('heading', { name: 'Reserva nº 77' })).toBeVisible();

  const consultas = chamadas.filter((chamada) => chamada.url.startsWith('/api/admin/auditorias'));
  expect(consultas.some((chamada) => chamada.url.includes('sem_admin=1'))).toBe(true);
  for (const chamada of consultas) {
    expect(chamada.url).not.toMatch(/2024000009|carla|Carla/);
  }
  expect(page.url()).not.toMatch(/2024000009|carla/);
});
