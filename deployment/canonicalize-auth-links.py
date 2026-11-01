"""Normalize native auth anchors in an already assembled static publication.

Usage: python3 canonicalize-auth-links.py PATH_TO_STAGED_PUBLIC
Run against a copy, before switching the public symlink. No scripts, form
handlers, assets, database settings or SMTP configuration are modified.
The matching nginx routes remain necessary for hydrated/legacy app links.
"""
from pathlib import Path
import re
import sys

root = Path(sys.argv[1]).resolve(strict=True)
pattern = re.compile(rb'href=(")(/auth/(?:register|login|forgot-password))(")')
changed = 0
for path in root.rglob('*.html'):
    before = path.read_bytes()
    after, count = pattern.subn(rb'href=\1\2/\3', before)
    if count:
        path.write_bytes(after)
        changed += count
print(f'Canonical auth anchors: {changed}')
