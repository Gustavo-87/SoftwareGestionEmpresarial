<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->string('lot_number', 100);
            $table->date('expiration_date');
            $table->unsignedInteger('quantity')->default(0);

            $table->timestamps();

            $table->unique(
                ['product_id', 'lot_number'],
                'lots_product_lot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};