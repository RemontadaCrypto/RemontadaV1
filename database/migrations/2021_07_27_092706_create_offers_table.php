<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOffersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('coin_id');
            $table->enum('price_type', ['fixed', 'relative']);
            $table->double('price', 20, 2);
            $table->double('rate')->nullable();
            $table->double('min', 20, 2);
            $table->double('max', 20, 2);
            $table->enum('status', ['active', 'running', 'closed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('offers');
    }
}
