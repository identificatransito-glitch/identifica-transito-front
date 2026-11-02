"""Use the owner's five original images across authenticated app versions.
Run on a staged copy after placing five canonical SVG/PNG wrappers in etiquetas-v2.
Preserve original bundles; version the namespace so immutable caches cannot serve old art.
"""
from pathlib import Path
import shutil
import sys
root=Path(sys.argv[1]).resolve(strict=True)
old_namespace='_nuxt-moto-20261102'
new_namespace='_nuxt-etiquetas-20261102'
source=root/old_namespace
new=root/new_namespace
assert source.is_dir() and not new.exists(), 'Unexpected publication version'
images=root/'images/dashboard/etiquetas-v2'
colors=['amarelo','azul','branco','verde','vermelho']
assert all((images/(color+'.svg')).is_file() for color in colors), 'Original image asset missing'
for alias,canonical in {'amarela':'amarelo','vermelha':'vermelho','branca':'branco'}.items():
    shutil.copy2(images/(canonical+'.svg'),images/(alias+'.svg'))
# Keep legacy direct-image links accurate as well. CSS provides a cache-safe fallback.
for color in colors:
    shutil.copy2(images/(color+'.svg'),root/'images/dashboard/etiquetas'/(color+'.svg'))
shutil.copytree(source,new,copy_function=shutil.copy2)
old='/images/dashboard/etiquetas/'
updated=cards=payments=0
for asset in new.rglob('*.js'):
    content=asset.read_text();result=content
    card=f'amarela:"{old}amarelo.svg",azul:'
    added=f'amarela:"{old}amarelo.svg",amarelo:"{old}amarelo.svg",branco:"{old}branco.svg",branca:"{old}branco.svg",vermelha:"{old}vermelho.svg",azul:'
    cards+=result.count(card);result=result.replace(card,added)
    payment='amarela:"amarelo",amarelo:"amarelo",azul:'
    payments+=result.count(payment);result=result.replace(payment,'branco:"branco",branca:"branco",'+payment)
    result=result.replace(f'laranja:"{old}laranja.svg"',f'laranja:"{old}azul.svg"')
    result=result.replace(old,'/images/dashboard/etiquetas-v2/')
    result=result.replace('/'+old_namespace+'/', '/'+new_namespace+'/')
    if result!=content:asset.write_text(result);updated+=1
assert cards>0 and payments>0, 'Expected color maps missing; do not publish staged release'
link=b'<link rel="stylesheet" href="/etiquetas-kit-v2.css?v=originais-20261102">'
count=0
for page in root.rglob('*.html'):
    original=page.read_bytes()
    content=original.replace(('/'+old_namespace+'/').encode(),('/'+new_namespace+'/').encode())
    content=content.replace(b'/etiquetas-kit-v2.css?v=20261102',b'/etiquetas-kit-v2.css?v=originais-20261102')
    if b'_nuxt' in content and link not in content:
        assert content.count(b'</head>')==1
        content=content.replace(b'</head>',link+b'</head>')
    if content!=original:page.write_bytes(content);count+=1
print('User image modules updated:',updated,'vehicle maps:',cards,'checkout maps:',payments,'HTML entries:',count)
print('Old assets, scripts, authentication and checkout actions retained.')
