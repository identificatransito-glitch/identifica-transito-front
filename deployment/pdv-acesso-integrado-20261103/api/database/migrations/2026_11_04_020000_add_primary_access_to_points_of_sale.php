<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::table('points_of_sale', fn (Blueprint $t) => $t->unsignedBigInteger('access_user_id')->nullable()->unique());
        // Preserve an existing unambiguous administrator; never invent credentials or reassign accounts.
        $groups=DB::table('users')->where('type',2)->whereNull('deleted_at')->whereNotNull('pdv_id')->get(['id','pdv_id'])->groupBy('pdv_id');
        foreach($groups as $id=>$users) if($users->count()===1) DB::table('points_of_sale')->where('id',$id)->update(['access_user_id'=>$users->first()->id]);
    }
    public function down(): void { Schema::table('points_of_sale', function(Blueprint $t){$t->dropUnique(['access_user_id']);$t->dropColumn('access_user_id');}); }
};
