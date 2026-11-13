export interface RegraDaSenha {
  texto: string;
  cumprida: boolean;
}

export function avaliarSenha(senha: string, confirmacao: string): RegraDaSenha[] {
  return [
    { texto: 'Pelo menos 8 caracteres', cumprida: senha.length >= 8 },
    { texto: 'Pelo menos uma letra', cumprida: /\p{L}/u.test(senha) },
    { texto: 'Pelo menos um número', cumprida: /\d/.test(senha) },
    { texto: 'A confirmação é igual à senha', cumprida: senha.length > 0 && senha === confirmacao },
  ];
}

export function senhaValida(senha: string, confirmacao: string): boolean {
  return avaliarSenha(senha, confirmacao).every((regra) => regra.cumprida);
}
