<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eet_zpravy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trzba_id')->constrained('eet_trzby')->cascadeOnDelete();
            $table->uuid('uuid_zpravy')->nullable();
            $table->dateTime('dat_odesl');
            $table->boolean('prvni_zaslani');
            $table->text('xml_pozadavku')->nullable();
            $table->text('xml_odpovedi')->nullable();
            $table->integer('kod_chyby')->nullable();
            $table->text('chyba')->nullable();
            $table->json('varovani')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_zpravy');
    }
};
