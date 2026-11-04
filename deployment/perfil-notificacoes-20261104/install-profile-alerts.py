from pathlib import Path
import os,sys,json,hashlib,subprocess,shutil,tempfile
from datetime import datetime
root=Path('/home/identificatransito-api/htdocs/api.identificatransito.com.br')
stage=Path('/root/it-profile-alerts-stage-20261104');stage.mkdir(mode=0o700,exist_ok=True)
files={str(p.relative_to(Path(__file__).parent/'api')):p.read_text() for p in (Path(__file__).parent/'api').rglob('*.php')}
files['test-profile-alerts.php']=(Path(__file__).parent/'test-profile-alerts.php').read_text()
originals=json.loads('{"app/Http/Requests/StoreNotificationDriverRequest.php": "58fe9e98e34c73157711b15d0266aaec6954e29235ed714b8128305526c11d99", "app/Http/Requests/UpdateUserRequest.php": "6aafa8cb6e04ed5e57142ab960f24bb569ded3f7b48c78f98344fc56f7b4d8a2", "app/Jobs/SendNotificationDriverMailJob.php": "4a45baa6ce9aede1fe401e9798ef6a93b1ae457b3bbed4002927e693b488cbe1", "app/Services/NotificationDriverService.php": "8bcdcc2e761e17a1ab28362b95d67ab60fa9bf9caef934ce83e4eb8e650003e0"}')
for name,source in files.items():
 p=stage/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(source);subprocess.run(['php8.4','-l',str(p)],check=True,stdout=subprocess.DEVNULL)
print('STAGED',stage)
if '--apply' not in sys.argv:sys.exit(0)
for name,sha in originals.items():
 assert hashlib.sha256((root/name).read_bytes()).hexdigest()==sha,'Live source changed: '+name
backup=Path('/root/it-before-profile-alerts-'+datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
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
