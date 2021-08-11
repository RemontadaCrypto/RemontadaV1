<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfferResource;
use App\Models\Coin;
use App\Models\Offer;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    /**
     * @OA\Get(
     ** path="/offers",
     *   tags={"Offer"},
     *   summary="Get all offers",
     *   operationId="get all offers",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="coin",
     *      in="query",
     *      required=false,
     *      description="e.g btc, eth, bch",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="price",
     *      in="query",
     *      required=false,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *   @OA\Parameter(
     *      name="filter",
     *      in="query",
     *      required=false,
     *      description="e.g popularity",
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
    public function index(): \Illuminate\Http\JsonResponse
    {
        // Set data and validate request
        $data = Arr::only(request()->all(), ['filter', 'offset', 'limit']);
        $validator = Validator::make($data, [
            'offset' => ['sometimes', 'integer'],
            'limit' => ['sometimes', 'integer'],
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        $offers = Offer::query()->with(['user'])->where('status', 'active');
        // filter by coin
        if (request('coin')) {
            $coin = Coin::query()->where('short_name', request('coin'))->first();
            if (!$coin)
                return response()->json(['error' => 'Coin not found or not supported'], 400);
            $offers->where('coin_id', $coin['id']);
        }
        $count = $offers->count();
        // filter by price
        if (request('price')) {
            $offers->where('min', '<=', request('price'))
                ->where('max', '>=', request('price'));
        }
        // filter by user trades

        return response()->json([
            'data' => OfferResource::collection($offers->offset(Arr::get($data, 'offset', 0))->limit(Arr::get($data, 'limit', 50))->get()),
            'meta' => [
                'total' => $count,
                'offset' => (int) Arr::get($data, 'offset', 0),
                'limit' => (int) Arr::get($data, 'limit', 50)
            ]
        ]);
    }

    /**
     * @OA\Get(
     ** path="/offers/user",
     *   tags={"Offer"},
     *   summary="Get authenticated user all offers",
     *   operationId="get authenticated user all offers",
     *   security={{ "apiAuth": {} }},
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
     *    @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function userOffers(): \Illuminate\Http\JsonResponse
    {
         // Set data and validate request
         $data = Arr::only(request()->all(), ['filter', 'offset', 'limit']);
         $validator = Validator::make($data, [
             'offset' => ['sometimes', 'integer'],
             'limit' => ['sometimes', 'integer'],
         ]);
         if ($validator->fails())
             return response()->json($validator->getMessageBag(), 422);
        return response()->json([
            'data' => OfferResource::collection(auth()->user()->offers()->offset(Arr::get($data, 'offset', 0))->limit(Arr::get($data, 'limit', 50))->get()),
            'meta' => [
                'total' => auth()->user()->offers()->count(),
                'offset' => (int) Arr::get($data, 'offset', 0),
                'limit' => (int) Arr::get($data, 'limit', 50)
            ]
        ]);
    }

    /**
     * @OA\Get(
     ** path="/offers/{offer}/show",
     *   tags={"Offer"},
     *   summary="Show offers",
     *   operationId="show offers",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="offer",
     *      in="path",
     *      required=true,
     *      description="this is the offer ID",
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
     *   )
     *)
     **/
    public function show(Offer $offer): \Illuminate\Http\JsonResponse
    {
        return response()->json(new OfferResource($offer));
    }

    /**
     * @OA\Post(
     ** path="/offers/store",
     *   tags={"Offer"},
     *   summary="Store offer",
     *   operationId="store offer",
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
     *      name="type",
     *      in="query",
     *      required=true,
     *      description="naira or dollar",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="min",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="max",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="rate",
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
    public function store(): \Illuminate\Http\JsonResponse
    {
        // Set data and validate request
        $data = Arr::only(request()->all(), ['coin', 'type', 'min', 'max', 'rate']);
        $validator = Validator::make($data, [
            'coin' => ['required', 'string'],
            'type' => ['required', 'string', 'in:naira,dollar'],
            'min' => ['required', 'numeric', 'lt:max'],
            'max' => ['required', 'numeric', 'gt:min'],
            'rate' => ['required', 'numeric']
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        $coin = Coin::query()->where('short_name', $data['coin'])->first();
        // Check if coin is supported
        if (!$coin)
            return response()->json(['error' => 'Coin not found or not supported'], 400);
        // Verify user wallet balance

        // Create offer
        Arr::forget($data, ['coin']);
        Arr::set($data, 'user_id', auth()->user()['id']);
        $offer = $coin->offers()->create($data);
        return response()->json([
            'message' => 'Offer created successfully',
            'data' => new OfferResource($offer)
        ]);
    }

    /**
     * @OA\Put(
     ** path="/offers/{offer}/update",
     *   tags={"Offer"},
     *   summary="Update offer",
     *   operationId="update offer",
     *   security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *      name="offer",
     *      in="path",
     *      required=true,
     *      description="this is the offer ID",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="type",
     *      in="query",
     *      required=true,
     *      description="naira or dollar",
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="min",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="max",
     *      in="query",
     *      required=true,
     *      @OA\Schema(
     *          type="string"
     *      )
     *   ),
     *     @OA\Parameter(
     *      name="rate",
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
     *   )
     *)
     **/
    public function update(Offer $offer): \Illuminate\Http\JsonResponse
    {
        // Set data and validate request
        $data = Arr::only(request()->all(), ['coin', 'type', 'min', 'max', 'rate']);
        $validator = Validator::make($data, [
            'type' => ['required', 'string', 'in:naira,dollar'],
            'min' => ['required', 'numeric', 'lt:max'],
            'max' => ['required', 'numeric', 'gt:min'],
            'rate' => ['required', 'numeric']
        ]);
        if ($validator->fails())
            return response()->json($validator->getMessageBag(), 422);
        // Verify user wallet balance

        // update offer
        Arr::forget($data, ['coin']);
        Arr::set($data, 'user_id', auth()->user()['id']);
        $offer->update($data);
        return response()->json([
            'message' => 'Offer updated successfully',
            'data' => new OfferResource($offer)
        ]);
    }

    /**
     * @OA\Delete(
     ** path="/offers/{offer}/delete",
     *   tags={"Offer"},
     *   summary="Delete offer",
     *   operationId="delete offer",
     *   security={{ "apiAuth": {} }},
     *   @OA\Parameter(
     *      name="offer",
     *      in="path",
     *      required=true,
     *      description="this is the offer ID",
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
    public function destroy(Offer $offer): \Illuminate\Http\JsonResponse
    {
        // Check if offer has pending trade
        if ($offer->trades()->count())
            return response()->json(['error' => 'Offer can\'t be deleted, trade already associated']);
        $offer->delete();
        return response()->json(['message' => 'Offer deleted successfully']);
    }
}
