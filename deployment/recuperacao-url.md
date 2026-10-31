# URL dos e-mails de recuperação

No ambiente da API, `APP_URL_FRONT` deve ser `https://www.identificatransito.com.br`, sem o prefixo antigo `/aprovacao`.
O `AppServiceProvider` já usa `config('app.url_front')` para criar a URL `/auth/reset-password?token=...&email=...`; nenhum handler de redefinição precisa ser alterado.

Após mudar somente essa variável, execute `php artisan config:cache` e `php artisan queue:restart` como o usuário da API. Preserve os demais campos do ambiente e um backup privado no servidor; nunca versionar o arquivo de ambiente.

Inclua `reset-password-routes.conf` dentro do server HTTPS do frontend. O trecho mantém os links antigos no mesmo domínio HTTPS e preserva os parâmetros, além de servir a página de redefinição sem cache heurístico. Faça backup do vhost, valide `nginx -t` e só então recarregue graciosamente.

Validação feita com parâmetros fictícios: os dois caminhos antigos e a rota atual sem barra retornam 302 para a rota atual com barra; token e e-mail permanecem idênticos. O destino retorna HTML 200, `Cache-Control: no-cache` e mantém `X-Content-Type-Options: nosniff`.
Não usar tokens reais de clientes em testes ou registros. A confirmação da nova senha deve ser feita pelo próprio usuário.
