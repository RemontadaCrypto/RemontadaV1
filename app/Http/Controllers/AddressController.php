<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Coin;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class AddressController extends Controller
{
    /**
     * @OA\Get(
     ** path="/balance/all",
     *   tags={"Balance"},
     *   summary="get user balance for all supported coins",
     *   operationId="get user balance for all supported coins",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   )
     *)
     **/
    public function getAllBalance(): \Illuminate\Http\JsonResponse
    {
        $balance = [];
        foreach (Coin::all() as $coin){
            $balance[] = [
                $coin['short_name'] => self::getAddressBalance($coin)
            ];
        }
        return response()->json(['data' => $balance]);
    }

    /**
     * @OA\Get(
     ** path="/balance/{coin}",
     *   tags={"Balance"},
     *   summary="get user balance by a specific coin short name supported coins",
     *   operationId="get user balance by a specific coin short name supported coins",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="coin",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   )
     *)
     **/
    public function getBalanceByCoin(Coin $coin): \Illuminate\Http\JsonResponse
    {
        return response()->json(['data' => self::getAddressBalance($coin)]);
    }

    public static function generateWalletAddress($user){
        Coin::all()->each(function ($coin) use ($user) {
            if (!$user->hasAddressByCoin($coin['id']))
                self::generateAddressByShortName($user, $coin);
        });
    }

    protected static function getAddressBalance($coin)
    {
        // Set network based on coin
        if ($coin['short_name'] == 'ETH') {
            $network = env('CRYPTO_NETWORK_2');
        } else {
            $network = env('CRYPTO_NETWORK_1');
        }
        // Generate address
        $address = auth()->user()->getAddressByCoin($coin['id'])['pth'];
        $res = Http::withHeaders([
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ])->get('https://api.cryptoapis.io/v1/bc/'.strtolower($coin['short_name']).'/'.$network.'/address/'.$address)->json();
        if (array_key_exists('payload', $res))
            return number_format($res['payload']['balance'], 8);
        else
            return '---';
    }

    protected static function generateAddressByShortName($user, $coin)
    {
        // Set network based on coin
        if ($coin['short_name'] == 'ETH') {
            $network = env('CRYPTO_NETWORK_2');
            $key = 'privateKey';
        } else {
            $network = env('CRYPTO_NETWORK_1');
            $key = 'wif';
        }
        // Generate address
        $res = Http::withHeaders([
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ])->post('https://api.cryptoapis.io/v1/bc/'.strtolower($coin['short_name']).'/'.$network.'/address')->json();
        // Save user address
        if (array_key_exists("payload", $res))
            $user->addresses()->create([
                'coin_id' => $coin['id'],
                'pth' => Crypt::encryptString($res['payload']['address']),
                'sig' => Crypt::encryptString($res['payload'][$key])
            ]);
    }

    public function getSig($address): string
    {
        return Crypt::decryptString(Address::where('address', $address)->first()->sig);
    }
}
