<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::table('notification_records',function(Blueprint $table){
  $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
  $table->foreignId('school_return_id')->nullable()->constrained('school_returns')->nullOnDelete();
  $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
  $table->string('subject')->nullable();
 }); }
 public function down():void { Schema::table('notification_records',function(Blueprint $table){ $table->dropConstrainedForeignId('school_id');$table->dropConstrainedForeignId('school_return_id');$table->dropConstrainedForeignId('sender_id');$table->dropColumn('subject'); }); }
};
