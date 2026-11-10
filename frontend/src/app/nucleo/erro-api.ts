import { HttpErrorResponse } from '@angular/common/http';

export interface ErroApi {
  status: number;
  mensagem: string;
  campos: Record<string, string[]>;
  dados: Record<string, unknown>;
}

const SEM_CONEXAO =
  'Não foi possível conectar ao servidor. Verifique sua internet e tente de novo.';
const SESSAO_ENCERRADA = 'Sua sessão terminou. Entre de novo para continuar.';
const MUITAS_TENTATIVAS = 'Muitas tentativas seguidas. Aguarde um minuto e tente de novo.';
const ERRO_SERVIDOR = 'Ocorreu um erro no servidor. Tente de novo em instantes.';

export function converterErroApi(erro: HttpErrorResponse, sessaoEncerrada = false): ErroApi {
  const corpo = typeof erro.error === 'object' && erro.error !== null ? erro.error : {};
  const status = erro.status;

  return {
    status,
    mensagem: escolherMensagem(status, corpo.message, sessaoEncerrada),
    campos: status === 422 && typeof corpo.errors === 'object' && corpo.errors ? corpo.errors : {},
    dados: typeof corpo.dados === 'object' && corpo.dados ? corpo.dados : {},
  };
}

function escolherMensagem(status: number, mensagemApi: unknown, sessaoEncerrada: boolean): string {
  if (status === 0) {
    return SEM_CONEXAO;
  }
  if (sessaoEncerrada) {
    return SESSAO_ENCERRADA;
  }
  if (status === 429) {
    return MUITAS_TENTATIVAS;
  }
  if (status < 500 && typeof mensagemApi === 'string' && mensagemApi) {
    return mensagemApi;
  }
  return ERRO_SERVIDOR;
}
