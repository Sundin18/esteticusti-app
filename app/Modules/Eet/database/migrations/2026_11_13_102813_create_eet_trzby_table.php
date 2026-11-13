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
        Schema::create('eet_trzby', function (Blueprint $table) {
            $table->id();
            $table->string('porad_cis', 25)->unique();
            $table->dateTime('dat_trzby')->index();
            $table->decimal('celk_trzba', 10, 2);
            $table->string('stav', 20)->default('ceka')->index();
            $table->string('pok', 39)->nullable();
            $table->boolean('testovaci')->default(false);
            $table->foreignId('user_id')->constrained();
            $table->nullableMorphs('zdroj');
            $table->foreignId('storno_trzby_id')->nullable()->constrained('eet_trzby');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_trzby');
    }
};
