<?php

namespace App\Http\Controllers;

use App\Http\Traits\helpers;
use App\Models\Coin;

use App\Models\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    use helpers;
    /**
     * @OA\Post(
     ** path="/transactions/{coin}/withdraw",
     *   tags={"Transactions"},
     *   summary="withdraw coin to another address",
     *   operationId="withdraw coin to another address",
     *   security={{ "apiAuth": {} }},
     *
     *   @OA\Parameter(
     *      name="coin",
     *      in="path",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="address",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *           type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="amount",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
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
     *      response=400,
     *       description="Bad Request"
     *   ),
     *   @OA\Response(
     *      response=401,
     *       description="Unauthenticated"
     *   ),
     *   @OA\Response(
     *      response=422,
     *      description="Unprocessed Entity"
     *   )
     *)
     **/
    public function withdraw(Coin $coin): \Illuminate\Http\JsonResponse
    {
        // Set credentials and validate request
        $data = Arr::only(request()->all(), ['address', 'amount']);
        $validator = Validator::make($data, [
            'address' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0']
        ]);
        if ($validator->fails()){
            return response()->json($validator->getMessageBag(), 422);
        }

        // Find user address for this coin
        $sender = auth()->user()->getAddressByCoin($coin['id']);

        // Process transaction
        $res = self::processCoinWithdrawal($coin, $sender, Arr::get($data, 'address'), (float)Arr::get($data, 'amount'));
        // Check for error
        if (array_key_exists("meta", $res))
            if (array_key_exists("error", $res['meta']))
                if (array_key_exists("message", $res['meta']['error']))
                    return response()->json(["message" => $res['meta']['error']['message']]);
        // Check for success
        if (array_key_exists("payload", $res)) {
            auth()->user()->transactions()->create([
                'coin_id' => $coin['id'], 'type' => 'withdrawal',
                'amount' => Arr::get($data, 'amount'),
                'party' => Arr::get($data, 'address')
            ]);
            return response()->json(["message" => 'You have successfully sent ' . Arr::get($data, 'amount') . ' ' . strtoupper($coin['short_name']) . ' to ' . Arr::get($data, 'address')]);
        }
        return response()->json(["message" => 'An error occurred']);
    }

    /**
     * @OA\Get(
     ** path="/transactions/{coin}",
     *   tags={"Transactions"},
     *   summary="get all user transactions by a supported coin",
     *   operationId="get all user transactions by a supported coin",
     *   security={{ "apiAuth": {} }},
     *
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
    public function getTransactionByCoin(Coin $coin): \Illuminate\Http\JsonResponse
    {
        // Set network based on coin
        $data = self::getRequestDataByCoin($coin);

        // Get transactions
        $res = Http::withHeaders(self::getHeaders())
            ->get(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/address/'.$data['address'].'/basic/transactions')
            ->json();
        return response()->json(['data' => $res['payload']]);
    }

    public static function processCoinWithdrawal($coin, $sender, $to, $amount)
    {
        // Set network based on coin
        $sig = self::transactionSignature($sender['sig']);
        $fee = self::getRecommendedTransactionFee();
        $data = self::getRequestDataByCoin($coin, $sender['pth'], $to, $sig, $amount, $fee);
        // Process withdrawal
        return Http::withHeaders(self::getHeaders())
            ->post(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/txs/'.$data['suffix'], $data['trxData'])
            ->json();
    }

    private static function transactionSignature($key): string
    {
        return Crypt::decryptString($key);
    }

    protected static function getRecommendedTransactionFee(): float
    {
        // to do - calculate transaction fee
        return 0.00008092;
    }
}