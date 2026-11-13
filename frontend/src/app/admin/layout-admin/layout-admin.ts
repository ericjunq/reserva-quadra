import { Component, effect, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { catchError, of } from 'rxjs';
import { SessaoService } from '../../nucleo/sessao.service';
import { AcessoService } from '../acesso/acesso.service';

@Component({
  selector: 'app-layout-admin',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './layout-admin.html',
})
export class LayoutAdmin {
  private readonly acesso = inject(AcessoService);
  private readonly sessao = inject(SessaoService);
  private readonly router = inject(Router);
  private saindo = false;

  protected readonly admin = toSignal(this.acesso.eu().pipe(catchError(() => of(null))));

  constructor() {
    effect(() => {
      if (!this.sessao.ativa() && !this.saindo) {
        this.router.navigate(['/admin/entrar'], {
          queryParams: { voltar: this.router.url, motivo: 'sessao' },
        });
      }
    });
  }

  protected sair(): void {
    this.saindo = true;
    this.acesso.sair().subscribe({
      complete: () => this.router.navigateByUrl('/admin/entrar'),
      error: () => this.router.navigateByUrl('/admin/entrar'),
    });
  }
}
