<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Spustí migraci.
     *
     * Klienti se mažou jen jako soft delete, takže tržba o svého klienta nikdy nepřijde.
     */
    public function up(): void
    {
        Schema::table('eet_sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained();
        });
    }

    /**
     * Vrátí migraci zpět.
     */
    public function down(): void
    {
        Schema::table('eet_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
