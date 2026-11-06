<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('shipping_settings',function(Blueprint $table){$table->unsignedTinyInteger('id')->primary();$table->boolean('enabled')->default(false);$table->decimal('fixed_amount',8,2)->default(0);$table->timestamps();}); }
 public function down(): void { Schema::dropIfExists('shipping_settings'); }
};
