<?php

namespace App\Http\Controllers;

use App\Exceptions\AddressInvalidException;
use App\Jobs\SendCustomEmailJob;
use App\Models\Coin;
use App\Http\Traits\helpers;
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
    public function withdraw(Coin $coin)
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

        // Check if user has sufficient balance
        if (AddressController::getAddressWithdrawAbleBalance($coin) < Arr::get($data, 'amount')) {
            return response()->json(["message" => 'Insufficient wallet balance'], 400);
        }

        // Find user address for this coin
        $sender = auth()->user()->getAddressByCoin($coin['id']);

        // Process transaction
        $res = self::processCoinWithdrawal($coin, $sender, Arr::get($data, 'address'), (float)Arr::get($data, 'amount'));
        // Check for success or error
        if (array_key_exists("meta", $res))
            if (array_key_exists("error", $res['meta']))
                if (array_key_exists("message", $res['meta']['error']))
                    return response()->json(["message" => $res['meta']['error']['message']], 400);
        if (array_key_exists("payload", $res)) {
            $transaction = auth()->user()->transactions()->create([
                'coin_id' => $coin['id'], 'type' => 'withdrawal',
                'amount' => Arr::get($data, 'amount'),
                'party' => Arr::get($data, 'address')
            ]);
            // Dispatch relevant job
            SendCustomEmailJob::dispatch(auth()->user(), 'withdrawal', $transaction);
            return response()->json(["message" => 'You have successfully sent ' . Arr::get($data, 'amount') . ' ' . strtoupper($coin['short_name']) . ' to ' . Arr::get($data, 'address')]);
        }
        return response()->json(["message" => 'An error occurred'], 400);
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
     *   @OA\Parameter(
     *      name="index",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *           type="integer",
     *           default="0"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="limit",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *           type="integer",
     *           default="50"
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
    public function getTransactionByCoin(Coin $coin, $index = 0, $limit = 50): \Illuminate\Http\JsonResponse
    {
        // Set network based on coin
        $data = self::getRequestDataByCoin($coin);

        // Get transactions
        try {
            $res = Http::withHeaders(self::getHeaders())
                ->get(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/address/'.$data['address'].'/basic/transactions?index='.$index.'&limit='.$limit)
                ->json();
            return response()->json(['data' => [
                'transactions' => $res['payload'],
                'meta' => $res['meta']
            ]]);
        } catch (\Exception $e) {
            return response()->json(["message" => 'An error occurred'], 400);
        }
    }

    /**
     * @throws AddressInvalidException
     */
    public static function processCoinWithdrawal($coin, $sender, $to, $amount)
    {
        // Verify destination wallet is valid
        if (!self::addressIsValidByCoin($coin, $to)) {
            throw new AddressInvalidException();
        }
        // Set network based on coin
        $sig = self::transactionSignature($sender['sig']);
        $fee = self::getRecommendedTransactionFee($coin, $sender, $to, $amount);
        $data = self::getRequestDataByCoin($coin, $sender['pth'], $to, $sig, $amount, $fee);
        // Get transaction size
        return Http::withHeaders(self::getHeaders())
            ->post(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/txs/'.$data['suffix'], $data['trxData'])
            ->json();
    }

    private static function transactionSignature($key): string
    {
        return Crypt::decryptString($key);
    }

    protected static function getRecommendedTransactionFee($coin, $sender, $to, $amount)
    {
        $data = self::getRequestDataByCoin($coin, $sender['pth'], $to, null, $amount, 0.0000000);
        // Get fee data
        $feeData = Http::withHeaders(self::getHeaders())
            ->get(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/txs/fee')
            ->json()['payload'];
        // Get transaction size or gas limit
        $trxFeeData = Http::withHeaders(self::getHeaders())
            ->post(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/txs/'.$data['feeEndpointType'], $data['trxSizeData'])
            ->json()['payload'];
        if ($coin['short_name'] == 'ETH') {
            return [
                'gasLimit' => $trxFeeData['gasLimit'],
                'gasPrice' => $feeData['standard']
            ];
        } else {
            return self::getFormattedCoinAmount($trxFeeData['tx_size_bytes'] * $feeData['standard_fee_per_byte']);
        }
    }

    protected static function addressIsValidByCoin($coin, $address): bool
    {
        $data = self::getRequestDataByCoin($coin);
        $res = Http::withHeaders(self::getHeaders())
            ->get(env('CRYPTO_API_BASE_URL').'/'.$data['coin'].'/'.$data['network'].'/address/'.$address)
            ->json();
        return array_key_exists("payload", $res);
    }
}
