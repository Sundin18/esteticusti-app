<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spustí migraci.
     */
    public function up(): void
    {
        Schema::create('eet_sales', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 25)->unique();
            $table->dateTime('sold_at')->index();
            $table->decimal('total_amount', 10, 2);
            $table->string('payment_method', 10);
            $table->string('status', 20)->default('pending')->index();
            $table->string('pok', 39)->nullable();
            $table->boolean('is_test')->default(false);
            $table->foreignId('user_id')->constrained();
            $table->nullableMorphs('source');
            $table->foreignId('cancelled_sale_id')->nullable()->constrained('eet_sales');
            $table->timestamps();
        });
    }

    /**
     * Vrátí migraci zpět.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_sales');
    }
};
