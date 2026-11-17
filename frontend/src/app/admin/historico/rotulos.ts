import { AcaoAuditoria, Auditoria, Mudanca, TipoAuditavel } from './modelos';

export const NOMES_DOS_TIPOS: Record<TipoAuditavel, string> = {
  RESERVA: 'Reserva',
  ALUNO: 'Aluno',
  ESPACO: 'Espaço',
  EQUIPAMENTO: 'Equipamento',
  CURSO: 'Curso',
  DATA_BLOQUEADA: 'Data bloqueada',
  ITEM_RESERVA: 'Equipamento de reserva',
  ADMIN: 'Admin',
  CLIENTE_API: 'Sistema parceiro',
};

export const NOMES_DAS_ACOES: Record<AcaoAuditoria, string> = {
  CRIACAO: 'Criou',
  ATUALIZACAO: 'Alterou',
  EXCLUSAO: 'Excluiu',
};

const ROTULOS_DOS_CAMPOS: Record<string, string> = {
  nome: 'Nome',
  name: 'Nome',
  nome_completo: 'Nome',
  registro_academico: 'RA',
  email: 'Email',
  password: 'Senha',
  remember_token: 'Token de sessão',
  curso_id: 'Curso',
  ativo: 'Ativo',
  anonimizado_em: 'Anonimizado em',
  principal: 'Admin principal',
  convite_aceito_em: 'Convite aceito em',
  email_verified_at: 'Email verificado em',
  data_reserva: 'Data',
  horario: 'Horário',
  hora_inicio: 'Início',
  hora_fim: 'Fim',
  espaco_id: 'Espaço',
  aluno_id: 'Aluno',
  criado_por: 'Criado por',
  status: 'Situação',
  tipo: 'Tipo',
  origem: 'Origem',
  descricao: 'Descrição',
  justificativa_revogacao: 'Motivo da revogação',
  revogada_em: 'Revogada em',
  revogada_por: 'Revogada por',
  cancelada_em: 'Cancelada em',
  confirmar_ate: 'Prazo para confirmar',
  confirmada_em: 'Confirmada em',
  termos_aceitos_em: 'Termos aceitos em',
  termos_versao: 'Versão dos termos',
  reconfirmacao_pedida_em: 'Reconfirmação pedida em',
  reconfirmar_ate: 'Prazo para reconfirmar',
  reconfirmada_em: 'Reconfirmada em',
  manter_ate: 'Prazo para manter',
  mantida_em: 'Mantida em',
  limite_semanal_por_curso: 'Limite semanal por curso',
  modo_horario: 'Modo de horário',
  abre_as: 'Abre às',
  fecha_as: 'Fecha às',
  duracao_maxima_minutos: 'Duração máxima (minutos)',
  quantidade_total: 'Quantidade total',
  aceita_solicitacao: 'Aceita pedido de outros espaços',
  espacos_compartilhados: 'Espaços compartilhados',
  data_inicio: 'Data de início',
  data_fim: 'Data de fim',
  motivo: 'Motivo',
  reserva_id: 'Reserva',
  equipamento_id: 'Equipamento',
  quantidade: 'Quantidade',
  situacao_devolucao: 'Devolução',
  quantidade_devolvida: 'Quantidade devolvida',
  observacao_devolucao: 'Observação da devolução',
  devolvido_em: 'Devolvido em',
  devolvido_por: 'Devolução registrada por',
  situacao_solicitacao: 'Pedido',
  solicitacao_obrigatoria: 'Pedido obrigatório',
  responder_ate: 'Prazo da resposta',
  respondida_em: 'Respondido em',
  respondida_por: 'Respondido por',
  justificativa_recusa: 'Motivo da recusa',
  organizacao: 'Organização',
  responsavel_nome: 'Responsável',
  responsavel_contato: 'Contato do responsável',
  responsavel_email: 'Email do responsável',
  finalidade: 'Finalidade',
  permissoes: 'Permissões',
  limite_por_minuto: 'Limite por minuto',
  aceite_token_hash: 'Link de aceite',
  aceite_expira_em: 'Link de aceite vale até',
  acesso_expira_em: 'Acesso vence em',
  aviso_vencimento_enviado_em: 'Aviso de vencimento enviado em',
  cliente_api_id: 'Sistema parceiro',
};

const NOMES_DOS_VALORES: Record<string, string> = {
  RESERVADO: 'Reservada',
  CANCELADO: 'Cancelada',
  REVOGADO: 'Revogada',
  ALUNO: 'Aluno',
  EVENTO: 'Evento',
  SITE: 'Pelo site',
  ADMIN: 'Pelo admin',
  REGISTRO_POSTERIOR: 'Registro posterior',
  API: 'Por sistema parceiro',
  AGUARDANDO: 'Aguardando resposta',
  APROVADA: 'Aprovado',
  RECUSADA: 'Recusado',
  VENCIDA: 'Sem resposta',
  PENDENTE: 'Pendente',
  DEVOLVIDO: 'Devolvido',
  DEVOLVIDO_COM_AVARIA: 'Devolvido com avaria',
  NAO_DEVOLVIDO: 'Não devolvido',
  FIXO: 'Horários fixos',
  LIVRE: 'Horário livre',
  FERIADO: 'Feriado',
  MANUAL: 'Manual',
};

const CAMPOS_COM_CODIGO = new Set([
  'status',
  'tipo',
  'origem',
  'situacao_solicitacao',
  'situacao_devolucao',
  'modo_horario',
]);

const DATA_HORA_LOCAL = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(:\d{2})?$/;
const DATA_LOCAL = /^(\d{4})-(\d{2})-(\d{2})$/;

const FORMATO_QUANDO = new Intl.DateTimeFormat('pt-BR', {
  timeZone: 'America/Sao_Paulo',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

export function rotuloDoCampo(campo: string): string {
  return ROTULOS_DOS_CAMPOS[campo] ?? campo;
}

export function formatarQuando(iso: string): string {
  const partes = Object.fromEntries(
    FORMATO_QUANDO.formatToParts(new Date(iso)).map((parte) => [parte.type, parte.value]),
  );
  return `${partes['day']}/${partes['month']}/${partes['year']} ${partes['hour']}:${partes['minute']}`;
}

export function formatarValor(campo: string, valor: unknown): string {
  if (valor === null || valor === undefined || valor === '') {
    return '—';
  }
  if (typeof valor === 'boolean') {
    return valor ? 'Sim' : 'Não';
  }
  if (Array.isArray(valor)) {
    return valor.length ? valor.map((item) => formatarValor(campo, item)).join(', ') : 'Nenhum';
  }
  if (typeof valor === 'object') {
    return JSON.stringify(valor);
  }
  const texto = String(valor);
  if (CAMPOS_COM_CODIGO.has(campo) && NOMES_DOS_VALORES[texto]) {
    return NOMES_DOS_VALORES[texto];
  }
  const dataHora = DATA_HORA_LOCAL.exec(texto);
  if (dataHora) {
    return `${dataHora[3]}/${dataHora[2]}/${dataHora[1]} ${dataHora[4]}:${dataHora[5]}`;
  }
  const data = DATA_LOCAL.exec(texto);
  if (data) {
    return `${data[3]}/${data[2]}/${data[1]}`;
  }
  return texto;
}

export function mudancasDe(auditoria: Auditoria): Mudanca[] {
  return auditoria.campos_alterados.map((campo) => ({
    campo,
    rotulo: rotuloDoCampo(campo),
    antes: auditoria.antes ? formatarValor(campo, auditoria.antes[campo]) : '—',
    depois: auditoria.depois ? formatarValor(campo, auditoria.depois[campo]) : '—',
  }));
}

export function linkDoRegistro(tipo: TipoAuditavel, id: number): string | null {
  switch (tipo) {
    case 'RESERVA':
      return `/admin/reservas/${id}`;
    case 'ALUNO':
      return `/admin/cadastros/alunos/${id}`;
    case 'ESPACO':
      return `/admin/cadastros/espacos/${id}`;
    case 'EQUIPAMENTO':
      return `/admin/cadastros/equipamentos/${id}`;
    case 'ADMIN':
      return `/admin/cadastros/admins/${id}`;
    case 'CLIENTE_API':
      return `/admin/cadastros/sistemas-parceiros/${id}`;
    default:
      return null;
  }
}

export function quemFez(auditoria: Auditoria): string {
  if (auditoria.feito_por) {
    return auditoria.feito_por.nome;
  }
  return auditoria.sistema_parceiro
    ? `Sistema parceiro: ${auditoria.sistema_parceiro.nome}`
    : 'Sistema ou aluno';
}
