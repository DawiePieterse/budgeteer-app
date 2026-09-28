<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A special project (for example a car rebuild) tracked on its own, outside the monthly budget.
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('name', 100);
            $table->bigInteger('budget_cents')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'name']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('person_id')->constrained()->nullOnDelete();
        });

        Schema::table('merchants', function (Blueprint $table) {
            // Everything from this merchant belongs to the project.
            $table->foreignId('project_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('projects');
    }
};
