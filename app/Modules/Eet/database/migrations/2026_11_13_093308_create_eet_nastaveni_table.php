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
        Schema::create('eet_nastaveni', function (Blueprint $table) {
            $table->id();
            $table->string('eic_popl', 12);
            $table->string('nazev_provozovny');
            $table->string('adresa_provozovny');
            $table->unsignedInteger('id_jednotky');
            $table->string('id_pokl', 20);
            $table->string('prostredi', 10)->default('test');
            $table->unsignedSmallInteger('mezni_doba_odezvy')->default(2);
            $table->unsignedBigInteger('posledni_porad_cis')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_nastaveni');
    }
};
