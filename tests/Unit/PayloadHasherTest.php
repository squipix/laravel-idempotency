<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Squipix\Idempotency\Services\PayloadHasher;

class PayloadHasherTest extends TestCase
{
    public function test_hash_is_consistent_for_same_payload()
    {
        $request1 = Request::create('/test', 'POST', ['foo' => 'bar', 'baz' => 123]);
        $request2 = Request::create('/test', 'POST', ['foo' => 'bar', 'baz' => 123]);
        $hash1 = PayloadHasher::hash($request1);
        $hash2 = PayloadHasher::hash($request2);
        $this->assertEquals($hash1, $hash2);
    }

    public function test_hash_differs_for_different_payloads()
    {
        $request1 = Request::create('/test', 'POST', ['foo' => 'bar']);
        $request2 = Request::create('/test', 'POST', ['foo' => 'baz']);
        $hash1 = PayloadHasher::hash($request1);
        $hash2 = PayloadHasher::hash($request2);
        $this->assertNotEquals($hash1, $hash2);
    }
}
