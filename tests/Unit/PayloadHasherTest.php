<?php

namespace Squipix\Idempotency\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Squipix\Idempotency\Services\PayloadHasher;

class PayloadHasherTest extends TestCase
{
    public function test_hash_is_consistent_for_same_payload()
    {
        $payload = ['foo' => 'bar', 'baz' => 123];
        $hash1 = PayloadHasher::hash($payload);
        $hash2 = PayloadHasher::hash($payload);
        $this->assertEquals($hash1, $hash2);
    }

    public function test_hash_differs_for_different_payloads()
    {
        $payload1 = ['foo' => 'bar'];
        $payload2 = ['foo' => 'baz'];
        $hash1 = PayloadHasher::hash($payload1);
        $hash2 = PayloadHasher::hash($payload2);
        $this->assertNotEquals($hash1, $hash2);
    }
}
