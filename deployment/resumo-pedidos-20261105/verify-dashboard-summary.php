<?php
$root=$argv[1];$stage=$argv[2]??null;require $root.'/vendor/autoload.php';if($stage)require $stage.'/app/Services/AdminService.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$result=(new App\Services\AdminService)->dashboard(now()->startOfYear()->format('Y-m-d'),now()->format('Y-m-d'));
$orders=$result['latest_orders'];$pickup=0;$free=0;$withShipment=0;
foreach($orders as $order){if(!$order->relationLoaded('shipment')||!$order->relationLoaded('tag'))throw new RuntimeException('Summary relations missing');if($order->pickup_point!==null&&$order->delivery===null)$pickup++;if($order->shipment)$withShipment++;if($order->mp_payment_status==='approved'&&(float)$order->price_total===0.0&&$order->mp_payment_status_detail==='coupon_full_discount')$free++;}
echo json_encode(['recent_orders'=>$orders->count(),'pickup_points_included'=>$pickup,'shipments_included'=>$withShipment,'approved_coupon_orders'=>$free,'read_only'=>true,'no_payment_changes'=>true]).PHP_EOL;
