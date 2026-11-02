export const VEHICLE_TYPE = {
  AUTOMOVEL: 1,
  MOTOCICLETA: 2,
  CAMINHAO: 3,
  ONIBUS: 4,
  OUTRO: 5
} as const;

export const USAGE_PROFILE = {
  PESSOA_IDOSA: 1,
  AUTISTA: 2,
  DEFICIENCIAS_OCULTAS: 3,
  GESTANTE: 4,
  RECEM_NASCIDO: 5,
  CNH_DEFINITIVA: 6,
  CNH_TEMPORARIA: 7,
  EMPRESA: 8,
  APLICATIVO: 9
} as const;

export const VEHICLE_TYPE_LABELS: Record<number, string> = {
  [VEHICLE_TYPE.AUTOMOVEL]: 'Automóvel',
  [VEHICLE_TYPE.MOTOCICLETA]: 'Motocicleta',
  [VEHICLE_TYPE.CAMINHAO]: 'Caminhao',
  [VEHICLE_TYPE.ONIBUS]: 'Onibus',
  [VEHICLE_TYPE.OUTRO]: 'Outro'
};

export const USAGE_PROFILE_LABELS: Record<number, string> = {
  [USAGE_PROFILE.PESSOA_IDOSA]: 'Pessoa idosa',
  [USAGE_PROFILE.AUTISTA]: 'Autista',
  [USAGE_PROFILE.DEFICIENCIAS_OCULTAS]: 'Deficiencias ocultas',
  [USAGE_PROFILE.GESTANTE]: 'Gestante',
  [USAGE_PROFILE.RECEM_NASCIDO]: 'Recem-nascido',
  [USAGE_PROFILE.CNH_DEFINITIVA]: 'CNH definitiva',
  [USAGE_PROFILE.CNH_TEMPORARIA]: 'CNH temporaria',
  [USAGE_PROFILE.EMPRESA]: 'Empresa',
  [USAGE_PROFILE.APLICATIVO]: 'Aplicativo (Uber/99)'
};

export const VEHICLE_TYPE_OPTIONS = [
  { label: 'Automóvel', value: VEHICLE_TYPE.AUTOMOVEL, icon: 'pi pi-car' },
  { label: 'Motocicleta', value: VEHICLE_TYPE.MOTOCICLETA, icon: 'pi pi-car' },
  { label: 'Caminhao', value: VEHICLE_TYPE.CAMINHAO, icon: 'pi pi-truck' },
  { label: 'Onibus', value: VEHICLE_TYPE.ONIBUS, icon: 'pi pi-bus' },
  { label: 'Outro', value: VEHICLE_TYPE.OUTRO, icon: 'pi pi-ellipsis-h' }
];

export const USAGE_PROFILE_OPTIONS = [
  { id: USAGE_PROFILE.PESSOA_IDOSA, label: 'Pessoa idosa', icon: 'pi pi-user' },
  { id: USAGE_PROFILE.AUTISTA, label: 'Autista', icon: 'pi pi-user' },
  { id: USAGE_PROFILE.DEFICIENCIAS_OCULTAS, label: 'Deficiencias Ocultas', icon: 'pi pi-eye-slash' },
  { id: USAGE_PROFILE.GESTANTE, label: 'Gestante', icon: 'pi pi-heart' },
  { id: USAGE_PROFILE.RECEM_NASCIDO, label: 'Recem-nascido', icon: 'pi pi-heart' },
  { id: USAGE_PROFILE.CNH_DEFINITIVA, label: 'CNH definitiva', icon: 'pi pi-id-card' },
  { id: USAGE_PROFILE.CNH_TEMPORARIA, label: 'CNH temporaria', icon: 'pi pi-id-card' },
  { id: USAGE_PROFILE.EMPRESA, label: 'Empresa', icon: 'pi pi-briefcase' },
  { id: USAGE_PROFILE.APLICATIVO, label: 'Aplicativo (Uber/99)', icon: 'pi pi-mobile' }
];

const parseEnumValue = (value?: number | string | null) => {
  if (value === null || value === undefined || value === '') return undefined;
  const num = Number(value);
  return Number.isNaN(num) ? undefined : num;
};

export const getVehicleTypeLabel = (value?: number | string | null) => {
  const num = parseEnumValue(value);
  if (!num) return '';
  return VEHICLE_TYPE_LABELS[num] || '';
};

export const getUsageProfileLabel = (value?: number | string | null) => {
  const num = parseEnumValue(value);
  if (!num) return '';
  return USAGE_PROFILE_LABELS[num] || '';
};

// Mapeamento de estado por extenso para UF (sigla)
export const STATE_NAME_TO_UF: Record<string, string> = {
  'Acre': 'AC',
  'Alagoas': 'AL',
  'Amapá': 'AP',
  'Amazonas': 'AM',
  'Bahia': 'BA',
  'Ceará': 'CE',
  'Distrito Federal': 'DF',
  'Espírito Santo': 'ES',
  'Goiás': 'GO',
  'Maranhão': 'MA',
  'Mato Grosso': 'MT',
  'Mato Grosso do Sul': 'MS',
  'Minas Gerais': 'MG',
  'Pará': 'PA',
  'Paraíba': 'PB',
  'Paraná': 'PR',
  'Pernambuco': 'PE',
  'Piauí': 'PI',
  'Rio de Janeiro': 'RJ',
  'Rio Grande do Norte': 'RN',
  'Rio Grande do Sul': 'RS',
  'Rondônia': 'RO',
  'Roraima': 'RR',
  'Santa Catarina': 'SC',
  'São Paulo': 'SP',
  'Sergipe': 'SE',
  'Tocantins': 'TO'
};

// Função para converter estado por extenso para UF
export const getStateUF = (stateName?: string | null): string => {
  if (!stateName) return '';
  // Se já é uma sigla (2 caracteres), retorna como está
  if (stateName.length === 2 && stateName === stateName.toUpperCase()) {
    return stateName;
  }
  // Caso contrário, busca no mapeamento
  return STATE_NAME_TO_UF[stateName] || '';
};
