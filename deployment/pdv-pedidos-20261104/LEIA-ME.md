# Pedidos de retirada no PDV e identificação completa

AdminPdvService::orders e updateOrderStatus passam a usar filtro agrupado: cliente cadastrado pelo administrador OU pedido sem entrega domiciliar cujo pickup_point.id corresponde ao pdv_id do administrador. IDs JSON numéricos e texto são considerados. Um administrador sem pdv_id não ganha acesso a pedidos sem vínculo. Busca permanece dentro do filtro. Nenhum pedido, pagamento ou cliente precisa ser regravado: a correção alcança os pedidos existentes.

Nenhuma alteração nos métodos de dashboard financeiro ou repasse. Retirada em um ponto não transfere atribuição comercial do cliente. Confirmação de retirada no PDV deve usar o QR do cliente: status 4 não pode ser marcado manualmente nem reaberto pelo endpoint operacional. A tela continua exibindo o status Retirado, mas não oferece essa escolha ao PDV; o Super Admin mantém sua interface existente.

Janela/preview/identificação impressa mostram cliente, placa, pedido, data, kit, quantidade, situação informada do pagamento e ponto de preferência com endereço/CEP, responsável, telefone e mapa (na tela). O ponto é identificado como preferência, não como prova de local efetivo de entrega. A conferência do pagamento e a entrega única permanecem no fluxo de QR. Não são impressos tokens de retirada.

Dados do PDV são obtidos pelo cadastro de pontos e combinados com o snapshot do pedido. Se não houver endereço disponível, aparece aviso, sem inventar dados. Retirada em qualquer ponto autorizado continua permitida conforme disponibilidade. Layout domiciliar de Correios preservado.

Validação: 18 verificações isoladas de escopo, busca, permissão, preparo, bloqueio de retirada manual e preservação de atribuição; 18 verificações Node da identificação. Build de produção e QA desktop/390px, sem overflow horizontal, conteúdo impresso e fechar conferidos com dados fictícios. Nenhuma impressão física, envio real de e-mail, pagamento ou alteração de pedido real feita nos testes.

Instalador aplica apenas um arquivo PHP com hash de origem e backup privado. Não há migration. A verificação de pedidos reais é somente leitura e retorna apenas contagens agregadas.
