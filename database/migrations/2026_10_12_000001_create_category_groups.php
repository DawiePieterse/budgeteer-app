<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The household's own groups of budget lines ("Home and bills", "Car"), for the desktop Overview.
        Schema::create('category_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('name', 60);
            $table->string('colour', 10);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['household_id', 'name']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('category_group_id')->nullable()->after('kind')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_group_id');
        });
        Schema::dropIfExists('category_groups');
    }
};
