<?php

namespace Tests\Unit;

use App\Support\SePayCheckoutSigner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SePayCheckoutSignerTest extends TestCase
{
    #[Test]
    public function it_signs_in_sdk_form_field_key_order(): void
    {
        $fields = [
            'merchant' => 'MERCHANT_123',
            'currency' => 'VND',
            'order_amount' => '100000',
            'operation' => 'PURCHASE',
            'order_description' => 'Payment for order #12345',
            'order_invoice_number' => 'INV_20231201_001',
            'success_url' => 'https://yoursite.com/payment/success',
            'error_url' => 'https://yoursite.com/payment/error',
            'cancel_url' => 'https://yoursite.com/payment/cancel',
        ];

        $signedString = 'merchant=MERCHANT_123,currency=VND,order_amount=100000,operation=PURCHASE,order_description=Payment for order #12345,order_invoice_number=INV_20231201_001,success_url=https://yoursite.com/payment/success,error_url=https://yoursite.com/payment/error,cancel_url=https://yoursite.com/payment/cancel';
        $expected = base64_encode(hash_hmac('sha256', $signedString, 'doc_test_secret', true));

        $this->assertSame($expected, SePayCheckoutSigner::sign($fields, 'doc_test_secret'));
    }

    #[Test]
    public function it_differs_from_wrong_api_doc_only_field_order(): void
    {
        $fields = [
            'merchant' => 'M1',
            'currency' => 'VND',
            'order_amount' => '50000',
            'operation' => 'PURCHASE',
            'order_description' => 'Desc',
            'order_invoice_number' => 'INV-2',
            'success_url' => 'https://example.com/s',
            'error_url' => 'https://example.com/e',
            'cancel_url' => 'https://example.com/c',
        ];

        $apiDocOrderString = 'order_amount=50000,merchant=M1,currency=VND,operation=PURCHASE,order_description=Desc,order_invoice_number=INV-2,success_url=https://example.com/s,error_url=https://example.com/e,cancel_url=https://example.com/c';

        $this->assertNotSame(
            base64_encode(hash_hmac('sha256', $apiDocOrderString, 'secret_test_key', true)),
            SePayCheckoutSigner::sign($fields, 'secret_test_key')
        );
    }
}
