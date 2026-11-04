<?php
$root=$argv[1];$stage=$argv[2];require $root.'/vendor/autoload.php';
foreach(['VehiclePlateGuard','VehicleService','TagPurchaseService'] as $class)require $stage.'/app/Services/'.$class.'.php';
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Queue,Mail,Http};use Illuminate\Database\Schema\Blueprint;use App\Models\{User,Vehicle};use App\Services\{VehicleService,VehiclePlateGuard,TagPurchaseService};
config(['database.default'=>'plate_memory','database.connections.plate_memory'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'cache.default'=>'array']);DB::setDefaultConnection('plate_memory');if(DB::connection()->getDatabaseName()!==':memory:')throw new RuntimeException('Memory guard failed');Queue::fake();Mail::fake();Http::preventStrayRequests();
Schema::create('users',function(Blueprint $t){$t->id();foreach(['name','email','password','gender','birth_date'] as $c)$t->string($c)->nullable();$t->integer('type');$t->softDeletes();$t->timestamps();});
Schema::create('vehicles',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('plate');foreach(['name','year','color','register_state','register_city','usage_profile_baby_date_of_birth'] as $c)$t->string($c)->nullable();$t->integer('type')->nullable();$t->integer('usage_profile')->nullable();$t->timestamps();});
$n=0;$ok=function($test,$msg)use(&$n){if(!$test)throw new RuntimeException($msg);$n++;};$reject=function(callable $fn,$field)use($ok){try{$fn();}catch(Illuminate\Validation\ValidationException $e){$ok(isset($e->errors()[$field]),'expected field '.$field);return;}throw new RuntimeException('Expected validation failure');};
$u=User::create(['name'=>'QA','email'=>'owner@example.test','type'=>3,'gender'=>'F']);$other=User::create(['name'=>'QA2','email'=>'other@example.test','type'=>3]);$svc=new VehicleService;
$v=$svc->create(['plate'=>' abc-1234 ','type'=>1],$u);$ok($v->plate==='ABC1234','plate normalized');$ok(Vehicle::count()===1,'single initial vehicle');Queue::assertPushed(App\Jobs\FillVehicleInfoJob::class,1);$ok(true,'enrichment queued');
foreach(['ABC1234','abc-1234',' A B C 1 2 3 4 '] as $p)$reject(fn()=>$svc->create(['plate'=>$p,'type'=>1],$u),'plate');
$reject(fn()=>$svc->create(['plate'=>'ABC1234','type'=>2],$other),'plate');$ok(Vehicle::count()===1,'duplicate attempt creates no record');Queue::assertPushed(App\Jobs\FillVehicleInfoJob::class,1);$ok(true,'duplicate queues no enrichment');
$v=$svc->update($v,['plate'=>'abc-1234','name'=>'Updated'],$u);$ok($v->name==='Updated'&&$v->plate==='ABC1234','same vehicle may update');
$second=$svc->create(['plate'=>'DEF4G56','type'=>2],$u);$ok($second->plate==='DEF4G56','different Mercosul plate allowed');$reject(fn()=>$svc->update($second,['plate'=>'abc1234'],$u),'plate');$ok($second->fresh()->plate==='DEF4G56','failed update preserved original plate');
try{$svc->update($v,['name'=>'bad'],$other);throw new RuntimeException('Unauthorized update accepted');}catch(Illuminate\Auth\Access\AuthorizationException $e){$ok(true,'owner permission retained');}
$svc->update($v,['usage_profile'=>4],$u);$id=$v->id;$svc->update($v,['usage_profile'=>5,'usage_profile_baby_date_of_birth'=>now()->subMonths(3)->format('Y-m-d')],$u);$ok($v->id===$id&&Vehicle::count()===2,'pregnant to baby retains registration');
$legacy=Vehicle::create(['plate'=>'abc-1234','user_id'=>$u->id]);$reject(fn()=>VehiclePlateGuard::ensureSingleRegistration($v),'vehicles');$reject(fn()=>$svc->update($v,['name'=>'duplicate'],$u),'plate');$ok(Vehicle::count()===3,'legacy records not deleted');$svc->update($legacy,['plate'=>'GHI5J67'],$u);VehiclePlateGuard::ensureSingleRegistration($v);$ok(true,'correcting duplicate resolves conflict');
$buyer=new TagPurchaseService;$reject(fn()=>$buyer->checkout(['vehicles'=>[['id'=>$v->id],['id'=>$v->id]]],$u),'vehicles');$reject(fn()=>$buyer->previewCoupon(['vehicles'=>[['id'=>$v->id],['id'=>$v->id]]],$u),'vehicles');
Vehicle::create(['plate'=>' ABC1234 ','user_id'=>$other->id]);$reject(fn()=>$buyer->checkout(['vehicles'=>[['id'=>$v->id]]],$u),'vehicles');$reject(fn()=>$buyer->previewCoupon(['vehicles'=>[['id'=>$v->id]]],$u),'vehicles');
$reject(fn()=>$svc->create(['plate'=>'INVALID'],$u),'plate');
try{VehiclePlateGuard::save('JKL6M78',null,fn($p)=>throw new RuntimeException('rollback'));}catch(RuntimeException $e){}$after=$svc->create(['plate'=>'JKL6M78'],$u);$ok($after->plate==='JKL6M78','failure does not poison later writes');
Mail::assertNothingSent();$ok(true,'no email sent');
echo 'PASS '.$n.' isolated unique plate checks; memory only, fake mail/queue, no real payment.'.PHP_EOL;
