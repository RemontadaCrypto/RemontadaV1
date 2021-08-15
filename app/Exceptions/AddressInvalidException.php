<?php

namespace App\Exceptions;

use Exception;

class AddressInvalidException extends Exception
{
    public function render($request)
    {
        if ($request->expectsJson()){
            return response()->json(['errors' => 'Address is invalid for this coin'], 400);
        }
    }
}
