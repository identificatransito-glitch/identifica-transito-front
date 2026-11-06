<?php
namespace App\Services;
use App\Models\ShippingSetting;
use Illuminate\Validation\ValidationException;
class ShippingPriceService {
 public function settings(): array {
  $settings=ShippingSetting::query()->find(1);
  return ['enabled'=>(bool)($settings?->enabled ?? false),'fixed_amount'=>(float)($settings?->fixed_amount ?? 0),'mode'=>'fixed'];
 }
 public function save(array $data): array {
  ShippingSetting::query()->updateOrCreate(['id'=>1],['enabled'=>(bool)$data['enabled'],'fixed_amount'=>round((float)$data['fixed_amount'],2)]);
  return $this->settings();
 }
 public function priceFor(array $payload): float {
  $settings=$this->settings();
  $amount=!empty($payload['is_delivery']) && $settings['enabled'] ? $settings['fixed_amount'] : 0.0;
  if(!is_numeric($payload['shipping_price'] ?? 0) || (int)round((float)($payload['shipping_price'] ?? 0)*100)!==(int)round($amount*100)) {
   throw ValidationException::withMessages(['shipping_price'=>'O valor do frete foi atualizado. Recarregue a compra para conferir o total antes de pagar.']);
  }
  return $amount;
 }
 public function paymentTotal(float $itemsTotal,float $shipping): float {return round($itemsTotal+$shipping,2);}
}
