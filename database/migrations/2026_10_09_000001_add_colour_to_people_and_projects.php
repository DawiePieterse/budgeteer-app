<?php

use App\Support\OwnerColours;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['people', 'projects'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('colour', 10)->nullable()->after('name');
            });
        }

        // Existing people first (by when they were added), then projects, each household on its own.
        $colours = array_keys(OwnerColours::CHOICES);
        foreach (DB::table('households')->pluck('id') as $householdId) {
            $i = 0;
            foreach (['people', 'projects'] as $table) {
                foreach (DB::table($table)->where('household_id', $householdId)->orderBy('id')->pluck('id') as $id) {
                    DB::table($table)->where('id', $id)->update(['colour' => $colours[$i++ % count($colours)]]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['people', 'projects'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('colour');
            });
        }
    }
};
