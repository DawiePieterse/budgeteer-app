<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per phone or browser that turned notifications on.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 200);
            $table->string('auth_token', 100);
            $table->string('device', 100)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });

        // What each person wants to hear about; all on unless turned off.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_recurring')->default(true);
            $table->boolean('notify_budget')->default(true);
            $table->boolean('notify_gmail')->default(true);
        });

        // Every notification sent, so each is sent once.
        Schema::create('sent_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('key', 150);
            $table->string('title', 150);
            $table->string('body', 500);
            $table->timestamp('created_at')->nullable();
            $table->unique(['household_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_notifications');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_recurring', 'notify_budget', 'notify_gmail']);
        });
        Schema::dropIfExists('push_subscriptions');
    }
};
