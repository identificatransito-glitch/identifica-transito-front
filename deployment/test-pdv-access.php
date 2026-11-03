<?php
$root=$argv[1]; $module=$argv[2]; require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,Schema,Hash,Queue,Mail};
use Illuminate\Database\Schema\Blueprint;
use App\Models\User;
use App\Http\Requests\StorePdvRequest;
use App\Http\Controllers\Api\PdvAccessController;
config(['database.default'=>'it_pdv_memory','database.connections.it_pdv_memory'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'cache.default'=>'array']);
DB::setDefaultConnection('it_pdv_memory');if(DB::connection()->getDriverName()!=='sqlite'||DB::connection()->getDatabaseName()!==':memory:')throw new RuntimeException('Memory guard failed');
Queue::fake();Mail::fake();require $module.'/app/Http/Controllers/Api/PdvAccessController.php';
Schema::create('points_of_sale',function(Blueprint $t){$t->id();foreach(['name','cnpj','address','phone','responsible','map_link'] as $c)$t->string($c)->nullable();$t->boolean('active')->default(true);$t->softDeletes();$t->timestamps();});
Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->string('email')->unique();$t->string('password');$t->integer('type');$t->unsignedBigInteger('pdv_id')->nullable();$t->softDeletes();$t->timestamps();});
$super=(new User)->forceFill(['id'=>100,'type'=>1]);$client=(new User)->forceFill(['id'=>101,'type'=>3]);
$make=function(array $data,User $user)use($app){$r=StorePdvRequest::create('/qa','POST',$data);$r->setContainer($app);$r->setRedirector($app->make('redirect'));$r->setUserResolver(fn()=>$user);$r->validateResolved();return $r;};
$data=['name'=>'PDV de teste','responsible'=>'Responsável de exemplo','active'=>true,'access_name'=>'Responsável de exemplo','access_email'=>'PDV-QA@example.com','password'=>'QA-test-12345','password_confirmation'=>'QA-test-12345','type'=>1,'pdv_id'=>999];
$controller=new PdvAccessController;$service=app(App\Services\PdvService::class);$checks=0;
$ok=function($v,$label)use(&$checks){if(!$v)throw new RuntimeException($label);$checks++;};
$denied=function(callable $call,string $label)use($ok){try{$call();throw new RuntimeException('Unexpected success '.$label);}catch(Illuminate\Validation\ValidationException|Illuminate\Auth\Access\AuthorizationException $e){$ok(true,$label);}catch(Symfony\Component\HttpKernel\Exception\HttpException $e){$ok($e->getStatusCode()===403,$label);}};
$denied(fn()=>$controller->store($make($data,$client),$service),'customer cannot create PDV staff');
foreach([['password_confirmation','different'],['password','short'],['access_email','bad-email']] as [$key,$value]){$bad=$data;$bad[$key]=$value;$denied(fn()=>$controller->store($make($bad,$super),$service),'invalid '.$key);}
$ok(DB::table('users')->count()===0&&DB::table('points_of_sale')->count()===0,'validation failure creates no records');
$result=$controller->store($make($data,$super),$service)->getData(true);$user=User::query()->firstOrFail();
$ok($user->type===2,'role cannot be escalated by payload');$ok($user->pdv_id===DB::table('points_of_sale')->value('id'),'PDV association is server assigned');
$ok($user->email==='pdv-qa@example.com','normalized email');$ok(Hash::check($data['password'],$user->password)&&$user->password!==$data['password'],'password is hashed');
$ok(!str_contains(json_encode($result),$data['password'])&&!str_contains(json_encode($result),$user->password),'no credential in response');
$duplicate=$data;$duplicate['name']='Outro ponto';$denied(fn()=>$controller->store($make($duplicate,$super),$service),'duplicate account is not reassigned');
$ok(DB::table('points_of_sale')->count()===1&&DB::table('users')->count()===1,'duplicate email leaves no orphan point');
User::creating(fn()=>throw new RuntimeException('simulated account storage failure'));
$failure=$data;$failure['access_email']='other-qa@example.com';try{$controller->store($make($failure,$super),$service);}catch(RuntimeException $e){if($e->getMessage()!=='simulated account storage failure')throw $e;}
$ok(DB::table('points_of_sale')->count()===1&&DB::table('users')->count()===1,'transaction rolls back point when account creation fails');
echo 'PASS '.$checks.' isolated PDV access checks; no real users, mail, or production writes.'.PHP_EOL;
