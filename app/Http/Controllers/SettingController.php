<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public static function getSupportedCoinData($supportedCoins): array
    {
        $coinArr = [];
        $coins = Http::withHeaders([
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ])->get(env('CRYPTO_API_MAIN_BASE_URL').'/assets')->json()['payload'];
        foreach ($coins as $coin) {
            if (in_array($coin['originalSymbol'], $supportedCoins)) {
                $coinArr[] = [
                    'name' => $coin['name'],
                    'slug' => $coin['slug'],
                    'short_name' => Str::upper($coin['originalSymbol']),
                    'market_cap' => $coin['marketCap'],
                    'volume' => $coin['volume'],
                    'price' => $coin['price']
                ];
            }
        }
        return $coinArr;
    }
}
