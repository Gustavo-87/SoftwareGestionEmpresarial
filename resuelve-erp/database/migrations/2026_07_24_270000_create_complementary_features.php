<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',function(Blueprint $t){$t->string('tower',50)->nullable();$t->string('unit',50)->nullable();});
  Schema::table('pqrs',function(Blueprint $t){$t->timestamp('last_reminder_at')->nullable();});
  Schema::create('pqr_tags',function(Blueprint $t){$t->id();$t->string('name',60)->unique();$t->string('color',7)->default('#1f6b57');$t->timestamps();});
  Schema::create('pqr_pqr_tag',function(Blueprint $t){$t->foreignId('pqr_id')->constrained('pqrs')->cascadeOnDelete();$t->foreignId('pqr_tag_id')->constrained('pqr_tags')->cascadeOnDelete();$t->primary(['pqr_id','pqr_tag_id']);});
  Schema::create('response_templates',function(Blueprint $t){$t->id();$t->string('name',100);$t->string('subject',150)->nullable();$t->text('body');$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();});
  Schema::create('satisfaction_surveys',function(Blueprint $t){$t->id();$t->foreignId('pqr_id')->unique()->constrained('pqrs')->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->unsignedTinyInteger('rating');$t->text('comment')->nullable();$t->timestamps();});
  Schema::create('automation_rules',function(Blueprint $t){$t->id();$t->string('name',100);$t->foreignId('tipo_pqr_id')->nullable()->constrained('tipo_pqrs')->cascadeOnDelete();$t->foreignId('assign_to_id')->nullable()->constrained('users')->nullOnDelete();$t->string('set_status',30)->default('en_revision');$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('audit_logs',function(Blueprint $t){$t->id();$t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$t->string('action',80);$t->string('auditable_type')->nullable();$t->unsignedBigInteger('auditable_id')->nullable();$t->string('ip_address',45)->nullable();$t->json('metadata')->nullable();$t->timestamps();$t->index(['auditable_type','auditable_id']);});
 }
 public function down(): void {Schema::dropIfExists('audit_logs');Schema::dropIfExists('automation_rules');Schema::dropIfExists('satisfaction_surveys');Schema::dropIfExists('response_templates');Schema::dropIfExists('pqr_pqr_tag');Schema::dropIfExists('pqr_tags');Schema::table('pqrs',fn(Blueprint $t)=>$t->dropColumn('last_reminder_at'));Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['tower','unit']));}
};
