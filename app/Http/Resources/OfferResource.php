<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
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
            'user' => new UserResource($this['user']),
            'coin' => new CoinResource($this['coin']),
            'price_type' => $this['price_type'],
            'price' => $this['price'],
            'rate' => $this['rate'],
            'min' => $this['min'],
            'max' => $this['max'],
            'status' => $this['status'],
            'created' => $this['created_at']
        ];
    }
}
