<?php

namespace App\Http\Controllers;

use App\Events\TradeAcceptedEvent;
use App\Events\TradeInitiatedEvent;
use App\Events\TradeCancelledEvent;
use App\Events\PaymentMadeEvent;
use App\Events\PaymentConfirmedEvent;
use App\Http\Resources\TradeResource;
use App\Models\Coin;
use App\Models\Offer;
use App\Models\Setting;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class TradeController extends Controller
{
    /**
     * @OA\Get(
     ** path="/trades/user",
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
        $data = Arr::only(request()->all(), ['filter']);
        $validator = Validator::make($data, [
            'filter' => ['sometimes', 'in:pending, cancelled, successful']
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        $trade = Trade::where(function ($q) { $q->where('seller_id', auth()->user()['id'])->orWhere('buyer_id', auth()->user()['id']); });
        $filter = Arr::get($data, 'filter');
        if ($filter)
            $trade->where('status', $filter);
        return response()->json(TradeResource::collection($trade->get()));
    }

    /**
     * @OA\Get(
     ** path="/trades/{trade}/show",
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
     ** path="/trades/initiate",
     *   tags={"Trade"},
     *   summary="Initiate trade",
     *   operationId="initiate trade",
     *   security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *      name="coin",
     *      in="query",
     *      required=true,
     *      description="e.g btc, eth, ltc",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
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
        $data = Arr::only(request()->all(), ['offer_id', 'coin', 'amount']);
        $validator = Validator::make($data, [
            'offer_id' => ['required'],
            'coin' => ['required', 'string'],
            'amount' => ['required', 'gt:0']
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        $coin = Coin::query()->where('short_name', $data['coin'])->first();
        // Check if coin is supported
        if (!$coin)
            return response()->json(['error' => 'Coin not found or not supported'], 400);
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
//        if (AddressController::getAddressBalance($coin) < round(Arr::get($data, 'amount') / $offer['price'], 9))
//            return response()->json(["message" => 'Seller doesn\'t have sufficient wallet balance for trade'], 400);
        // Get trade fee
        $feeInUSD = Setting::first()['fee'];
        // Create trade
        $trade = $offer->trades()->create([
            'ref' => Trade::generateTradeRef(),
            'coin_id' => $coin['id'],
            'buyer_id' => auth()->user()['id'],
            'seller_id' => $offer->user['id'],
            'amount_in_ngn' => Arr::get($data, 'amount'),
            'amount_in_coin' => round(Arr::get($data, 'amount') / $offer['price'], 9),
            'amount_in_usd' => round(Arr::get($data, 'amount') / $offer['rate'], 2),
            'fee_in_ngn' => $feeInUSD * $offer['rate'],
            'fee_in_coin' => round($feeInUSD / $coin['price'], 9),
            'fee_in_usd' => $feeInUSD
        ]);
        $offer->update(['status' => 'running']);
        broadcast(new TradeInitiatedEvent($trade))->toOthers();
        return response()->json([
            'message' => 'Trade initiated successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/trades/{trade}/accept",
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
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if (auth()->user()['id'] != $trade['seller_id'])
            return response()->json(['error' => 'Action unauthorized'], 400);
        if ($trade['seller_trade_state'] != 0)
            return response()->json(['error' => 'Trade already running'], 400);
        $trade->update([
            'seller_trade_state' => 1
        ]);
        broadcast(new TradeAcceptedEvent($trade))->toOthers();
        return response()->json([
            'message' => 'Trade accepted successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/trades/{trade}/make-payment",
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
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if (auth()->user()['id'] != $trade['buyer_id'])
            return response()->json(['error' => 'Action unauthorized'], 400);
        if ($trade['buyer_trade_state'] != 1 || $trade['seller_trade_state'] != 1)
            return response()->json(['error' => 'Action not allowed, trade not yet accepted by seller'], 400);
        $trade->update([
            'buyer_trade_state' => 2
        ]);
        broadcast(new PaymentMadeEvent($trade))->toOthers();
        return response()->json([
            'message' => 'Payment made request sent successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/trades/{trade}/confirm-payment",
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
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if (auth()->user()['id'] != $trade['seller_id'])
            return response()->json(['error' => 'Action unauthorized'], 400);
        if ($trade['buyer_trade_state'] != 2 || $trade['seller_trade_state'] != 1)
            return response()->json(['error' => 'Action not allowed, payment made request not sent by buyer'], 400);
        $trade->update([
            'seller_trade_state' => 2,
            'status' => 'successful'
        ]);
        $trade->offer()->update(['status' => 'closed']);
        broadcast(new PaymentConfirmedEvent($trade))->toOthers();
        return response()->json([
            'message' => 'Payment confirmed successfully, trade successful',
            'data' => new TradeResource($trade)
        ]);
    }

    /**
     * @OA\Post(
     ** path="/trades/{trade}/cancel",
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
        if ($trade['status'] != 'pending')
            return response()->json(['error' => 'Trade already '.$trade['status']], 400);
        if (auth()->user()['id'] != $trade['buyer_id'])
            return response()->json(['error' => 'Action unauthorized'], 400);
        if ($trade['seller_trade_state'] == 2)
            return response()->json(['error' => 'Action not allowed, trade already successful'], 400);
        $trade->update([
            'status' => 'cancelled'
        ]);
        $trade->offer()->update(['status' => 'active']);
        broadcast(new TradeCancelledEvent($trade))->toOthers();
        return response()->json([
            'message' => 'Trade cancelled successfully',
            'data' => new TradeResource($trade)
        ]);
    }

    public static function getUserTrades(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return TradeResource::collection(Trade::where(function ($q) { $q->where('seller_id', auth()->user()['id'])->orWhere('buyer_id', auth()->user()['id']); })->get());
    }
}
