import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  LucideChevronRight,
  LucideClock,
  LucideFlaskConical,
  LucidePresentation,
  LucideUsers,
  LucideVolleyball,
} from '@lucide/angular';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { Espaco, TipoEspaco } from '../modelos';
import { NOMES_DOS_TIPOS, descreverHorario } from '../textos';

@Component({
  selector: 'app-inicio',
  imports: [
    RouterLink,
    AvisoErro,
    LucideChevronRight,
    LucideClock,
    LucideFlaskConical,
    LucidePresentation,
    LucideUsers,
    LucideVolleyball,
  ],
  templateUrl: './inicio.html',
})
export class Inicio {
  private readonly api = inject(ApiPublicaService);

  protected readonly espacos = signal<Espaco[] | null>(null);
  protected readonly erro = signal<string | null>(null);
  protected readonly filtro = signal<TipoEspaco | null>(null);
  protected readonly nomesDosTipos = NOMES_DOS_TIPOS;
  protected readonly descreverHorario = descreverHorario;

  protected readonly tiposPresentes = computed(() => {
    const tipos = new Set((this.espacos() ?? []).map((espaco) => espaco.tipo));
    return (Object.keys(NOMES_DOS_TIPOS) as TipoEspaco[]).filter((tipo) => tipos.has(tipo));
  });

  protected readonly visiveis = computed(() => {
    const filtro = this.filtro();
    const espacos = this.espacos() ?? [];
    return filtro ? espacos.filter((espaco) => espaco.tipo === filtro) : espacos;
  });

  constructor() {
    this.carregar();
  }

  protected carregar(): void {
    this.erro.set(null);
    this.api.listarEspacos().subscribe({
      next: (espacos) => this.espacos.set(espacos),
      error: (erro: ErroApi) => this.erro.set(erro.mensagem),
    });
  }
}
