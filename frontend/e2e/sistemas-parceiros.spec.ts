import { expect, test } from '@playwright/test';
import { entrarComoAdmin, prepararApi, tokenDoEmail } from './api-falsa';

test('o admin cadastra um sistema parceiro e o token nunca aparece para ele', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto('/admin/cadastros');
  await page.getByRole('link', { name: /Sistemas parceiros/ }).click();
  await expect(page.getByRole('heading', { name: 'Sistemas parceiros' })).toBeVisible();
  await expect(page.getByText('App da Atlética')).toBeVisible();

  await page.getByRole('link', { name: 'Cadastrar sistema' }).click();
  await expect(page.getByRole('heading', { name: 'Cadastrar sistema parceiro' })).toBeVisible();
  await page.getByLabel('Nome do sistema').fill('Portal Acadêmico');
  await page.getByLabel('Organização').fill('TI da Faculdade');
  await page.getByLabel('Nome do responsável').fill('Dora Ramos');
  await page.getByLabel('Email do responsável').fill('dora@fac.edu');
  await page.getByLabel('Finalidade').fill('Mostrar os horários livres no portal do aluno.');
  await page.getByLabel('Ver espaços, calendário e equipamentos').check();
  await page.getByRole('button', { name: 'Cadastrar e enviar o link' }).click();

  await expect(page.getByRole('heading', { name: 'Portal Acadêmico' })).toBeVisible();
  await expect(
    page.getByText('O responsável recebeu o link para aceitar os termos.'),
  ).toBeVisible();
  await expect(page.getByText('Aguardando aceite')).toBeVisible();
  await expect(page.getByText(/Enviado, vale até/)).toBeVisible();

  const cadastro = chamadas.find(
    (chamada) => chamada.url === '/api/admin/clientes-api' && chamada.metodo === 'POST',
  );
  expect(cadastro?.corpo).toEqual({
    nome: 'Portal Acadêmico',
    organizacao: 'TI da Faculdade',
    responsavel_nome: 'Dora Ramos',
    responsavel_email: 'dora@fac.edu',
    finalidade: 'Mostrar os horários livres no portal do aluno.',
    permissoes: ['espacos:ler'],
    limite_por_minuto: 60,
  });
  expect(chamadas.some((chamada) => chamada.url.includes('dora'))).toBe(false);
  await expect(page.getByText(/tokenDoParceiro/)).toHaveCount(0);
});

test('o admin revoga o token de um sistema ativo', async ({ page }) => {
  const chamadas = await prepararApi(page);
  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();

  await page.goto('/admin/cadastros/sistemas-parceiros/3');
  await expect(page.getByRole('heading', { name: /App da Atlética/ })).toBeVisible();
  await expect(page.getByText('versão 2026-11')).toBeVisible();

  await page.getByRole('button', { name: 'Revogar o token' }).click();
  await page.getByRole('button', { name: 'Confirmar: revogar o token' }).click();

  await expect(page.getByText('Token revogado.', { exact: false })).toBeVisible();
  await expect(page.getByText('Aguardando aceite')).toBeVisible();
  const envio = chamadas.find(
    (chamada) => chamada.url === '/api/admin/clientes-api/3/enviar-aceite',
  );
  expect(envio?.corpo).toEqual({ revogar_atual: true });
});

test('o responsável aceita os termos e vê o token uma única vez, sem o link no endereço', async ({
  page,
}) => {
  const chamadas = await prepararApi(page);

  await page.goto(`/parceiros/aceite/${tokenDoEmail}`);
  await expect(page.getByRole('heading', { name: 'Pedido de acesso' })).toBeVisible();
  await expect(page).toHaveURL(/\/parceiros\/aceite$/);
  await expect(page.getByText('Reservar para um aluno pelo RA')).toBeVisible();
  await expect(
    page.getByText('Envie à API só o RA de um aluno que pediu a reserva.'),
  ).toBeVisible();

  const aceitar = page.getByRole('button', { name: 'Aceitar e gerar o token' });
  await expect(aceitar).toBeDisabled();
  await page.getByLabel(/Li e aceito os termos de uso da API/).check();
  await aceitar.click();

  await expect(page.getByRole('heading', { name: 'Termos aceitos' })).toBeVisible();
  await expect(page.getByLabel('Token de acesso')).toHaveValue('12|tokenDoParceiroGeradoUmaVezSo');
  await expect(page.getByText('Ele aparece só agora')).toBeVisible();

  const aceite = chamadas.find(
    (chamada) =>
      chamada.url === `/api/parceiros/aceite/${tokenDoEmail}` && chamada.metodo === 'POST',
  );
  expect(aceite?.corpo).toEqual({ aceite_termos: true });
  const guardado = await page.evaluate(() =>
    JSON.stringify({ ...localStorage, ...sessionStorage }),
  );
  expect(guardado).not.toContain('tokenDoParceiro');
});
