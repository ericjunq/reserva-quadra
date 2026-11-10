import { Component, computed, inject, input, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { LucideCircleCheck, LucideInfo } from '@lucide/angular';
import { catchError, map, of } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { Curso } from '../modelos';
import { RaEmMemoriaService } from '../ra-em-memoria.service';

export function caminhoInterno(valor: string | null | undefined): string | null {
  return valor && valor.startsWith('/') && !valor.startsWith('//') && !valor.includes('\\')
    ? valor
    : null;
}

@Component({
  selector: 'app-cadastro',
  imports: [FormsModule, RouterLink, AvisoErro, LucideCircleCheck, LucideInfo],
  templateUrl: './cadastro.html',
})
export class Cadastro {
  private readonly api = inject(ApiPublicaService);
  private readonly router = inject(Router);
  private readonly raEmMemoria = inject(RaEmMemoriaService);

  readonly voltar = input<string>();

  protected readonly nome = signal('');
  protected readonly ra = signal('');
  protected readonly email = signal('');
  protected readonly cursoId = signal<number | null>(null);
  protected readonly enviando = signal(false);
  protected readonly erro = signal<ErroApi | null>(null);
  protected readonly cadastrado = signal(false);

  protected readonly cursos = toSignal(
    this.api.listarCursos().pipe(
      map((cursos) => ({ cursos, erro: null as string | null })),
      catchError((erro: ErroApi) => of({ cursos: [] as Curso[], erro: erro.mensagem })),
    ),
  );

  protected readonly destino = computed(() => caminhoInterno(this.voltar()));

  protected erroDoCampo(campo: string): string | null {
    return this.erro()?.campos[campo]?.[0] ?? null;
  }

  protected cadastrar(): void {
    if (this.enviando()) {
      return;
    }
    const aluno = {
      nome_completo: this.nome().trim(),
      registro_academico: this.ra().trim(),
      email: this.email().trim(),
      curso_id: this.cursoId() ?? 0,
    };

    this.enviando.set(true);
    this.erro.set(null);
    this.api.cadastrarAluno(aluno).subscribe({
      next: () => {
        this.enviando.set(false);
        this.raEmMemoria.lembrar(aluno.registro_academico);
        this.nome.set('');
        this.email.set('');
        const destino = this.destino();
        if (destino) {
          this.router.navigateByUrl(destino);
        } else {
          this.cadastrado.set(true);
        }
      },
      error: (erro: ErroApi) => {
        this.enviando.set(false);
        this.erro.set(erro);
      },
    });
  }
}
