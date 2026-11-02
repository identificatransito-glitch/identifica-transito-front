"""Apply the vehicleEnums.ts option to the preserved composite static release.

Run only on a staged copy of public. Keep the previous release and _nuxt files.
A new asset namespace avoids changing URLs already cached as immutable.
No API, user data, authentication, or business rules are modified.
"""
from pathlib import Path
import hashlib
import shutil
import sys

root = Path(sys.argv[1]).resolve(strict=True)
old = root / '_nuxt'
new = root / '_nuxt-moto-20261102'
assert not new.exists(), 'Release already staged'
expected = {
    'CgDozrWb.js': ('e', '78696a0bd60f421c054a80046597f621014a75174278a19e94500d08c1daffe1'),
    'mpEBnM66.js': ('o', '333260f39aae3011fda6b3b46ac28dad780ab5f4b3d4e9330943a60d6abfd2b7'),
}
updates = {}
for name, (symbol, sha) in expected.items():
    source = (old / name).read_bytes()
    assert hashlib.sha256(source).hexdigest() == sha, f'Unexpected asset: {name}'
    marker = f'{{label:"Caminhao",value:{symbol}.CAMINHAO,icon:"pi pi-truck"}}'.encode()
    addition = f'{{label:"Motocicleta",value:{symbol}.MOTOCICLETA,icon:"pi pi-car"}},'.encode()
    assert source.count(marker) == 1 and addition not in source
    updates[name] = source.replace(marker, addition + marker)
# All validation precedes writes to the staged release.
shutil.copytree(old, new, copy_function=shutil.copy2)
for name, content in updates.items():
    (new / name).write_bytes(content)
changed = []
for path in root.rglob('*.html'):
    original = path.read_bytes()
    updated = original.replace(b'/_nuxt/', b'/_nuxt-moto-20261102/')
    if updated != original:
        path.write_bytes(updated)
        changed.append(str(path.relative_to(root)))
# Explicit absolute paths, if present in a future copied asset, use the same namespace.
for path in new.rglob('*'):
    if path.suffix in {'.js', '.css', '.json'}:
        original = path.read_bytes()
        updated = original.replace(b'/_nuxt/', b'/_nuxt-moto-20261102/')
        if updated != original:
            path.write_bytes(updated)
print('Enabled Motocicleta = 2 in both preserved frontend builds.')
print('HTML entry points updated:', len(changed))
print('Original _nuxt assets retained unchanged.')
