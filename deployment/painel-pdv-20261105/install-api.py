from pathlib import Path
import os,hashlib,subprocess,shutil,tempfile,json
from datetime import datetime
root=Path('/home/identificatransito-api/htdocs/api.identificatransito.com.br');stage=Path('/root/it-pdv-dashboard-stage-20261105')
expected={'app/Services/PdvSalesService.php':'ff4108055301a41bceb36fb3348eb6124486f8ba8ed83fa033888ae347eb584d','app/Services/AdminPdvService.php':'823772f13bc5d2249703a40235ad3eeebe0ce94209ddd52da4f85a84a731c532'}
for name,sha in expected.items():assert hashlib.sha256((root/name).read_bytes()).hexdigest()==sha,'Source changed; stop'
result=subprocess.run(['php8.4',str(stage/'test-dashboard.php'),str(root),str(stage)],capture_output=True,text=True)
assert result.returncode==0,result.stdout[-1500:]+result.stderr[-1000:]
backup=Path('/root/it-before-pdv-dashboard-'+datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
files=list(expected)
meta=(root/'app/Services/AdminService.php').stat()
for name in files:
 source=stage/name;subprocess.run(['php8.4','-l',str(source)],check=True,stdout=subprocess.DEVNULL)
 target=root/name
 if target.exists():
  saved=backup/name;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(target,saved)
 fd,tmp=tempfile.mkstemp(prefix='.it-commission-',dir=target.parent)
 with os.fdopen(fd,'w') as f:f.write(source.read_text())
 os.chmod(tmp,0o644);os.chown(tmp,meta.st_uid,meta.st_gid);os.replace(tmp,target)
subprocess.run(['systemctl','reload','php8.4-fpm'],check=True)
print(json.dumps({'applied':files,'private_source_backup':str(backup),'tests':result.stdout.strip(),'database_writes':False,'payments_or_transfers':False}))
