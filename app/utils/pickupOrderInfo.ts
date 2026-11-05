import { pickupMapUrl } from './pickupPoints.ts';
export const pickupOrderInfo = (order: any, points: any[]) => {
  const saved=order?.pickup_point || {};
  const current=points.find(point=>Number(point.id)>0 && Number(point.id)===Number(saved.id));
  const point={...saved,...current};
  return {id:Number(saved.id)||null,name:point.name||saved.label||'Qualquer PDV autorizado',address:point.address||'',responsible:point.responsible||point.responsable||'',phone:point.phone||'',map:pickupMapUrl({id:Number(saved.id)||0,name:point.name||saved.label||'',address:point.address,map_link:point.map_link})};
};
export const pickupPaymentLabel = (order:any) => order?.mp_payment_status_label || ({approved:'Aprovado',pending:'Pendente',in_process:'Em processamento',rejected:'Rejeitado',cancelled:'Cancelado',refunded:'Reembolsado',charged_back:'Contestado'} as Record<string,string>)[order?.mp_payment_status] || 'Não informado';
export const pickupOrderDate = (value:string) => {
  if (!value) return '';
  if (!/^\d{4}-\d{2}-\d{2}/.test(value)) return value;
  const date=new Date(value); return Number.isNaN(date.getTime())?value:date.toLocaleString('pt-BR');
};
