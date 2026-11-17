import { readFileSync } from 'node:fs';
import { expect, test, type Page } from '@playwright/test';
import { datas, entrarComoAdmin, prepararApi, rotuloDoHorario } from './api-falsa';

const csp = /Content-Security-Policy "([^"]+)"/.exec(readFileSync('deploy/nginx.conf', 'utf8'))![1];

async function comCsp(page: Page): Promise<() => Promise<string[]>> {
  await page.addInitScript(() => {
    const janela = window as unknown as { violacoes: string[] };
    janela.violacoes = [];
    document.addEventListener('securitypolicyviolation', (evento) =>
      janela.violacoes.push(`${evento.violatedDirective} ${evento.blockedURI}`),
    );
  });
  await page.route('**/*', async (route) => {
    if (route.request().resourceType() !== 'document') {
      return route.fallback();
    }
    const resposta = await route.fetch();
    await route.fulfill({
      response: resposta,
      headers: { ...resposta.headers(), 'content-security-policy': csp },
    });
  });
  return () => page.evaluate(() => (window as unknown as { violacoes: string[] }).violacoes);
}

test('o site funciona com a CSP do guia de deploy, sem nada bloqueado', async ({ page }) => {
  await prepararApi(page);
  const violacoes = await comCsp(page);

  await page.goto(`/espacos/1?semana=${datas.segundaSeguinte}`);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Quadra Poliesportiva');
  expect(await page.evaluate(() => getComputedStyle(document.body).fontFamily)).toContain(
    'Montserrat',
  );
  await page
    .getByRole('link', { name: rotuloDoHorario(datas.tercaSeguinte, '20:30', '22:00') })
    .click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reservar Quadra Poliesportiva');
  expect(await violacoes()).toEqual([]);

  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();
  await page.goto('/admin/historico');
  await expect(page.getByRole('heading', { name: 'Histórico', exact: true })).toBeVisible();
  await page.goto('/espacos/4');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Laboratório de Informática');
  expect(await violacoes()).toEqual([]);
});
