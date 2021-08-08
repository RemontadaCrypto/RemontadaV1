<?php

namespace App\Http\Controllers;

use App\Http\Resources\CoinResource;
use App\Models\Coin;
use Illuminate\Http\Request;

class DefaultController extends Controller
{
    /**
     * @OA\Get(
     ** path="/coins",
     *   tags={"Coins"},
     *   summary="All supported coin",
     *   operationId="all supported coin",
     *   security={{ "apiAuth": {} }},
     *   @OA\Response(
     *      response=200,
     *       description="Success",
     *      @OA\MediaType(
     *           mediaType="application/json",
     *      )
     *   )
     *)
     **/
    public function getCoins(): \Illuminate\Http\JsonResponse
    {
        return response()->json(CoinResource::collection(Coin::all()));
    }

    /**
     * @OA\Get(
     ** path="/coins/{coin}/show",
     *   tags={"Coins"},
     *   summary="Show coin",
     *   operationId="show coin",
     *   security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *      name="coin",
     *      in="path",
     *      required=false,
     *      description="e.g btc, eth, bch",
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
    public function showCoin(Coin $coin): \Illuminate\Http\JsonResponse
    {
        return response()->json(new CoinResource($coin));
    }
}
