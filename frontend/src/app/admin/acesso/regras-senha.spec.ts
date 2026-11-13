import { avaliarSenha, senhaValida } from './regras-senha';

describe('regras da senha', () => {
  it('confere tamanho, letra, número e confirmação como a API', () => {
    expect(avaliarSenha('abc', '').map((regra) => regra.cumprida)).toEqual([
      false,
      true,
      false,
      false,
    ]);
    expect(senhaValida('Senha1234', 'Senha1234')).toBe(true);
    expect(senhaValida('12345678', '12345678')).toBe(false);
    expect(senhaValida('senhasemnumero', 'senhasemnumero')).toBe(false);
    expect(senhaValida('Senha1234', 'Senha12345')).toBe(false);
    expect(senhaValida('ação1234', 'ação1234')).toBe(true);
  });
});
