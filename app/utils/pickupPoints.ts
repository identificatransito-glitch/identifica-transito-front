export interface PickupPoint {
  id: number;
  name: string;
  address?: string | null;
  map_link?: string | null;
  responsible?: string | null;
  phone?: string | null;
  active?: boolean | number | string;
}
export interface PickupRegion { zip: string; city: string; state: string; neighborhood: string }
export const searchText = (value: string) => (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').replace(/\s+/g, ' ').trim();
export const zipDigits = (value: string) => (value || '').replace(/\D/g, '');
export const pointRegion = (point: PickupPoint): PickupRegion => {
  const address = point.address || '';
  const parts = address.split(/\s+[—–]\s+/);
  const cityState = parts.find(part => /\/[A-Z]{2}$/i.test(part.trim()))?.trim();
  return {
    zip: zipDigits(address.match(/CEP\s*(\d{5}-?\d{3})/i)?.[1] || ''),
    city: cityState?.slice(0, -3).trim() || '', state: cityState?.slice(-2).toUpperCase() || '',
    neighborhood: parts.find(part => /^bairro\s+/i.test(part))?.replace(/^bairro\s+/i, '').trim() || ''
  };
};
export const regionPriority = (point: PickupPoint, reference: PickupRegion | null): number => {
  if (!reference) return 4;
  const region = pointRegion(point);
  if (reference.zip.length === 8 && region.zip === reference.zip) return 0;
  if (region.city && reference.city && searchText(region.city) === searchText(reference.city) && region.state === reference.state) {
    return region.neighborhood && reference.neighborhood && searchText(region.neighborhood) === searchText(reference.neighborhood) ? 1 : 2;
  }
  return region.state && reference.state && region.state === reference.state ? 3 : 4;
};
export const regionLabel = (priority: number) => ['Mesmo CEP', 'Mesmo bairro', 'Mesma cidade', 'Mesmo estado', ''][priority] || '';
export const findPickupPoints = (points: PickupPoint[], query: string, reference: PickupRegion | null): PickupPoint[] => {
  const words = searchText(query).split(' ').filter(Boolean);
  return points.filter(point => ![false, 0, '0', 'false'].includes(point.active as any) && words.every(word => searchText(`${point.name} ${point.address || ''}`).includes(word)))
    .sort((a,b) => regionPriority(a, reference) - regionPriority(b, reference) || a.name.localeCompare(b.name, 'pt-BR') || a.id-b.id);
};
export const pickupMapUrl = (point: PickupPoint): string => {
  if (point.map_link) {
    try { const url = new URL(point.map_link); if (url.protocol === 'https:' || url.protocol === 'http:') return url.href; } catch {}
  }
  return point.address?.trim() ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${point.name} ${point.address}`)}` : '';
};
