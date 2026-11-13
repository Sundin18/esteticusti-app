<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sloupce s údaji poplatníka a provozovny platnými v okamžiku tržby.
     *
     * @var list<string>
     */
    private const array COLUMNS = ['taxpayer_eic', 'premises_name', 'premises_address', 'unit_id', 'register_id'];

    /**
     * Spustí migraci.
     *
     * Dosavadní tržby převezmou údaje z aktuálního nastavení, jiné v době jejich vzniku neexistovaly.
     */
    public function up(): void
    {
        Schema::table('eet_sales', function (Blueprint $table) {
            $table->string('taxpayer_eic', 12)->nullable()->after('receipt_number');
            $table->string('premises_name')->nullable()->after('taxpayer_eic');
            $table->string('premises_address')->nullable()->after('premises_name');
            $table->unsignedInteger('unit_id')->nullable()->after('premises_address');
            $table->string('register_id', 20)->nullable()->after('unit_id');
        });

        $settings = DB::table('eet_settings')->first(self::COLUMNS);

        if ($settings !== null) {
            DB::table('eet_sales')->update((array) $settings);
        }

        Schema::table('eet_sales', function (Blueprint $table) {
            $table->string('taxpayer_eic', 12)->nullable(false)->change();
            $table->string('premises_name')->nullable(false)->change();
            $table->string('premises_address')->nullable(false)->change();
            $table->unsignedInteger('unit_id')->nullable(false)->change();
            $table->string('register_id', 20)->nullable(false)->change();
        });
    }

    /**
     * Vrátí migraci zpět.
     */
    public function down(): void
    {
        Schema::table('eet_sales', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
