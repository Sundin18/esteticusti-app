<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing names are split at the first space: the first word becomes the first name.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('firstname')->default('')->after('id');
            $table->string('lastname')->default('')->after('firstname');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'firstname' => Str::before($user->name, ' '),
                'lastname' => Str::contains($user->name, ' ') ? Str::after($user->name, ' ') : '',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('firstname')->default(null)->change();
            $table->string('lastname')->default(null)->change();
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->default('')->after('id');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'name' => trim($user->firstname.' '.$user->lastname),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->default(null)->change();
            $table->dropColumn(['firstname', 'lastname']);
        });
    }
};
