from pathlib import Path
from datetime import datetime
import os,json,hashlib,subprocess,shutil,tempfile,gzip
root=Path('/home/identificatransito-api/htdocs/api.identificatransito.com.br');stage=Path('/root/it-shipping-stage-20261105')
expected={'app/Services/TagPurchaseService.php':'b7b22b7f68196e6227a1f2b9894264e6c57ffbc83622e7632b998d27617cae53','routes/api.php':'c19ce6636b4a96c460b0fa5670ff5614fc33955261dd26809586ed1623d9bc5a'}
for name,sha in expected.items():assert hashlib.sha256((root/name).read_bytes()).hexdigest()==sha,'Live source changed; stop'
new=['app/Models/ShippingSetting.php','app/Services/ShippingPriceService.php','app/Http/Controllers/Api/ShippingSettingsController.php','routes/shipping.php','database/migrations/2026_11_05_230000_create_shipping_settings_table.php']
for name in new:assert not (root/name).exists(),'New file already exists; inspect first'
r=subprocess.run(['php8.4',str(stage/'test-shipping.php'),str(root),str(stage)],capture_output=True,text=True);assert r.returncode==0,r.stdout[-1000:]+r.stderr[-1000:]
def php(code):
 snippet="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"+code
 result=subprocess.run(['php8.4','-r',snippet],cwd=root,capture_output=True,text=True);assert result.returncode==0,'Private PHP preflight failed';return result.stdout
assert php("echo Illuminate\\Support\\Facades\\Schema::hasTable('shipping_settings')?'1':'0';").strip()=='0','Settings table exists; inspect first'
backup=Path('/root/it-before-shipping-'+datetime.now().strftime('%Y%m%d-%H%M%S'));backup.mkdir(mode=0o700)
for name in expected:
 saved=backup/name;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(root/name,saved)
# Database backup stays private on the server; credentials are captured only in memory.
connection=json.loads(php("echo json_encode(config('database.connections.'.config('database.default')));"));assert connection['driver']=='mysql','Unexpected database driver'
def option(value):return '"'+str(value).replace('\\','\\\\').replace('"','\\"').replace('\n','\\n')+'"'
cnf=backup/'mysql-client.cnf';cnf.write_text('[client]\n'+''.join(key+'='+option(connection[source])+'\n' for key,source in [('host','host'),('port','port'),('user','username'),('password','password')]));os.chmod(cnf,0o600)
try:
 dump=shutil.which('mariadb-dump') or shutil.which('mysqldump');assert dump,'Database backup tool unavailable'
 process=subprocess.Popen([dump,'--defaults-extra-file='+str(cnf),'--single-transaction','--quick','--no-tablespaces',connection['database']],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
 with gzip.open(backup/'database.sql.gz','wb') as target:shutil.copyfileobj(process.stdout,target)
 process.stderr.read();assert process.wait()==0,'Private database backup failed'
 os.chmod(backup/'database.sql.gz',0o600)
finally:cnf.unlink(missing_ok=True)
meta=(root/'app/Services/TagPurchaseService.php').stat()
def apply(name,content):
 target=root/name;target.parent.mkdir(parents=True,exist_ok=True);fd,tmp=tempfile.mkstemp(prefix='.it-shipping-',dir=target.parent)
 with os.fdopen(fd,'w') as f:f.write(content)
 os.chmod(tmp,0o644);os.chown(tmp,meta.st_uid,meta.st_gid);os.replace(tmp,target)
for name in new:
 subprocess.run(['php8.4','-l',str(stage/name)],check=True,stdout=subprocess.DEVNULL);apply(name,(stage/name).read_text())
subprocess.run(['php8.4','artisan','migrate','--path=database/migrations/2026_11_05_230000_create_shipping_settings_table.php','--force'],cwd=root,check=True,stdout=subprocess.DEVNULL)
apply('routes/api.php',(root/'routes/api.php').read_text().rstrip()+"\nrequire __DIR__ . '/shipping.php';\n")
apply('app/Services/TagPurchaseService.php',(stage/'app/Services/TagPurchaseService.php').read_text())
subprocess.run(['php8.4','artisan','route:clear'],cwd=root,check=True,stdout=subprocess.DEVNULL)
settings=json.loads(php("echo json_encode((new App\\Services\\ShippingPriceService)->settings());"));assert settings=={'enabled':False,'fixed_amount':0,'mode':'fixed'},'Unexpected initial shipping state'
subprocess.run(['systemctl','reload','php8.4-fpm'],check=True)
print(json.dumps({'applied':True,'shipping_enabled':False,'shipping_amount':0,'private_backup':str(backup),'database_backup_sha256':hashlib.sha256((backup/'database.sql.gz').read_bytes()).hexdigest(),'tests':r.stdout.strip(),'payment_calls':False}))
