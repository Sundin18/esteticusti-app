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
        Schema::create('eet_certifikaty', function (Blueprint $table) {
            $table->id();
            $table->text('privatni_klic');
            $table->text('certifikat');
            $table->string('predmet');
            $table->dateTime('platnost_od');
            $table->dateTime('platnost_do');
            $table->boolean('aktivni')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_certifikaty');
    }
};
