import { expect, test } from '@playwright/test';
import { datas, entrarComoAdmin, prepararApi } from './api-falsa';

test('o admin vê quem reservou no calendário e cria um evento num horário livre', async ({
  page,
}) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto(`/admin/calendario?espaco=4&semana=${datas.segundaSeguinte}`);
  await expect(page.getByRole('heading', { name: 'Calendário' })).toBeVisible();
  await expect(page.getByText('Carla Dias · Enfermagem')).toBeVisible();
  await expect(page.getByText('Aguardando confirmação')).toBeVisible();
  expect(
    chamadas.some(
      (chamada) =>
        chamada.url.startsWith('/api/admin/espacos/4/disponibilidade') &&
        chamada.url.includes(`de=${datas.segundaSeguinte}`),
    ),
  ).toBe(true);

  await page.getByRole('link', { name: 'Reservar aqui' }).first().click();
  await expect(page.getByRole('heading', { name: 'Nova reserva' })).toBeVisible();
  await expect(page.getByLabel('Espaço')).toHaveValue('4');
  await expect(page.getByLabel('Início')).toHaveValue('07:00');
  await page.getByLabel('Evento da faculdade').check();
  await page.getByLabel('Descrição').fill('Maratona de programação');
  await page.getByLabel('Responsável', { exact: true }).fill('Prof. Marcos Lima');
  await page.getByRole('button', { name: 'Criar evento' }).click();

  await expect(page.getByRole('heading', { name: 'Reserva nº 31' })).toBeVisible();
  await expect(page.getByText('Maratona de programação').first()).toBeVisible();
  const envio = chamadas.find((chamada) => chamada.url === '/api/admin/reservas/eventos');
  expect(envio?.corpo).toMatchObject({
    espaco_id: 4,
    data_reserva: datas.segundaSeguinte,
    hora_inicio: '07:00',
    descricao: 'Maratona de programação',
    responsavel_nome: 'Prof. Marcos Lima',
  });
  await expect(page.getByText('Prof. Marcos Lima')).toBeVisible();
  expect(page.url()).not.toContain('Marcos');
});

test('o admin reserva um horário para um aluno pela coordenação', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto(`/admin/calendario?espaco=4&semana=${datas.segundaSeguinte}`);
  await page.getByRole('link', { name: 'Reservar aqui' }).first().click();
  await expect(page.getByRole('heading', { name: 'Nova reserva' })).toBeVisible();
  await expect(page.getByLabel('Para um aluno (RA)')).toBeChecked();
  await page.getByLabel('RA do aluno').fill('2024000009');
  await page.getByText('Equipamento de outro espaço (opcional)').click();
  await page.getByRole('button', { name: 'Mais Microscópio de outro espaço' }).click();
  await page.getByRole('button', { name: 'Reservar para o aluno' }).click();

  await expect(page.getByRole('heading', { name: 'Reserva nº 42' })).toBeVisible();
  await expect(page.getByText('Pelo admin').first()).toBeVisible();
  expect(page.url()).not.toContain('2024000009');
  const envio = chamadas.find((chamada) => chamada.url === '/api/admin/reservas/aluno');
  expect(envio?.corpo).toMatchObject({
    espaco_id: 4,
    data_reserva: datas.segundaSeguinte,
    registro_academico: '2024000009',
    solicitacoes: [{ id: 5, quantidade: 1 }],
  });
});
