<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Offer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user(): \Illuminate\Database\Eloquent\Relations\belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function trades(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Trade::class);
    }

    public function getMaxPriceInCoin(): float
    {
        if ($this['type'] == 'naira')
            return round(($this['max'] / $this['rate']) / $this['coin']['price'], 9);
        else return round($this['max'] / $this['coin']['price'], 9);
    }

    public static function getMaxPriceInCoinByData($coin, $data): float
    {
        if (Arr::get($data, 'type') == 'naira')
            return round((Arr::get($data, 'max') / Arr::get($data, 'rate')) / $coin['price'], 9);
        else return round(Arr::get($data, 'max') / $coin['price'], 9);
    }

    public function isClosableByTrade($trade): bool
    {
        return $this->trades()
                    ->where('id', '!=', $trade['id'])
                    ->where('status', 'pending')
                    ->count() == 0;
    }
}
