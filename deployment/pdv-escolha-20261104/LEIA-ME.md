# Escolha de PDVs e cartão temporariamente oculto

Checkout: cards com nome, endereço, telefone quando disponível, mapa e seleção opcional. Busca por nome/bairro/cidade sem diferenciar acentos. CEP de referência consultado no ViaCEP ao clicar em Buscar região; requisições antigas canceladas, timeout e fallback de busca manual. Usa endereço de entrega informado no checkout ou endereço de pedido anterior do próprio usuário, quando disponível. Não grava um novo endereço no perfil.

Ordenação por mesmo CEP, bairro, cidade e estado, com desempate por nome. Não calcula nem promete distância em quilômetros. Endereços antigos sem dados estruturados continuam na lista e podem ser encontrados pela busca. Links aceitam somente http/https; sem link cadastrado, abre pesquisa no Google Maps pelo endereço comercial.

Pontos inativos removidos. Estados de carregamento, erro/repetir, nenhum resultado e mostrar todos (>6) incluídos. Preferência pode ser limpa e não restringe retirada: qualquer PDV autorizado conforme disponibilidade. Preço, QR de retirada e APIs de pagamento preservados.

Cartão de crédito oculto no seletor e no formulário por CREDIT_CARD_ENABLED=false em pagamento.vue. Guarda também impede entrar pelo ramo de envio de cartão. Pix, boleto e pedido gratuito mantidos. Código de tokenização/integração preservado para reativação futura. Não foram alteradas configurações financeiras ou cobranças no backend.

Validação: build de produção; 23 verificações Node; desktop e 390px no navegador com pontos fictícios, busca, seleção, limpar, CEP inválido e consulta de CEP válida. A rota QA foi removida e não consta da compilação publicada. Nenhum pagamento realizado.

Teste: Node 24 test-pickup-points.mjs /caminho/do/repositorio
