import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { datas, entrarComoAdmin, prepararApi, rotuloDoHorario, tokenDoEmail } from './api-falsa';

const REGRAS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function semProblemas(page: Page, pagina: string): Promise<void> {
  await expect(page.getByRole('heading', { level: 1 }).first()).toBeVisible();
  const resultado = await new AxeBuilder({ page }).withTags(REGRAS).analyze();
  const problemas = resultado.violations.map(
    (violacao) =>
      `${pagina}: ${violacao.id} (${violacao.impact}) em ${violacao.nodes
        .map((no) => no.target.join(' '))
        .join(', ')}`,
  );
  expect.soft(problemas).toEqual([]);
}

test('as páginas públicas não têm problemas de acessibilidade', async ({ page }) => {
  test.setTimeout(120_000);
  await prepararApi(page);

  await page.goto('/');
  await semProblemas(page, 'início');

  await page.goto('/espacos/1');
  await semProblemas(page, 'calendário da quadra');

  await page.goto(`/espacos/1?semana=${datas.segundaSeguinte}`);
  await page
    .getByRole('link', { name: rotuloDoHorario(datas.tercaSeguinte, '20:30', '22:00') })
    .click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reservar Quadra Poliesportiva');
  await semProblemas(page, 'reservar');

  await page.goto('/espacos/4');
  await semProblemas(page, 'calendário do laboratório');

  await page.goto('/cadastro');
  await semProblemas(page, 'cadastro');

  await page.goto('/minhas-reservas');
  await semProblemas(page, 'minhas reservas');

  await page.goto('/privacidade');
  await semProblemas(page, 'termos e privacidade');

  await page.goto(`/reservas/confirmar/${tokenDoEmail}`);
  await semProblemas(page, 'confirmar reserva');
});

test('as páginas do admin não têm problemas de acessibilidade', async ({ page }) => {
  test.setTimeout(120_000);
  await prepararApi(page);

  await page.goto('/admin/entrar');
  await semProblemas(page, 'entrar');

  await entrarComoAdmin(page);
  await expect(page.getByRole('heading', { name: 'Painel' })).toBeVisible();
  await semProblemas(page, 'painel');

  for (const [caminho, nome] of [
    ['/admin/reservas', 'reservas'],
    ['/admin/reservas/77', 'detalhe da reserva'],
    ['/admin/reservas/nova', 'nova reserva'],
    ['/admin/calendario', 'calendário do admin'],
    ['/admin/cadastros', 'cadastros'],
    ['/admin/cadastros/espacos', 'espaços'],
    ['/admin/cadastros/equipamentos/5', 'equipamento'],
    ['/admin/cadastros/alunos/3', 'aluno'],
    ['/admin/historico', 'histórico'],
    ['/admin/minha-conta', 'minha conta'],
  ]) {
    await page.goto(caminho);
    await semProblemas(page, nome);
  }
});

test('dá para reservar só pelo teclado, com o foco sempre visível', async ({ page }) => {
  await prepararApi(page);
  await page.goto(`/espacos/1?semana=${datas.segundaSeguinte}`);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Quadra Poliesportiva');

  await page.keyboard.press('Tab');
  const pular = page.getByRole('link', { name: 'Pular para o conteúdo' });
  await expect(pular).toBeFocused();
  await expect(pular).toBeVisible();

  const horario = page.getByRole('link', {
    name: rotuloDoHorario(datas.tercaSeguinte, '20:30', '22:00'),
  });
  for (let i = 0; i < 60; i++) {
    if (await horario.evaluate((elemento) => elemento === document.activeElement)) {
      break;
    }
    await page.keyboard.press('Tab');
  }
  await expect(horario).toBeFocused();
  const contorno = await horario.evaluate((elemento) => getComputedStyle(elemento).outlineStyle);
  expect(contorno).not.toBe('none');

  await page.keyboard.press('Enter');
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reservar Quadra Poliesportiva');
  await page.keyboard.press('Tab');
  await expect(page.locator(':focus')).toBeVisible();
});
