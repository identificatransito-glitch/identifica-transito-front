export interface RecentOrderCard { id:string;title:string;status:'em-andamento'|'em-rota'|'entregue'|'cancelado';statusLabel:string;paymentLabel:string;fulfillmentLabel:string }
export function recentOrderCard(order:any):RecentOrderCard {
  const payment=String(order.mp_payment_status||'').toLowerCase();
  const shipment=String(order.shipment?.status??'').toLowerCase();
  const pickup=Boolean(order.pickup_point) && !order.delivery;
  const declined=['cancelled','canceled','rejected','refunded','charged_back'].includes(payment);
  const approved=payment==='approved';
  const delivered=['4','entregue','delivered'].includes(shipment);
  const inRoute=['2','3','in_transit','out_for_delivery'].includes(shipment);
  const failed=['5','6','failed','returned'].includes(shipment);
  const status:RecentOrderCard['status']=delivered?'entregue':declined||failed?'cancelado':!pickup&&inRoute?'em-rota':'em-andamento';
  let statusLabel=delivered?(pickup?'Retirado':'Entregue'):declined?'Pedido cancelado':failed?'Entrega não concluída':!approved?'Aguardando pagamento':pickup?(shipment==='1'?'Disponível para retirada':'Aguardando retirada'):inRoute?'Em rota de entrega':'Preparando envio';
  const zero=Number(order.price_total)===0 && Number(order.shipping_price||0)===0;
  const free=approved&&zero&&(order.payment_method==='free'||order.mp_payment_status_detail==='coupon_full_discount');
  const paymentLabel=free?'Aprovado · sem cobrança (cupom integral)':({approved:'Pagamento aprovado',pending:'Pagamento pendente',in_process:'Pagamento em processamento',rejected:'Pagamento rejeitado',cancelled:'Pagamento cancelado',canceled:'Pagamento cancelado',refunded:'Pagamento reembolsado',charged_back:'Pagamento contestado'} as Record<string,string>)[payment]||'Pagamento não informado';
  const point=order.pickup_point?.name||order.pickup_point?.label||(order.pickup_point?.id?`PDV #${order.pickup_point.id}`:'Qualquer PDV autorizado');
  const qty=Number(order.qty)||1;
  return {id:`#${order.id}`,title:`${order.tag_name||order.tag?.name||'Kit de etiquetas'} · ${qty} ${qty===1?'kit':'kits'}`,status,statusLabel,paymentLabel,fulfillmentLabel:pickup?`Retirada: ${point}`:'Entrega em domicílio'};
}
