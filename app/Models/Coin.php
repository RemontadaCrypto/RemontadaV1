<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coin extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function offers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function platformAddress(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformAddress::class);
    }

    public function getFeeDepositAddress()
    {
        return $this->platformAddress()->first()['pth'];
    }
}
