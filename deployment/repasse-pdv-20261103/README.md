# Repasse configurado por PDV — 03/11/2026

O percentual agora pertence ao ponto de venda (`points_of_sale.commission_percentage`), com valor opcional de 0 a 100 e até duas casas decimais. O Super Admin configura em **Pontos de Venda → Novo/Editar → Repasse do PDV**. Usuários vinculados herdam a regra; os formulários de usuário não gravam taxa individual.

Relatório `/admin/pdv-commissions` retorna uma linha por PDV, com `staff_count`. Vendas aprovadas continuam atribuídas pelo criador do cliente (`user_id_admin_pdv`), sem mudar permissões ou considerar o local de retirada como origem da venda. O painel individual usa o mesmo percentual, preservando sua própria carteira. A regra atual recalcula os períodos consultados; não há liquidação automática, cobrança ou transferência.

Campo em branco significa não configurado e não inventa percentual. As colunas antigas no usuário ficam preservadas, mas não são mais usadas nem aceitas em gravações. Na inspeção anterior à migração havia 1 usuário, 1 PDV e 0 compras; nenhum histórico de compra precisava ser convertido. Não executar limpeza de dados.

Validação: 40 verificações em SQLite `:memory:` com HTTP proibido e filas/e-mails simulados: agrupamento de dois administradores, elegibilidade de vendas, isolamento entre PDVs, arredondamento, limites, ausência de configuração, edição central, exclusão de campos pessoais e restrição de leitura pública do percentual.

Publicação: executar o instalador sem parâmetros para staging e rodar o teste antes de `--apply`; instalador confere SHA dos arquivos originais e cria backup privado. Depois aplicar somente a migração `2026_11_03_180000_add_commission_percentage_to_points_of_sale.php`. Fazer backup privado do banco antes. Frontend mantém HTML institucional aprovado e arquivos antigos em namespace; apenas telas do sistema recebem o novo build. Reversão: restaurar fontes privadas anteriores e symlink frontend anterior; manter a coluna nova no banco para preservar a configuração.
