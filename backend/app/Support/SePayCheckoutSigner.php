<?php

namespace App\Support;

final class SePayCheckoutSigner
{
    /**
     * Các field được phép ký (SePay PHP SDK — SignatureGenerator).
     *
     * @var list<string>
     */
    private const ALLOWED_SIGN_FIELDS = [
        'merchant',
        'env',
        'operation',
        'payment_method',
        'order_amount',
        'currency',
        'order_invoice_number',
        'order_description',
        'customer_id',
        'agreement_id',
        'agreement_name',
        'agreement_type',
        'agreement_payment_frequency',
        'agreement_amount_per_payment',
        'success_url',
        'error_url',
        'cancel_url',
    ];

    /**
     * Thứ tự field form checkout (khớp SePay CheckoutResource::prepareFormFields).
     *
     * @var list<string>
     */
    public const FORM_FIELD_ORDER = [
        'merchant',
        'currency',
        'order_amount',
        'operation',
        'order_description',
        'payment_method',
        'order_invoice_number',
        'customer_id',
        'success_url',
        'error_url',
        'cancel_url',
        'signature',
    ];

    /**
     * @param  array<string, string|int|null>  $fields  Fields theo thứ tự form (không gồm signature).
     */
    public static function sign(array $fields, string $secretKey): string
    {
        $signed = [];

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, self::ALLOWED_SIGN_FIELDS, true)) {
                continue;
            }
            if (! isset($fields[$field]) || $fields[$field] === '') {
                continue;
            }
            $signed[] = $field.'='.$fields[$field];
        }

        return base64_encode(hash_hmac('sha256', implode(',', $signed), $secretKey, true));
    }
}
