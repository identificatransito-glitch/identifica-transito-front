# Uma etiqueta por placa

Bloqueia cadastros e edições que repetem a placa, independentemente de proprietário ou perfil. Normaliza maiúsculas, hífen e espaços. Cadastro simultâneo usa lock MySQL por placa, com conferência e gravação na mesma transação e liberação em finally. O job de enriquecimento só é enviado após commit.

Compras e prévias de cupom rejeitam mais de um kit da mesma placa ou cadastro legado duplicado antes de chamar pagamentos. Registros existentes não são apagados. Para resolver duplicidades anteriores, o titular deve manter um único cadastro; não há remoção automática.

26 verificações isoladas com SQLite em memória, fila e e-mail falsos. Sem dados reais alterados ou cobrança nos testes. Frontend e layout preservados. Mensagens de validação são apresentadas pelo formulário existente.

Instalação: python3 install-unique-plate.py para preparar; executar test-unique-plate.php com raiz e stage; guardar backup privado, então --apply e php8.4 artisan optimize:clear. O instalador confere hashes dos arquivos atuais e guarda cópias antes de substituir.
