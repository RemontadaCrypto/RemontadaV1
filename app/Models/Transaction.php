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

    public function getPartyAttribute($value)
    {
        $this->attributes['party'] = Crypt::decrypt($value);
    }

    public function coin()
    {
        $this->belongsTo(Coin::class);
    }
}
