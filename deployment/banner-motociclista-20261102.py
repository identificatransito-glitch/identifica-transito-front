"""Update only the second homepage banner on a staged copy of public.
Use the image from banner-motociclista-20261102.zip in the release assets.
The original family image remains available; other slides/timing are preserved.
"""
from pathlib import Path
import hashlib
import sys
root = Path(sys.argv[1]).resolve(strict=True)
index = root / 'index.html'
stylesheet = root / 'abertura.css'
assert hashlib.sha256(index.read_bytes()).hexdigest() == 'fcfa91504b52bd7effc6b2134533697462eb4049b5651f21e76e0de9dc3cc5e5'
assert hashlib.sha256(stylesheet.read_bytes()).hexdigest() == '97403e3de08ad5a44d4b1640c90447bfa5198d0df9f0473b616e557c6ed7e0ac'
assert (root / 'assets/banner-motociclista-20261102.webp').is_file()
old = '<article class="opening-slide opening-slide-1" role="group" aria-roledescription="slide" aria-label="2 de 4" inert aria-hidden="true"><div class="opening-photo"><img src="assets/banner-familia.webp" alt="Família junto a um carro estacionado em ambiente arborizado" draggable="false" loading="eager"></div><div class="opening-copy"><span class="eyebrow">POR QUEM FAZ PARTE DA SUA VIDA</span><h2>Todo trajeto merece<br>um pouco mais de cuidado.</h2><p>Por trás de cada volante, existe uma história. Conheça uma ideia que aproxima informação, empatia e respeito.</p><div class="opening-actions"><a class="button" href="projeto.html">Conheça o projeto <span aria-hidden="true"><span class="desktop-arrow">↗</span><svg class="site-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 19 19 5M5 5h14v14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a></div></div></article>'
new = '<article class="opening-slide opening-slide-1 opening-slide-moto" role="group" aria-roledescription="slide" aria-label="2 de 4" inert aria-hidden="true"><div class="opening-photo"><img src="assets/banner-motociclista-20261102.webp" alt="Motociclista com a etiqueta azul Identifica Trânsito na parte traseira do capacete" draggable="false" loading="eager"></div><div class="opening-copy"><span class="eyebrow">UM SINAL DE CUIDADO, TAMBÉM NA MOTO</span><h2>Por trás do capacete,<br>existe uma vida.</h2><p>A etiqueta Identifica Trânsito leva um convite ao respeito também para quem segue sobre duas rodas. Conheça o projeto e faça parte.</p><div class="opening-actions"><a class="button" href="projeto.html">Conheça o projeto <span aria-hidden="true"><span class="desktop-arrow">↗</span><svg class="site-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M5 19 19 5M5 5h14v14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span></a></div></div></article>'
css = '\n/* Banner do motociclista: foto enviada preservada e contraste só neste slide. */\n.opening-slide-moto .opening-photo>img{object-position:center top}\n.opening-slide-moto:before{background:linear-gradient(90deg,rgba(5,15,26,.96) 0%,rgba(5,15,26,.86) 25%,rgba(5,15,26,.55) 43%,rgba(5,15,26,.12) 61%,transparent 76%)}\n.opening-slide-moto .opening-copy h2{color:#fff}\n.opening-slide-moto .opening-copy .eyebrow{color:#dce5ff}\n.opening-slide-moto .opening-copy>p{color:#edf2f7}\n.opening-carousel:has(.opening-slide-moto[aria-hidden="false"]) .opening-hint{color:#e1e8ef}\n@media(max-width:650px){\n.opening-slide-moto .opening-photo>img{object-position:center top}\n.opening-slide-moto:before{background:linear-gradient(0deg,#071522 0%,rgba(7,21,34,.97) 29%,rgba(7,21,34,.65) 49%,rgba(7,21,34,.1) 73%,transparent 100%)}\n}\n'
source = index.read_text()
assert source.count(old) == 1
updated = source.replace(old, new).replace('href="abertura.css?v=ordem-banners"', 'href="abertura.css?v=moto-20261102"')
assert 'abertura.css?v=moto-20261102' in updated
index.write_text(updated)
stylesheet.write_bytes(stylesheet.read_bytes() + css.encode())
print('Second banner updated; original family asset, all other slides and backend preserved.')
