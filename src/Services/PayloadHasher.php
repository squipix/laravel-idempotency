<?php
namespace squipix\Idempotency\Services;

use Illuminate\Http\Request;

class PayloadHasher
{
    public static function hash(Request $request): string
    {
        return hash('sha256', json_encode($request->all()));
    }
}
