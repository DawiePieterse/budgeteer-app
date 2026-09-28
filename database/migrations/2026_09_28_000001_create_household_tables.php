<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Day of the month a budget period starts, for example 25 for payday on the 25th.
            $table->unsignedTinyInteger('period_start_day')->default(1);
            // Names that payments between the household's own accounts carry, for example "J SMITH",
            // one per line. A payment in or out whose description starts with one of them is a transfer.
            $table->text('own_account_names')->nullable();
            $table->timestamps();
        });

        // Google sign-in only: a user exists before first sign-in, which is what the allowlist is.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('google_id')->nullable()->unique();
            $table->string('avatar_url', 500)->nullable();
            $table->rememberToken();
            $table->timestamp('last_signed_in_at')->nullable();
            $table->timestamps();
        });

        // People outside the household who can owe money, for example a son with a card on the account.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('bank', 30);
            $table->string('kind', 20);
            $table->string('name');
            $table->string('number_ending', 4);
            $table->bigInteger('statement_balance_cents')->nullable();
            $table->date('statement_balance_on')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'bank', 'number_ending']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('account_id')->constrained();
            $table->string('number_ending', 4);
            $table->string('holder_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            // Every transaction on this card is owed by this person and stays out of the budget.
            $table->foreignId('charge_to_person_id')->nullable()->constrained('people');
            $table->timestamps();
            $table->unique(['account_id', 'number_ending']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('name');
            $table->string('kind', 10); // expense or income
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['household_id', 'name']);
        });

        // Merchant memory: the last category chosen for a cleaned merchant name.
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('key', 100);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('times_confirmed')->default(0);
            $table->timestamps();
            $table->unique(['household_id', 'key']);
        });

        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->date('period_from');
            $table->date('period_to');
            $table->bigInteger('opening_cents');
            $table->bigInteger('closing_cents');
            $table->unsignedInteger('lines');
            $table->unsignedInteger('added');
            $table->unsignedInteger('already_there');
            $table->char('fingerprint', 64);
            $table->timestamps();
            $table->unique(['household_id', 'fingerprint']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('card_id')->nullable()->constrained();
            $table->string('source', 10); // statement, email or manual
            $table->foreignId('statement_import_id')->nullable()->constrained();
            $table->date('posted_on');
            $table->string('description');
            $table->string('bank_type')->nullable();
            $table->string('merchant_key', 100)->index();
            $table->bigInteger('amount_cents'); // negative is money out
            $table->char('currency', 3)->default('ZAR');
            $table->string('kind', 15);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_transfer')->default(false);
            $table->foreignId('transfer_pair_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->bigInteger('balance_after_cents')->nullable();
            $table->unsignedInteger('line_on_statement')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['household_id', 'posted_on']);
            $table->index(['account_id', 'posted_on', 'amount_cents']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('statement_imports');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('people');
        Schema::dropIfExists('users');
        Schema::dropIfExists('households');
    }
};
