import { Injectable, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class RaEmMemoriaService {
  private readonly valor = signal<string | null>(null);

  readonly ra = this.valor.asReadonly();

  lembrar(ra: string): void {
    this.valor.set(ra);
  }

  esquecer(): void {
    this.valor.set(null);
  }
}
