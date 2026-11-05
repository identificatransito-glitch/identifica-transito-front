# Últimos Pedidos do Super Admin

O resumo consultava somente campos básicos do pedido. Não selecionava pickup_point/delivery nem carregava shipment/tag. Por isso o card não mostrava o PDV e usava sempre o estado genérico Em andamento. O vínculo existente não precisa ser regravado.

AdminService::dashboard passa a selecionar os metadados de retirada/entrega, cupom e motivo de aprovação, com tag e status de shipment carregados. Não altera pedidos, pagamento, contadores financeiros ou comissão.

Card do Super Admin mostra etiqueta/kit, número real do pedido, PDV de preferência (ou entrega em domicílio), aprovação financeira e situação da retirada/envio separadas. O identificador técnico FREE_ deixa de aparecer como número de pedido. Cupom integral aprovado com valor e frete zero é identificado como sem cobrança; não é apresentado como Pix pago. Retirado só aparece quando shipment está confirmado em status 4. Casos pendentes, rejeitados, reembolsados, entrega domiciliar e quantidade múltipla cobertos.

Os componentes compartilhados aceitam dados adicionais opcionais. Telas de cliente/PDV mantêm a apresentação anterior quando não passam esses dados. O status do componente agora reage à atualização das props.

Validação: 21 verificações Node; 16 testes isolados de dashboard em SQLite memória com fila/e-mail falsos; build de produção; QA desktop/390px e atualização do estado de retirada. Prévia usa dados fictícios, rota removida e ausente do build publicado. Verificação de pedidos reais é somente leitura e imprime apenas contagens agregadas.
