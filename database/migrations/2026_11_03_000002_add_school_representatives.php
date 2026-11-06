<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',function(Blueprint $t){$t->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();});
  Schema::table('school_returns',function(Blueprint $t){$t->foreignId('profile_confirmed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('profile_confirmed_at')->nullable();$t->json('profile_snapshot')->nullable();});
 }
 public function down():void {
  Schema::table('school_returns',function(Blueprint $t){$t->dropConstrainedForeignId('profile_confirmed_by');$t->dropColumn(['profile_confirmed_at','profile_snapshot']);});
  Schema::table('users',function(Blueprint $t){$t->dropConstrainedForeignId('school_id');});
 }
};
