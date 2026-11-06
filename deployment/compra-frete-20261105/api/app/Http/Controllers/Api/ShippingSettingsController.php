<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\ShippingPriceService;
use Illuminate\Http\Request;
class ShippingSettingsController extends Controller {
 public function show(ShippingPriceService $service) {return response()->json(['data'=>$service->settings()])->header('Cache-Control','private, no-store');}
 public function update(Request $request,ShippingPriceService $service) {
  abort_unless((int)$request->user()?->type===1,403);
  $data=$request->validate(['enabled'=>'required|boolean','fixed_amount'=>'required|numeric|min:0|max:9999.99|decimal:0,2']);
  return response()->json(['data'=>$service->save($data),'message'=>'Configuração de frete salva.']);
 }
}
