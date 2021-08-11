<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
