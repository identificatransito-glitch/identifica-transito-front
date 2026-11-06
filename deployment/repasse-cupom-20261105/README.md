Repasse sobre o valor dos kits antes dos descontos — 05/11/2026

Regra confirmada pelo proprietário: em pedidos de retirada, o PDV escolhido na compra recebe o repasse, inclusive com cupom de 100%. Sem ponto de retirada, mantém o PDV de origem do cliente. Cada pedido pertence a um único PDV para cálculo financeiro.
Base: preço unitário histórico do pedido × quantidade, sem desconto e sem frete. Receita recebida continua sendo price_total (após desconto). Somente pagamentos aprovados e pedidos não excluídos. Estornos, cancelamentos e pagamentos pendentes não geram repasse. Percentual centralizado no PDV.

Relatórios do Super Admin (início, ranking, lista e detalhe) usam a mesma fonte. Dashboard PDV inclui clientes/veículos/pedidos do escopo operacional já autorizado, inclusive cliente cadastrado sozinho que escolheu o ponto. Nomes já disponíveis nos pedidos podem aparecer em Últimos usuários; dados de contato de clientes externos não são expostos. Permissões de editar usuários, QR de retirada e pagamentos não mudam.

Pedidos existentes são recalculados em consulta, sem regravar clientes, compras ou pagamentos. Nenhum repasse é transferido automaticamente. Sem migrações.

42 testes isolados SQLite em memória, filas/e-mail HTTP falsos. Consulta de leitura MySQL confirmou um pedido aprovado com valor zero e um ponto com venda atribuída. A compra não foi refeita.
