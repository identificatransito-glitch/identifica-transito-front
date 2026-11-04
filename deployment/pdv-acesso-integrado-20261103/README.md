# PDV com acesso integrado — 03/11/2026

O Super Admin cadastra o PDV, o responsável, o e-mail e a senha no mesmo formulário. Cada novo PDV cria atomicamente um administrador de tipo 2, vinculado somente ao ponto. O endpoint canônico `/admin/pickup-points` e o alias anterior usam a mesma regra; nenhum deles cria pontos novos sem acesso.

A edição carrega o e-mail do acesso principal e permite definir uma nova senha ou manter a atual deixando-a em branco. Um PDV antigo sem conta exige senha inicial ao salvar. A migração acrescenta `points_of_sale.access_user_id` e preserva um vínculo anterior somente quando há exatamente um administrador. Vínculos ambíguos não são escolhidos automaticamente. A inspeção de produção encontrou 1 PDV e nenhum administrador PDV; não se inventaram e-mail ou senha para o ponto existente.

A área Usuários fica exclusiva para clientes: listagem, contagens e cadastros não misturam administradores de PDV. Contas PDV são gerenciadas no ponto, e o Super Admin permanece preservado. Administrador tipo 2 continua usando o login e a recuperação de senha existentes.

E-mails são normalizados e exclusivos na plataforma. A edição não toma posse de outra conta nem transfere vínculos. Senhas são armazenadas com hash e nunca retornadas na API. Alterar e-mail ou senha revoga sessões, tokens de acesso e tokens antigos de recuperação daquela conta. As permissões existentes do administrador PDV continuam restritas à sua carteira; criar um PDV não concede Super Admin.

45 verificações isoladas em SQLite na memória, com HTTP bloqueado e e-mails/filas simulados: campos obrigatórios, confirmação, normalização, exclusividade, papel fixo, associação, edição sem duplicação, manutenção e troca de senha, revogação de sessões, privacidade do retorno, migração de vínculo legado, proteção do cadastro genérico e rollback de transação. Build Nuxt e revisão desktop/celular realizados.

Também atualiza somente os textos pedidos no site institucional: o banner passa a TAG REFLETIVA e a nota Proposta visual / Versão 2 / imagens de IA é removida do rodapé de início e projeto. HTML atualizado está em marketing/ para compor a publicação estática sem substituir a página institucional pelo template Nuxt.

Publicação Hostinger: backup privado do banco e fontes antes de aplicar. Executar instalador sem `--apply`, rodar testes isolados; conferir hashes e só então aplicar fontes e a migração específica `2026_11_04_020000_add_primary_access_to_points_of_sale.php`. Manter todas as versões anteriores do frontend e seus namespaces; usar o novo shell somente nas telas de autenticação e sistema. A instalação não cria usuários reais nem altera credenciais já cadastradas. O proprietário informa as credenciais na edição do PDV antigo.
