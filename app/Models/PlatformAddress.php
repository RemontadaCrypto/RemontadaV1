<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PlatformAddress extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function getPthAttribute($value): string
    {
        return Crypt::decryptString($value);
    }
}
