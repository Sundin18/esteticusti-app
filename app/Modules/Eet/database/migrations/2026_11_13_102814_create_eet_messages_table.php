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
        Schema::create('eet_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('eet_sales')->cascadeOnDelete();
            $table->uuid('message_uuid')->nullable();
            $table->dateTime('sent_at');
            $table->boolean('is_first_attempt');
            $table->text('request_xml')->nullable();
            $table->text('response_xml')->nullable();
            $table->integer('error_code')->nullable();
            $table->text('error')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Vrátí migraci zpět.
     */
    public function down(): void
    {
        Schema::dropIfExists('eet_messages');
    }
};
