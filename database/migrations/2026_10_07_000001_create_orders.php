<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An online order read from the shop's confirmation email, linked to the card payment for it.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained();
            $table->string('shop', 20); // takealot, amazon
            $table->string('order_number', 40);
            $table->timestamp('ordered_at');
            $table->bigInteger('total_cents');
            $table->string('deliver_to', 100)->nullable(); // the name only, never the address
            $table->foreignId('transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('ingested_email_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['household_id', 'shop', 'order_number']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('name', 300);
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('price_cents')->nullable(); // as the email shows it
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
