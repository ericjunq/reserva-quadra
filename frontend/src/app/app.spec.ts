import { TestBed } from '@angular/core/testing';
import { App } from './app';

describe('App', () => {
  it('cria o componente raiz com a área das rotas', async () => {
    await TestBed.configureTestingModule({ imports: [App] }).compileComponents();
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    expect(fixture.nativeElement.querySelector('router-outlet')).not.toBeNull();
  });
});
