<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->json('startup_commands')->after('startup')->nullable();
        });

        DB::table('eggs')->select(['id', 'startup'])->cursor()->each(function ($egg) {
            $startup = $egg->startup;

            DB::table('eggs')->where('id', $egg->id)->update(['startup_commands' => [$startup => $startup]]);
        });

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('startup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eggs', function (Blueprint $table) {
            $table->text('startup')->after('startup_commands');
        });

        DB::table('eggs')->select(['id', 'startup_commands'])->cursor()->each(function ($egg) {
            DB::table('eggs')->where('id', $egg->id)->update([
                'startup' => Arr::first(json_decode($egg->startup_commands, true, 512, JSON_THROW_ON_ERROR)),
            ]);
        });

        Schema::table('eggs', function (Blueprint $table) {
            $table->dropColumn('startup_commands');
        });
    }
};
