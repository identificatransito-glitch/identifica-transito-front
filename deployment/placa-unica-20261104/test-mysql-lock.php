<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$config=config('database.connections.'.config('database.default'));
$a=DB::build($config);$b=DB::build($config);if($a->getDriverName()!=='mysql')throw new RuntimeException('Expected MySQL');
$key='it:qa-plate-lock:'.bin2hex(random_bytes(12));
try {
 if((int)$a->selectOne('SELECT GET_LOCK(?, 0) AS acquired',[$key])->acquired!==1)throw new RuntimeException('Initial lock failed');
 if((int)$b->selectOne('SELECT GET_LOCK(?, 0) AS acquired',[$key])->acquired!==0)throw new RuntimeException('Concurrent lock was not blocked');
 $a->selectOne('SELECT RELEASE_LOCK(?) AS released',[$key]);
 if((int)$b->selectOne('SELECT GET_LOCK(?, 0) AS acquired',[$key])->acquired!==1)throw new RuntimeException('Released lock unavailable');
 echo 'PASS MySQL: competing connections serialized; lock available after release; no data written.'.PHP_EOL;
} finally {$a->selectOne('SELECT RELEASE_LOCK(?) AS released',[$key]);$b->selectOne('SELECT RELEASE_LOCK(?) AS released',[$key]);}
