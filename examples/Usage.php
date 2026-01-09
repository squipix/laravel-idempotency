<?php

/*
|--------------------------------------------------------------------------
| Example Usage: Payment API Endpoint
|--------------------------------------------------------------------------
|
| This file demonstrates how to use the idempotency package in a real-world
| payment processing scenario.
|
*/

namespace App\Http\Controllers;

use App\Jobs\CapturePaymentJob;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Create a new payment
     * 
     * Route: POST /api/payments
     * Middleware: ['auth', 'idempotency']
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'currency' => 'required|string|size:3',
            'customer_id' => 'required|string',
            'description' => 'nullable|string|max:255',
        ]);

        // Create payment record
        $payment = Payment::create([
            'id' => 'pay_' . Str::random(24),
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'customer_id' => $validated['customer_id'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        // Dispatch async capture job (idempotent)
        CapturePaymentJob::dispatch($payment->id, $payment->amount);

        return response()->json([
            'id' => $payment->id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'created_at' => $payment->created_at,
        ], 201);
    }

    /**
     * Refund a payment
     * 
     * Route: POST /api/payments/{payment}/refund
     * Middleware: ['auth', 'idempotency']
     */
    public function refund(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'amount' => 'nullable|integer|min:1',
            'reason' => 'nullable|string|max:255',
        ]);

        $refundAmount = $validated['amount'] ?? $payment->amount;

        // Validate refund
        if ($refundAmount > $payment->amount) {
            return response()->json([
                'error' => 'Refund amount cannot exceed payment amount',
            ], 422);
        }

        if ($payment->status !== 'succeeded') {
            return response()->json([
                'error' => 'Only succeeded payments can be refunded',
            ], 422);
        }

        // Process refund (your payment gateway logic here)
        $refund = $payment->refunds()->create([
            'id' => 'ref_' . Str::random(24),
            'amount' => $refundAmount,
            'reason' => $validated['reason'] ?? 'requested_by_customer',
            'status' => 'succeeded',
        ]);

        // Update payment status if fully refunded
        if ($payment->refunds()->sum('amount') >= $payment->amount) {
            $payment->update(['status' => 'refunded']);
        }

        return response()->json([
            'id' => $refund->id,
            'payment_id' => $payment->id,
            'amount' => $refund->amount,
            'status' => $refund->status,
            'created_at' => $refund->created_at,
        ], 200);
    }
}

/*
|--------------------------------------------------------------------------
| Example: Idempotent Queue Job
|--------------------------------------------------------------------------
*/

namespace App\Jobs;

use App\Models\Payment;
use App\Services\PaymentGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use squipix\Idempotency\Jobs\IdempotentJobMiddleware;

class CapturePaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public string $paymentId,
        public int $amount
    ) {}

    /**
     * Add idempotent middleware
     */
    public function middleware(): array
    {
        return [new IdempotentJobMiddleware()];
    }

    /**
     * Define the idempotency key
     * 
     * This ensures that even if the job is retried or dispatched multiple times,
     * the payment will only be captured once.
     */
    public function idempotencyKey(): string
    {
        return "payment-capture:{$this->paymentId}";
    }

    /**
     * Execute the job
     */
    public function handle(PaymentGateway $gateway)
    {
        $payment = Payment::findOrFail($this->paymentId);

        if ($payment->status === 'succeeded') {
            // Already captured, skip
            return;
        }

        try {
            // Capture payment via gateway
            $result = $gateway->capturePayment($payment->id, $this->amount);

            // Update payment status
            $payment->update([
                'status' => 'succeeded',
                'gateway_transaction_id' => $result['transaction_id'],
                'captured_at' => now(),
            ]);

            // Send confirmation email
            // dispatch(new SendPaymentConfirmationEmail($payment));

        } catch (\Exception $e) {
            $payment->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger job retry
        }
    }
}

/*
|--------------------------------------------------------------------------
| Example: Routes Configuration
|--------------------------------------------------------------------------
*/

// routes/api.php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Apply idempotency middleware to payment endpoints
    Route::middleware('idempotency')->group(function () {
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);
    });
    
    // Read-only endpoints don't need idempotency
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Example: Client-Side Usage (JavaScript)
|--------------------------------------------------------------------------
*/

/*
// Generate idempotency key on client
import { v4 as uuidv4 } from 'uuid';

async function createPayment() {
    const idempotencyKey = uuidv4(); // or use localStorage for retry
    
    try {
        const response = await fetch('https://api.example.com/payments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${accessToken}`,
                'Idempotency-Key': idempotencyKey,
            },
            body: JSON.stringify({
                amount: 1000,
                currency: 'USD',
                customer_id: 'cus_123',
                description: 'Order #12345'
            })
        });
        
        if (!response.ok) {
            throw new Error('Payment failed');
        }
        
        const payment = await response.json();
        console.log('Payment created:', payment);
        
    } catch (error) {
        console.error('Error:', error);
        // Safe to retry with same idempotency key
        // The server will return the original response if already processed
    }
}

// For network retry scenarios
async function createPaymentWithRetry() {
    const idempotencyKey = localStorage.getItem('payment-idempotency-key') 
        || uuidv4();
    
    localStorage.setItem('payment-idempotency-key', idempotencyKey);
    
    const maxRetries = 3;
    let attempt = 0;
    
    while (attempt < maxRetries) {
        try {
            const response = await fetch('https://api.example.com/payments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${accessToken}`,
                    'Idempotency-Key': idempotencyKey,
                },
                body: JSON.stringify({ amount: 1000, currency: 'USD' })
            });
            
            if (response.ok) {
                localStorage.removeItem('payment-idempotency-key');
                return await response.json();
            }
            
            throw new Error(`HTTP ${response.status}`);
            
        } catch (error) {
            attempt++;
            if (attempt >= maxRetries) throw error;
            await new Promise(r => setTimeout(r, 1000 * attempt));
        }
    }
}
*/

/*
|--------------------------------------------------------------------------
| Example: Testing
|--------------------------------------------------------------------------
*/

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_payment_request_creates_only_one_payment()
    {
        $user = User::factory()->create();
        $key = 'test-payment-' . uniqid();
        
        $payload = [
            'amount' => 1000,
            'currency' => 'USD',
            'customer_id' => 'cus_test',
        ];
        
        // First request
        $response1 = $this->actingAs($user)
            ->postJson('/api/payments', $payload, [
                'Idempotency-Key' => $key,
            ]);
        
        $response1->assertStatus(201);
        $paymentId1 = $response1->json('id');
        
        // Duplicate request (simulates network retry)
        $response2 = $this->actingAs($user)
            ->postJson('/api/payments', $payload, [
                'Idempotency-Key' => $key,
            ]);
        
        $response2->assertStatus(201);
        $paymentId2 = $response2->json('id');
        
        // Should return same payment ID
        $this->assertEquals($paymentId1, $paymentId2);
        
        // Should only create one payment
        $this->assertEquals(1, Payment::count());
    }
    
    public function test_same_key_different_amount_rejected()
    {
        $user = User::factory()->create();
        $key = 'test-payment-' . uniqid();
        
        $this->actingAs($user)
            ->postJson('/api/payments', [
                'amount' => 1000,
                'currency' => 'USD',
                'customer_id' => 'cus_test',
            ], [
                'Idempotency-Key' => $key,
            ])
            ->assertStatus(201);
        
        // Same key, different amount
        $this->actingAs($user)
            ->postJson('/api/payments', [
                'amount' => 2000,
                'currency' => 'USD',
                'customer_id' => 'cus_test',
            ], [
                'Idempotency-Key' => $key,
            ])
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Payload mismatch for idempotency key'
            ]);
    }
    
    public function test_concurrent_requests_prevented()
    {
        $user = User::factory()->create();
        $key = 'test-payment-' . uniqid();
        
        $payload = [
            'amount' => 1000,
            'currency' => 'USD',
            'customer_id' => 'cus_test',
        ];
        
        // Simulate concurrent requests (in practice, use parallel execution)
        $response1 = $this->actingAs($user)
            ->postJson('/api/payments', $payload, [
                'Idempotency-Key' => $key,
            ]);
        
        // Second request should either wait or return 409
        $response2 = $this->actingAs($user)
            ->postJson('/api/payments', $payload, [
                'Idempotency-Key' => $key,
            ]);
        
        // One succeeds with 201, other returns cached or 409
        $this->assertTrue(
            ($response1->status() === 201 && $response2->status() === 201) ||
            ($response1->status() === 201 && $response2->status() === 409)
        );
    }
}
