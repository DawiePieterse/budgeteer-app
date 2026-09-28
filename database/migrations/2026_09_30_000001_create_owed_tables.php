<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // What the person owed on a date, typed in once; transactions charged to them count from the next day.
            $table->bigInteger('opening_balance_cents')->default(0)->after('phone');
            $table->date('opening_balance_on')->nullable()->after('opening_balance_cents');
            // Text in the description of a payment from this person, for example "DEWAN", to offer it as a repayment.
            $table->string('payment_reference', 100)->nullable()->after('opening_balance_on');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Charged to someone outside the household (for example their card on our account): owed, not spending.
            $table->foreignId('person_id')->nullable()->after('category_id')->constrained('people')->nullOnDelete();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->bigInteger('amount_cents');
            $table->date('received_on');
            // The payment into one of our accounts, when it came by EFT.
            $table->foreignId('transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['opening_balance_cents', 'opening_balance_on', 'payment_reference']);
        });
    }
};
