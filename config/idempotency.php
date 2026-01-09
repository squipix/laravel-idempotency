<?php
return [
    'header' => 'Idempotency-Key',
    'lock_ttl' => 10, // seconds
    'response_ttl' => 86400, // 24 hours
    'reject_payload_mismatch' => true,
    'queue' => [
        'enabled' => true,
        'ttl' => 86400,
    ],
];
