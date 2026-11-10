export function paraIso(data: Date): string {
  const mes = String(data.getMonth() + 1).padStart(2, '0');
  const dia = String(data.getDate()).padStart(2, '0');
  return `${data.getFullYear()}-${mes}-${dia}`;
}

export function deIso(iso: string): Date {
  const [ano, mes, dia] = iso.split('-').map(Number);
  return new Date(ano, mes - 1, dia);
}

export function somarDias(data: Date, dias: number): Date {
  return new Date(data.getFullYear(), data.getMonth(), data.getDate() + dias);
}

export function segundaDaSemana(data: Date): Date {
  const diaDaSemana = data.getDay();
  return somarDias(data, diaDaSemana === 0 ? -6 : 1 - diaDaSemana);
}

export function ehDiaUtil(iso: string): boolean {
  const diaDaSemana = deIso(iso).getDay();
  return diaDaSemana >= 1 && diaDaSemana <= 5;
}

export function ehDataIso(valor: string | null | undefined): valor is string {
  if (!valor || !/^\d{4}-\d{2}-\d{2}$/.test(valor)) {
    return false;
  }
  return paraIso(deIso(valor)) === valor;
}

export function ehHora(valor: string | null | undefined): valor is string {
  return !!valor && /^([01]\d|2[0-3]):[0-5]\d$/.test(valor);
}
