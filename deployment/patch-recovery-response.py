"""Apply the owner's requested recovery response without changing password rules."""
from datetime import datetime
from pathlib import Path
import os
import re
import shutil
import subprocess
import sys
import tempfile

controller = Path(sys.argv[1]).resolve()
original = controller.read_bytes()
source = original.decode('utf-8')
pattern = re.compile(r'        // apenas debug\s*/\*if \(\$status !== Password::RESET_LINK_SENT\) \{.*?\}\*/', re.S)
replacement = """        if ($status !== Password::RESET_LINK_SENT) {
            $message = match ($status) {
                Password::INVALID_USER => 'Este e-mail não está cadastrado.',
                Password::RESET_THROTTLED => 'Aguarde um pouco antes de solicitar outro link.',
                default => 'Não foi possível enviar o link de recuperação. Tente novamente.',
            };

            return response()->json(['message' => $message], 422);
        }"""
updated, count = pattern.subn(lambda _: replacement, source)
if count != 1:
    raise SystemExit('Unexpected controller version; no change applied.')
marker = '    public function resetPassword('
if marker not in source or source.split(marker, 1)[1] != updated.split(marker, 1)[1]:
    raise SystemExit('Reset password method differs; no change applied.')
metadata = controller.stat()
fd, temporary = tempfile.mkstemp(prefix='.recovery-response-', suffix='.php', dir=controller.parent)
try:
    with os.fdopen(fd, 'wb') as stream:
        stream.write(updated.encode('utf-8'))
    subprocess.run(['php', '-l', temporary], check=True)
    backup = Path('/root/it-recovery-response-' + datetime.now().strftime('%Y%m%d-%H%M%S'))
    backup.mkdir(mode=0o700)
    shutil.copy2(controller, backup / controller.name)
    os.chmod(backup / controller.name, 0o600)
    os.chmod(temporary, metadata.st_mode & 0o777)
    os.chown(temporary, metadata.st_uid, metadata.st_gid)
    if controller.read_bytes() != original:
        raise SystemExit('Controller changed concurrently; no change applied.')
    os.replace(temporary, controller)
    print('Recovery response updated; reset handler preserved; private backup:', backup)
finally:
    if os.path.exists(temporary):
        os.unlink(temporary)
