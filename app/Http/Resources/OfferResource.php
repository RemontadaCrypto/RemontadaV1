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
            'type' => $this['type'],
            'rate' => (float) $this['rate'],
            'min' => (float) $this['min'],
            'max' => (float) $this['max'],
            'status' => $this['status'],
            'created' => $this['created_at']
        ];
    }
}
