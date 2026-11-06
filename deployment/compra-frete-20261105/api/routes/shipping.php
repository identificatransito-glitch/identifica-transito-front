<?php
use App\Http\Controllers\Api\ShippingSettingsController;
use Illuminate\Support\Facades\Route;
Route::middleware('auth:sanctum')->group(function () {
 Route::get('/shipping-settings',[ShippingSettingsController::class,'show']);
 Route::get('/admin/shipping-settings',[ShippingSettingsController::class,'show'])->middleware('super.admin');
 Route::put('/admin/shipping-settings',[ShippingSettingsController::class,'update'])->middleware('super.admin');
});
