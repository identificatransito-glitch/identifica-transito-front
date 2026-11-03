<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('points_of_sale', fn (Blueprint $table) => $table->decimal('commission_percentage', 5, 2)->nullable());
        // No rate is guessed. Existing user fields remain archived, but no longer drive calculations.
    }
    public function down(): void {
        Schema::table('points_of_sale', fn (Blueprint $table) => $table->dropColumn('commission_percentage'));
    }
};
