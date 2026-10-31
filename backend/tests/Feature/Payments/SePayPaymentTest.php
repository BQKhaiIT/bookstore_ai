<?php

namespace Tests\Feature\Payments;

use App\Enums\BookStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SePayPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sepay.env' => 'sandbox',
            'sepay.merchant_id' => 'SP-TEST-MERCHANT',
            'sepay.secret_key' => 'test_secret_key_for_ipn',
            'sepay.checkout_init_url' => 'https://pay-sandbox.sepay.vn/v1/checkout/init',
            'sepay.api_base_url' => 'https://pgapi-sandbox.sepay.vn',
            'sepay.public_base_url' => 'https://public-tunnel.example.com',
        ]);
    }

    /**
     * @return array{user: User, address: UserAddress, book: Book}
     */
    private function customerReadyForCheckout(): array
    {
        $user = User::factory()->create();
        $book = Book::factory()->create([
            'status' => BookStatus::Published,
            'stock_quantity' => 20,
            'price' => 85000,
        ]);
        $cart = Cart::query()->create(['user_id' => $user->id]);
        CartItem::query()->create([
            'cart_id' => $cart->id,
            'book_id' => $book->id,
            'quantity' => 1,
        ]);
        $address = UserAddress::query()->create([
            'user_id' => $user->id,
            'recipient_name' => 'Test User',
            'phone' => '0900000000',
            'province' => 'HCM',
            'district' => 'Q1',
            'ward' => 'P1',
            'address_line' => '1 Test St',
            'is_default' => true,
        ]);

        return compact('user', 'address', 'book');
    }

    public function test_checkout_cod_regression(): void
    {
        ['user' => $user, 'address' => $address] = $this->customerReadyForCheckout();
        $this->actingAs($user);

        $response = $this->postSpa('/api/v1/checkout', [
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Cod->value,
        ], true, ['Idempotency-Key' => Str::uuid()->toString()]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.method', PaymentMethod::Cod->value)
            ->assertJsonPath('data.payment.status', PaymentStatus::Pending->value);
    }

    public function test_checkout_sandbox_online_creates_pending_sepay_payment(): void
    {
        ['user' => $user, 'address' => $address] = $this->customerReadyForCheckout();
        $this->actingAs($user);

        $response = $this->postSpa('/api/v1/checkout', [
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::SandboxOnline->value,
        ], true, ['Idempotency-Key' => Str::uuid()->toString()]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.method', PaymentMethod::SandboxOnline->value)
            ->assertJsonPath('data.payment.provider', 'sepay')
            ->assertJsonPath('data.payment.environment', 'sandbox');
    }

    public function test_payment_session_returns_signed_checkout_form(): void
    {
        ['user' => $user, 'address' => $address] = $this->customerReadyForCheckout();
        $this->actingAs($user);

        $orderId = $this->postSpa('/api/v1/checkout', [
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::SandboxOnline->value,
        ], true, ['Idempotency-Key' => Str::uuid()->toString()])->json('data.id');

        $session = $this->postSpa('/api/v1/orders/'.$orderId.'/payment-sessions', [])
            ->assertOk()
            ->json('data');

        $this->assertSame('https://pay-sandbox.sepay.vn/v1/checkout/init', $session['checkout']['action_url']);
        $fields = $session['checkout']['fields'];
        $this->assertSame('SP-TEST-MERCHANT', $fields['merchant']);
        $this->assertNotEmpty($fields['signature']);
        $this->assertNotEmpty($session['payment']['invoice_number']);
        $this->assertStringStartsWith('https://public-tunnel.example.com/payment/sepay/return/', $fields['success_url']);
        $this->assertSame(\App\Support\SePayCheckoutSigner::FORM_FIELD_ORDER, $session['checkout']['field_order']);
        $unsigned = $fields;
        unset($unsigned['signature']);
        $this->assertSame($fields['signature'], \App\Support\SePayCheckoutSigner::sign($unsigned, 'test_secret_key_for_ipn'));
    }

    public function test_ipn_rejects_invalid_secret(): void
    {
        $payment = $this->sandboxPendingPayment();

        $this->postJson('/api/v1/payments/webhooks/sepay', $this->ipnPayload($payment), [
            'X-Secret-Key' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_ipn_marks_payment_paid_idempotently(): void
    {
        $payment = $this->sandboxPendingPayment();
        $payload = $this->ipnPayload($payment);

        $this->postJson('/api/v1/payments/webhooks/sepay', $payload, [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertOk();

        $this->postJson('/api/v1/payments/webhooks/sepay', $payload, [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('txn_test_001', $payment->transaction_reference);
        $this->assertSame(1, Payment::query()->where('order_id', $payment->order_id)->where('status', PaymentStatus::Paid)->count());
    }

    public function test_ipn_rejects_amount_mismatch(): void
    {
        $payment = $this->sandboxPendingPayment();
        $payload = $this->ipnPayload($payment);
        $payload['order']['order_amount'] = '1.00';

        $this->postJson('/api/v1/payments/webhooks/sepay', $payload, [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertStatus(422);
    }

    public function test_ipn_rejects_unknown_invoice(): void
    {
        $payment = $this->sandboxPendingPayment();
        $payload = $this->ipnPayload($payment);
        $payload['order']['order_invoice_number'] = 'UNKNOWN';

        $this->postJson('/api/v1/payments/webhooks/sepay', $payload, [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertNotFound();
    }

    public function test_payment_session_requires_order_owner(): void
    {
        $payment = $this->sandboxPendingPayment();
        $other = User::factory()->create();
        $this->actingAs($other);

        $this->postSpa('/api/v1/orders/'.$payment->order_id.'/payment-sessions', [])
            ->assertNotFound();
    }

    public function test_dashboard_excludes_sandbox_revenue_from_real_revenue(): void
    {
        $this->actingAsAdmin();

        $realOrder = Order::factory()->create();
        Payment::factory()->paid()->create(['order_id' => $realOrder->id, 'amount' => 200000]);

        $sandboxOrder = Order::factory()->create();
        Payment::factory()->paid()->create([
            'order_id' => $sandboxOrder->id,
            'amount' => 500000,
            'method' => PaymentMethod::SandboxOnline,
            'provider' => 'sepay',
            'environment' => 'sandbox',
        ]);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue', 200000)
            ->assertJsonPath('data.sandbox_revenue', 500000);
    }

    public function test_success_redirect_keeps_pending_until_delayed_ipn(): void
    {
        $payment = $this->sandboxPendingPayment();

        $this->get('/payment/sepay/return/'.$payment->order_id.'?outcome=success')
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        $this->postJson('/api/v1/payments/webhooks/sepay', $this->ipnPayload($payment), [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(1, Payment::query()->where('order_id', $payment->order_id)->where('status', PaymentStatus::Paid)->count());
    }

    public function test_duplicate_ipn_with_another_transaction_does_not_double_charge(): void
    {
        $payment = $this->sandboxPendingPayment();
        $payload = $this->ipnPayload($payment);

        $this->postJson('/api/v1/payments/webhooks/sepay', $payload, [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertOk();

        $payload['transaction']['transaction_id'] = 'txn_test_002';
        $this->postJson('/api/v1/payments/webhooks/sepay', $payload, [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('txn_test_001', $payment->transaction_reference);
        $this->assertSame(1, Payment::query()->where('order_id', $payment->order_id)->where('status', PaymentStatus::Paid)->count());
    }

    public function test_get_payment_reconciles_captured_sandbox_order_once(): void
    {
        $payment = $this->sandboxPendingPayment();
        $this->fakeSePayOrder($this->capturedRemoteOrder($payment));
        $this->actingAs($payment->order->user);

        $this->getJson('/api/v1/orders/'.$payment->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.transaction_reference', 'PAY-TEST-001')
            ->assertJsonPath('data.amount', 115000);

        $this->getJson('/api/v1/orders/'.$payment->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->assertSame(1, Payment::query()->where('order_id', $payment->order_id)->where('status', PaymentStatus::Paid)->count());

        $this->postSpa('/api/v1/orders/'.$payment->order_id.'/payment-sessions', [])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_ALREADY_PAID');
    }

    public function test_reconcile_prefers_approved_transaction_id(): void
    {
        $payment = $this->sandboxPendingPayment();
        $remote = $this->capturedRemoteOrder($payment);
        $remote['transactions'] = [[
            'transaction_id' => 'txn_remote_9',
            'transaction_type' => 'PAYMENT',
            'transaction_status' => 'APPROVED',
            'transaction_amount' => '115000',
            'transaction_currency' => 'VND',
        ]];
        $this->fakeSePayOrder($remote);

        $this->actingAs($payment->order->user)
            ->getJson('/api/v1/orders/'.$payment->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value)
            ->assertJsonPath('data.transaction_reference', 'txn_remote_9');
    }

    public function test_reconcile_ignores_uncaptured_mismatch_and_failed_lookup(): void
    {
        $pending = $this->sandboxPendingPayment();
        $remote = $this->capturedRemoteOrder($pending);
        $remote['order_status'] = 'AUTHENTICATION_NOT_NEEDED';
        $this->fakeSePayOrder($remote);

        $this->actingAs($pending->order->user)
            ->getJson('/api/v1/orders/'.$pending->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value);

        $mismatch = $this->sandboxPendingPayment();
        $remote = $this->capturedRemoteOrder($mismatch);
        $remote['order_amount'] = '1.00';
        $this->fakeSePayOrder($remote);

        $this->actingAs($mismatch->order->user)
            ->getJson('/api/v1/orders/'.$mismatch->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value);

        $unapproved = $this->sandboxPendingPayment();
        $remote = $this->capturedRemoteOrder($unapproved);
        $remote['transactions'] = [[
            'transaction_id' => 'txn_declined',
            'transaction_type' => 'PAYMENT',
            'transaction_status' => 'DECLINED',
            'transaction_amount' => '115000',
            'transaction_currency' => 'VND',
        ]];
        $this->fakeSePayOrder($remote);

        $this->actingAs($unapproved->order->user)
            ->getJson('/api/v1/orders/'.$unapproved->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value);

        $offline = $this->sandboxPendingPayment();
        Http::fake([
            'https://pgapi-sandbox.sepay.vn/*' => Http::response(['message' => 'down'], 503),
        ]);

        $this->actingAs($offline->order->user)
            ->getJson('/api/v1/orders/'.$offline->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Pending->value);
    }

    public function test_ipn_after_reconcile_does_not_double_sandbox_revenue(): void
    {
        $payment = $this->sandboxPendingPayment();
        $this->fakeSePayOrder($this->capturedRemoteOrder($payment));

        $this->actingAs($payment->order->user)
            ->getJson('/api/v1/orders/'.$payment->order_id.'/payment')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Paid->value);

        $this->postJson('/api/v1/payments/webhooks/sepay', $this->ipnPayload($payment), [
            'X-Secret-Key' => 'test_secret_key_for_ipn',
        ])->assertOk();

        $this->assertSame(1, Payment::query()->where('order_id', $payment->order_id)->where('status', PaymentStatus::Paid)->count());

        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.revenue', 0)
            ->assertJsonPath('data.sandbox_revenue', 115000);
    }

    private function sandboxPendingPayment(): Payment
    {
        $order = Order::factory()->create(['total_amount' => 115000]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'method' => PaymentMethod::SandboxOnline,
            'provider' => 'sepay',
            'environment' => 'sandbox',
            'amount' => 115000,
            'invoice_number' => 'BS-TEST-INV-'.$order->id,
            'status' => PaymentStatus::Pending,
        ]);

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function ipnPayload(Payment $payment): array
    {
        return [
            'timestamp' => time(),
            'notification_type' => 'ORDER_PAID',
            'order' => [
                'order_status' => 'CAPTURED',
                'order_currency' => 'VND',
                'order_amount' => (string) $payment->amount.'.00',
                'order_invoice_number' => $payment->invoice_number,
            ],
            'transaction' => [
                'transaction_status' => 'APPROVED',
                'transaction_amount' => (string) $payment->amount,
                'transaction_currency' => 'VND',
                'transaction_id' => 'txn_test_001',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fakeSePayOrder(array $data): void
    {
        Http::fake([
            'https://pgapi-sandbox.sepay.vn/*' => Http::response(['data' => $data], 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedRemoteOrder(Payment $payment): array
    {
        $payment->loadMissing('order');

        return [
            'order_id' => 'PAY-TEST-001',
            'order_invoice_number' => $payment->invoice_number,
            'order_status' => 'CAPTURED',
            'order_amount' => $payment->amount.'.00',
            'order_currency' => 'VND',
            'customer_id' => 'user_'.$payment->order->user_id,
            'transactions' => [],
        ];
    }
}
