<?php

namespace App\Http\Resources;

use App\Http\Controllers\AddressController;
use App\Http\Controllers\TradeController;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
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
            'name' => $this['name'],
            'email' => $this['email'],
            'email_verified' => !is_null($this['email_verified_at']),
            'joined_date' => $this['created_at'],
            'addresses' => AddressController::getAllAddresses()
        ];
    }
}
