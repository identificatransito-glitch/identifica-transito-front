"""Migração interativa: o usuário insere e confirma a credencial no servidor."""
import getpass
import os
from pathlib import Path
import re
import shutil
import smtplib
import ssl
import subprocess
import tempfile
from datetime import datetime

APP = Path('/home/identificatransito-api/htdocs/api.identificatransito.com.br')
ENV = APP / '.env'
ACCOUNT = 'identificatransito@gmail.com'
OWNER = 'identificatransito-api'


def replace_mail_fields(source, values):
    for key, value in values.items():
        pattern = re.compile(r'^' + re.escape(key) + r'=[^\r\n]*', re.MULTILINE)
        if len(pattern.findall(source)) > 1:
            raise ValueError('Campo duplicado na configuração: ' + key)
        if pattern.search(source):
            source = pattern.sub(lambda _: key + '=' + value, source)
        else:
            source += '\n' + key + '=' + value + '\n'
    return source


def atomic_write(path, data, metadata):
    fd, temporary = tempfile.mkstemp(prefix='.env.mail-', dir=str(path.parent))
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(data)
            stream.flush()
            os.fsync(stream.fileno())
        os.chmod(temporary, metadata.st_mode & 0o777)
        os.chown(temporary, metadata.st_uid, metadata.st_gid)
        os.replace(temporary, path)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def artisan(command):
    return subprocess.run(
        ['runuser', '-u', OWNER, '--', '/usr/bin/php', 'artisan', command],
        cwd=str(APP), stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        timeout=60, check=False
    ).returncode


def main():
    if os.geteuid() != 0 or not ENV.is_file() or not (APP / 'artisan').is_file():
        raise SystemExit('Execute no console administrativo correto da Hostinger.')
    original = ENV.read_bytes()
    metadata = ENV.stat()
    print('Conta de envio: ' + ACCOUNT)
    print('Conexão: smtp.gmail.com, porta 587, TLS com certificado verificado.')
    print('A senha não será exibida nem gravada no histórico do terminal.')
    password = ''.join(getpass.getpass('Cole a SENHA DE APP do Google e pressione Enter: ').split())
    if not re.fullmatch(r'[A-Za-z0-9]{16}', password):
        raise SystemExit('Formato inesperado. Nenhuma configuração foi alterada.')
    try:
        with smtplib.SMTP('smtp.gmail.com', 587, timeout=25) as smtp:
            smtp.ehlo()
            smtp.starttls(context=ssl.create_default_context())
            smtp.ehlo()
            smtp.login(ACCOUNT, password)
    except Exception:
        raise SystemExit('Gmail não validou a conexão. Nenhuma configuração foi alterada.')
    print('Autenticação Gmail validada. Nenhum e-mail foi enviado.')
    print('Alteração proposta: usuário SMTP e remetente = ' + ACCOUNT)
    print('Nome do remetente = Identifica Trânsito. Demais campos serão preservados.')
    if input('Para aplicar, digite APLICAR e pressione Enter: ').strip() != 'APLICAR':
        raise SystemExit('Cancelado. Nenhuma configuração foi alterada.')
    if ENV.read_bytes() != original:
        raise SystemExit('A configuração mudou durante a validação. Nada foi aplicado.')
    backup = Path('/root/identifica-mail-backup-' + datetime.now().strftime('%Y%m%d-%H%M%S'))
    backup.mkdir(mode=0o700)
    shutil.copy2(ENV, backup / 'env-original')
    os.chmod(backup / 'env-original', 0o600)
    values = {'MAIL_USERNAME': ACCOUNT, 'MAIL_PASSWORD': password,
              'MAIL_FROM_ADDRESS': ACCOUNT, 'MAIL_FROM_NAME': '"Identifica Trânsito"'}
    updated = replace_mail_fields(original.decode('utf-8'), values).encode('utf-8')
    try:
        atomic_write(ENV, updated, metadata)
        if artisan('config:cache') != 0:
            raise RuntimeError('cache')
    except Exception:
        atomic_write(ENV, original, metadata)
        artisan('config:cache')
        raise SystemExit('Não foi possível aplicar. Configuração anterior restaurada.')
    try:
        queue_status = artisan('queue:restart')
    except Exception:
        queue_status = 1
    print('Remetente atualizado para ' + ACCOUNT)
    print('Backup privado preservado no servidor. Nenhum e-mail foi enviado pelo procedimento.')
    if queue_status:
        print('ATENÇÃO: falta conferir a recarga dos workers de envio.')
    else:
        print('Recarga graciosa da fila solicitada. Teste agora a recuperação da sua conta.')


if __name__ == '__main__':
    try:
        main()
    except (KeyboardInterrupt, EOFError):
        print('\nOperação interrompida pelo usuário.')
