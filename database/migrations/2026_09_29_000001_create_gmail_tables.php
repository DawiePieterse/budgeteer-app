<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gmail_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('email');
            $table->text('refresh_token');
            $table->text('access_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            $table->string('label_id')->nullable();
            $table->string('history_id')->nullable();
            $table->string('status', 20)->default('active'); // active, label_missing, needs_relink, error
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['household_id', 'email']);
        });

        // Every email looked at, so none is read twice. The email body itself is not kept.
        Schema::create('ingested_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->foreignId('gmail_connection_id')->constrained()->cascadeOnDelete();
            $table->string('gmail_message_id', 64);
            $table->string('sender')->nullable();
            $table->string('subject', 300)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('status', 20); // added, matched, ignored, unrecognised, failed
            $table->string('note', 500)->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['gmail_connection_id', 'gmail_message_id']);
            $table->index(['household_id', 'created_at']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dateTime('occurred_at')->nullable()->after('posted_on');
        });

        Schema::table('statement_imports', function (Blueprint $table) {
            // Lines that were already in Budgeteer from a bank email.
            $table->unsignedInteger('matched_emails')->default(0)->after('already_there');
        });
    }

    public function down(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->dropColumn('matched_emails');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('occurred_at');
        });
        Schema::dropIfExists('ingested_emails');
        Schema::dropIfExists('gmail_connections');
    }
};
