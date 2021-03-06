<?php

use App\Http\Controllers\DefaultController;
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
            $table->double('market_cap', 30, 8);
            $table->double('volume', 30, 8);
            $table->double('price', 30, 8);
            $table->timestamps();
        });

        $supportedCoins = ['BTC', 'ETH', 'BCH', 'LTC'];
        $coinsArr = DefaultController::getSupportedCoinData($supportedCoins);
        DB::table('coins')->insert($coinsArr);
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
