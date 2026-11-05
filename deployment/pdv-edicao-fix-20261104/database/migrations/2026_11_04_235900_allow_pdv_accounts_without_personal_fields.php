<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        // Client registration still requires these values in StoreUserRequest.
        // PDV administrators are business logins and must not receive invented identity data.
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf',14)->nullable()->change();
            $table->date('birth_date')->nullable()->change();
            $table->string('phone',20)->nullable()->change();
        });
    }
    public function down(): void {
        if (DB::table('users')->whereNull('cpf')->orWhereNull('birth_date')->orWhereNull('phone')->exists()) {
            throw new RuntimeException('Cannot restore required personal fields while business accounts have null values. No data was modified.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf',14)->nullable(false)->change();
            $table->date('birth_date')->nullable(false)->change();
            $table->string('phone',20)->nullable(false)->change();
        });
    }
};
