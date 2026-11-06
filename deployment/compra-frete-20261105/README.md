Compra: um kit por placa e configuração de frete — 05/11/2026

Um kit por veículo, sem seletor de quantidade. Outro kit exige outro veículo cadastrado. Mantido o bloqueio da API para IDs repetidos, placas duplicadas e recompra de kit já aprovado.

Super Admin → Comercial → Frete: habilitar/desabilitar cobrança e configurar valor fixo por pedido. Padrão inicial desativado e R$0. Retirada em PDV sempre gratuita. Valor salvo pode ficar guardado enquanto cobrança estiver desligada. Controle disponível em desktop e mobile, somente Super Admin pode alterar. Configuração autenticada, sem cache e validada na API.

Backend calcula o frete pela configuração, compara com o total conferido na compra e rejeita preços antigos/adulterados antes de chamar pagamento. Frete cobrado uma vez no total do pagamento, sem multiplicar por veículos. Cupom100% nos kits não isenta frete habilitado; total positivo exige meio de pagamento. Totalzero mantém aprovação por cupom. Mantida a convenção de shipping_price nos itens; confirmação usa o maior frete do grupo.

Nenhuma cobrança real foi executada. Testes:46 verificações de API/checkout com SQLite memória e SDK de pagamentos substituído por falso;17 verificações do script real de compra. UIcomponentesreais: habilitar, valor12.50, salvar local, valor negativo bloqueado, restaurar0 e desativado; desktop/390px sem overflow. RotatemporáriaQA removida antes do buildfinal.

Cotação automática por CEP NÃO está ativa. Proprietário informou não ter conta no Melhor Envio. API sem mensalidade, mas exige cadastro/autorização e cada postagem tem custo. Integração futura também precisará CEPdeorigem, peso e medidas da embalagem. Não foi criada conta nem comprado frete. O valor fixo não é cotação dos Correios.
Fontes oficiais: https://docs.melhorenvio.com.br/reference/introducao-api-melhor-envio ; https://centraldeajuda.melhorenvio.com.br/hc/pt-br/articles/31220458428308-Por-que-a-nota-da-transportadora-informa-um-valor-menor ; https://www.correios.com.br/atendimento/developers/manuais/manual-api-preco-1

PublicaçãoAPI cria somente tabela shipping_settings por migration específica; faz backupprivado de fontes e banco no servidor antes. Não altera pedidos/clientes existentes, comissão, usuários ou cobrança já realizada. Credenciais e dump nunca são exportados para GitHub ou para os arquivos de entrega. routes/api.php recebe somente require de routes/shipping.php preservando as rotas anteriores.
