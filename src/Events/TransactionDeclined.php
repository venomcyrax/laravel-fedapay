<?php

namespace RivascoTech\FedaPay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

class TransactionDeclined
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly FedaPayTransaction $transaction,
        public readonly mixed              $payload = null,
    ) {}
}
