<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Transaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function setPartyAttribute($value)
    {
        $this->attributes['party'] = Crypt::encrypt($value);
    }

    public function setHashAttribute($value)
    {
        $this->attributes['hash'] = Crypt::encrypt($value);
    }

    public function getPartyAttribute($value)
    {
        return Crypt::decrypt($value);
    }

    public function getHashAttribute($value)
    {
        return Crypt::decrypt($value);
    }

    public function coin(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coin::class);
    }

    public function trade(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
