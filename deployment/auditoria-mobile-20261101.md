# Navegação móvel — 01/11/2026

Relato: Safari no iPhone solicitava download ao tocar em Quero fazer parte.

Confirmado: /auth/register e /auth/login redirecionavam de HTTPS para HTTP
antes de retornar ao HTTPS. Os links da página inicial não tinham barra final.
Não foi possível reproduzir um Content-Disposition attachment: as páginas
responderam text/html mesmo com User-Agent de iPhone. Portanto não se atribui
causa definitiva ao download sem confirmação no aparelho.

Aplicado:
- backup do vhost em /root/it-vhost-antes-mobile-20261101.conf;
- três rotas canônicas de canonical-auth-routes.conf incorporadas ao vhost;
- proxy_redirect mapeia somente o host próprio de HTTP para HTTPS;
- nginx -t aprovado antes do reload gracioso;
- cópia public-mobile-20261101 da publicação anterior, com 13 âncoras
  normalizadas por canonicalize-auth-links.py;
- public alterado atomicamente para essa cópia, preservando propriedade;
- public-redefinicao-20261031 continua intacta.

Verificação: 36 GETs (12 caminhos, agentes iPhone/Android/desktop) sem HTTP,
sem attachment/octet-stream, todos terminam 200. Oito páginas e 77 recursos
públicos sem falhas na inspeção. Clique real na largura 390px: Quero fazer
parte abre Crie sua conta; Fazer Login abre novo login; recuperação abre novo
layout. Nenhum download nos cliques monitorados. Não é teste do Safari real.

Sem alterações de API, dados, SMTP, autenticação ou design. A área autenticada
não foi percorrida com conta de cliente. A checagem não inclui pentest.

Rollback: restaurar o backup do vhost, executar nginx -t antes de reload;
recriar symlink temporário para public-redefinicao-20261031 com proprietário
identificatransito e substituir public com mv -Tf. Não apagar versões antigas.
