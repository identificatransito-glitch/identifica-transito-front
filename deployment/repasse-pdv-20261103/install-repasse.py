from pathlib import Path
import os,sys,json,hashlib,subprocess,shutil,tempfile
from datetime import datetime
root=Path('/home/identificatransito-api/htdocs/api.identificatransito.com.br')
stage=Path('/root/it-repasse-stage-20261103');stage.mkdir(mode=0o700,exist_ok=True)
files={str(p.relative_to(Path(__file__).parent/'api')):p.read_text() for p in (Path(__file__).parent/'api').rglob('*.php')}
files['test-repasse.php']=(Path(__file__).parent/'test-repasse.php').read_text()
originals=json.loads('{"app/Models/Pdv.php": "fe861ba8e58fe6f786bb1b77c7fe827439f9483ce00da22cbe481f627a85e63f", "app/Http/Resources/UserResource.php": "ca81f1b3a0fc56ace36f167cfae79295c7daa58b9e642efdae52ef49866818cf", "app/Http/Resources/PdvResource.php": "05dd5dc66c4e87f71e522e8a077674a9f9cb846daf25c17ffdd034ff214d4cc3", "app/Http/Requests/StorePdvRequest.php": "4b68edf28842823621419b505ece7fa2670301213e32062dd6305150f7f393dd", "app/Http/Requests/UpdatePdvRequest.php": "38588c0f3ce4c277636e4f5e7654ddfa1912de7002164dda592cdd4cc65a694e", "app/Http/Requests/Admin/StoreAdminUserRequest.php": "63d679be72ccf8c5c103c849c7320fe45a9292b7d9aff6747fe62646b02f304a", "app/Http/Requests/Admin/UpdateAdminUserRequest.php": "ecac7219dc0d3def4e415a49fdf9c0a0ba83340784c3eb17bed375f7d18ebf57", "app/Services/AdminPdvService.php": "09904c777aa79cb32510b8a63793afc70c9a4fc0152173a59fc1c2b582331784", "app/Services/AdminService.php": "98b914216a2e0a6d86d66200d45376ba66c41468f1c486d5ff10e89c06a3c1bf"}')
for name,source in files.items():
 p=stage/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(source);subprocess.run(['php8.4','-l',str(p)],check=True,stdout=subprocess.DEVNULL)
print('STAGED',stage)
if '--apply' not in sys.argv:sys.exit(0)
for name,sha in originals.items():
 assert hashlib.sha256((root/name).read_bytes()).hexdigest()==sha,'Live source changed: '+name
backup=Path('/root/it-before-repasse-'+datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
meta=(root/'app/Models/Pdv.php').stat()
for name,source in files.items():
 if name.startswith('test-'):continue
 p=root/name
 if p.exists():
  saved=backup/name;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,saved)
 p.parent.mkdir(parents=True,exist_ok=True);fd,tmp=tempfile.mkstemp(prefix='.it-repasse-',dir=p.parent)
 with os.fdopen(fd,'w') as f:f.write(source)
 os.chmod(tmp,0o644);os.chown(tmp,meta.st_uid,meta.st_gid);os.replace(tmp,p)
print('APPLIED with source backup',backup)
