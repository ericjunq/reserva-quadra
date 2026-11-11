import { expect, test } from '@playwright/test';
import { formatarDia } from '../src/app/publico/datas';
import { datas, prepararApi, rotuloDoHorario, tituloDaSemana } from './api-falsa';

test('o aluno escolhe a quadra, troca de semana e reserva um horário com equipamento', async ({
  page,
}) => {
  const chamadas = await prepararApi(page);
  await page.goto('/');

  await page.getByRole('link', { name: /Quadra Poliesportiva/ }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Quadra Poliesportiva');
  await expect(page.getByText(tituloDaSemana(datas.segundaAtual))).toBeVisible();

  await page.getByRole('button', { name: 'Próxima semana' }).click();
  await expect(page.getByText(tituloDaSemana(datas.segundaSeguinte))).toBeVisible();
  await expect(page).toHaveURL(new RegExp(`semana=${datas.segundaSeguinte}`));
  await expect(page.getByText('Ana Souza')).toBeVisible();

  await page
    .getByRole('link', { name: rotuloDoHorario(datas.tercaSeguinte, '20:30', '22:00') })
    .click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reservar Quadra Poliesportiva');
  await expect(page.locator('aside')).toContainText(formatarDia(datas.tercaSeguinte));

  await page.getByLabel('O registro acadêmico, com 10 números').fill('2024000001');
  await page.getByRole('button', { name: 'Mais Bola de futsal' }).click();
  await expect(page.locator('aside')).toContainText('1× Bola de futsal');
  await page.getByRole('button', { name: 'Reservar', exact: true }).click();

  await expect(page.getByRole('heading', { name: 'Reserva feita!' })).toBeVisible();
  const reserva = chamadas.find((chamada) => chamada.url === '/api/reservas');
  expect(reserva?.corpo).toEqual({
    registro_academico: '2024000001',
    espaco_id: 1,
    data_reserva: datas.tercaSeguinte,
    horario: 'SEGUNDO_HORARIO',
    equipamentos: [{ id: 1, quantidade: 1 }],
  });
  expect(chamadas.every((chamada) => !chamada.url.includes('2024000001'))).toBe(true);
});

test('o calendário abre direto pelo endereço e reaproveita a semana já carregada', async ({
  page,
}) => {
  const chamadas = await prepararApi(page);
  await page.goto('/espacos/1');
  await expect(page.getByText(tituloDaSemana(datas.segundaAtual))).toBeVisible();

  await page.getByRole('button', { name: 'Próxima semana' }).click();
  await expect(page.getByText(tituloDaSemana(datas.segundaSeguinte))).toBeVisible();
  await page.getByRole('button', { name: 'Semana anterior' }).click();
  await expect(page.getByText(tituloDaSemana(datas.segundaAtual))).toBeVisible();

  const consultas = chamadas.filter((chamada) => chamada.url.includes('/disponibilidade'));
  expect(consultas.map((chamada) => chamada.url)).toEqual([
    `/api/espacos/1/disponibilidade?de=${datas.segundaAtual}&ate=${datas.sextaAtual}`,
    `/api/espacos/1/disponibilidade?de=${datas.segundaSeguinte}&ate=${datas.sextaSeguinte}`,
  ]);
});

test('o aluno sem cadastro se cadastra e volta para a reserva com o RA preenchido', async ({
  page,
}) => {
  await prepararApi(page);
  const endereco = `/espacos/1/reservar?data=${datas.quartaSeguinte}&horario=SEGUNDO_HORARIO`;
  await page.goto(endereco);

  await page.getByRole('link', { name: 'Fazer meu cadastro' }).click();
  await expect(page.getByRole('heading', { name: 'Cadastro do aluno' })).toBeVisible();
  await page.getByLabel('Nome completo').fill('Bruno Lima');
  await page.locator('#ra').fill('2024000002');
  await page.locator('#email').fill('bruno@email.com');
  await page.getByLabel('Curso').selectOption({ label: 'Direito' });
  await page.getByRole('button', { name: 'Cadastrar' }).click();

  await expect(page).toHaveURL(endereco);
  await expect(page.locator('#ra')).toHaveValue('2024000002');
});
