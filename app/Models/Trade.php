<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function coin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function offer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function buyer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id', 'id');
    }

    public function seller(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id', 'id');
    }

    public static function generateTradeRef(): int
    {
        do {
            $ref = rand(100000000000, 999999999999);
        } while (static::query()->where('ref', $ref)->count() > 0);
        return $ref;
    }
}
