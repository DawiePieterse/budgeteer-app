<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('name', 100);
            // A word or words in the transaction description (or its merchant key), for example "PPS".
            $table->string('match_text', 100);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount_cents'); // expected amount, positive
            $table->boolean('amount_varies')->default(false);
            $table->string('frequency', 10); // weekly, monthly, yearly
            $table->unsignedTinyInteger('day'); // day of month, or ISO weekday (1 = Monday) when weekly
            $table->unsignedTinyInteger('month')->nullable(); // for yearly
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Occurrences marked by hand: skipped this time, or paid somewhere Budgeteer does not see.
        Schema::create('recurring_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_payment_id')->constrained()->cascadeOnDelete();
            $table->date('due_on');
            $table->string('status', 10); // skipped, paid
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->unique(['recurring_payment_id', 'due_on']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_payment_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_payment_id');
        });
        Schema::dropIfExists('recurring_marks');
        Schema::dropIfExists('recurring_payments');
    }
};
