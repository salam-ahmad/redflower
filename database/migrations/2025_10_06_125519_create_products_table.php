<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('barcode')->unique()->nullable();
            $table->text('description')->nullable();
            $table->double('buy_price')->default(0);
            $table->double('sell_price')->default(0);
            $table->foreignId('currency_id')->constrained()->onDelete('restrict');
            $table->integer('stock_quantity')->default(0);
            $table->integer('min_stock_alert')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
