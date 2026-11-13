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
        Schema::create('eet_settings', function (Blueprint $table) {
            $table->id();
            $table->string('taxpayer_eic', 12);
            $table->string('premises_name');
            $table->string('premises_address');
            $table->unsignedInteger('unit_id');
            $table->string('register_id', 20);
            $table->string('environment', 10)->default('test');
            $table->unsignedSmallInteger('response_timeout')->default(2);
            $table->unsignedBigInteger('last_receipt_number')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Vrátí migraci zpět.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_settings');
    }
};
