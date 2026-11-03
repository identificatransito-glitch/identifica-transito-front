# Retirada de kits e operação dos PDVs

## Comportamento

- Catálogo: R$ 29,90 por kit nas cinco cores; valores de pedidos anteriores permanecem intactos. Cupons válidos continuam calculados no servidor.
- O cliente abre o comprovante em **Meus pedidos → QR de retirada**. É um código diferente do QR PIX, sem CPF, e-mail ou dados de cartão.
- Qualquer PDV ativo pode conferir o código. O operador deve entrar com sua conta de PDV. Super Admin informa o ponto onde a entrega está ocorrendo.
- Conferir consulta novamente o Mercado Pago. Confirmar entrega consulta novamente e grava uma única retirada, com transação, bloqueio de linhas, ponto efetivo, operador e data. O código não substitui pagamento aprovado nem disponibilidade do kit.
- Pagamentos pendentes, recusados, estornados, de teste, em outra moeda ou com valor divergente não liberam a retirada. Falhas na consulta não registram entrega. Pedidos isentos por cupom integral são identificados separadamente.
- Pedidos com entrega em casa não recebem comprovante de retirada.

## Cadastro dos PDVs

- CEP busca rua, bairro, cidade e UF no ViaCEP. Campos permanecem editáveis; falhas permitem preenchimento manual. Consultas são canceladas quando o CEP muda e não sobrescrevem campos editados durante a consulta.
- O endereço completo continua no campo existente `address`, preservando o contrato com os pedidos e os pontos antigos.
- E-mail, nova senha e confirmação permitem criar a conta do responsável junto com um novo ponto. A operação é exclusiva do Super Admin, atômica e sempre cria o papel de PDV (2), vinculado ao ponto criado no servidor. Não reaproveita nem altera uma conta existente.
- Senha é armazenada somente como hash no usuário, sem campos de senha no PDV nem credenciais na resposta.
- Para responsáveis já cadastrados, o vínculo e a redefinição da senha continuam na edição do usuário.

## Impressão

A ação está na listagem compartilhada de pedidos do Super Admin e do PDV, mantendo o escopo de acesso de cada um. O destinatário vem do endereço de entrega do pedido. O remetente pode vir do PDV de origem; nome, endereço completo e CEP devem ser conferidos antes de imprimir. A etiqueta não exibe CPF, e-mail nem valor da compra. Ajustes de impressão não alteram o endereço salvo no pedido.

É um rótulo de endereçamento para a embalagem, com área para a identificação dos Correios. Frete e documento fiscal são emitidos separadamente. Pedidos de retirada imprimem identificação interna, não rótulo de postagem.

## Instalação e testes

Os instaladores criam cópias privadas das rotas antes das alterações e fazem `php -l`. Executar a preparação sem `--apply`, depois os testes com SQLite `:memory:`, HTTP/Mail/Queue falsos. Não executar testes contra o banco real.

- `install-pickup-receipts.py <raiz-api>`; testar `test-pickup-receipts.php <raiz-api> /root/it-pickup-stage-20261102`.
- Aplicar com `--apply`; migrar **somente** `2026_11_02_200000_create_pickup_receipts_table.php`; atualizar cache de rotas.
- `install-pdv-access.py <raiz-api>`; testar `test-pdv-access.php <raiz-api> /root/it-pdv-access-stage-20261102`; aplicar com `--apply`; atualizar cache de rotas.
- Validações executadas: 25 casos de retirada e 13 de criação de acesso, sem cobranças, e-mails, usuários ou pedidos reais nos testes.
- Frontend: `pnpm install --frozen-lockfile`; gerar com API real e `NUXT_PUBLIC_USE_MOCKS=false`. A publicação da Hostinger preserva a homepage estática aprovada e os recursos anteriores; não substituir a homepage pelo índice gerado do Nuxt.

## Pendência de ativação financeira

A credencial existente responde ao Mercado Pago, porém o e-mail da conta vinculada difere da conta informada pelo proprietário. Essa titularidade ainda precisa ser confirmada antes de considerar a cobrança configurada para o projeto. Nenhum Access Token, senha, credencial ou dado financeiro foi incluído no repositório, e nenhuma cobrança real foi executada para os testes.

## Fontes de integração

- [Mercado Pago — credenciais](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/resources/credentials)
- [ViaCEP — consulta e preenchimento de CEP](https://viacep.com.br/)
- [Correios — guia de endereçamento](https://www.correios.com.br/enviar/precisa-de-ajuda/guia-de-enderecamento/guia-de-enderecamento)
- [Correios — envio de encomendas e documentos fiscais](https://www.correios.com.br/enviar/encomendas/nacional)
