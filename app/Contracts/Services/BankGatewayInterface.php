<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Payment;

interface BankGatewayInterface
{
    /**
     * Verify a payment with the external bank gateway.
     *
     * @return array{ok:bool, code?:string|null, message?:string|null, raw_request?:string|null, raw_response?:string|null}
     */
    public function verify(Payment $payment): array;
}
