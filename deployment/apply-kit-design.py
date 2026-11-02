"""Enable the shared tag-card stylesheet on a staged composite publication.
Preserves all HTML payloads, scripts, state bindings and image URLs.
"""
from pathlib import Path
import hashlib
import sys
root=Path(sys.argv[1]).resolve(strict=True)
css=root/'etiquetas-kit-v2.css'
assert hashlib.sha256(css.read_bytes()).hexdigest()=='1254de9ae940f08bbea6ed1e317a016c1dd9b90475e6b2ac3b2dd36c5fb1747f'
link=b'<link rel="stylesheet" href="/etiquetas-kit-v2.css?v=20261102">'
count=0
for page in root.rglob('*.html'):
    original=page.read_bytes()
    if b'_nuxt' not in original or link in original:
        continue
    assert original.count(b'</head>')==1, str(page)
    page.write_bytes(original.replace(b'</head>',link+b'</head>'))
    count+=1
print('Tag-card styles enabled for HTML entry points:',count)
