<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTradesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('ref');
            $table->foreignId('coin_id');
            $table->foreignId('offer_id');
            $table->foreignId('buyer_id');
            $table->foreignId('seller_id');
            $table->decimal('amount_in_coin', 20, 9);
            $table->decimal('amount_in_usd', 20, 2);
            $table->decimal('amount_in_ngn', 20, 2);
            $table->decimal('fee_in_coin', 20, 9);
            $table->decimal('fee_in_usd', 20, 9);
            $table->decimal('fee_in_ngn', 20, 9);
            $table->integer('buyer_trade_state')->default(1);
            $table->integer('seller_trade_state')->default(0);
            $table->boolean('coin_released')->default(false);
            $table->enum('status', ['pending', 'cancelled', 'successful'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trades');
    }
}
