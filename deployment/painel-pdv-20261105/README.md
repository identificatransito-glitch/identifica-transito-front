Painel PDV — e-mail, últimos pedidos e gráficos

Exibe e permite pesquisar e-mail dos clientes no escopo autorizado do PDV, conforme solicitação do proprietário. Telefone de clientes externos continua oculto; não amplia o escopo de consulta para clientes de outros pontos. Cadastro/edição/exclusão pelo PDV continuam bloqueados.

Últimos Pedidos usa o mesmo recentOrderCard do Super Admin. A API inclui tag, shipment, pickup_point, delivery, shipping_price e motivo da aprovação por cupom. Número legível do pedido, pagamento e retirada separados; não exibe referência técnicaFREE_.

Gráficos usam todos os pedidos aprovados atribuídos ao ponto no período, não só os últimos10. Etiquetas: kits por cor, incluindo cupom100%. Evolução do repasse: acumulado calculado sobre preço histórico×quantidade antes de descontos e sem frete. Não significa saldo pago ou transferido. Receita recebida é consolidada separadamente. Totais monetários arredondados sobre o acumulado para coincidir com o repasse central.

Validação:68testes isolados SQLite memória,21verificações do resumo,14verificações do script real dos gráficos. E-mail limitado aoPDV, telefone oculto, pesquisa, isolamento, leitura apenas, atualizaçãode retirada, cupom, filtrosde período, maisde10pedidos, totaldocard=gráfico, valores monetários. QA com dados fictícios:desktop e390px semoverflow, mudança reativa de retirada e restauração verificadas. RotaQA removida antes do buildfinal. Sem pagamentos/e-mails/migrações/gravação de dados de clientes ou pedidos.
