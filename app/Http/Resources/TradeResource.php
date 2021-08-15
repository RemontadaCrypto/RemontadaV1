<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this['id'],
            'ref' => (string) $this['ref'],
            'coin' => new CoinResource($this['coin']),
            'offer' => new OfferResource($this['offer']),
            'buyer' => new UserResource($this['buyer']),
            'seller' => new UserResource($this['seller']),
            'amount_in_coin' => $this['amount_in_coin'],
            'amount_in_usd' => round($this['amount_in_usd'], 2),
            'amount_in_ngn' => round($this['amount_in_ngn'], 2),
            'fee_in_coin' => $this['fee_in_coin'],
            'fee_in_usd' => round($this['fee_in_usd'], 2),
            'fee_in_ngn' => round($this['fee_in_ngn'], 2),
            'buyer_trade_state' => $this['buyer_trade_state'],
            'seller_trade_state' => $this['seller_trade_state'],
            'status' => $this['status'],
            'coin_released' => $this['coin_released'] == 1,
            'initiated_on' => $this['created_at']
        ];
    }
}
