<?php

use App\Http\Traits\helpers;
use App\Models\Coin;
use App\Models\PlatformAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class CreatePlatformAddressesTable extends Migration
{
    use helpers;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('platform_addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('coin_id');
            $table->text('pth');
            $table->text('sig');
            $table->timestamps();
        });

        Coin::all()->each(function ($coin) {
            // Set network based on coin
            $data = self::getRequestDataByCoin($coin);

            // Generate address
            $res = Http::withHeaders([
                'Content-type' => 'application/json',
                'X-API-Key' => env('CRYPTO_API_KEY')
            ])->post(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/address')->json();
            // Save user address
            if (array_key_exists("payload", $res))
                PlatformAddress::query()->create([
                    'coin_id' => $coin['id'],
                    'pth' => Crypt::encryptString($res['payload']['address']),
                    'sig' => Crypt::encryptString($res['payload'][$data['key']])
                ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('platform_addresses');
    }
}
