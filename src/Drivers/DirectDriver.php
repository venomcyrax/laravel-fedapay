<?php

namespace RivascoTech\FedaPay\Drivers;

use FedaPay\Transaction;
use Illuminate\Support\Facades\Http;
use RivascoTech\FedaPay\Contracts\Payable;
use RivascoTech\FedaPay\Contracts\PaymentDriver;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

/**
 * Driver Direct — envoie une demande de paiement push à l'opérateur Mobile Money.
 * L'utilisateur reçoit une notification USSD/OTP sur son téléphone sans quitter le site.
 *
 * Options requises : phone, country, endpoint, operator_id
 */
class DirectDriver implements PaymentDriver
{
    public function initiate(Payable $payable, array $options = []): array
    {
        $phone      = $options['phone'];
        $country    = $options['country'];
        $endpoint   = $options['endpoint'];
        $operatorId = $options['operator_id'] ?? '';

        $callbackUrl = $options['callback_url']
            ?? (config('fedapay.routes.callback_url')
                ? url(config('fedapay.routes.callback_url'))
                : null);

        $customer = array_merge($payable->getPayableCustomer(), [
            'phone_number' => [
                'number'  => $phone,
                'country' => $country,
            ],
        ]);

        // 1. Créer la transaction FedaPay
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
            'customer'           => $customer,
        ]);

        // 2. Générer le token de paiement
        $token = $transaction->generateToken();

        // 3. Déclencher le paiement direct via l'API FedaPay
        $apiBase = config('fedapay.environment') === 'live'
            ? 'https://api.fedapay.com'
            : 'https://sandbox-api.fedapay.com';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('fedapay.secret_key'),
            'Content-Type'  => 'application/json',
        ])->post("{$apiBase}/v1/{$endpoint}", [
            'token'        => $token->token,
            'phone_number' => [
                'number'  => $phone,
                'country' => $country,
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "FedaPay direct payment failed [{$endpoint}]: " . $response->body()
            );
        }

        // 4. Persister la transaction
        $record = FedaPayTransaction::create([
            'payable_type'       => get_class($payable),
            'payable_id'         => $payable->getKey(),
            'fedapay_id'         => (string) $transaction->id,
            'fedapay_token'      => $token->token,
            'merchant_reference' => $payable->getPayableReference(),
            'amount'             => $payable->getPayableAmount(),
            'currency'           => config('fedapay.currency', 'XOF'),
            'status'             => 'pending',
            'driver'             => 'direct',
            'operator_id'        => $operatorId,
            'metadata'           => [
                'endpoint'     => $endpoint,
                'phone'        => $phone,
                'country'      => $country,
                'api_response' => $response->json(),
            ],
        ]);

        return [
            'type'           => 'direct',
            'redirect_url'   => null,
            'transaction_id' => (string) $transaction->id,
            'transaction'    => $record,
        ];
    }
}
