<?php

namespace App\Http\Controllers;

use App\Models\Coin;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
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
            'amount' => ['required', 'numeric']
        ]);
        if ($validator->fails()){
            return response()->json($validator->getMessageBag(), 422);
        }

        // Find user address for this coin
        $sender = auth()->user()->getAddressByCoin($coin['id']);

        // Process transaction
        $res = self::processCoinWithdrawal($coin, $sender, Arr::get($data, 'address'), Arr::get($data, 'amount'));
        // Check for error
        if (array_key_exists("meta", $res))
            if (array_key_exists("error", $res['meta']))
                if (array_key_exists("message", $res['meta']['error']))
                    return response()->json(["message" => $res['meta']['error']['message']]);
        if (array_key_exists("payload", $res))
            return response()->json(["message" => 'You have successfully sent '.Arr::get($data, 'amount').' '.strtoupper($coin['short_name']).' to '.Arr::get($data, 'address')]);
        return response()->json(["message" => 'An error occurred']);
    }

    public static function processCoinWithdrawal($coin, $sender, $to, $amount)
    {
        // Set network based on coin
        $fee = self::getRecommendedTransactionFee();
        if ($coin['short_name'] == 'ETH') {
            $network = env('CRYPTO_NETWORK_2');
            $suffix = 'new-pvtkey';
            $trxData = [
                "fromAddress" =>  $sender['pth'],
                "toAddress" => $to,
                "gasPrice" => 56000000000,
                "gasLimit" => 21000,
                "value" => $amount,
                "privateKey" => self::transactionSignature($sender['sig'])
            ];
        } else {
            $network = env('CRYPTO_NETWORK_1');
            $suffix = 'new';
            $trxData = [
                "createTx" =>  [
                    "inputs" => [
                        [
                            "address" => $sender['pth'],
                            "value" => ($amount - $fee)
                        ]
                    ],
                    "outputs" => [
                        [
                            "address" => $to,
                            "value" => ($amount - $fee)
                        ]
                    ],
                    "fee" => [
                        "address" => $sender['pth'],
                        "value" =>  $fee
                    ]
                ],
                "wifs" => [
                    self::transactionSignature($sender['sig'])
                ]
            ];
        }

        // Process withdrawal
        return Http::withHeaders([
            'Content-type' => 'application/json',
            'X-API-Key' => env('CRYPTO_API_KEY')
        ])->post('https://api.cryptoapis.io/v1/bc/'.strtolower($coin['short_name']).'/'.$network.'/txs/'.$suffix, $trxData)->json();
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
