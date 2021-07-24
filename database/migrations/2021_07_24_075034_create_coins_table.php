<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use \Illuminate\Support\Facades\DB;

class CreateCoinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('coins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('short_name');
        });

        DB::table('coins')->insert([
            [
                'name' => 'Bitcoin',
                'slug' => 'bitcoin',
                'short_name' => 'BTC'
            ],
            [
                'name' => 'Ethereum',
                'slug' => 'ethereum',
                'short_name' => 'ETH'
            ],
            [
                'name' => 'Bitcoin Cash',
                'slug' => 'bitcoin-cash',
                'short_name' => 'BCH'
            ],
            [
                'name' => 'Litecoin',
                'slug' => 'litecoin',
                'short_name' => 'LTC'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('coins');
    }
}
