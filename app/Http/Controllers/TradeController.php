<?php

namespace App\Http\Controllers;

use App\Events\TradeAcceptedEvent;
use App\Events\TradeInitiatedEvent;
use App\Events\TradeCancelledEvent;
use App\Events\PaymentMadeEvent;
use App\Events\PaymentConfirmedEvent;
use App\Http\Resources\TradeResource;
use App\Http\Traits\helpers;
use App\Jobs\SendCustomEmailJob;
use App\Jobs\SettleTradeJob;
use App\Models\Coin;
use App\Models\Offer;
use App\Models\Trade;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class TradeController extends Controller
{
    use helpers;
    /**
     * @OA\Get(
     ** path="/v1/trades/user",
     *   tags={"Trade"},
     *   summary="Get authenticated user trades",
     *   operationId="get authenticated user trades",
     *   security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *      name="filter",
     *      in="query",
     *      required=false,
     *      description="pending, cancelled or successful",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="offset",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="integer"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="limit",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="integer"
     *      )
     *   ),
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function fetchUserTrades(): \Illuminate\Http\JsonResponse
    {
        // Set data and validate request
        $data = Arr::only(request()->all(), ['filter', 'offset', 'limit']);
        $validator = Validator::make($data, [
            'filter' => ['sometimes', 'in:pending, cancelled, successful'],
            'offset' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer'],
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        $trade = Trade::query()->where(function ($q) { $q->where('seller_id', auth()->user()['id'])->orWhere('buyer_id', auth()->user()['id']); });
        $count = $trade->count();
        $filter = Arr::get($data, 'filter');
        if ($filter)
            $trade->where('status', $filter);
        return response()->json([
            'data' => TradeResource::collection($trade->offset(Arr::get($data, 'offset', 0))->limit(Arr::get($data, 'limit', 50))->get()),
            'meta' => [
                'total' => $count,
                'offset' => (int) Arr::get($data, 'offset', 0),
                'limit' => (int) Arr::get($data, 'limit', 50)
            ]
        ]);
    }

    /**
     * @OA\Get(
     ** path="/v1/trades/{trade}/show",
     *   tags={"Trade"},
     *   summary="Show trade",
     *   operationId="show trade",
     *   security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *      name="trade",
     *      in="path",
     *      required=false,
     *      description="This is the trade ID",
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
     *   )
     *)
     **/
    public function show(Trade $trade): \Illuminate\Http\JsonResponse
    {
        return response()->json(new TradeResource($trade));
    }

    /**
     * @OA\Post(
     ** path="/v1/trades/initiate",
     *   tags={"Trade"},
     *   summary="Initiate trade",
     *   operationId="initiate trade",
     *   security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *      name="offer_id",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
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
     *      response=422,
     *       description="Unprocessed Entity"
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request"
     *   )
     *)
     **/
    public function initiate(): \Illuminate\Http\JsonResponse
    {
        // Set data and validate request
        $data = Arr::only(request()->all(), ['offer_id', 'amount']);
        $validator = Validator::make($data, [
            'offer_id' => ['required'],
            'amount' => ['required', 'gt:0']
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        $offer = Offer::find(Arr::get($data, 'offer_id'));
        // Check if offer exists
        if (!$offer)
            return response()->json(['error' => 'Offer not found'], 400);
        // Check if offer is active
        if ($offer['status'] != 'active')
            return response()->json(['error' => 'Offer not active'], 400);
        // Check if offer is not out of range
        if (Arr::get($data, 'amount') < $offer['min'] || Arr::get($data, 'amount') > $offer['max'])
            return response()->json(['error' => 'Input amount is out of range, must be between '.$offer['min'].' and '.$offer['max']], 400);
        // Check if trade doesn't belong to user
        if (auth()->user()['id'] == $offer->user['id'])
            return response()->json(['error' => 'You can\'t initiate a trade with your own offer' ], 400);
        // Check if seller has sufficient balance for trade
        if ($offer['type'] == 'naira') {
            $amountInNGN = Arr::get($data, 'amount');
            $amountInUSD = Arr::get($data, 'amount') / $offer['rate'];
        } else {
            $amountInUSD = Arr::get($data, 'amount');
            $amountInNGN = Arr::get($data, 'amount') * $offer['rate'];
        }
        if (AddressController::getAddressTradeAbleBalance($offer) < self::getFormattedCoinAmount($amountInUSD / $offer['coin']['price']))
           return response()->json(["message" => 'Seller doesn\'t have sufficient wallet balance for trade'], 400);
        // Get trade fee
        $feeInUSD = $amountInUSD * (env('PLATFORM_TRADE_CHARGE_PERCENT') / 100);
        // Create trade
        $trade = $offer->trades()->create([
            'ref' => Trade::generateTradeRef(),
            'coin_id' => $offer['coin']['id'],
            'buyer_id' => auth()->user()['id'],
            'seller_id' => $offer->user['id'],
            'amount_in_ngn' => round($amountInNGN, 2),
            'amount_in_coin' => round($amountInUSD / $offer['coin']['price'], 8),
            'amount_in_usd' => round($amountInUSD, 2),
            'fee_in_ngn' => $feeInUSD * $offer['rate'],
            'fee_in_coin' => round($feeInUSD / $offer['coin']['price'], 8),
            'fee_in_usd' => $feeInUSD,
            'buyer_trade_state' => 1,
            'seller_trade_state' => 0,
            'status' => "pending",
        ]);
        // Broadcast and dispatch relevant jobs
        broadcast(new TradeInitiatedEvent($trade))->toOthers();
        SendCustomEmailJob::dispatch($trade['seller'], 'trade-initiated', $trade);
        return response()->json([
            'message' => 'Trade initiated successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/v1/trades/{trade}/accept",
     *   tags={"Trade"},
     *   summary="Accept trade",
     *   operationId="accept trade",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="trade",
     *      in="path",
     *      required=true,
     *      description="this is the trade ID",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *    @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function accept(Trade $trade): \Illuminate\Http\JsonResponse
    {
        try {
            $this->authorize('seller', $trade);
        } catch (\Exception $e) {
            return response()->json(['errors' => 'This action is unauthorized'], 403);
        }
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if ($trade['seller_trade_state'] != 0)
            return response()->json(['error' => 'Trade already running'], 400);
        $trade->update([
            'seller_trade_state' => 1
        ]);
        // Broadcast and dispatch relevant jobs
        broadcast(new TradeAcceptedEvent($trade))->toOthers();
        SendCustomEmailJob::dispatch($trade['buyer'], 'trade-accepted', $trade);
        return response()->json([
            'message' => 'Trade accepted successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/v1/trades/{trade}/make-payment",
     *   tags={"Trade"},
     *   summary="Make payment for trade",
     *   operationId="make payment for trade",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="trade",
     *      in="path",
     *      required=true,
     *      description="this is the trade ID",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *    @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function makePayment(Trade $trade): \Illuminate\Http\JsonResponse
    {
        try {
            $this->authorize('buyer', $trade);
        } catch (\Exception $e) {
            return response()->json(['errors' => 'This action is unauthorized'], 403);
        }
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if ($trade['buyer_trade_state'] != 1 || $trade['seller_trade_state'] != 1)
            return response()->json(['error' => 'Action not allowed, payment made request already sent'], 400);
        $trade->update([
            'buyer_trade_state' => 2
        ]);
        // Broadcast and dispatch relevant jobs
        broadcast(new PaymentMadeEvent($trade))->toOthers();
        SendCustomEmailJob::dispatch($trade['seller'], 'payment-made', $trade);
        return response()->json([
            'message' => 'Payment made request sent successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/v1/trades/{trade}/confirm-payment",
     *   tags={"Trade"},
     *   summary="Confirm payment for trade",
     *   operationId="confirm payment for trade",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="trade",
     *      in="path",
     *      required=true,
     *      description="this is the trade ID",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *    @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function confirmPayment(Trade $trade): \Illuminate\Http\JsonResponse
    {
        self::closeOrUpdateOffer($trade);
        try {
            $this->authorize('seller', $trade);
        } catch (\Exception $e) {
            return response()->json(['errors' => 'This action is unauthorized'], 403);
        }
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if ($trade['buyer_trade_state'] != 2 || $trade['seller_trade_state'] != 1)
            return response()->json(['error' => 'Action not allowed, payment made request not sent by buyer'], 400);
        $trade->update([
            'seller_trade_state' => 2,
            'status' => 'successful'
        ]);
        // Broadcast and dispatch relevant jobs
        SettleTradeJob::dispatch($trade);
        broadcast(new PaymentConfirmedEvent($trade))->toOthers();
        SendCustomEmailJob::dispatch($trade['buyer'], 'payment-confirmed', $trade);
        SendCustomEmailJob::dispatch($trade['seller'], 'seller', $trade);
        SendCustomEmailJob::dispatch($trade['buyer'], 'buyer', $trade);
        return response()->json([
            'message' => 'Payment confirmed successfully, trade successful',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/v1/trades/{trade}/cancel",
     *   tags={"Trade"},
     *   summary="Cancel trade",
     *   operationId="cancel trade",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="trade",
     *      in="path",
     *      required=true,
     *      description="this is the trade ID",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *    @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   ),
     *   @OA\Response(
     *      response=400,
     *       description="Bad Request",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function cancel(Trade $trade): \Illuminate\Http\JsonResponse
    {
        try {
            $this->authorize('buyer', $trade);
        } catch (\Exception $e) {
            return response()->json(['errors' => 'This action is unauthorized'], 403);
        }
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if ($trade['seller_trade_state'] == 2)
            return response()->json(['error' => 'Action not allowed, trade already successful'], 400);
        $trade->update([
            'status' => 'cancelled'
        ]);
        // Broadcast and dispatch relevant jobs
        broadcast(new TradeCancelledEvent($trade))->toOthers();
        SendCustomEmailJob::dispatch($trade['buyer'], 'trade-cancelled-buyer', $trade);
        SendCustomEmailJob::dispatch($trade['seller'], 'trade-cancelled-seller', $trade);
        return response()->json([
            'message' => 'Trade cancelled successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    public static function sendCoinToBuyer($trade)
    {
        if (!$trade['coin_released']) {
            $res = TransactionController::processCoinWithdrawal(
                $trade['coin'],
                $trade['seller']->getAddressByCoin($trade['coin']['id']),
                $trade['buyer']->getAddressByCoin($trade['coin']['id'])['pth'],
                self::getFormattedCoinAmount($trade['amount_in_coin'] - $trade['fee_in_coin'])
            );
            if (array_key_exists("payload", $res)) {
                $trade->update(['coin_released' => true]);
                $coin = $trade->coin;
                $coin->transactions()->create([
                    'hash' => $res['payload']['hex'],
                    'type' => 'trade',
                    'amount' => self::getFormattedCoinAmount($trade['amount_in_coin'] - $trade['fee_in_coin']),
                    'party' => $trade['buyer']->getAddressByCoin($trade['coin']['id'])['pth']
                ]);
            }
        }
    }

    public static function sendFeeToAdmin($trade)
    {
        if (!$trade['fee_released']) {
            $res = TransactionController::processCoinWithdrawal(
                $trade['coin'],
                $trade['seller']->getAddressByCoin($trade['coin']['id']),
                $trade['coin']->getFeeDepositAddress(),
                self::getFormattedCoinAmount($trade['fee_in_coin']),
                AddressController::getAddressNonce($trade['coin'], $trade['seller']->getAddressByCoin($trade['coin']['id'])['pth'])
            );
            if (array_key_exists("payload", $res)) {
                $trade->update(['fee_released' => true]);
                $coin = $trade->coin;
                $coin->transactions()->create([
                    'hash' => $res['payload']['hex'],
                    'type' => 'fee',
                    'amount' => self::getFormattedCoinAmount($trade['fee_in_coin']),
                    'party' => $trade['coin']->getFeeDepositAddress()
                ]);
            }
        }
    }

    public static function closeOrUpdateOffer($trade)
    {
        $offer = $trade->offer;
        if ($offer->isClosableByTrade($trade)) {
            $balance = AddressController::getAddressWithdrawAbleBalanceExcludingSingleOffer($offer);
            if ($balance < $offer->getMaxPriceInCoin()) {
                $balanceInUSD = $balance * $offer['coin']['price'];
                $balanceInNGN = $balanceInUSD * $offer['rate'];
                if ($offer['type'] == 'naira') {
                    if ($balanceInNGN > $offer['min']) $offer->update(['max' => $balanceInNGN]);
                    else $offer->update(['status' => 'closed']);
                } else {
                    if ($balanceInUSD > $offer['min']) $offer->update(['max' => $balanceInUSD]);
                    else $offer->update(['status' => 'closed']);
                }
            }
        }
    }

    public static function getUserTrades(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return TradeResource::collection(Trade::where(function ($q) { $q->where('seller_id', auth()->user()['id'])->orWhere('buyer_id', auth()->user()['id']); })->get());
    }
}
