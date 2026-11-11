import { DOCUMENT } from '@angular/common';
import { DestroyRef, Injectable, inject, signal } from '@angular/core';
import { ApiPublicaService } from './api-publica.service';

export const INTERVALO_DE_ATUALIZACAO_MS = 15_000;

@Injectable({ providedIn: 'root' })
export class AtualizacaoDisponibilidadeService {
  private readonly api = inject(ApiPublicaService);
  private readonly documento = inject(DOCUMENT);
  private readonly ultimaVersao = signal<string | null>(null);
  private readonly mudancas = signal(0);
  private observadores = 0;
  private temporizador: ReturnType<typeof setInterval> | null = null;
  private consultando = false;

  readonly versao = this.mudancas.asReadonly();

  private readonly aoMudarVisibilidade = () => {
    if (this.documento.visibilityState === 'visible') {
      this.conferir();
      this.ligar();
    } else {
      this.desligar();
    }
  };

  observar(destruir: DestroyRef): void {
    this.observadores++;
    if (this.observadores === 1) {
      this.documento.addEventListener('visibilitychange', this.aoMudarVisibilidade);
      this.conferir();
      if (this.documento.visibilityState === 'visible') {
        this.ligar();
      }
    }
    destruir.onDestroy(() => {
      this.observadores--;
      if (this.observadores === 0) {
        this.documento.removeEventListener('visibilitychange', this.aoMudarVisibilidade);
        this.desligar();
      }
    });
  }

  private ligar(): void {
    this.temporizador ??= setInterval(() => this.conferir(), INTERVALO_DE_ATUALIZACAO_MS);
  }

  private desligar(): void {
    if (this.temporizador) {
      clearInterval(this.temporizador);
      this.temporizador = null;
    }
  }

  private conferir(): void {
    if (this.consultando) {
      return;
    }
    this.consultando = true;
    this.api.consultarVersao().subscribe({
      next: (versao) => {
        this.consultando = false;
        const anterior = this.ultimaVersao();
        this.ultimaVersao.set(versao);
        if (anterior !== null && anterior !== versao) {
          this.mudancas.update((valor) => valor + 1);
        }
      },
      error: () => {
        this.consultando = false;
      },
    });
  }
}
