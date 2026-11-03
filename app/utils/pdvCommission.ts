export const pdvCommissionError = (value: string): string | null => {
    if (value.trim() === '') return null;
    const number = Number(value);
    return Number.isFinite(number) && number >= 0 && number <= 100 && /^\d+(?:\.\d{1,2})?$/.test(value)
        ? null : 'Informe um percentual de 0 a 100, com até duas casas decimais.';
};
export const formatPdvCommission = (value: unknown): string => value === null || value === undefined || value === ''
    ? 'Não configurado' : `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })}%`;
