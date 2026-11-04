from pathlib import Path
import os,sys,json,hashlib,subprocess,shutil,tempfile
from datetime import datetime
root=Path('/home/identificatransito-api/htdocs/api.identificatransito.com.br')
stage=Path('/root/it-pdv-account-stage-20261103');stage.mkdir(mode=0o700,exist_ok=True)
files={str(p.relative_to(Path(__file__).parent/'api')):p.read_text() for p in (Path(__file__).parent/'api').rglob('*.php')}
files['test-integrated-pdv.php']=(Path(__file__).parent/'test-integrated-pdv.php').read_text()
originals=json.loads('{"app/Models/Pdv.php": "391f6753633db110baf220c26b31f7e477260f88a701679aef5f62374cd9fa2b", "app/Http/Resources/PdvResource.php": "49ae7c662d7c3b62a1658f2d6feb6a7573ee2752bd74f7504475663ea4a6f5f7", "app/Http/Requests/StorePdvRequest.php": "b5984fcf3767784ba53b97828a203680ce4cbf65eeae985bd71a9e4d7e6f4f99", "app/Http/Requests/UpdatePdvRequest.php": "4835c7610d4f045a1ab4653ba1932918c90fff36bed8d87f63fd61543eae13de", "app/Http/Requests/Admin/StoreAdminUserRequest.php": "52d86e1f9c2d89c285c564d65be07331d1450cd020df67a035b0060419547f34", "app/Http/Requests/Admin/UpdateAdminUserRequest.php": "abed44c856b955115d3b38162f22a2661d7a868eda8d121978d18fb7d7b1956c", "app/Http/Controllers/Api/PdvAccessController.php": "89f9b92ecf56bea278af6931a21608a741045eadbb4c677b79deb7de073d1dff", "app/Services/PdvService.php": "b481dd815d5c6100947af82602af588b052871d10eb3723e1c2b273457d48bf8", "app/Services/AdminService.php": "ad4da53988568e6ff3bf8bb538fa7614e6ca98b16500326d781d55dfab6a03e9"}')
for name,source in files.items():
 p=stage/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(source);subprocess.run(['php8.4','-l',str(p)],check=True,stdout=subprocess.DEVNULL)
print('STAGED',stage)
if '--apply' not in sys.argv:sys.exit(0)
for name,sha in originals.items():
 assert hashlib.sha256((root/name).read_bytes()).hexdigest()==sha,'Live source changed: '+name
backup=Path('/root/it-before-pdv-account-'+datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
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
