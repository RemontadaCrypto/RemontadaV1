<?php

namespace App\Http\Controllers;

use App\Http\Traits\helpers;
use App\Models\Address;
use App\Models\Coin;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class AddressController extends Controller
{
    use helpers;
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
            $address = auth()->user()->getAddressByCoin($coin['id'])['pth'] ?? null;
            $balance[] = [
                $coin['short_name'] => [
                    'address' => $address,
                    'balance' => $address ? self::getAddressBalance($coin) : null
                ]
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
     *      description="e.g btc, eth, ltc",
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
        $address = auth()->user()->getAddressByCoin($coin['id'])['pth'] ?? null;
        return response()->json(['data' => [
            'address' => $address,
            'balance' => $address ? self::getAddressBalance($coin) : null
        ]]);
    }

    public static function generateWalletAddress($user){
        Coin::all()->each(function ($coin) use ($user) {
            if (!$user->hasAddressByCoin($coin['id']))
                self::generateAddressByShortName($user, $coin);
        });
    }

    public static function getAddressBalance($coin): ?string
    {
        // Set network based on coin
        $data = self::getRequestDataByCoin($coin);

        // Generate address
        $res = Http::withHeaders([
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ])->get(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/address/'.$data['address'])->json();
        return number_format($res['payload']['balance'], 8) ?? null;
    }

    protected static function generateAddressByShortName($user, $coin)
    {
        // Set network based on coin
        $data = self::getRequestDataByCoin($coin);

        // Generate address
        $res = Http::withHeaders([
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ])->post(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/address')->json();
        // Save user address
        if (array_key_exists("payload", $res))
            $user->addresses()->create([
                'coin_id' => $coin['id'],
                'pth' => Crypt::encryptString($res['payload']['address']),
                'sig' => Crypt::encryptString($res['payload'][$data['key']])
            ]);
    }

    public static function getAllAddresses(): array
    {
        $addresses = [];
        foreach (Coin::all() as $coin){
            $addresses[] = [
                $coin['short_name'] => [
                    'address' => auth()->user()->getAddressByCoin($coin['id'])['pth'] ?? null,
                ]
            ];
        }
        return $addresses;
    }
}
