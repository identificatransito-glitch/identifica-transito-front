# Correção de edição/criação de acesso do PDV

Causa confirmada no log: MySQL 1364, CPF sem valor padrão. O serviço de PDV cria contas com nome do responsável, e-mail, senha com hash, type=2 e pdv_id. O banco herdado ainda exigia CPF varchar(14), birth_date date e phone varchar(20), todos NOT NULL. Os testes anteriores usavam esses campos nullable e por isso não reproduziam a restrição de produção.

A nova migration altera somente a nulabilidade desses três campos, preservando tipos, tamanhos e índices. Não inventa CPF/data de nascimento/telefone. StoreUserRequest continua exigindo os três no cadastro de clientes. Dados existentes não são atualizados ou removidos. O rollback é recusado se houver contas com valores nulos, para evitar perda de dados ou preenchimento fictício.

Frontend de novo/edição de PDV usa a primeira mensagem de validação de campo conhecido, com fallback seguro. Não exibe SQL, stacktrace ou conteúdo de erro 500. Credenciais permanecem ocultas. O serviço de PDV, perfis de acesso, vínculo e hash da senha não foram alterados.

Validação: erro original reproduzido com tabela estrita em SQLite memória; migration; 23 verificações de conta nova, edição de PDV existente sem acesso, hash/login/serialização com dados pessoais nulos, duplicidade de e-mail, comissão, preservação de dados e regras de clientes. 8 verificações Node de mensagens. Fila/e-mail falsos; nenhum acesso real criado e nenhuma senha real alterada nos testes. Build de produção concluído.

Instalação: preparar stage pelo instalador; executar test-pdv-personal-fields.php ROOT STAGE; backup privado; instalar migration --apply; revisar artisan migrate --pretend --path=database/migrations/2026_11_04_235900_allow_pdv_accounts_without_personal_fields.php --force; aplicar somente esse caminho e limpar caches. Não executar migrations pendentes fora desse escopo.
