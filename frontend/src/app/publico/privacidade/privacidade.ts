import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { catchError, map, of } from 'rxjs';
import { AvisoErro } from '../../compartilhado/aviso-erro/aviso-erro';
import { ErroApi } from '../../nucleo/erro-api';
import { ApiPublicaService } from '../api-publica.service';
import { Termos } from '../modelos';
import { SecoesTermos } from '../secoes-termos/secoes-termos';

@Component({
  selector: 'app-privacidade',
  imports: [AvisoErro, SecoesTermos],
  template: `
    <div class="mx-auto max-w-3xl px-4 py-8">
      <h1 class="text-3xl font-extrabold italic text-azul">Termos e privacidade</h1>
      @if (estado(); as estado) {
        @if (estado.termos; as termos) {
          @if (termos.rascunho) {
            <p class="mt-4 rounded-lg bg-amarelo/15 px-4 py-3 text-sm text-slate-800">
              Texto provisório, em validação pela instituição. Versão {{ termos.versao }}.
            </p>
          }
          <section
            id="termos"
            class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
          >
            <h2 class="text-xl font-bold text-azul">Termos de uso</h2>
            <app-secoes-termos [secoes]="termos.termos_de_uso" />
          </section>
          <section
            id="privacidade"
            class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200"
          >
            <h2 class="text-xl font-bold text-azul">Aviso de privacidade</h2>
            <app-secoes-termos [secoes]="termos.privacidade" />
          </section>
        } @else {
          <div class="mt-6"><app-aviso-erro [mensagem]="estado.erro" /></div>
        }
      } @else {
        <p class="mt-6 text-slate-600">Carregando…</p>
      }
    </div>
  `,
})
export class Privacidade {
  protected readonly estado = toSignal(
    inject(ApiPublicaService)
      .consultarTermos()
      .pipe(
        map((termos) => ({ termos, erro: null as string | null })),
        catchError((erro: ErroApi) => of({ termos: null as Termos | null, erro: erro.mensagem })),
      ),
  );
}
