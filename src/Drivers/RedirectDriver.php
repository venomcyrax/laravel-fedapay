<?php

namespace RivascoTech\FedaPay\Drivers;

use FedaPay\Transaction;
use RivascoTech\FedaPay\Contracts\Payable;
use RivascoTech\FedaPay\Contracts\PaymentDriver;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

/**
 * Driver Redirect — redirige l'utilisateur vers la page de paiement FedaPay.
 * Compatible avec tous les opérateurs (Mobile Money, carte bancaire…).
 */
class RedirectDriver implements PaymentDriver
{
    public function initiate(Payable $payable, array $options = []): array
    {
        $callbackUrl = $options['callback_url']
            ?? (config('fedapay.routes.callback_url')
                ? url(config('fedapay.routes.callback_url'))
                : null);

        $transaction = Transaction::create([
            'description'        => $payable->getPayableDescription(),
            'amount'             => $payable->getPayableAmount(),
            'currency'           => ['iso' => config('fedapay.currency', 'XOF')],
            'callback_url'       => $callbackUrl,
            'merchant_reference' => $payable->getPayableReference(),
            'custom_metadata'    => [
                'payable_type' => get_class($payable),
                'payable_id'   => $payable->getKey(),
            ],
            'customer'           => $payable->getPayableCustomer(),
        ]);

        $token = $transaction->generateToken();

        $record = FedaPayTransaction::create([
            'payable_type'       => get_class($payable),
            'payable_id'         => $payable->getKey(),
            'fedapay_id'         => (string) $transaction->id,
            'fedapay_token'      => $token->token,
            'merchant_reference' => $payable->getPayableReference(),
            'amount'             => $payable->getPayableAmount(),
            'currency'           => config('fedapay.currency', 'XOF'),
            'status'             => 'pending',
            'driver'             => 'redirect',
            'metadata'           => ['redirect_url' => $token->url],
        ]);

        return [
            'type'           => 'redirect',
            'redirect_url'   => $token->url,
            'transaction_id' => (string) $transaction->id,
            'transaction'    => $record,
        ];
    }
}
