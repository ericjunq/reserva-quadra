import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CampoHorario } from './campo-horario';

describe('CampoHorario', () => {
  let fixture: ComponentFixture<CampoHorario>;
  let elemento: HTMLElement;

  async function montar(valor = '') {
    fixture = TestBed.createComponent(CampoHorario);
    fixture.componentRef.setInput('idCampo', 'inicio');
    fixture.componentRef.setInput('rotulo', 'início');
    fixture.componentRef.setInput('opcoes', ['07:00', '07:15', '10:45', '11:00', '11:15']);
    fixture.componentRef.setInput('valor', valor);
    fixture.detectChanges();
    await fixture.whenStable();
    elemento = fixture.nativeElement as HTMLElement;
  }

  const campo = () => elemento.querySelector('input')!;
  const opcoes = () => [...elemento.querySelectorAll<HTMLElement>('[role="option"]')];
  const teclar = async (tecla: string) => {
    campo().dispatchEvent(new KeyboardEvent('keydown', { key: tecla, cancelable: true }));
    fixture.detectChanges();
    await fixture.whenStable();
  };

  it('mostra todos os horários ao abrir, mesmo com um já escolhido, destacando o mais próximo', async () => {
    await montar('10:45');
    elemento.querySelector<HTMLButtonElement>('button')!.click();
    fixture.detectChanges();

    expect(opcoes().map((opcao) => opcao.textContent?.trim())).toEqual([
      '07:00',
      '07:15',
      '10:45',
      '11:00',
      '11:15',
    ]);
    expect(campo().getAttribute('aria-expanded')).toBe('true');
    expect(campo().getAttribute('aria-activedescendant')).toBe('inicio-opcao-2');
    expect(opcoes()[2].getAttribute('aria-selected')).toBe('true');
  });

  it('escolhe com o clique, avisa quem usa e fecha a lista', async () => {
    await montar();
    const escolhidos: string[] = [];
    fixture.componentInstance.escolhido.subscribe((valor) => escolhidos.push(valor));

    elemento.querySelector<HTMLButtonElement>('button')!.click();
    fixture.detectChanges();
    opcoes()[3].click();
    fixture.detectChanges();

    expect(fixture.componentInstance.valor()).toBe('11:00');
    expect(escolhidos).toEqual(['11:00']);
    expect(opcoes().length).toBe(0);
  });

  it('funciona pelo teclado: setas, Enter e Esc', async () => {
    await montar('10:50');
    await teclar('ArrowDown');
    expect(campo().getAttribute('aria-activedescendant')).toBe('inicio-opcao-3');

    await teclar('ArrowUp');
    await teclar('Enter');
    expect(fixture.componentInstance.valor()).toBe('10:45');
    expect(opcoes().length).toBe(0);

    await teclar('ArrowDown');
    await teclar('Escape');
    expect(opcoes().length).toBe(0);
    expect(fixture.componentInstance.valor()).toBe('10:45');
  });

  it('aceita o que a pessoa digita e avisa quando não há horários', async () => {
    await montar();
    campo().value = '14h';
    campo().dispatchEvent(new Event('input'));
    expect(fixture.componentInstance.valor()).toBe('14h');

    fixture.componentRef.setInput('opcoes', []);
    fixture.componentRef.setInput('semOpcoes', 'Nenhum horário livre neste dia.');
    elemento.querySelector<HTMLButtonElement>('button')!.click();
    fixture.detectChanges();
    expect(elemento.textContent).toContain('Nenhum horário livre neste dia.');
  });
});
