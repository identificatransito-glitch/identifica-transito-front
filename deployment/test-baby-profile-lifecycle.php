<?php
// Isolated SQLite memory database; no SMTP, real jobs, or production writes.
$root = $argv[1] ?? '';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use App\Jobs\NotifyBabyProfileLifecycleJob;
use App\Mail\BabyProfileLifecycleMail;

if (!extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('SQLite required for isolated validation; no database modified.');
}
config(['database.default' => 'it_lifecycle_memory', 'database.connections.it_lifecycle_memory' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'cache.default' => 'array']);
DB::setDefaultConnection('it_lifecycle_memory');
$app['cache']->setDefaultDriver('array');
if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('Isolated database guard failed.');
}
Mail::fake(); Queue::fake();
Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('email'); $t->integer('type')->default(3); $t->string('gender')->nullable(); $t->date('date_of_birth')->nullable(); $t->softDeletes(); $t->timestamps(); });
Schema::create('vehicles', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->string('plate'); $t->integer('usage_profile'); $t->date('usage_profile_baby_date_of_birth')->nullable(); $t->softDeletes(); $t->timestamps(); });
Schema::create('notifications_drivers', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->unsignedBigInteger('driver_id'); $t->string('plate'); $t->integer('type'); $t->integer('read')->default(0); $t->softDeletes(); $t->timestamps(); });
$migration = require $root . '/database/migrations/2026_11_02_160000_add_system_event_tracking_to_notifications_drivers.php';
$migration->up();
$check = function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
DB::table('users')->insert([['id' => 1, 'name' => 'Pessoa de teste', 'email' => 'qa-lifecycle@example.com'], ['id' => 2, 'name' => 'Outra pessoa de teste', 'email' => 'qa-other@example.com']]);
foreach ([1 => [5,'2024-11-02'],2 => [5,'2024-11-03'],3 => [5,'2024-12-02'],4 => [5,'2024-12-03'],5 => [4,null],6 => [6,'2024-11-02'],7 => [5,null],8 => [5,'2027-11-02']] as $id => [$profile,$dob]) {
    DB::table('vehicles')->insert(['id'=>$id,'user_id'=>1,'plate'=>'QA'.str_pad((string)$id,5,'0',STR_PAD_LEFT),'usage_profile'=>$profile,'usage_profile_baby_date_of_birth'=>$dob]);
}
$check(NotifyBabyProfileLifecycleJob::expiryDate(Carbon::parse('2024-11-02 00:00:00','UTC'))->toDateString()==='2026-11-02', 'Calendar DATE must not shift with timezone');
Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00','America/Sao_Paulo'));
$exit = Artisan::call('vehicles:notify-baby-profile-expiring', ['--dry-run'=>true, '--as-of'=>'2026-11-02']);
$consoleOutput = Artisan::output();
$check($exit===0 && str_contains($consoleOutput,'"expiring":2') && str_contains($consoleOutput,'"expired":1'),'Boundary eligibility counts');
$check(count(Queue::getFacadeRoot()->pushedJobs())===0, 'Dry run queued nothing'); $check(Mail::sent(BabyProfileLifecycleMail::class)->count()===0, 'Dry run sent nothing');
$check(DB::table('notifications_drivers')->count()===0,'Dry run must not create notices');
(new NotifyBabyProfileLifecycleJob(1,1,'2026-11-02','expired'))->handle();
$check(DB::table('notifications_drivers')->where('type',6)->where('driver_id',1)->count()===1,'Expired panel notification');
$check(Mail::sent(BabyProfileLifecycleMail::class)->count()===1, 'Expected mail count 1');
(new NotifyBabyProfileLifecycleJob(1,1,'2026-11-02','expired'))->handle();
$check(DB::table('notifications_drivers')->count()===1,'Panel deduplication');
$check(Mail::sent(BabyProfileLifecycleMail::class)->count()===1, 'Expected mail count 1');
(new NotifyBabyProfileLifecycleJob(2,1,'2026-11-03','expiring'))->handle();
$check(DB::table('notifications_drivers')->where('type',5)->count()===1,'Advance panel notification');
$check(Mail::sent(BabyProfileLifecycleMail::class)->count()===2, 'Expected mail count 2');
// Future expiration and changed profile/owner/date must not leak stale messages.
(new NotifyBabyProfileLifecycleJob(4,1,'2026-12-03','expired'))->handle();
DB::table('vehicles')->where('id',2)->update(['usage_profile'=>4]);
(new NotifyBabyProfileLifecycleJob(2,1,'2026-11-03','expiring'))->handle();
DB::table('vehicles')->where('id',1)->update(['user_id'=>2]);
(new NotifyBabyProfileLifecycleJob(1,1,'2026-11-02','expired'))->handle();
DB::table('vehicles')->where('id',3)->update(['usage_profile_baby_date_of_birth'=>'2024-12-05']);
(new NotifyBabyProfileLifecycleJob(3,1,'2026-12-02','expiring'))->handle();
$check(Mail::sent(BabyProfileLifecycleMail::class)->count()===2, 'Expected mail count 2');
$check(DB::table('notifications_drivers')->count()===2,'Stale jobs must not create notifications');
// Leap-day birth expires at the end of February, with calendar years rather than 730 days.
DB::table('vehicles')->insert(['id'=>9,'user_id'=>1,'plate'=>'QA00009','usage_profile'=>5,'usage_profile_baby_date_of_birth'=>'2024-02-29']);
Carbon::setTestNow(Carbon::parse('2026-02-28 09:00:00','America/Sao_Paulo'));
(new NotifyBabyProfileLifecycleJob(9,1,'2026-02-28','expired'))->handle();
$check(Mail::sent(BabyProfileLifecycleMail::class)->count()===3, 'Expected mail count 3');
$check(DB::table('notifications_drivers')->where('plate','QA00009')->where('type',6)->count()===1,'Leap-day expiry');
$body=(new BabyProfileLifecycleMail('<unsafe>', '02/11/2026', 'expired','https://www.identificatransito.com.br/dashboard/veiculos/detalhes/1'))->render();
$check(str_contains($body,'&lt;unsafe&gt;') && !str_contains($body,'<unsafe>'),'Email escapes input');
$check(!str_contains($body,'02/11/2024'),'Email avoids birth-date disclosure');
Carbon::setTestNow();
echo "ISOLATED TESTS PASSED: boundaries, calendar leap year, panel/email delivery, duplicate suppression, stale-job guards, no production writes.\n";
