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
        Schema::create('eet_certificates', function (Blueprint $table) {
            $table->id();
            $table->text('private_key');
            $table->text('certificate');
            $table->string('subject');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Vrátí migraci zpět.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_certificates');
    }
};
