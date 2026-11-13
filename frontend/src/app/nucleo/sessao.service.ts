import { Injectable, computed, signal } from '@angular/core';

interface DadosSessao {
  token: string;
  expiraEm: string;
}

const CHAVE = 'reservas.sessao';

@Injectable({ providedIn: 'root' })
export class SessaoService {
  private readonly dados = signal<DadosSessao | null>(this.lerArmazenada());

  readonly ativa = computed(() => this.dados() !== null);

  iniciar(token: string, expiraEm: string): void {
    const dados = { token, expiraEm };
    this.dados.set(dados);
    this.gravar(dados);
  }

  encerrar(): void {
    this.dados.set(null);
    this.gravar(null);
  }

  tokenValido(): string | null {
    const dados = this.dados();
    if (dados && this.expirou(dados)) {
      this.encerrar();
      return null;
    }
    return dados?.token ?? null;
  }

  private lerArmazenada(): DadosSessao | null {
    try {
      const bruto = sessionStorage.getItem(CHAVE);
      if (!bruto) {
        return null;
      }
      const dados = JSON.parse(bruto) as DadosSessao;
      if (
        typeof dados?.token !== 'string' ||
        typeof dados?.expiraEm !== 'string' ||
        this.expirou(dados)
      ) {
        sessionStorage.removeItem(CHAVE);
        return null;
      }
      return dados;
    } catch {
      return null;
    }
  }

  private gravar(dados: DadosSessao | null): void {
    try {
      if (dados) {
        sessionStorage.setItem(CHAVE, JSON.stringify(dados));
      } else {
        sessionStorage.removeItem(CHAVE);
      }
    } catch {
      return;
    }
  }

  private expirou(dados: DadosSessao): boolean {
    const expira = Date.parse(dados.expiraEm);
    return Number.isNaN(expira) || expira <= Date.now();
  }
}
