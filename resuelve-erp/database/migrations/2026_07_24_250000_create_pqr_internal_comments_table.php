<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('pqr_internal_comments', function (Blueprint $table) { $table->id(); $table->foreignId('pqr_id')->constrained('pqrs')->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->text('body'); $table->timestamps(); }); }
    public function down(): void { Schema::dropIfExists('pqr_internal_comments'); }
};
