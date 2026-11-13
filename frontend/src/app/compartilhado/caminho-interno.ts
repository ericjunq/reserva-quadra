export function caminhoInterno(valor: string | null | undefined): string | null {
  return valor && valor.startsWith('/') && !valor.startsWith('//') && !valor.includes('\\')
    ? valor
    : null;
}
