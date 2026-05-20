<?php

namespace RivascoTech\FedaPay;

use FedaPay\FedaPay as FedaPaySDK;
use RivascoTech\FedaPay\Contracts\Payable;
use RivascoTech\FedaPay\Contracts\PaymentDriver;
use RivascoTech\FedaPay\Drivers\DirectDriver;
use RivascoTech\FedaPay\Drivers\RedirectDriver;
use RivascoTech\FedaPay\Models\FedaPayTransaction;

/**
 * Point d'entrée principal du module.
 *
 * Usage depuis le projet hôte :
 *
 *   // Paiement avec redirection
 *   $result = FedaPay::initiate($order);
 *   return redirect($result['redirect_url']);
 *
 *   // Paiement direct Mobile Money
 *   $result = FedaPay::direct($order, phone: '64000001', country: 'bj', operatorId: 'mtn_bj');
 *   // → type: 'direct', transaction_id: '...'
 *
 *   // Mode test (bypass API)
 *   $result = FedaPay::fake($order);
 */
class FedaPayManager
{
    public function __construct()
    {
        $this->bootSdk();
    }

    // ── API publique ──────────────────────────────────────────────────────

    /**
     * Paiement avec redirection vers la page FedaPay (tous opérateurs).
     */
    public function initiate(Payable $payable, array $options = []): array
    {
        if (config('fedapay.test_mode')) {
            return $this->fakeResult($payable);
        }

        return $this->driver('redirect')->initiate($payable, $options);
    }

    /**
     * Paiement direct Mobile Money (push USSD/OTP — sans redirection).
     */
    public function direct(
        Payable $payable,
        string  $phone,
        string  $country,
        string  $operatorId,
        array   $options = []
    ): array {
        if (config('fedapay.test_mode')) {
            return $this->fakeResult($payable);
        }

        $operator = $this->findOperator($operatorId);

        if (! $operator || ! ($operator['enabled'] ?? false)) {
            throw new \InvalidArgumentException("Opérateur [{$operatorId}] inconnu ou désactivé.");
        }

        return $this->driver('direct')->initiate($payable, array_merge($options, [
            'phone'       => preg_replace('/\D/', '', $phone),
            'country'     => $country,
            'endpoint'    => $operator['endpoint'],
            'operator_id' => $operatorId,
        ]));
    }

    /**
     * Résout automatiquement le driver selon la config et l'opérateur choisi.
     * Fallback vers redirect si le direct n'est pas disponible.
     */
    public function resolve(Payable $payable, ?string $operatorId = null, ?string $phone = null): array
    {
        if (config('fedapay.test_mode')) {
            return $this->fakeResult($payable);
        }

        if (
            $operatorId
            && $phone
            && config('fedapay.direct_payment_enabled')
            && $this->supportsDirectPayment($operatorId)
        ) {
            $operator = $this->findOperator($operatorId);
            return $this->direct($payable, $phone, $operator['country'], $operatorId);
        }

        return $this->initiate($payable);
    }

    /**
     * Simule un paiement approuvé sans appeler l'API (test_mode ou CI).
     */
    public function fake(Payable $payable): array
    {
        return $this->fakeResult($payable);
    }

    /**
     * Retourne la liste des opérateurs actifs.
     */
    public function enabledOperators(): array
    {
        return collect(config('fedapay.operators', []))
            ->filter(fn($op) => $op['enabled'] ?? false)
            ->values()
            ->toArray();
    }

    /**
     * Trouve un opérateur par son ID.
     */
    public function findOperator(string $operatorId): ?array
    {
        return collect(config('fedapay.operators', []))
            ->firstWhere('id', $operatorId);
    }

    /**
     * Récupère le statut actuel d'une transaction via l'API FedaPay.
     * Utilisé pour le polling après un paiement direct ou redirect.
     *
     * @param  string $fedapayId  ID FedaPay de la transaction (ex: "111099897")
     * @return array  ['fedapay_id', 'status', 'amount', 'currency', 'paid_at', 'last_error_code']
     */
    public function getTransactionStatus(string $fedapayId): array
    {
        $this->bootSdk();

        $remote = \FedaPay\Transaction::retrieve((int) $fedapayId);

        $status = $remote->status ?? 'pending';

        // Mettre à jour le statut local si la transaction existe en DB
        $tx = FedaPayTransaction::where('fedapay_id', $fedapayId)->first();
        if ($tx && $tx->status !== $status) {
            $tx->status  = $status;
            $tx->paid_at = ($status === 'approved') ? now() : null;
            $tx->save();
        }

        return [
            'fedapay_id'     => $fedapayId,
            'status'         => $status,
            'amount'         => $tx?->amount ?? ($remote->amount ?? null),
            'currency'       => $tx?->currency ?? config('fedapay.currency', 'XOF'),
            'paid_at'        => $tx?->paid_at?->toISOString(),
            'last_error_code'=> $remote->last_error_code ?? null,
        ];
    }

    /**
     * Vérifie si un opérateur supporte le paiement direct.
     */
    public function supportsDirectPayment(string $operatorId): bool
    {
        $op = $this->findOperator($operatorId);
        if (! $op || ! ($op['enabled'] ?? false)) return false;

        // Tous les opérateurs avec un endpoint défini supportent le paiement direct
        return ! empty($op['endpoint']);
    }

    // ── Interne ──────────────────────────────────────────────────────────

    private function driver(string $type): PaymentDriver
    {
        return match ($type) {
            'direct'   => new DirectDriver(),
            'redirect' => new RedirectDriver(),
            default    => throw new \InvalidArgumentException("Driver [{$type}] inconnu."),
        };
    }

    private function bootSdk(): void
    {
        FedaPaySDK::setApiKey(config('fedapay.secret_key'));
        FedaPaySDK::setEnvironment(config('fedapay.environment', 'sandbox'));
    }

    private function fakeResult(Payable $payable): array
    {
        $fakeId = 'FAKE-' . strtoupper(substr(md5(uniqid()), 0, 12));

        $record = FedaPayTransaction::create([
            'payable_type'       => get_class($payable),
            'payable_id'         => $payable->getKey(),
            'fedapay_id'         => $fakeId,
            'fedapay_token'      => null,
            'merchant_reference' => $payable->getPayableReference(),
            'amount'             => $payable->getPayableAmount(),
            'currency'           => config('fedapay.currency', 'XOF'),
            'status'             => 'approved',
            'driver'             => 'test',
            'paid_at'            => now(),
            'metadata'           => ['mode' => 'test'],
        ]);

        return [
            'type'           => 'test',
            'redirect_url'   => null,
            'transaction_id' => $fakeId,
            'transaction'    => $record,
        ];
    }
}
