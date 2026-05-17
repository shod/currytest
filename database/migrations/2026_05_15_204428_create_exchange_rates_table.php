<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_code', 3);
            $table->foreignId('target_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('rate', total: 20, places: 10);
            $table->dateTime('fetched_at');
            $table->timestamps();

            $table->unique(['base_code', 'target_currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
